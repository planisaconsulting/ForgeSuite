<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Capacity is working time that is still available.
 *
 * Utilisation = scheduled productive minutes / available working minutes × 100.
 * Available minutes come from the resource work schedule, minus leave,
 * maintenance, and other non-working periods. A calendar exception with
 * working_day_override = 0 removes that day. Over-capacity is scheduled
 * minutes minus available minutes when that difference is positive. Work is
 * not compressed to hide the overload.
 */
final class CapacityService
{
    /**
     * @return array{available_minutes: string, scheduled_minutes: string, utilisation_percent: ?string, over_capacity_minutes: string, over_capacity_hours: string}
     */
    public function summarise(string $availableMinutes, string $scheduledMinutes): array
    {
        $available = Decimal::round($this->nonNegative($availableMinutes), 2);
        $scheduled = Decimal::round($this->nonNegative($scheduledMinutes), 2);
        $difference = Decimal::sub($scheduled, $available);
        $over = Decimal::cmp($difference, '0') > 0 ? Decimal::round($difference, 2) : '0.00';
        $percent = null;
        if (Decimal::cmp($available, '0') > 0) {
            $percent = Decimal::round(Decimal::mul(Decimal::div($scheduled, $available), '100'), 2);
        }

        return [
            'available_minutes' => $available,
            'scheduled_minutes' => $scheduled,
            'utilisation_percent' => $percent,
            'over_capacity_minutes' => $over,
            'over_capacity_hours' => Decimal::round(Decimal::div($over, '60'), 2),
        ];
    }

    /**
     * Working minutes on one date before unavailability is removed.
     *
     * @param array<string, mixed> $schedule
     * @param array<string, mixed>|null $exception
     */
    public function scheduledWindowMinutes(array $schedule, string $date, ?array $exception): int
    {
        if ($exception !== null && (int) ($exception['working_day_override'] ?? 0) !== 1) {
            return 0;
        }
        [$start, $end] = $this->window($schedule, $date, $exception);
        if ($start === null || $end === null || $end <= $start) {
            return 0;
        }
        $minutes = (int) round(($end - $start) / 60) - (int) ($schedule['break_minutes'] ?? 0);

        return max(0, $minutes);
    }

    /**
     * @param array<string, mixed> $schedule
     * @param array<string, mixed>|null $exception
     * @param list<array<string, mixed>> $unavailability
     */
    public function availableMinutes(array $schedule, string $date, ?array $exception, array $unavailability): int
    {
        $open = $this->scheduledWindowMinutes($schedule, $date, $exception);
        if ($open === 0) {
            return 0;
        }
        [$start, $end] = $this->window($schedule, $date, $exception);
        if ($start === null || $end === null) {
            return 0;
        }
        $lost = 0;
        foreach ($unavailability as $row) {
            if (strtoupper((string) ($row['status'] ?? 'ACTIVE')) === 'CANCELLED') {
                continue;
            }
            $from = strtotime((string) $row['start_datetime']);
            $to = strtotime((string) $row['end_datetime']);
            if ($from === false || $to === false) {
                continue;
            }
            $overlapStart = max($start, $from);
            $overlapEnd = min($end, $to);
            if ($overlapEnd > $overlapStart) {
                $lost += (int) round(($overlapEnd - $overlapStart) / 60);
            }
        }

        return max(0, $open - $lost);
    }

    /**
     * @param array<string, mixed> $schedule
     * @param array<string, mixed>|null $exception
     */
    public function insideWorkingHours(array $schedule, string $startAt, string $endAt, ?array $exception): bool
    {
        $start = strtotime($startAt);
        $end = strtotime($endAt);
        if ($start === false || $end === false || $end <= $start) {
            return false;
        }
        if (date('Y-m-d', $start) !== date('Y-m-d', $end)) {
            return false;
        }
        [$open, $close] = $this->window($schedule, date('Y-m-d', $start), $exception);
        if ($open === null || $close === null) {
            return false;
        }

        return $start >= $open && $end <= $close;
    }

    /**
     * @param list<int> $durations remaining stage minutes in order
     * @param list<array{start: int, end: int}> $busy
     */
    public function earliestFinish(int $cursor, array $durations, array $schedule, array $busy): int
    {
        foreach ($durations as $minutes) {
            $need = max(1, $minutes) * 60;
            $cursor = $this->nextSlot($cursor, $need, $schedule, $busy);
        }

        return $cursor;
    }

    /**
     * @param array<string, mixed> $schedule
     * @param list<array{start: int, end: int}> $busy
     */
    private function nextSlot(int $cursor, int $seconds, array $schedule, array $busy): int
    {
        $guard = 0;
        while ($guard < 400) {
            $guard++;
            $date = date('Y-m-d', $cursor);
            [$open, $close] = $this->window($schedule, $date, null);
            if ($open === null || $close === null) {
                $cursor = strtotime($date . ' 00:00:00 +1 day') ?: ($cursor + 86400);
                continue;
            }
            if ($cursor < $open) {
                $cursor = $open;
            }
            if ($cursor + $seconds <= $close && !$this->hitsBusy($cursor, $cursor + $seconds, $busy)) {
                return $cursor + $seconds;
            }
            $cursor = strtotime($date . ' 00:00:00 +1 day') ?: ($cursor + 86400);
        }

        return $cursor + $seconds;
    }

    /**
     * @param list<array{start: int, end: int}> $busy
     */
    private function hitsBusy(int $start, int $end, array $busy): bool
    {
        foreach ($busy as $row) {
            if ($start < $row['end'] && $end > $row['start']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $schedule
     * @param array<string, mixed>|null $exception
     * @return array{0: ?int, 1: ?int}
     */
    private function window(array $schedule, string $date, ?array $exception): array
    {
        $day = strtolower(date('l', strtotime($date) ?: time()));
        $start = $schedule[$day . '_start'] ?? null;
        $end = $schedule[$day . '_end'] ?? null;
        if (($start === null || $end === null) && $exception !== null && (int) ($exception['working_day_override'] ?? 0) === 1) {
            $start = $schedule['monday_start'] ?? null;
            $end = $schedule['monday_end'] ?? null;
        }
        if ($start === null || $end === null || $start === '' || $end === '') {
            return [null, null];
        }
        $open = strtotime($date . ' ' . substr((string) $start, 0, 8));
        $close = strtotime($date . ' ' . substr((string) $end, 0, 8));
        if ($open === false || $close === false) {
            return [null, null];
        }

        return [$open, $close];
    }

    private function nonNegative(string $value): string
    {
        return Decimal::cmp($value, '0') < 0 ? '0' : $value;
    }
}

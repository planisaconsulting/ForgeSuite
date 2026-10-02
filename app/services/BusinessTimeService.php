<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;

/**
 * Adds business time using the default work schedule.
 * Days with no start and end, and public holidays, are skipped.
 * Break minutes are not placed on the clock because the schedule does not store a break time.
 */
final class BusinessTimeService
{
    /** @var array<int, array{start: int, end: int}|null>|null */
    private ?array $windows = null;

    /** @var array<string, bool>|null */
    private ?array $closed = null;

    public function addBusinessHours(\DateTimeImmutable $start, int $hours): \DateTimeImmutable
    {
        $remaining = max(0, $hours) * 60;
        $cursor = $start;
        $guard = 0;
        while ($remaining > 0 && $guard < 20000) {
            $guard++;
            $window = $this->window($cursor);
            if ($window === null) {
                $cursor = $cursor->modify('tomorrow')->setTime(0, 0);
                continue;
            }
            $minute = ((int) $cursor->format('H')) * 60 + (int) $cursor->format('i');
            if ($minute < $window['start']) {
                $cursor = $cursor->setTime(intdiv($window['start'], 60), $window['start'] % 60);
                $minute = $window['start'];
            }
            if ($minute >= $window['end']) {
                $cursor = $cursor->modify('tomorrow')->setTime(0, 0);
                continue;
            }
            $take = min($remaining, $window['end'] - $minute);
            $cursor = $cursor->modify('+' . $take . ' minutes');
            $remaining -= $take;
        }

        return $cursor;
    }

    public function addBusinessDays(\DateTimeImmutable $start, int $days): \DateTimeImmutable
    {
        $cursor = $start;
        $left = max(0, $days);
        $guard = 0;
        while ($left > 0 && $guard < 400) {
            $guard++;
            $cursor = $cursor->modify('+1 day');
            if ($this->window($cursor) !== null) {
                $left--;
            }
        }

        return $cursor;
    }

    public function subtractBusinessMinutes(\DateTimeImmutable $end, int $minutes): \DateTimeImmutable
    {
        $remaining = max(0, $minutes);
        $cursor = $end;
        $guard = 0;
        while ($remaining > 0 && $guard < 20000) {
            $guard++;
            $window = $this->window($cursor);
            if ($window === null) {
                $cursor = $cursor->modify('-1 day')->setTime(23, 59);
                continue;
            }
            $minute = ((int) $cursor->format('H')) * 60 + (int) $cursor->format('i');
            if ($minute > $window['end']) {
                $cursor = $cursor->setTime(intdiv($window['end'], 60), $window['end'] % 60);
                $minute = $window['end'];
            }
            if ($minute <= $window['start']) {
                $cursor = $cursor->modify('-1 day')->setTime(23, 59);
                continue;
            }
            $available = $minute - $window['start'];
            $step = min($remaining, $available);
            $cursor = $cursor->modify('-' . $step . ' minutes');
            $remaining -= $step;
        }

        return $cursor;
    }

    public function businessMinutesBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        if ($end <= $start) {
            return 0;
        }
        $cursor = $start;
        $total = 0;
        $guard = 0;
        while ($cursor < $end && $guard < 20000) {
            $guard++;
            $window = $this->window($cursor);
            if ($window === null) {
                $cursor = $cursor->modify('tomorrow')->setTime(0, 0);
                continue;
            }
            $minute = ((int) $cursor->format('H')) * 60 + (int) $cursor->format('i');
            if ($minute < $window['start']) {
                $cursor = $cursor->setTime(intdiv($window['start'], 60), $window['start'] % 60);
                continue;
            }
            if ($minute >= $window['end']) {
                $cursor = $cursor->modify('tomorrow')->setTime(0, 0);
                continue;
            }
            $dayEnd = $cursor->setTime(intdiv($window['end'], 60), $window['end'] % 60);
            $stop = $end < $dayEnd ? $end : $dayEnd;
            $total += (int) (($stop->getTimestamp() - $cursor->getTimestamp()) / 60);
            $cursor = $stop;
        }

        return $total;
    }

    /**
     * @return array{start: int, end: int}|null
     */
    private function window(\DateTimeImmutable $day): ?array
    {
        $date = $day->format('Y-m-d');
        if (($this->closed()[$date] ?? false) === true) {
            return null;
        }
        $index = (int) $day->format('N');

        return $this->windows()[$index] ?? null;
    }

    /**
     * @return array<int, array{start: int, end: int}|null>
     */
    private function windows(): array
    {
        if ($this->windows !== null) {
            return $this->windows;
        }
        $names = [1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday', 7 => 'sunday'];
        $row = Database::connection()->query(
            'SELECT * FROM work_schedules WHERE is_default = 1 AND active = 1 ORDER BY id LIMIT 1'
        )->fetch() ?: null;
        $windows = [];
        foreach ($names as $index => $name) {
            $start = $row[$name . '_start'] ?? null;
            $end = $row[$name . '_end'] ?? null;
            if ($row === null && $index <= 5) {
                $windows[$index] = ['start' => 8 * 60, 'end' => 17 * 60];
                continue;
            }
            if (!is_string($start) || !is_string($end) || $start === '' || $end === '') {
                $windows[$index] = null;
                continue;
            }
            $windows[$index] = ['start' => $this->minutes($start), 'end' => $this->minutes($end)];
        }
        $this->windows = $windows;

        return $windows;
    }

    /**
     * @return array<string, bool>
     */
    private function closed(): array
    {
        if ($this->closed !== null) {
            return $this->closed;
        }
        $this->closed = [];
        $rows = Database::connection()->query(
            'SELECT exception_date, working_day_override FROM calendar_exceptions'
        )->fetchAll();
        foreach ($rows as $row) {
            if ((int) $row['working_day_override'] === 0) {
                $this->closed[(string) $row['exception_date']] = true;
            }
        }

        return $this->closed;
    }

    private function minutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
    }
}

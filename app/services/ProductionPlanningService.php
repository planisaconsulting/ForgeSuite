<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductionControlRepository;

/**
 * Planning dates are suggestions. They are not a promise.
 * Health names the reason. It is not a score.
 */
final class ProductionPlanningService
{
    public function __construct(
        private readonly ProductionControlRepository $repo = new ProductionControlRepository(),
        private readonly BusinessTimeService $time = new BusinessTimeService(),
        private readonly ReleaseReadinessService $readiness = new ReleaseReadinessService()
    ) {
    }

    /**
     * @param array<string, string> $days
     * @return array{planning_date: string, label: string, at_risk: bool, reason: string|null, capacity_note: string}
     */
    public function latestStart(string $requiredDate, array $days): array
    {
        $minutes = 0;
        $dayMinutes = 480;
        foreach ($days as $step => $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $minutes += (int) round(((float) $value) * $dayMinutes);
        }
        $end = new \DateTimeImmutable($requiredDate . ' 16:00:00');
        $start = $this->time->subtractBusinessMinutes($end, $minutes);
        $today = new \DateTimeImmutable('today');
        $risk = $start < $today;

        return [
            'planning_date' => $start->format('Y-m-d H:i'),
            'label' => 'PLANNING DATE',
            'at_risk' => $risk,
            'reason' => $risk ? 'Today is after the latest safe start for ' . $requiredDate . '.' : null,
            'capacity_note' => 'Other jobs were not moved. Capacity is a separate check.',
        ];
    }

    /**
     * @return array{status: string, reasons: list<string>}
     */
    public function health(int $jobId): array
    {
        $job = $this->repo->job($jobId);
        if ($job === null) {
            return ['status' => 'BLOCKED', 'reasons' => ['The job was not found.']];
        }
        $reasons = [];
        if ((string) $job['preparation_status'] === 'NOT_READY') {
            $reasons[] = 'Not released to production.';
        }
        if ((string) $job['preparation_status'] === 'RELEASE_BLOCKED') {
            $reasons[] = 'Release review is required.';
        }
        $evaluated = $this->readiness->evaluate($jobId);
        foreach ($evaluated['checks'] as $check) {
            if ($check['result'] === 'BLOCK') {
                $reasons[] = $check['message'];
            }
        }
        foreach ($evaluated['materials'] as $row) {
            if ((string) ($row['status'] ?? '') === 'SHORTAGE' || (float) ($row['shortage'] ?? 0) > 0) {
                $reasons[] = (string) $row['name'] . ' is short by ' . (string) $row['shortage'] . '.';
            }
        }
        if ($this->repo->openQcFail($jobId) > 0) {
            $reasons[] = 'QC has failed.';
        }
        $target = (string) ($job['target_date'] ?? '');
        if ($target !== '' && $target < date('Y-m-d') && !in_array((string) $job['status'], ['COMPLETED', 'CANCELLED'], true)) {
            $reasons[] = 'The internal target date ' . $target . ' has passed.';

            return ['status' => 'OVERDUE', 'reasons' => $reasons];
        }
        foreach ($this->repo->stages($jobId) as $stage) {
            if ((string) $stage['status'] === 'BLOCKED') {
                $reasons[] = (string) $stage['stage_name'] . ' is blocked' . ($stage['blocked_reason'] ? ': ' . $stage['blocked_reason'] : '') . '.';

                return ['status' => 'BLOCKED', 'reasons' => $reasons];
            }
        }
        if ($reasons !== []) {
            return ['status' => 'AT_RISK', 'reasons' => $reasons];
        }

        return ['status' => 'ON_TRACK', 'reasons' => ['No release, material, QC, or date exception.']];
    }

    /**
     * @return array{overdue: int, blocked: int, not_released: int}
     */
    public function watchdog(): array
    {
        $overdue = 0;
        $blocked = 0;
        $notReleased = 0;
        foreach ($this->repo->releaseQueue([]) as $job) {
            $health = $this->health((int) $job['id']);
            if ($health['status'] === 'OVERDUE') {
                $overdue++;
            }
            if ($health['status'] === 'BLOCKED') {
                $blocked++;
            }
            if ((string) $job['preparation_status'] === 'NOT_READY') {
                $notReleased++;
            }
        }

        return ['overdue' => $overdue, 'blocked' => $blocked, 'not_released' => $notReleased];
    }

    /**
     * First-pass yield is good quantity divided by good quantity plus rework quantity.
     * Waste is recorded separately and is not treated as a passed item.
     *
     * @return array{formula: string, good: string, rework: string, yield: string|null}
     */
    public function firstPassYield(int $jobId): array
    {
        $good = '0';
        $rework = '0';
        foreach ($this->repo->items($jobId) as $item) {
            $good = \App\Helpers\Decimal::add($good, (string) $item['good_quantity'], 4);
            $rework = \App\Helpers\Decimal::add($rework, (string) $item['rework_quantity'], 4);
        }
        $total = \App\Helpers\Decimal::add($good, $rework, 4);
        $yield = \App\Helpers\Decimal::cmp($total, '0') > 0
            ? \App\Helpers\Decimal::round(\App\Helpers\Decimal::div($good, $total, 6), 4)
            : null;

        return [
            'formula' => 'good / (good + rework)',
            'good' => $good,
            'rework' => $rework,
            'yield' => $yield,
        ];
    }
}

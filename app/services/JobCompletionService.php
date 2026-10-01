<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\WorkshopCodes;
use App\Helpers\Decimal;
use App\Repositories\WorkshopRepository;

/**
 * Extra completion checks for workshop records.
 * Existing jobs with no production items, snags, or quality checks are unchanged.
 */
final class JobCompletionService
{
    public function __construct(private readonly WorkshopRepository $workshop = new WorkshopRepository())
    {
    }

    /**
     * @return list<string>
     */
    public function blockers(int $jobId): array
    {
        $messages = [];
        if ($this->workshop->criticalOpen($jobId) > 0) {
            $messages[] = 'A critical snag is still open.';
        }
        if ((new QualityCheckService())->blocksDispatch($jobId)) {
            $messages[] = 'A quality check failed and has not been resolved.';
        }
        foreach ($this->workshop->productionItems($jobId) as $piece) {
            if (in_array((string) $piece['status'], [WorkshopCodes::ITEM_COMPLETE, WorkshopCodes::ITEM_READY], true)
                && Decimal::cmp((string) $piece['quantity_completed'], (string) $piece['quantity']) >= 0) {
                continue;
            }
            $messages[] = 'Production is not complete.';
            break;
        }
        if (SettingsService::get('workshop_time_tracking', '0') === '1' && $this->workshop->openTime($jobId) > 0) {
            $messages[] = 'A time entry is still open.';
        }

        return $messages;
    }

    /**
     * @return array{reservations: list<array<string, mixed>>, finance: array<string, mixed>}
     */
    public function handoff(int $jobId, int $userId): array
    {
        $finance = (new JobFinancialService())->report($jobId);
        $reservations = $this->workshop->openReservations($jobId);
        $message = 'Commercial ' . ($finance['commercial'] ?? '0')
            . '. Invoiced ' . ($finance['invoiced'] ?? '0')
            . '. Remaining ' . ($finance['remaining'] ?? '0') . '.';
        if ($reservations !== []) {
            $message .= ' ' . count($reservations) . ' material reservation(s) still open. Release, return, or reconcile them.';
        }
        (new WorkshopNotifier())->send('JOB_FULLY_COMPLETED', 'Job completed', $message, 'job', $jobId, 'ACCOUNTS');
        (new AutomationService())->fire('JOB_FULLY_COMPLETED', 'job', $jobId, $userId);

        return ['reservations' => $reservations, 'finance' => $finance];
    }
}

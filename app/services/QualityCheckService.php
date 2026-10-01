<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\WorkshopCodes;
use App\Helpers\Database;
use App\Repositories\WorkshopRepository;

/**
 * Structured quality checks. A failure blocks normal dispatch until it is
 * reworked, reprinted, or an authorised override resolves it.
 */
final class QualityCheckService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function record(int $jobId, array $input, int $userId): array
    {
        if (!can('qc.perform') && !can('production.update')) {
            return ['_form' => 'You cannot record a quality check.'];
        }
        $status = strtoupper(trim((string) ($input['status'] ?? '')));
        if (!in_array($status, [WorkshopCodes::QC_PASS, WorkshopCodes::QC_FAIL, WorkshopCodes::QC_NOTE], true)) {
            return ['status' => 'Choose pass, fail, or pass with a note.'];
        }
        $action = strtoupper(trim((string) ($input['fail_action'] ?? '')));
        $reason = trim((string) ($input['fail_reason'] ?? ''));
        if ($status === WorkshopCodes::QC_FAIL) {
            if ($reason === '') {
                return ['fail_reason' => 'Record why the check failed.'];
            }
            if (!in_array($action, ['REWORK', 'REPRINT', 'HOLD'], true)) {
                return ['fail_action' => 'Choose rework, reprint, or hold.'];
            }
        }
        $productionId = (int) ($input['production_item_id'] ?? 0);
        $item = $productionId > 0 ? $this->workshop->productionItem($productionId) : null;
        if ($productionId > 0 && ($item === null || (int) $item['job_id'] !== $jobId)) {
            return ['production_item_id' => 'That piece is not on this job.'];
        }
        Database::transaction(function () use ($jobId, $input, $userId, $status, $action, $reason, $item, $productionId): void {
            $id = $this->workshop->insertQuality([
                'job_id' => $jobId,
                'job_item_id' => $item['job_item_id'] ?? (((int) ($input['job_item_id'] ?? 0)) > 0 ? (int) $input['job_item_id'] : null),
                'production_item_id' => $productionId > 0 ? $productionId : null,
                'check_type' => mb_substr(trim((string) ($input['check_type'] ?? 'FINAL')), 0, 120),
                'status' => $status,
                'fail_reason' => $reason !== '' ? $reason : null,
                'fail_action' => $status === WorkshopCodes::QC_FAIL ? $action : null,
                'notes' => blank_to_null($input['notes'] ?? null),
                'checked_by' => $userId,
            ]);
            if ($item !== null && $status === WorkshopCodes::QC_FAIL) {
                $this->workshop->updateProductionItem((int) $item['id'], [
                    'status' => WorkshopCodes::ITEM_QC_FAILED,
                    'quantity_completed' => (string) $item['quantity_completed'],
                    'current_stage_id' => $item['current_stage_id'],
                    'assigned_resource_id' => $item['assigned_resource_id'],
                ]);
            }
            $this->audit->record('job', $jobId, $status === WorkshopCodes::QC_FAIL ? 'QC_FAILED' : 'QC_PASSED', null, [
                'quality_check_id' => $id,
                'status' => $status,
                'production_item_id' => $productionId > 0 ? $productionId : null,
            ], $userId);
            $trigger = $status === WorkshopCodes::QC_FAIL ? 'QC_FAILED' : 'QC_PASSED';
            (new AutomationService())->fire($trigger, 'job', $jobId, $userId);
            if ($status === WorkshopCodes::QC_FAIL) {
                (new WorkshopNotifier())->send('QC_FAILED', 'Quality check failed', $reason, 'job', $jobId);
            }
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function override(int $checkId, string $reason, int $userId): array
    {
        if (!can('qc.override')) {
            return ['_form' => 'You cannot override a failed quality check.'];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['reason' => 'An override needs a reason.'];
        }
        $this->workshop->resolveQuality($checkId, $reason);
        $this->audit->record('quality_check', $checkId, 'QC_OVERRIDDEN', null, ['reason' => $reason], $userId);

        return [];
    }

    public function blocksDispatch(int $jobId): bool
    {
        return $this->workshop->unresolvedFail($jobId) !== null;
    }
}

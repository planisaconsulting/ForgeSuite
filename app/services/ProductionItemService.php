<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\JobItemStatus;
use App\Domain\WorkshopCodes;
use App\Helpers\Decimal;
use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\WorkshopRepository;

/**
 * Physical pieces or batches that belong to an existing job item.
 * A sticker run stays one batch. Simple services stay untracked.
 */
final class ProductionItemService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly TrackingCodeService $tracking = new TrackingCodeService()
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function setMode(int $jobItemId, string $mode, int $userId): array
    {
        if (!can('production.update') && !can('production.start')) {
            return ['_form' => 'You cannot change tracking for this item.'];
        }
        $mode = strtoupper(trim($mode));
        if (!in_array($mode, WorkshopCodes::trackingModes(), true)) {
            return ['tracking_mode' => 'Choose batch, individual, or none.'];
        }
        $item = $this->ops->item($jobItemId);
        if ($item === null) {
            return ['_form' => 'That job item was not found.'];
        }
        if ($this->workshop->itemsForJobItem($jobItemId) !== []) {
            return ['_form' => 'Tracking is already in use for this item. Finish or keep the existing pieces.'];
        }
        $this->workshop->setTrackingMode($jobItemId, $mode);
        (new AuditService())->record('job', (int) $item['job_id'], 'TRACKING_MODE_SET', null, [
            'job_item_id' => $jobItemId,
            'tracking_mode' => $mode,
        ], $userId);

        return [];
    }

    public function generateForJob(int $jobId, int $userId): void
    {
        $job = (new JobRepository())->find($jobId);
        if ($job === null) {
            return;
        }
        foreach ($this->ops->items($jobId) as $item) {
            $this->generateForItem($item, $job, $userId);
        }
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $job
     * @return list<int>
     */
    public function generateForItem(array $item, array $job, int $userId): array
    {
        $mode = strtoupper((string) ($item['tracking_mode'] ?? WorkshopCodes::MODE_NONE));
        if ($mode === WorkshopCodes::MODE_NONE || $mode === '') {
            return [];
        }
        $existing = $this->workshop->itemsForJobItem((int) $item['id']);
        if ($existing !== []) {
            return array_map(static fn (array $row): int => (int) $row['id'], $existing);
        }
        $cap = (int) SettingsService::get('individual_tracking_cap', '200');
        if ($cap < 1) {
            $cap = 200;
        }
        $qty = Decimal::round((string) ($item['quantity'] ?? '1'), 4);
        $pieces = 1;
        $each = $qty;
        if ($mode === WorkshopCodes::MODE_INDIVIDUAL) {
            $whole = (int) Decimal::round($qty, 0);
            if ($whole > $cap) {
                $mode = WorkshopCodes::MODE_BATCH;
            } elseif ($whole > 0) {
                $pieces = $whole;
                $each = '1.0000';
            }
        }
        $ids = [];
        $stages = $this->workshop->stages((int) $job['id'], (int) $item['id']);
        $stageId = $stages[0]['id'] ?? null;
        $sequence = 1;
        $created = 0;
        while ($created < $pieces) {
            $code = $this->code((string) $job['job_number'], (int) ($item['sort_order'] ?? 1), $sequence);
            $sequence++;
            if ($sequence > $pieces + 1000) {
                break;
            }
            if ($this->tracking->findByCode($code) !== null || $this->workshop->productionByCode($code) !== null) {
                continue;
            }
            $n = $created + 1;
            $id = $this->workshop->insertProductionItem([
                'job_id' => (int) $job['id'],
                'job_item_id' => (int) $item['id'],
                'tracking_code' => $code,
                'description' => mb_substr((string) ($item['description'] ?? $job['title']), 0, 255),
                'quantity' => $each,
                'quantity_completed' => '0.0000',
                'sequence_number' => $n,
                'status' => WorkshopCodes::ITEM_WAITING,
                'current_stage_id' => $stageId !== null ? (int) $stageId : null,
                'assigned_resource_id' => null,
            ]);
            $this->tracking->ensure('PRODUCTION_ITEM', $id, $code, $userId);
            $ids[] = $id;
            $created++;
        }

        return $ids;
    }

    private function code(string $jobNumber, int $itemSeq, int $piece): string
    {
        $parts = explode('-', $jobNumber);
        $tail = count($parts) >= 2 ? implode('-', array_slice($parts, -2)) : $jobNumber;
        $itemSeq = max(1, $itemSeq);

        return 'PI-' . $tail . '-' . str_pad((string) $itemSeq, 2, '0', STR_PAD_LEFT) . '-' . str_pad((string) $piece, 2, '0', STR_PAD_LEFT);
    }

    public function syncJobItem(int $jobItemId): void
    {
        $pieces = $this->workshop->itemsForJobItem($jobItemId);
        if ($pieces === []) {
            return;
        }
        $open = false;
        foreach ($pieces as $piece) {
            if (!in_array((string) $piece['status'], [WorkshopCodes::ITEM_COMPLETE, WorkshopCodes::ITEM_READY], true)) {
                $open = true;
            }
            if (Decimal::cmp((string) $piece['quantity_completed'], (string) $piece['quantity']) < 0) {
                $open = true;
            }
        }
        $this->workshop->setItemStatus(
            $jobItemId,
            $open ? JobItemStatus::InProduction->value : JobItemStatus::Complete->value
        );
    }
}

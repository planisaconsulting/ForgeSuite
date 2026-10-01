<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\InventoryRepository;
use App\Repositories\WorkshopRepository;

/**
 * Checks a scanned roll, sheet, or offcut against the job, then consumes it
 * once through the existing stock ledger.
 */
final class MaterialScanService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly TrackingCodeService $tracking = new TrackingCodeService(),
        private readonly InventoryRepository $inventory = new InventoryRepository()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(int $jobId, string $codeOrToken): array
    {
        if (!can('workshop.scan') && !can('materials.record_usage')) {
            return ['ok' => false, 'error' => 'You cannot scan material.'];
        }
        $item = $this->resolveItem($codeOrToken);
        if ($item === null) {
            return ['ok' => false, 'error' => 'That material code was not found.'];
        }
        $requirement = $this->workshop->requirement($jobId, (int) $item['product_id']);
        $match = $requirement === null || (int) $requirement['product_id'] === (int) $item['product_id'];
        if ($requirement !== null && (int) $requirement['product_id'] !== (int) $item['product_id']) {
            $match = false;
        }

        return [
            'ok' => true,
            'match' => $match,
            'error' => $match ? '' : 'MATERIAL DOES NOT MATCH REQUIREMENT',
            'item' => $item,
            'requirement' => $requirement,
            'reserved' => $this->workshop->reserved($jobId, (int) $item['id']),
            'consumed' => $this->workshop->consumed($jobId, (int) $item['product_id']),
            'remaining' => (string) $item['remaining_quantity'],
            'location' => (string) ($item['location_name'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function issue(int $jobId, array $input, int $userId): array
    {
        if (!can('materials.record_usage') && !can('workshop.scan')) {
            return ['errors' => ['_form' => 'You cannot issue material.'], 'id' => null];
        }
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        if ($key === '') {
            return ['errors' => ['idempotency_key' => 'A repeat of this issue needs an idempotency key.'], 'id' => null];
        }
        $existing = $this->workshop->issueByKey($key);
        if ($existing !== null) {
            return ['errors' => [], 'id' => (int) $existing['id']];
        }
        $item = $this->resolveItem((string) ($input['code'] ?? ''));
        if ($item === null) {
            return ['errors' => ['code' => 'Scan a roll, sheet, or offcut code.'], 'id' => null];
        }
        $production = Decimal::round((string) ($input['production_quantity'] ?? '0'), 4);
        $waste = Decimal::round((string) ($input['waste_quantity'] ?? '0'), 4);
        if (Decimal::cmp($production, '0') < 0 || Decimal::cmp($waste, '0') < 0) {
            return ['errors' => ['production_quantity' => 'Quantities cannot be negative.'], 'id' => null];
        }
        if (Decimal::cmp(Decimal::add($production, $waste), '0') <= 0 && empty($input['offcut_width_mm'])) {
            return ['errors' => ['production_quantity' => 'Enter the amount used.'], 'id' => null];
        }
        $requirement = $this->workshop->requirement($jobId, (int) $item['product_id']);
        $wanted = $requirement !== null ? (int) $requirement['product_id'] : (int) ($input['expected_product_id'] ?? 0);
        $override = trim((string) ($input['override_reason'] ?? ''));
        if ($wanted > 0 && $wanted !== (int) $item['product_id']) {
            if ($override === '' || !can('production.override_material')) {
                return ['errors' => ['_form' => 'MATERIAL DOES NOT MATCH REQUIREMENT'], 'id' => null];
            }
        }
        $usageId = null;
        $wasteId = null;
        $offcutId = null;
        try {
            $result = Database::transaction(function () use ($jobId, $input, $userId, $key, $item, $production, $waste, $override, &$usageId, &$wasteId, &$offcutId): int {
                if ($this->workshop->issueByKey($key) !== null) {
                    return (int) $this->workshop->issueByKey($key)['id'];
                }
                $materials = new MaterialUsageService(stock: new JobStockHook());
                if (Decimal::cmp($production, '0') > 0) {
                    $recorded = $materials->record($jobId, [
                        'usage_type' => 'PRODUCTION',
                        'product_id' => (int) $item['product_id'],
                        'quantity' => $production,
                        'job_item_id' => (int) ($input['job_item_id'] ?? 0),
                        'inventory_item_id' => (int) $item['id'],
                        'reason' => 'NORMAL',
                        'notes' => 'Workshop material issue',
                    ], $userId);
                    if ($recorded['errors'] !== []) {
                        throw new StockRejected($recorded['errors']);
                    }
                    $usageId = $recorded['id'];
                }
                if (Decimal::cmp($waste, '0') > 0) {
                    $recorded = $materials->record($jobId, [
                        'usage_type' => 'WASTE',
                        'product_id' => (int) $item['product_id'],
                        'quantity' => $waste,
                        'job_item_id' => (int) ($input['job_item_id'] ?? 0),
                        'inventory_item_id' => (int) $item['id'],
                        'reason' => 'TRIM',
                        'notes' => 'Workshop waste',
                    ], $userId);
                    if ($recorded['errors'] !== []) {
                        throw new StockRejected($recorded['errors']);
                    }
                    $wasteId = $recorded['id'];
                }
                if (trim((string) ($input['offcut_width_mm'] ?? '')) !== '') {
                    $offcut = (new StockMovementService())->createOffcut([
                        'product_id' => (int) $item['product_id'],
                        'stock_location_id' => (int) $item['stock_location_id'],
                        'width_mm' => $input['offcut_width_mm'],
                        'height_mm' => $input['offcut_height_mm'] ?? '',
                        'source_inventory_item_id' => (int) $item['id'],
                        'job_id' => $jobId,
                        'notes' => 'Created from workshop issue',
                    ], $userId);
                    if ($offcut['id'] === null) {
                        throw new StockRejected($offcut['errors']);
                    }
                    $offcutId = $offcut['id'];
                    $offcutRow = $this->inventory->item((int) $offcutId);
                    if ($offcutRow !== null) {
                        $this->tracking->ensure('OFFCUT', (int) $offcutId, (string) $offcutRow['inventory_code'], $userId);
                    }
                }
                $id = $this->workshop->insertIssue([
                    'idempotency_key' => $key,
                    'job_id' => $jobId,
                    'job_item_id' => ((int) ($input['job_item_id'] ?? 0)) > 0 ? (int) $input['job_item_id'] : null,
                    'production_item_id' => ((int) ($input['production_item_id'] ?? 0)) > 0 ? (int) $input['production_item_id'] : null,
                    'inventory_item_id' => (int) $item['id'],
                    'product_id' => (int) $item['product_id'],
                    'production_quantity' => $production,
                    'waste_quantity' => $waste,
                    'override_reason' => $override !== '' ? $override : null,
                    'usage_id' => $usageId,
                    'waste_usage_id' => $wasteId,
                    'offcut_inventory_item_id' => $offcutId,
                    'created_by' => $userId,
                ]);
                $this->tracking->recordScan([
                    'entity_type' => (string) $item['inventory_type'],
                    'entity_id' => (int) $item['id'],
                    'tracking_code' => (string) $item['inventory_code'],
                    'token_id' => null,
                ], 'MATERIAL_ISSUED', $userId, null, ['job_id' => $jobId, 'issue_id' => $id]);

                return $id;
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $result];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveItem(string $codeOrToken): ?array
    {
        $codeOrToken = trim($codeOrToken);
        if ($codeOrToken === '') {
            return null;
        }
        $resolved = $this->tracking->resolve($codeOrToken);
        if ($resolved !== null && in_array($resolved['entity_type'], ['ROLL', 'SHEET', 'OFFCUT', 'INVENTORY_ITEM'], true)) {
            return $this->inventory->item((int) $resolved['entity_id']);
        }
        if (preg_match('/^[a-f0-9]{64}$/i', $codeOrToken)) {
            return null;
        }

        return $this->workshop->inventoryByCode($codeOrToken);
    }
}

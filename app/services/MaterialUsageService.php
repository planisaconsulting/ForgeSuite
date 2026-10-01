<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\MaterialUsageType;
use App\Domain\WasteReason;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\OperationsRepository;
use App\Repositories\ProductRepository;

/**
 * Records material actually consumed on a job.
 *
 * The unit cost is copied from the product at this moment. A price posted
 * with the form is ignored, and later catalogue changes do not rewrite the
 * row. When a StockConsumptionHook is attached, a tracked product also
 * writes a stock movement in the same transaction. Untracked products
 * still record usage only. A posted unit cost is ignored.
 */
final class MaterialUsageService
{
    public function __construct(
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly JobCostingService $costing = new JobCostingService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly ?StockConsumptionHook $stock = null
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function record(int $jobId, array $input, int $userId): array
    {
        if (!can('materials.record_usage')) {
            return ['errors' => ['_form' => 'You cannot record material on a job.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['usage_type'] ?? '')));
        $usage = MaterialUsageType::tryFrom($type);
        if ($usage === null) {
            return ['errors' => ['usage_type' => 'Choose how the material was used.'], 'id' => null];
        }
        $productId = (int) ($input['product_id'] ?? 0);
        $product = $productId > 0 ? $this->products->find($productId) : null;
        if ($product === null) {
            return ['errors' => ['product_id' => 'Choose a product.'], 'id' => null];
        }
        $quantity = trim((string) ($input['quantity'] ?? ''));
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
            return ['errors' => ['quantity' => 'Enter a quantity greater than zero.'], 'id' => null];
        }
        $reason = strtoupper(trim((string) ($input['reason'] ?? '')));
        if ($usage->needsReason()) {
            if (WasteReason::tryFrom($reason) === null || $reason === WasteReason::Normal->value) {
                return ['errors' => ['reason' => 'Record why this material was wasted or reworked.'], 'id' => null];
            }
        } elseif ($reason === '') {
            $reason = WasteReason::Normal->value;
        } elseif (WasteReason::tryFrom($reason) === null) {
            return ['errors' => ['reason' => 'That reason is not available.'], 'id' => null];
        }
        $itemId = (int) ($input['job_item_id'] ?? 0);
        if ($itemId > 0 && !$this->onJob($jobId, $itemId)) {
            return ['errors' => ['job_item_id' => 'That item is not on this job.'], 'id' => null];
        }
        $unitCost = Decimal::round((string) ($product['cost_price'] ?? '0'), 4);
        $unit = substr(trim((string) ($product['cost_unit'] ?? 'unit')), 0, 20);
        if ($unit === '') {
            $unit = 'unit';
        }
        if ($this->stock !== null) {
            try {
                $quoted = $this->stock->prepare($product, $input);
            } catch (StockRejected $e) {
                return ['errors' => $e->errors, 'id' => null];
            }
            if ($quoted !== null) {
                $unitCost = $quoted['unit_cost'];
                if ($quoted['unit'] !== '') {
                    $unit = substr($quoted['unit'], 0, 20);
                }
            }
        }
        $total = Decimal::money(Decimal::mul($quantity, $unitCost));
        $usageId = 0;
        try {
            Database::transaction(function () use ($jobId, $itemId, $productId, $product, $usage, $quantity, $unit, $unitCost, $total, $reason, $input, $userId, &$usageId): void {
                $usageId = $this->ops->insertUsage([
                    'job_id' => $jobId,
                    'job_item_id' => $itemId > 0 ? $itemId : null,
                    'product_id' => $productId,
                    'usage_type' => $usage->value,
                    'quantity' => Decimal::round($quantity, 4),
                    'unit' => $unit,
                    'unit_cost_snapshot' => $unitCost,
                    'total_cost' => $total,
                    'reason' => $reason,
                    'notes' => blank_to_null($input['notes'] ?? null),
                    'recorded_by' => $userId,
                ]);
                $action = $usage->needsReason() ? 'WASTE_RECORDED' : 'MATERIAL_RECORDED';
                $this->audit->record('job', $jobId, $action, null, [
                    'usage_id' => $usageId,
                    'product_id' => $productId,
                    'usage_type' => $usage->value,
                    'quantity' => Decimal::round($quantity, 4),
                    'unit_cost_snapshot' => $unitCost,
                ], $userId);
                if ($this->stock !== null) {
                    $this->stock->recordConsumption([
                        'usage_id' => $usageId,
                        'job_id' => $jobId,
                        'product_id' => $productId,
                        'usage_type' => $usage->value,
                        'quantity' => Decimal::round($quantity, 4),
                        'unit' => $unit,
                        'unit_cost' => $unitCost,
                        'inventory_item_id' => (int) ($input['inventory_item_id'] ?? 0),
                        'stock_location_id' => (int) ($input['stock_location_id'] ?? 0),
                        'reservation_id' => (int) ($input['reservation_id'] ?? 0),
                        'allow_negative' => posted_flag($input, 'stock_override', 0) === 1,
                        'override_reason' => trim((string) ($input['override_reason'] ?? '')),
                        'reason' => $reason,
                        'notes' => blank_to_null($input['notes'] ?? null),
                        'user_id' => $userId,
                    ]);
                }
                $this->costing->refresh($jobId);
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $usageId];
    }

    private function onJob(int $jobId, int $itemId): bool
    {
        $item = $this->ops->item($itemId);

        return $item !== null && (int) $item['job_id'] === $jobId;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\CostingMethod;
use App\Domain\InventoryItemStatus;
use App\Domain\InventoryItemType;
use App\Domain\InventoryMethod;
use App\Domain\MovementType;
use App\Domain\OffcutValuation;
use App\Domain\ReservationStatus;
use App\Domain\StockCountStatus;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;

/**
 * The stock ledger.
 *
 * Movements are insert-only. A mistake is corrected with another movement.
 * On-hand quantity is the sum of signed quantities. Reservations sit beside
 * that sum until they are consumed, released, or cancelled.
 */
final class StockMovementService
{
    public function __construct(
        private readonly InventoryRepository $inventory = new InventoryRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly AuditService $audit = new AuditService(),
        private readonly NumberingService $numbers = new NumberingService()
    ) {
    }

    public function isTracked(array $product): bool
    {
        $method = (string) ($product['inventory_method'] ?? 'NONE');

        return $method !== '' && $method !== InventoryMethod::None->value;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function opening(array $input, int $userId): array
    {
        if (!can('inventory.receive')) {
            return ['errors' => ['_form' => 'You cannot receive stock.'], 'id' => null];
        }

        return $this->guard(function () use ($input, $userId): int {
            $product = $this->requireProduct((int) ($input['product_id'] ?? 0));
            $locationId = $this->requireLocation((int) ($input['stock_location_id'] ?? 0));
            $method = InventoryMethod::tryFrom((string) ($product['inventory_method'] ?? 'NONE')) ?? InventoryMethod::None;
            $cost = $this->moneyCost($input['unit_cost'] ?? null, (string) $product['cost_price']);
            if ($method === InventoryMethod::Roll) {
                return $this->createRoll($product, $locationId, $input, $cost, $userId, MovementType::OpeningBalance, []);
            }
            if ($method === InventoryMethod::Sheet || $method === InventoryMethod::Length) {
                return $this->createPieces($product, $locationId, $input, $cost, $userId, MovementType::OpeningBalance, []);
            }
            $qty = $this->positive($input['quantity'] ?? '', 'quantity');
            $this->applyReceiptCost($product, $qty, $cost, $userId);
            return $this->post([
                'product_id' => (int) $product['id'],
                'stock_location_id' => $locationId,
                'movement_type' => MovementType::OpeningBalance->value,
                'quantity' => $qty,
                'unit' => $this->unit($product, null),
                'unit_cost_snapshot' => $cost,
                'reference_type' => 'opening',
                'reason' => 'Opening balance',
                'notes' => blank_to_null($input['notes'] ?? null),
                'created_by' => $userId,
            ]);
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function adjust(array $input, int $userId): array
    {
        if (!can('inventory.adjust')) {
            return ['errors' => ['_form' => 'You cannot adjust stock.'], 'id' => null];
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            return ['errors' => ['reason' => 'An adjustment needs a reason.'], 'id' => null];
        }

        return $this->guard(function () use ($input, $userId, $reason): int {
            $product = $this->requireProduct((int) ($input['product_id'] ?? 0));
            $locationId = $this->requireLocation((int) ($input['stock_location_id'] ?? 0));
            $direction = strtoupper(trim((string) ($input['direction'] ?? 'OUT')));
            $type = $direction === 'IN' ? MovementType::AdjustmentIn : MovementType::AdjustmentOut;
            $qty = $this->positive($input['quantity'] ?? '', 'quantity');
            $itemId = (int) ($input['inventory_item_id'] ?? 0);
            $id = $this->post([
                'product_id' => (int) $product['id'],
                'stock_location_id' => $locationId,
                'inventory_item_id' => $itemId > 0 ? $itemId : null,
                'movement_type' => $type->value,
                'quantity' => $qty,
                'unit' => $this->unit($product, $itemId > 0 ? $this->inventory->item($itemId) : null),
                'unit_cost_snapshot' => $this->costFor($product, $itemId),
                'reference_type' => 'adjustment',
                'reason' => substr($reason, 0, 80),
                'notes' => blank_to_null($input['notes'] ?? null),
                'created_by' => $userId,
                'allow_negative' => false,
            ]);
            $this->audit->record('product', (int) $product['id'], 'STOCK_ADJUSTED', null, [
                'movement_id' => $id,
                'direction' => $direction,
                'quantity' => $qty,
                'reason' => $reason,
            ], $userId);

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function transfer(array $input, int $userId): array
    {
        if (!can('inventory.transfer')) {
            return ['_form' => 'You cannot transfer stock.'];
        }

        try {
            Database::transaction(function () use ($input, $userId): void {
                $product = $this->requireProduct((int) ($input['product_id'] ?? 0));
                $from = $this->requireLocation((int) ($input['from_location_id'] ?? 0));
                $to = $this->requireLocation((int) ($input['to_location_id'] ?? 0));
                if ($from === $to) {
                    throw new StockRejected(['to_location_id' => 'Choose a different location.']);
                }
                $qty = $this->positive($input['quantity'] ?? '', 'quantity');
                $itemId = (int) ($input['inventory_item_id'] ?? 0);
                $item = $itemId > 0 ? $this->inventory->lockItem($itemId) : null;
                if ($itemId > 0 && ($item === null || (int) $item['product_id'] !== (int) $product['id'])) {
                    throw new StockRejected(['inventory_item_id' => 'That inventory item is not this product.']);
                }
                $unit = $this->unit($product, $item);
                $cost = $this->costFor($product, $itemId);
                $transferId = $this->inventory->insertTransfer([
                    'product_id' => (int) $product['id'],
                    'inventory_item_id' => $itemId > 0 ? $itemId : null,
                    'from_location_id' => $from,
                    'to_location_id' => $to,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'reason' => blank_to_null($input['reason'] ?? null),
                    'notes' => blank_to_null($input['notes'] ?? null),
                    'created_by' => $userId,
                ]);
                $this->post([
                    'product_id' => (int) $product['id'],
                    'stock_location_id' => $from,
                    'inventory_item_id' => $itemId > 0 ? $itemId : null,
                    'movement_type' => MovementType::TransferOut->value,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'unit_cost_snapshot' => $cost,
                    'reference_type' => 'transfer',
                    'reference_id' => $transferId,
                    'reason' => 'Transfer',
                    'created_by' => $userId,
                    'adjust_item' => $itemId === 0,
                ]);
                $this->post([
                    'product_id' => (int) $product['id'],
                    'stock_location_id' => $to,
                    'inventory_item_id' => $itemId > 0 ? $itemId : null,
                    'movement_type' => MovementType::TransferIn->value,
                    'quantity' => $qty,
                    'unit' => $unit,
                    'unit_cost_snapshot' => $cost,
                    'reference_type' => 'transfer',
                    'reference_id' => $transferId,
                    'reason' => 'Transfer',
                    'created_by' => $userId,
                    'adjust_item' => false,
                ]);
                if ($item !== null) {
                    $this->inventory->updateItemQuantity($itemId, (string) $item['remaining_quantity'], (string) $item['status'], $to);
                }
                $this->audit->record('product', (int) $product['id'], 'STOCK_TRANSFERRED', null, [
                    'transfer_id' => $transferId,
                    'quantity' => $qty,
                    'from' => $from,
                    'to' => $to,
                ], $userId);
            });
        } catch (StockRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function reserve(array $input, int $userId): array
    {
        if (!can('inventory.consume') && !can('materials.record_usage')) {
            return ['errors' => ['_form' => 'You cannot reserve stock.'], 'id' => null];
        }

        return $this->guard(function () use ($input, $userId): int {
            $product = $this->requireProduct((int) ($input['product_id'] ?? 0));
            $jobId = (int) ($input['job_id'] ?? 0);
            if ($jobId < 1) {
                throw new StockRejected(['job_id' => 'Choose a job.']);
            }
            $qty = $this->positive($input['quantity'] ?? '', 'quantity');
            $itemId = (int) ($input['inventory_item_id'] ?? 0);
            $locationId = (int) ($input['stock_location_id'] ?? 0);
            $this->inventory->lockProduct((int) $product['id']);
            $item = null;
            if ($itemId > 0) {
                $item = $this->inventory->lockItem($itemId);
                if ($item === null || (int) $item['product_id'] !== (int) $product['id']) {
                    throw new StockRejected(['inventory_item_id' => 'That inventory item is not this product.']);
                }
                $locationId = (int) $item['stock_location_id'];
                $free = Decimal::sub((string) $item['remaining_quantity'], $this->inventory->reserved((int) $product['id'], null, $itemId, (string) $item['inventory_type'] === 'OFFCUT'), 4);
            } else {
                $locationId = $this->requireLocation($locationId);
                $free = StockValuation::available(
                    $this->inventory->onHand((int) $product['id'], $locationId, false),
                    $this->inventory->reserved((int) $product['id'], $locationId, null, false)
                );
            }
            if (Decimal::cmp($qty, $free) > 0) {
                throw new StockRejected(['_form' => 'Not enough stock. Available is ' . $free . '.']);
            }
            $id = $this->inventory->insertReservation([
                'job_id' => $jobId,
                'job_material_requirement_id' => ((int) ($input['requirement_id'] ?? 0)) > 0 ? (int) $input['requirement_id'] : null,
                'product_id' => (int) $product['id'],
                'inventory_item_id' => $itemId > 0 ? $itemId : null,
                'stock_location_id' => $locationId,
                'quantity' => $qty,
                'unit' => $this->unit($product, $item),
                'status' => ReservationStatus::Reserved->value,
                'reserved_by' => $userId,
                'notes' => blank_to_null($input['notes'] ?? null),
            ]);
            if ($item !== null && Decimal::cmp($qty, (string) $item['remaining_quantity']) >= 0) {
                $this->inventory->updateItemQuantity($itemId, (string) $item['remaining_quantity'], InventoryItemStatus::Reserved->value);
            }

            return $id;
        });
    }

    /**
     * @return array<string, string>
     */
    public function release(int $reservationId, int $userId): array
    {
        if (!can('inventory.consume') && !can('materials.record_usage')) {
            return ['_form' => 'You cannot release a reservation.'];
        }
        try {
            Database::transaction(function () use ($reservationId): void {
                $row = $this->inventory->reservation($reservationId);
                if ($row === null || (string) $row['status'] !== ReservationStatus::Reserved->value) {
                    throw new StockRejected(['_form' => 'That reservation is not open.']);
                }
                $this->inventory->setReservation($reservationId, (string) $row['quantity'], ReservationStatus::Released->value);
                $itemId = (int) ($row['inventory_item_id'] ?? 0);
                if ($itemId > 0) {
                    $item = $this->inventory->lockItem($itemId);
                    if ($item !== null && (string) $item['status'] === InventoryItemStatus::Reserved->value) {
                        $this->inventory->updateItemQuantity($itemId, (string) $item['remaining_quantity'], InventoryItemStatus::Available->value);
                    }
                }
            });
        } catch (StockRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function jobReturn(array $input, int $userId): array
    {
        if (!can('inventory.consume') && !can('materials.record_usage')) {
            return ['errors' => ['_form' => 'You cannot return material.'], 'id' => null];
        }

        return $this->guard(function () use ($input, $userId): int {
            $product = $this->requireProduct((int) ($input['product_id'] ?? 0));
            $qty = $this->positive($input['quantity'] ?? '', 'quantity');
            $itemId = (int) ($input['inventory_item_id'] ?? 0);
            $locationId = $itemId > 0
                ? (int) (($this->inventory->item($itemId)['stock_location_id'] ?? 0))
                : $this->requireLocation((int) ($input['stock_location_id'] ?? 0));
            $cost = $this->costFor($product, $itemId);
            $jobId = (int) ($input['job_id'] ?? 0);
            $id = $this->post([
                'product_id' => (int) $product['id'],
                'stock_location_id' => $locationId,
                'inventory_item_id' => $itemId > 0 ? $itemId : null,
                'movement_type' => MovementType::JobReturn->value,
                'quantity' => $qty,
                'unit' => $this->unit($product, $itemId > 0 ? $this->inventory->item($itemId) : null),
                'unit_cost_snapshot' => $cost,
                'job_id' => $jobId > 0 ? $jobId : null,
                'reference_type' => 'job_return',
                'reason' => 'Returned from job',
                'notes' => blank_to_null($input['notes'] ?? null),
                'created_by' => $userId,
            ]);
            if ($jobId > 0) {
                $negative = Decimal::money(Decimal::mul(Decimal::sub('0', $qty, 4), $cost));
                (new \App\Repositories\OperationsRepository())->insertUsage([
                    'job_id' => $jobId,
                    'job_item_id' => null,
                    'product_id' => (int) $product['id'],
                    'usage_type' => 'OTHER',
                    'quantity' => Decimal::sub('0', $qty, 4),
                    'unit' => $this->unit($product, null),
                    'unit_cost_snapshot' => $cost,
                    'total_cost' => $negative,
                    'reason' => 'NORMAL',
                    'notes' => 'Returned to stock',
                    'recorded_by' => $userId,
                ]);
                (new JobCostingService())->refresh($jobId);
            }
            $this->audit->record('product', (int) $product['id'], 'STOCK_RETURNED', null, [
                'movement_id' => $id,
                'quantity' => $qty,
                'job_id' => $jobId,
            ], $userId);

            return $id;
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createOffcut(array $input, int $userId): array
    {
        if (!can('inventory.consume') && !can('inventory.receive')) {
            return ['errors' => ['_form' => 'You cannot create an offcut.'], 'id' => null];
        }

        return $this->guard(function () use ($input, $userId): int {
            $product = $this->requireProduct((int) ($input['product_id'] ?? 0));
            $locationId = $this->requireLocation((int) ($input['stock_location_id'] ?? 0));
            $width = $this->positive($input['width_mm'] ?? '', 'width_mm');
            $height = $this->positive($input['height_mm'] ?? ($input['length_mm'] ?? ''), 'height_mm');
            $sourceId = (int) ($input['source_inventory_item_id'] ?? 0);
            $source = $sourceId > 0 ? $this->inventory->item($sourceId) : null;
            $acquisition = $source !== null ? (string) $source['acquisition_cost'] : (string) $product['cost_price'];
            $treatment = (string) SettingsService::get('offcut_valuation', 'REDUCED_COST');
            $percent = (string) SettingsService::get('offcut_value_percent', '50');
            $unitCost = StockValuation::offcutUnitCost($acquisition, $treatment, $percent);
            $code = $this->numbers->offcut();
            $itemId = $this->inventory->insertItem([
                'product_id' => (int) $product['id'],
                'stock_location_id' => $locationId,
                'inventory_type' => InventoryItemType::Offcut->value,
                'inventory_code' => $code,
                'supplier_id' => null,
                'purchase_order_item_id' => null,
                'source_inventory_item_id' => $sourceId > 0 ? $sourceId : null,
                'source_job_id' => ((int) ($input['job_id'] ?? 0)) > 0 ? (int) $input['job_id'] : null,
                'status' => InventoryItemStatus::Available->value,
                'received_date' => date('Y-m-d'),
                'expiry_date' => null,
                'original_quantity' => '0.0000',
                'remaining_quantity' => '0.0000',
                'unit' => 'piece',
                'unit_cost' => $unitCost,
                'acquisition_cost' => Decimal::round($acquisition, 4),
                'valuation_treatment' => $treatment,
                'width_mm' => Decimal::round($width, 2),
                'length_mm' => null,
                'height_mm' => Decimal::round($height, 2),
                'batch_number' => null,
                'supplier_reference' => null,
                'notes' => blank_to_null($input['notes'] ?? null),
            ]);
            $this->inventory->setOriginalQuantity($itemId, '1.0000');
            $this->post([
                'product_id' => (int) $product['id'],
                'stock_location_id' => $locationId,
                'inventory_item_id' => $itemId,
                'movement_type' => MovementType::OffcutCreated->value,
                'quantity' => '1',
                'unit' => 'piece',
                'unit_cost_snapshot' => $unitCost,
                'job_id' => ((int) ($input['job_id'] ?? 0)) > 0 ? (int) $input['job_id'] : null,
                'reference_type' => 'offcut',
                'reference_id' => $itemId,
                'reason' => 'Usable offcut',
                'created_by' => $userId,
            ]);
            $this->audit->record('inventory_item', $itemId, 'OFFCUT_CREATED', null, [
                'code' => $code,
                'width_mm' => $width,
                'height_mm' => $height,
                'unit_cost' => $unitCost,
            ], $userId);

            return $itemId;
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function supplierReturn(array $input, int $userId): array
    {
        if (!can('inventory.adjust') && !can('purchasing.receive')) {
            return ['errors' => ['_form' => 'You cannot return stock to a supplier.'], 'id' => null];
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            return ['errors' => ['reason' => 'A supplier return needs a reason.'], 'id' => null];
        }

        return $this->guard(function () use ($input, $userId, $reason): int {
            $product = $this->requireProduct((int) ($input['product_id'] ?? 0));
            $qty = $this->positive($input['quantity'] ?? '', 'quantity');
            $locationId = $this->requireLocation((int) ($input['stock_location_id'] ?? 0));
            $id = $this->post([
                'product_id' => (int) $product['id'],
                'stock_location_id' => $locationId,
                'inventory_item_id' => ((int) ($input['inventory_item_id'] ?? 0)) > 0 ? (int) $input['inventory_item_id'] : null,
                'movement_type' => MovementType::SupplierReturn->value,
                'quantity' => $qty,
                'unit' => $this->unit($product, null),
                'unit_cost_snapshot' => $this->costFor($product, (int) ($input['inventory_item_id'] ?? 0)),
                'purchase_order_id' => ((int) ($input['purchase_order_id'] ?? 0)) > 0 ? (int) $input['purchase_order_id'] : null,
                'reference_type' => 'supplier_return',
                'reason' => substr($reason, 0, 80),
                'notes' => blank_to_null($input['notes'] ?? null),
                'created_by' => $userId,
            ]);
            $this->audit->record('product', (int) $product['id'], 'STOCK_RETURNED', null, [
                'movement_id' => $id,
                'supplier_return' => true,
                'reason' => $reason,
            ], $userId);

            return $id;
        });
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function startCount(int $locationId, int $userId): array
    {
        if (!can('inventory.count')) {
            return ['errors' => ['_form' => 'You cannot start a stock count.'], 'id' => null];
        }

        return $this->guard(function () use ($locationId, $userId): int {
            $locationId = $this->requireLocation($locationId);
            $countId = $this->inventory->insertCount([
                'reference_code' => 'CNT-' . date('Ymd-His'),
                'stock_location_id' => $locationId,
                'status' => StockCountStatus::InProgress->value,
                'notes' => null,
                'created_by' => $userId,
            ]);
            foreach ($this->inventory->locationBalances($locationId) as $row) {
                if ((int) ($row['track_stock'] ?? 0) !== 1 && (string) $row['inventory_method'] === 'NONE') {
                    continue;
                }
                $this->inventory->insertCountItem([
                    'stock_count_id' => $countId,
                    'product_id' => (int) $row['product_id'],
                    'inventory_item_id' => null,
                    'stock_location_id' => $locationId,
                    'system_quantity' => Decimal::round((string) $row['on_hand'], 4),
                    'physical_quantity' => null,
                    'unit' => (string) $row['cost_unit'],
                    'unit_cost_snapshot' => Decimal::round((string) ($row['average_cost'] ?: $row['cost_price']), 4),
                    'notes' => null,
                ]);
            }

            return $countId;
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function saveCount(int $countId, array $input, int $userId): array
    {
        if (!can('inventory.count')) {
            return ['_form' => 'You cannot record a stock count.'];
        }
        $lines = $input['physical'] ?? [];
        if (!is_array($lines)) {
            return ['_form' => 'Enter the physical quantities.'];
        }
        try {
            Database::transaction(function () use ($countId, $lines): void {
                $count = $this->inventory->count($countId);
                if ($count === null || (string) $count['status'] === StockCountStatus::Completed->value) {
                    throw new StockRejected(['_form' => 'That stock count is closed.']);
                }
                foreach ($lines as $itemId => $physical) {
                    if (!Decimal::isNumeric(trim((string) $physical))) {
                        throw new StockRejected(['_form' => 'Physical quantities must be numbers.']);
                    }
                    $this->inventory->savePhysical((int) $itemId, Decimal::round(trim((string) $physical), 4));
                }
            });
        } catch (StockRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function approveCount(int $countId, int $userId): array
    {
        if (!can('inventory.adjust')) {
            return ['_form' => 'You cannot approve a stock count.'];
        }
        try {
            Database::transaction(function () use ($countId, $userId): void {
                $count = $this->inventory->count($countId);
                if ($count === null || (string) $count['status'] === StockCountStatus::Completed->value) {
                    throw new StockRejected(['_form' => 'That stock count is already complete.']);
                }
                foreach ($this->inventory->countItems($countId) as $line) {
                    if ($line['physical_quantity'] === null) {
                        throw new StockRejected(['_form' => 'Enter a physical quantity for every line before approval.']);
                    }
                    $variance = Decimal::sub((string) $line['physical_quantity'], (string) $line['system_quantity'], 4);
                    if (Decimal::cmp($variance, '0') === 0) {
                        continue;
                    }
                    $abs = Decimal::cmp($variance, '0') < 0 ? Decimal::sub('0', $variance, 4) : $variance;
                    $this->post([
                        'product_id' => (int) $line['product_id'],
                        'stock_location_id' => (int) $line['stock_location_id'],
                        'inventory_item_id' => $line['inventory_item_id'] !== null ? (int) $line['inventory_item_id'] : null,
                        'movement_type' => MovementType::StockCountCorrection->value,
                        'quantity' => $abs,
                        'signed_quantity' => $variance,
                        'unit' => (string) $line['unit'],
                        'unit_cost_snapshot' => (string) $line['unit_cost_snapshot'],
                        'reference_type' => 'stock_count',
                        'reference_id' => $countId,
                        'reason' => 'Stock count',
                        'created_by' => $userId,
                    ]);
                }
                $this->inventory->finishCount($countId, StockCountStatus::Completed->value, $userId);
                $this->audit->record('stock_count', $countId, 'STOCK_COUNT_COMPLETED', null, ['count_id' => $countId], $userId);
            });
        } catch (StockRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * Receipt of one purchase line. Quantity is in the purchase-order unit.
     * Rolls become one inventory item per roll. Sheets can be one batch or one item each.
     *
     * @param array<string, mixed> $spec
     * @return list<int> movement ids
     */
    public function receivePurchaseLine(array $spec, int $userId): array
    {
        $product = $this->requireProduct((int) $spec['product_id']);
        $locationId = $this->requireLocation((int) $spec['stock_location_id']);
        $method = InventoryMethod::tryFrom((string) ($product['inventory_method'] ?? 'NONE')) ?? InventoryMethod::Quantity;
        $qty = $this->positive($spec['quantity'] ?? '', 'quantity');
        $pieceCost = Decimal::round((string) $spec['unit_cost'], 4);
        $this->inventory->lockProduct((int) $product['id']);
        $this->applyReceiptCost($product, $qty, $pieceCost, $userId);
        $links = [
            'purchase_order_id' => $spec['purchase_order_id'] ?? null,
            'purchase_order_item_id' => $spec['purchase_order_item_id'] ?? null,
            'supplier_id' => $spec['supplier_id'] ?? null,
            'reference_type' => 'goods_receipt_item',
            'reference_id' => $spec['goods_receipt_item_id'] ?? null,
        ];
        $ids = [];
        if ($method === InventoryMethod::Roll) {
            $lengthMm = $this->positive($spec['length_mm'] ?? '0', 'length_mm');
            $width = $this->positive($spec['width_mm'] ?? ($product['roll_width_mm'] ?? '0'), 'width_mm');
            $count = (int) Decimal::round($qty, 0);
            if ($count < 1) {
                throw new StockRejected(['quantity' => 'Receive at least one roll.']);
            }
            $metres = Decimal::div($lengthMm, '1000', 4);
            $perMetre = Decimal::cmp($metres, '0') === 0 ? $pieceCost : Decimal::round(Decimal::div($pieceCost, $metres), 4);
            for ($i = 0; $i < $count; $i++) {
                $ids[] = $this->createIdentified(
                    $product,
                    $locationId,
                    InventoryItemType::Roll,
                    $this->numbers->roll(),
                    $metres,
                    'm',
                    $perMetre,
                    $perMetre,
                    $width,
                    $lengthMm,
                    null,
                    $userId,
                    MovementType::PurchaseReceipt,
                    $links
                );
            }

            return $ids;
        }
        if (($method === InventoryMethod::Sheet || $method === InventoryMethod::Length) && !empty($spec['track_each'])) {
            $count = (int) Decimal::round($qty, 0);
            for ($i = 0; $i < $count; $i++) {
                $type = $method === InventoryMethod::Length ? InventoryItemType::UnitGroup : InventoryItemType::Sheet;
                $code = $method === InventoryMethod::Length ? $this->numbers->batch() : $this->numbers->sheet();
                $ids[] = $this->createIdentified(
                    $product,
                    $locationId,
                    $type,
                    $code,
                    '1.0000',
                    $this->unit($product, null),
                    $pieceCost,
                    $pieceCost,
                    $spec['width_mm'] ?? $product['sheet_width_mm'] ?? null,
                    $spec['length_mm'] ?? null,
                    $spec['height_mm'] ?? $product['sheet_height_mm'] ?? null,
                    $userId,
                    MovementType::PurchaseReceipt,
                    $links
                );
            }

            return $ids;
        }
        $ids[] = $this->post([
            'product_id' => (int) $product['id'],
            'stock_location_id' => $locationId,
            'movement_type' => MovementType::PurchaseReceipt->value,
            'quantity' => $qty,
            'unit' => (string) ($spec['unit'] ?? $this->unit($product, null)),
            'unit_cost_snapshot' => $pieceCost,
            'purchase_order_id' => $links['purchase_order_id'],
            'reference_type' => 'goods_receipt_item',
            'reference_id' => $links['reference_id'],
            'reason' => 'Goods receipt',
            'created_by' => $userId,
        ]);
        $this->audit->record('product', (int) $product['id'], 'STOCK_RECEIVED', null, [
            'quantity' => $qty,
            'purchase_order_id' => $links['purchase_order_id'],
        ], $userId);

        return $ids;
    }

    /**
     * Called by job material usage. Must run inside the usage transaction.
     *
     * @param array<string, mixed> $usage
     */
    public function consumeForJob(array $usage): void
    {
        $product = $this->products->find((int) $usage['product_id']);
        if ($product === null || !$this->isTracked($product)) {
            return;
        }
        if (!can('inventory.consume') && !can('inventory.override')) {
            throw new StockRejected(['_form' => 'You cannot consume tracked stock.']);
        }
        $typeName = (string) ($usage['usage_type'] ?? 'PRODUCTION');
        $movement = match ($typeName) {
            'WASTE', 'REWORK', 'TEST_PRINT' => MovementType::Waste,
            'DAMAGE' => MovementType::Damage,
            default => MovementType::JobConsumption,
        };
        $itemId = (int) ($usage['inventory_item_id'] ?? 0);
        $reservationId = (int) ($usage['reservation_id'] ?? 0);
        $locationId = (int) ($usage['stock_location_id'] ?? 0);
        $qty = Decimal::round((string) $usage['quantity'], 4);
        if ($itemId > 0) {
            $item = $this->inventory->lockItem($itemId);
            if ($item === null) {
                throw new StockRejected(['inventory_item_id' => 'That roll, sheet, or offcut was not found.']);
            }
            $locationId = (int) $item['stock_location_id'];
            if ((string) $item['inventory_type'] === InventoryItemType::Offcut->value) {
                $movement = MovementType::OffcutConsumed;
            }
        }
        if ($reservationId > 0) {
            $reservation = $this->inventory->reservation($reservationId);
            if ($reservation === null || (string) $reservation['status'] !== ReservationStatus::Reserved->value) {
                throw new StockRejected(['reservation_id' => 'That reservation is not open.']);
            }
            if (Decimal::cmp($qty, (string) $reservation['quantity']) > 0) {
                throw new StockRejected(['quantity' => 'That is more than the reservation.']);
            }
            $left = Decimal::sub((string) $reservation['quantity'], $qty, 4);
            $this->inventory->setReservation(
                $reservationId,
                Decimal::cmp($left, '0') === 0 ? (string) $reservation['quantity'] : $left,
                Decimal::cmp($left, '0') === 0 ? ReservationStatus::Consumed->value : ReservationStatus::Reserved->value
            );
            $locationId = (int) $reservation['stock_location_id'];
            if ($itemId === 0 && $reservation['inventory_item_id'] !== null) {
                $itemId = (int) $reservation['inventory_item_id'];
            }
        }
        if ($locationId < 1) {
            throw new StockRejected(['stock_location_id' => 'Choose a stock location or a tracked roll, sheet, or offcut.']);
        }
        $allow = !empty($usage['allow_negative']);
        $id = $this->post([
            'product_id' => (int) $product['id'],
            'stock_location_id' => $locationId,
            'inventory_item_id' => $itemId > 0 ? $itemId : null,
            'movement_type' => $movement->value,
            'quantity' => $qty,
            'unit' => (string) ($usage['unit'] ?? $this->unit($product, null)),
            'unit_cost_snapshot' => (string) $usage['unit_cost'],
            'job_id' => (int) $usage['job_id'],
            'job_material_usage_id' => (int) $usage['usage_id'],
            'reference_type' => 'job_material_usage',
            'reference_id' => (int) $usage['usage_id'],
            'reason' => (string) ($usage['reason'] ?? ''),
            'notes' => $usage['notes'] ?? null,
            'created_by' => (int) $usage['user_id'],
            'allow_negative' => $allow,
            'override_reason' => (string) ($usage['override_reason'] ?? ''),
        ]);
        $action = $movement === MovementType::Waste || $movement === MovementType::Damage
            ? 'STOCK_WASTED'
            : ($movement === MovementType::OffcutConsumed ? 'OFFCUT_CONSUMED' : 'STOCK_CONSUMED');
        $this->audit->record('job', (int) $usage['job_id'], $action, null, [
            'movement_id' => $id,
            'usage_id' => (int) $usage['usage_id'],
            'quantity' => $qty,
        ], (int) $usage['user_id']);
        if ($allow) {
            $this->audit->record('job', (int) $usage['job_id'], 'NEGATIVE_STOCK_OVERRIDE', null, [
                'reason' => (string) ($usage['override_reason'] ?? ''),
                'movement_id' => $id,
            ], (int) $usage['user_id']);
        }
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @return array{unit_cost: string, unit: string}|null
     */
    public function quoteJobCost(array $product, array $input): ?array
    {
        if (!$this->isTracked($product)) {
            return null;
        }
        $itemId = (int) ($input['inventory_item_id'] ?? 0);
        $unitCost = $this->costFor($product, $itemId);
        $unit = $this->unit($product, $itemId > 0 ? $this->inventory->item($itemId) : null);
        if ($itemId > 0) {
            $item = $this->inventory->item($itemId);
            if ($item !== null) {
                $unit = (string) $item['unit'];
                $unitCost = Decimal::round((string) $item['unit_cost'], 4);
            }
        }

        return ['unit_cost' => $unitCost, 'unit' => $unit];
    }

    /**
     * @param array<string, mixed> $row
     */
    public function post(array $row): int
    {
        $type = MovementType::tryFrom((string) $row['movement_type']);
        if ($type === null) {
            throw new StockRejected(['_form' => 'That movement type is not available.']);
        }
        $productId = (int) $row['product_id'];
        $this->inventory->lockProduct($productId);
        $absolute = Decimal::round((string) $row['quantity'], 4);
        if (isset($row['signed_quantity'])) {
            $signed = Decimal::round((string) $row['signed_quantity'], 4);
        } elseif ($type->decreasesStock()) {
            $signed = Decimal::sub('0', $absolute, 4);
        } else {
            $signed = $absolute;
        }
        $itemId = isset($row['inventory_item_id']) ? (int) $row['inventory_item_id'] : 0;
        $locationId = (int) $row['stock_location_id'];
        $offcut = false;
        if ($itemId > 0 && ($row['adjust_item'] ?? true) !== false) {
            $item = $this->inventory->lockItem($itemId);
            if ($item === null) {
                throw new StockRejected(['inventory_item_id' => 'That inventory item was not found.']);
            }
            $offcut = (string) $item['inventory_type'] === InventoryItemType::Offcut->value;
            $nextRemaining = Decimal::add((string) $item['remaining_quantity'], $signed, 4);
            if (Decimal::cmp($nextRemaining, '0') < 0 && empty($row['allow_negative'])) {
                throw new StockRejected(['_form' => 'Not enough left on ' . $item['inventory_code'] . '.']);
            }
            $status = Decimal::cmp($nextRemaining, '0') <= 0
                ? InventoryItemStatus::Consumed->value
                : ((string) $item['status'] === InventoryItemStatus::Consumed->value
                    ? InventoryItemStatus::Available->value
                    : (string) $item['status']);
            $this->inventory->updateItemQuantity($itemId, Decimal::cmp($nextRemaining, '0') < 0 ? '0.0000' : $nextRemaining, $status);
        }
        if (Decimal::cmp($signed, '0') < 0 && empty($row['allow_negative'])) {
            $onHand = $this->inventory->onHand($productId, $locationId, $offcut, $offcut ? $itemId : null);
            $next = Decimal::add($onHand, $signed, 4);
            if (Decimal::cmp($next, '0') < 0) {
                throw new StockRejected(['_form' => 'Not enough stock. Available is ' . $onHand . '.']);
            }
        } elseif (!empty($row['allow_negative'])) {
            $reason = trim((string) ($row['override_reason'] ?? ''));
            if ($reason === '' || !can('inventory.override')) {
                throw new StockRejected(['_form' => 'Going below zero needs an authorised reason.']);
            }
        }
        $unitCost = Decimal::round((string) ($row['unit_cost_snapshot'] ?? '0'), 4);
        $total = Decimal::money(Decimal::mul($signed, $unitCost));

        return $this->inventory->insertMovement([
            'product_id' => $productId,
            'stock_location_id' => $locationId,
            'inventory_item_id' => $itemId > 0 ? $itemId : null,
            'movement_type' => $type->value,
            'quantity' => $signed,
            'unit' => substr((string) $row['unit'], 0, 20),
            'unit_cost_snapshot' => $unitCost,
            'total_cost' => $total,
            'reference_type' => $row['reference_type'] ?? null,
            'reference_id' => $row['reference_id'] ?? null,
            'job_id' => $row['job_id'] ?? null,
            'purchase_order_id' => $row['purchase_order_id'] ?? null,
            'job_material_usage_id' => $row['job_material_usage_id'] ?? null,
            'reason' => isset($row['reason']) ? substr((string) $row['reason'], 0, 80) : null,
            'notes' => $row['notes'] ?? null,
            'movement_date' => $row['movement_date'] ?? date('Y-m-d'),
            'created_by' => $row['created_by'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $product
     */
    public function applyReceiptCost(array $product, string $receiptQty, string $unitCost, int $userId): void
    {
        $method = InventoryMethod::tryFrom((string) ($product['inventory_method'] ?? 'NONE')) ?? InventoryMethod::None;
        $costing = CostingMethod::tryFrom((string) ($product['costing_method'] ?? 'LAST_COST')) ?? CostingMethod::LastCost;
        $current = Decimal::round((string) $product['cost_price'], 4);
        $unitCost = Decimal::round($unitCost, 4);
        if ($costing === CostingMethod::WeightedAverage && $method->allowsWeightedAverage()) {
            $existingQty = $this->inventory->onHand((int) $product['id'], null, false);
            $existingCost = $product['average_cost'] !== null && $product['average_cost'] !== ''
                ? (string) $product['average_cost']
                : $current;
            $average = StockValuation::weightedAverage($existingQty, $existingCost, $receiptQty, $unitCost);
            $this->inventory->setAverageCost((int) $product['id'], $average);
            if (Decimal::cmp($current, $average) !== 0) {
                $this->products->addPriceHistory((int) $product['id'], $current, $average, $userId);
                $this->products->updateCost((int) $product['id'], $average);
            }

            return;
        }
        if ($costing === CostingMethod::LastCost && Decimal::cmp($current, $unitCost) !== 0) {
            $this->products->addPriceHistory((int) $product['id'], $current, $unitCost, $userId);
            $this->products->updateCost((int) $product['id'], $unitCost);
        }
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @param array<string, mixed> $links
     */
    private function createRoll(array $product, int $locationId, array $input, string $costPerMetre, int $userId, MovementType $type, array $links): int
    {
        $lengthM = $this->positive($input['length_m'] ?? '', 'length_m');
        $width = $this->positive($input['width_mm'] ?? ($product['roll_width_mm'] ?? ''), 'width_mm');
        $lengthMm = Decimal::mul($lengthM, '1000', 2);

        return $this->createIdentified(
            $product,
            $locationId,
            InventoryItemType::Roll,
            $this->numbers->roll(),
            $lengthM,
            'm',
            $costPerMetre,
            $costPerMetre,
            $width,
            $lengthMm,
            null,
            $userId,
            $type,
            $links
        );
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @param array<string, mixed> $links
     */
    private function createPieces(array $product, int $locationId, array $input, string $cost, int $userId, MovementType $type, array $links): int
    {
        $qty = $this->positive($input['quantity'] ?? '1', 'quantity');
        $method = (string) ($product['inventory_method'] ?? '');
        $each = !empty($input['track_each']) || $method === InventoryMethod::Sheet->value;
        if (!$each) {
            $this->applyReceiptCost($product, $qty, $cost, $userId);

            return $this->post([
                'product_id' => (int) $product['id'],
                'stock_location_id' => $locationId,
                'movement_type' => $type->value,
                'quantity' => $qty,
                'unit' => $this->unit($product, null),
                'unit_cost_snapshot' => $cost,
                'reference_type' => 'opening',
                'reason' => 'Opening balance',
                'created_by' => $userId,
            ]);
        }
        $count = max(1, (int) Decimal::round($qty, 0));
        $last = 0;
        for ($i = 0; $i < $count; $i++) {
            $code = $method === InventoryMethod::Length->value ? $this->numbers->batch() : $this->numbers->sheet();
            $itemType = $method === InventoryMethod::Length->value ? InventoryItemType::UnitGroup : InventoryItemType::Sheet;
            $length = $method === InventoryMethod::Length->value
                ? $this->positive($input['length_m'] ?? '6', 'length_m')
                : '1.0000';
            $last = $this->createIdentified(
                $product,
                $locationId,
                $itemType,
                $code,
                $method === InventoryMethod::Length->value ? $length : '1.0000',
                $method === InventoryMethod::Length->value ? 'm' : $this->unit($product, null),
                $cost,
                $cost,
                $product['sheet_width_mm'] ?? null,
                $method === InventoryMethod::Length->value ? Decimal::mul($length, '1000', 2) : null,
                $product['sheet_height_mm'] ?? null,
                $userId,
                $type,
                $links
            );
        }

        return $last;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $links
     */
    private function createIdentified(
        array $product,
        int $locationId,
        InventoryItemType $type,
        string $code,
        string $quantity,
        string $unit,
        string $unitCost,
        string $acquisition,
        mixed $width,
        mixed $length,
        mixed $height,
        int $userId,
        MovementType $movement,
        array $links
    ): int {
        $itemId = $this->inventory->insertItem([
            'product_id' => (int) $product['id'],
            'stock_location_id' => $locationId,
            'inventory_type' => $type->value,
            'inventory_code' => $code,
            'supplier_id' => $links['supplier_id'] ?? null,
            'purchase_order_item_id' => $links['purchase_order_item_id'] ?? null,
            'source_inventory_item_id' => null,
            'source_job_id' => null,
            'status' => InventoryItemStatus::Available->value,
            'received_date' => date('Y-m-d'),
            'expiry_date' => $links['expiry_date'] ?? null,
            'original_quantity' => '0.0000',
            'remaining_quantity' => '0.0000',
            'unit' => $unit,
            'unit_cost' => Decimal::round($unitCost, 4),
            'acquisition_cost' => Decimal::round($acquisition, 4),
            'valuation_treatment' => OffcutValuation::FullCost->value,
            'width_mm' => $width !== null && $width !== '' ? Decimal::round((string) $width, 2) : null,
            'length_mm' => $length !== null && $length !== '' ? Decimal::round((string) $length, 2) : null,
            'height_mm' => $height !== null && $height !== '' ? Decimal::round((string) $height, 2) : null,
            'batch_number' => $links['batch_number'] ?? null,
            'supplier_reference' => $links['supplier_reference'] ?? null,
            'notes' => null,
        ]);
        $movementId = $this->post([
            'product_id' => (int) $product['id'],
            'stock_location_id' => $locationId,
            'inventory_item_id' => $itemId,
            'movement_type' => $movement->value,
            'quantity' => $quantity,
            'unit' => $unit,
            'unit_cost_snapshot' => Decimal::round($unitCost, 4),
            'purchase_order_id' => $links['purchase_order_id'] ?? null,
            'reference_type' => $links['reference_type'] ?? 'inventory_item',
            'reference_id' => $links['reference_id'] ?? $itemId,
            'reason' => $movement->label(),
            'created_by' => $userId,
        ]);
        $this->inventory->updateItemQuantity($itemId, Decimal::round($quantity, 4), InventoryItemStatus::Available->value);
        $this->inventory->setOriginalQuantity($itemId, Decimal::round($quantity, 4));
        $this->audit->record('inventory_item', $itemId, 'STOCK_RECEIVED', null, [
            'code' => $code,
            'movement_id' => $movementId,
            'quantity' => $quantity,
        ], $userId);

        return $itemId;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed>|null $item
     */
    private function unit(array $product, ?array $item): string
    {
        if ($item !== null && (string) ($item['unit'] ?? '') !== '') {
            return substr((string) $item['unit'], 0, 20);
        }
        $method = (string) ($product['inventory_method'] ?? '');
        if ($method === InventoryMethod::Roll->value || $method === InventoryMethod::Length->value) {
            return 'm';
        }
        $unit = trim((string) ($product['cost_unit'] ?? 'unit'));

        return $unit === '' ? 'unit' : substr($unit, 0, 20);
    }

    /**
     * @param array<string, mixed> $product
     */
    private function costFor(array $product, int $itemId): string
    {
        if ($itemId > 0) {
            $item = $this->inventory->item($itemId);
            if ($item !== null) {
                return Decimal::round((string) $item['unit_cost'], 4);
            }
        }
        if ((string) ($product['costing_method'] ?? '') === CostingMethod::WeightedAverage->value && $product['average_cost'] !== null && $product['average_cost'] !== '') {
            return Decimal::round((string) $product['average_cost'], 4);
        }

        return Decimal::round((string) ($product['cost_price'] ?? '0'), 4);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireProduct(int $id): array
    {
        $product = $id > 0 ? $this->products->find($id) : null;
        if ($product === null) {
            throw new StockRejected(['product_id' => 'Choose a product.']);
        }
        if (!$this->isTracked($product)) {
            throw new StockRejected(['product_id' => 'Turn stock tracking on for this product first.']);
        }

        return $product;
    }

    private function requireLocation(int $id): int
    {
        if ($this->inventory->location($id) === null) {
            throw new StockRejected(['stock_location_id' => 'Choose a stock location.']);
        }

        return $id;
    }

    private function positive(mixed $value, string $field): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if (!Decimal::isNumeric($text) || Decimal::cmp($text, '0') <= 0) {
            throw new StockRejected([$field => 'Enter a quantity greater than zero.']);
        }

        return Decimal::round($text, 4);
    }

    private function moneyCost(mixed $posted, string $fallback): string
    {
        $text = str_replace(',', '.', trim((string) $posted));
        if ($text === '' || !Decimal::isNumeric($text) || Decimal::cmp($text, '0') < 0) {
            return Decimal::round($fallback, 4);
        }

        return Decimal::round($text, 4);
    }

    /**
     * @param callable(): int $callback
     * @return array{errors: array<string, string>, id: int|null}
     */
    private function guard(callable $callback): array
    {
        try {
            $id = Database::transaction($callback);
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $id];
    }
}

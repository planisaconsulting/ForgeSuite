<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ProcurementRepository;

/**
 * Confirmed receipts post stock once.
 * Damaged quantity can be moved to quarantine and is not available stock.
 */
final class ReceivingControlService
{
    public function __construct(
        private readonly PurchasingService $purchasing = new PurchasingService(),
        private readonly ProcurementRepository $repo = new ProcurementRepository(),
        private readonly StockMovementService $stock = new StockMovementService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function confirm(int $orderId, array $input, int $userId): array
    {
        if (!can('receiving.perform') && !can('purchasing.receive') && !can('inventory.receive')) {
            return ['errors' => ['_form' => 'You cannot receive goods.'], 'id' => null];
        }
        $serial = trim((string) ($input['serial_number'] ?? ''));
        if ($serial !== '' && $this->repo->serialByNumber($serial) !== null) {
            return ['errors' => ['_form' => 'That serial number is already on file.'], 'id' => null];
        }
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        $already = $key !== '' ? (new \App\Repositories\PurchasingRepository())->receiptByKey($key) : null;
        $result = $this->purchasing->receive($orderId, $input, $userId);
        if ($result['id'] === null || $already !== null) {
            return $result;
        }
        $damaged = Decimal::round((string) ($input['damaged_quantity'] ?? '0'), 4);
        $quarantine = (int) ($input['quarantine_location_id'] ?? 0);
        $from = (int) ($input['stock_location_id'] ?? 0);
        if (Decimal::cmp($damaged, '0') > 0 && $quarantine > 0 && $from > 0) {
            $productId = (int) ($input['product_id'] ?? 0);
            $this->stock->transfer([
                'product_id' => $productId,
                'from_location_id' => $from,
                'to_location_id' => $quarantine,
                'quantity' => $damaged,
                'reason' => 'Quarantine damaged receipt',
            ], $userId);
            $this->repo->insertException([
                'goods_receipt_id' => $result['id'],
                'purchase_order_id' => $orderId,
                'product_id' => $productId > 0 ? $productId : null,
                'exception_type' => 'DAMAGED',
                'quantity' => $damaged,
                'status' => 'QUARANTINED',
                'notes' => 'Damaged quantity is in quarantine and is not available stock.',
                'created_by' => $userId,
            ]);
            BusinessEventDispatcher::emit('RECEIVING_EXCEPTION_CREATED', 'GOODS_RECEIPT', (int) $result['id'], $userId, []);
        }
        if ($serial !== '') {
            try {
                $this->repo->insertSerial([
                    'product_id' => (int) ($input['product_id'] ?? 0),
                    'serial_number' => $serial,
                    'manufacturer_serial' => blank_to_null($input['manufacturer_serial'] ?? null),
                    'goods_receipt_id' => $result['id'],
                    'stock_location_id' => $from > 0 ? $from : null,
                ]);
            } catch (\PDOException) {
                return ['errors' => ['_form' => 'That serial number is already on file.'], 'id' => $result['id']];
            }
        }
        $lot = trim((string) ($input['lot_code'] ?? ''));
        if ($lot !== '') {
            $qty = '0';
            foreach ((array) ($input['receive_qty'] ?? []) as $value) {
                if (Decimal::isNumeric((string) $value)) {
                    $qty = Decimal::add($qty, (string) $value, 4);
                }
            }
            $this->repo->insertLot([
                'product_id' => (int) ($input['product_id'] ?? 0),
                'lot_code' => $lot,
                'supplier_id' => null,
                'goods_receipt_id' => $result['id'],
                'quantity_received' => $qty,
                'quantity_remaining' => $qty,
                'received_date' => date('Y-m-d'),
            ]);
        }
        BusinessEventDispatcher::emit('GOODS_RECEIVED', 'GOODS_RECEIPT', (int) $result['id'], $userId, []);

        return $result;
    }

    /**
     * @return array{errors: array<string, string>, id: int|null, movement_id: int|null}
     */
    public function supplierReturn(array $input, int $userId): array
    {
        if (!can('supplier_returns.manage') && !can('purchasing.receive')) {
            return ['errors' => ['_form' => 'You cannot return stock to a supplier.'], 'id' => null, 'movement_id' => null];
        }
        $movement = $this->stock->supplierReturn($input, $userId);
        if ($movement['id'] === null) {
            return ['errors' => $movement['errors'], 'id' => null, 'movement_id' => null];
        }
        $id = $this->repo->insertReturn([
            'return_number' => (new NumberingService())->supplierReturnNumber(),
            'supplier_id' => (int) ($input['supplier_id'] ?? 0),
            'purchase_order_id' => ((int) ($input['purchase_order_id'] ?? 0)) > 0 ? (int) $input['purchase_order_id'] : null,
            'goods_receipt_id' => ((int) ($input['goods_receipt_id'] ?? 0)) > 0 ? (int) $input['goods_receipt_id'] : null,
            'reason_code' => strtoupper((string) ($input['reason_code'] ?? 'OTHER')),
            'credit_reference' => blank_to_null($input['credit_reference'] ?? null),
            'credit_value' => ($input['credit_value'] ?? '') === '' ? null : Decimal::money((string) $input['credit_value']),
            'notes' => blank_to_null($input['notes'] ?? null),
            'created_by' => $userId,
        ]);
        $this->repo->insertReturnItem($id, (int) $input['product_id'], Decimal::round((string) $input['quantity'], 4), (int) $input['stock_location_id']);
        BusinessEventDispatcher::emit('SUPPLIER_RETURN_CREATED', 'SUPPLIER_RETURN', $id, $userId, []);

        return ['errors' => [], 'id' => $id, 'movement_id' => $movement['id']];
    }
}

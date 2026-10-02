<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\PurchaseOrderStatus;
use App\Domain\PurchaseRequestStatus;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PurchasingRepository;
use App\Repositories\SupplierRepository;

/**
 * Purchase orders and goods receipts.
 * Posted totals are ignored. Line totals are quantity times the server unit cost.
 */
final class PurchasingService
{
    public function __construct(
        private readonly PurchasingRepository $orders = new PurchasingRepository(),
        private readonly InventoryRepository $inventory = new InventoryRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly SupplierRepository $suppliers = new SupplierRepository(),
        private readonly StockMovementService $stock = new StockMovementService(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createOrder(array $input, int $userId): array
    {
        if (!can('purchasing.create')) {
            return ['errors' => ['_form' => 'You cannot create a purchase order.'], 'id' => null];
        }
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        if ($this->suppliers->find($supplierId) === null) {
            return ['errors' => ['supplier_id' => 'Choose a supplier.'], 'id' => null];
        }
        try {
            $id = Database::transaction(function () use ($input, $userId, $supplierId): int {
                $vat = Decimal::round((string) SettingsService::get('default_vat_percent', '15'), 2);
                $id = $this->orders->insertOrder([
                    'po_number' => $this->numbers->purchaseOrder(),
                    'supplier_id' => $supplierId,
                    'order_date' => $this->date($input['order_date'] ?? null) ?? date('Y-m-d'),
                    'expected_date' => $this->date($input['expected_date'] ?? null),
                    'status' => PurchaseOrderStatus::Draft->value,
                    'subtotal' => '0.00',
                    'vat_rate' => $vat,
                    'vat_amount' => '0.00',
                    'total' => '0.00',
                    'supplier_reference' => blank_to_null($input['supplier_reference'] ?? null),
                    'notes' => blank_to_null($input['notes'] ?? null),
                    'internal_notes' => blank_to_null($input['internal_notes'] ?? null),
                    'created_by' => $userId,
                ]);
                $this->audit->record('purchase_order', $id, 'PO_CREATED', null, ['supplier_id' => $supplierId], $userId);

                return $id;
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addLine(int $orderId, array $input, int $userId): array
    {
        if (!can('purchasing.edit') && !can('purchasing.create')) {
            return ['_form' => 'You cannot edit a purchase order.'];
        }
        try {
            Database::transaction(function () use ($orderId, $input, $userId): void {
                $order = $this->orders->lockOrder($orderId);
                if ($order === null) {
                    throw new StockRejected(['_form' => 'That purchase order was not found.']);
                }
                if ((string) $order['status'] !== PurchaseOrderStatus::Draft->value) {
                    throw new StockRejected(['_form' => 'Only a draft purchase order can be changed.']);
                }
                $product = $this->products->find((int) ($input['product_id'] ?? 0));
                if ($product === null) {
                    throw new StockRejected(['product_id' => 'Choose a product.']);
                }
                $qty = $this->positive($input['quantity'] ?? '', 'quantity');
                $supplierProductId = (int) ($input['supplier_product_id'] ?? 0);
                $unitCost = $this->lineCost($product, $supplierProductId, $input);
                $this->orders->insertItem([
                    'purchase_order_id' => $orderId,
                    'product_id' => (int) $product['id'],
                    'supplier_product_id' => $supplierProductId > 0 ? $supplierProductId : null,
                    'description' => substr(trim((string) ($input['description'] ?? $product['name'])), 0, 180),
                    'ordered_quantity' => $qty,
                    'unit' => substr((string) ($product['cost_unit'] ?: 'unit'), 0, 20),
                    'unit_cost' => $unitCost,
                    'line_total' => Decimal::money(Decimal::mul($qty, $unitCost)),
                    'expected_date' => $this->date($input['expected_date'] ?? null),
                    'job_id' => ((int) ($input['job_id'] ?? 0)) > 0 ? (int) $input['job_id'] : null,
                    'notes' => blank_to_null($input['notes'] ?? null),
                ]);
                $this->recalculate($orderId);
            });
        } catch (StockRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function setStatus(int $orderId, string $status, int $userId): array
    {
        $next = PurchaseOrderStatus::tryFrom($status);
        if ($next === null) {
            return ['_form' => 'That status is not available.'];
        }
        try {
            Database::transaction(function () use ($orderId, $next, $userId): void {
                $order = $this->orders->lockOrder($orderId);
                if ($order === null) {
                    throw new StockRejected(['_form' => 'That purchase order was not found.']);
                }
                $current = PurchaseOrderStatus::from((string) $order['status']);
                $allowed = match ($current) {
                    PurchaseOrderStatus::Draft => [PurchaseOrderStatus::PendingApproval, PurchaseOrderStatus::Cancelled],
                    PurchaseOrderStatus::PendingApproval => [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Draft, PurchaseOrderStatus::Cancelled],
                    PurchaseOrderStatus::Approved => [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Cancelled],
                    PurchaseOrderStatus::Ordered => [PurchaseOrderStatus::Cancelled],
                    default => [],
                };
                if (!in_array($next, $allowed, true)) {
                    throw new StockRejected(['_form' => 'That status change is not available from ' . $current->label() . '.']);
                }
                if ($next === PurchaseOrderStatus::Approved && !can('purchasing.approve')) {
                    throw new StockRejected(['_form' => 'You cannot approve a purchase order.']);
                }
                if ($next === PurchaseOrderStatus::Cancelled && !can('purchasing.cancel')) {
                    throw new StockRejected(['_form' => 'You cannot cancel a purchase order.']);
                }
                if ($next === PurchaseOrderStatus::Cancelled) {
                    foreach ($this->orders->items($orderId) as $item) {
                        if (Decimal::cmp((string) $item['received_quantity'], '0') > 0) {
                            throw new StockRejected(['_form' => 'A purchase order with receipts cannot be cancelled.']);
                        }
                    }
                }
                if (!can('purchasing.edit') && $next !== PurchaseOrderStatus::Approved && !can('purchasing.create')) {
                    throw new StockRejected(['_form' => 'You cannot change this purchase order.']);
                }
                $this->orders->setOrderStatus($orderId, $next->value, $next === PurchaseOrderStatus::Approved ? $userId : null);
                $action = match ($next) {
                    PurchaseOrderStatus::Approved => 'PO_APPROVED',
                    PurchaseOrderStatus::Ordered => 'PO_ORDERED',
                    PurchaseOrderStatus::Cancelled => 'PO_CANCELLED',
                    default => 'PO_CREATED',
                };
                if ($next !== PurchaseOrderStatus::PendingApproval && $next !== PurchaseOrderStatus::Draft) {
                    $this->audit->record('purchase_order', $orderId, $action, ['status' => $current->value], ['status' => $next->value], $userId);
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
    public function receive(int $orderId, array $input, int $userId): array
    {
        if (!can('purchasing.receive') && !can('inventory.receive')) {
            return ['errors' => ['_form' => 'You cannot receive goods.'], 'id' => null];
        }
        try {
            $id = Database::transaction(function () use ($orderId, $input, $userId): int {
                $key = trim((string) ($input['idempotency_key'] ?? ''));
                if ($key !== '') {
                    $existing = $this->orders->receiptByKey($key);
                    if ($existing !== null) {
                        return (int) $existing['id'];
                    }
                }
                $order = $this->orders->lockOrder($orderId);
                if ($order === null) {
                    throw new StockRejected(['_form' => 'That purchase order was not found.']);
                }
                $status = PurchaseOrderStatus::from((string) $order['status']);
                if (!$status->canReceive()) {
                    throw new StockRejected(['_form' => 'Mark the purchase order as ordered before receiving.']);
                }
                $locationId = (int) ($input['stock_location_id'] ?? 0);
                if ((new InventoryRepository())->location($locationId) === null) {
                    throw new StockRejected(['stock_location_id' => 'Choose a stock location.']);
                }
                $receiptId = $this->orders->insertReceipt([
                    'grn_number' => $this->numbers->goodsReceipt(),
                    'purchase_order_id' => $orderId,
                    'supplier_id' => (int) $order['supplier_id'],
                    'received_date' => $this->date($input['received_date'] ?? null) ?? date('Y-m-d'),
                    'supplier_delivery_note' => blank_to_null($input['supplier_delivery_note'] ?? null),
                    'supplier_invoice_number' => blank_to_null($input['supplier_invoice_number'] ?? null),
                    'received_by' => $userId,
                    'notes' => blank_to_null($input['notes'] ?? null),
                    'idempotency_key' => $key !== '' ? $key : null,
                    'status' => 'CONFIRMED',
                ]);
                $any = false;
                $quantities = is_array($input['receive_qty'] ?? null) ? $input['receive_qty'] : [];
                foreach ($this->orders->items($orderId) as $item) {
                    $raw = trim((string) ($quantities[$item['id']] ?? ''));
                    if ($raw === '' || Decimal::cmp($raw, '0') === 0) {
                        continue;
                    }
                    if (!Decimal::isNumeric($raw) || Decimal::cmp($raw, '0') < 0) {
                        throw new StockRejected(['_form' => 'Received quantities must be zero or greater.']);
                    }
                    $qty = Decimal::round($raw, 4);
                    $outstanding = Decimal::sub((string) $item['ordered_quantity'], (string) $item['received_quantity'], 4);
                    $decision = strtoupper(trim((string) ($input['over_delivery'] ?? '')));
                    if (Decimal::cmp($qty, $outstanding) > 0) {
                        if ($decision === 'ACCEPT_PARTIAL') {
                            $qty = $outstanding;
                        } elseif ($decision !== 'ACCEPT') {
                            throw new StockRejected(['_form' => 'You cannot receive more than the outstanding quantity of ' . $item['description'] . '.']);
                        }
                    }
                    $any = true;
                    $receiptItemId = $this->orders->insertReceiptItem([
                        'goods_receipt_id' => $receiptId,
                        'purchase_order_item_id' => (int) $item['id'],
                        'product_id' => (int) $item['product_id'],
                        'quantity_received' => $qty,
                        'unit' => (string) $item['unit'],
                        'unit_cost' => (string) $item['unit_cost'],
                        'stock_location_id' => $locationId,
                        'width_mm' => blank_to_null($input['width_mm'] ?? null),
                        'length_mm' => blank_to_null($input['length_mm'] ?? null),
                        'track_each' => posted_flag($input, 'track_each', 0),
                    ]);
                    $this->stock->receivePurchaseLine([
                        'product_id' => (int) $item['product_id'],
                        'stock_location_id' => $locationId,
                        'quantity' => $qty,
                        'unit_cost' => (string) $item['unit_cost'],
                        'unit' => (string) $item['unit'],
                        'width_mm' => $input['width_mm'] ?? null,
                        'length_mm' => $input['length_mm'] ?? null,
                        'height_mm' => $input['height_mm'] ?? null,
                        'track_each' => posted_flag($input, 'track_each', 0) === 1,
                        'purchase_order_id' => $orderId,
                        'purchase_order_item_id' => (int) $item['id'],
                        'supplier_id' => (int) $order['supplier_id'],
                        'goods_receipt_item_id' => $receiptItemId,
                    ], $userId);
                    $this->orders->addReceived((int) $item['id'], Decimal::add((string) $item['received_quantity'], $qty, 4));
                }
                if (!$any) {
                    throw new StockRejected(['_form' => 'Enter a quantity for at least one line.']);
                }
                $this->refreshReceiptStatus($orderId);
                $this->audit->record('purchase_order', $orderId, 'PO_RECEIVED', null, ['goods_receipt_id' => $receiptId], $userId);

                return $receiptId;
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function request(array $input, int $userId): array
    {
        if (!can('purchasing.create') && !can('materials.record_usage') && !can('inventory.consume')) {
            return ['errors' => ['_form' => 'You cannot request a purchase.'], 'id' => null];
        }
        $product = $this->products->find((int) ($input['product_id'] ?? 0));
        if ($product === null) {
            return ['errors' => ['product_id' => 'Choose a product.'], 'id' => null];
        }
        try {
            $qty = $this->positive($input['quantity'] ?? '', 'quantity');
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }
        $id = $this->orders->insertRequest([
            'job_id' => ((int) ($input['job_id'] ?? 0)) > 0 ? (int) $input['job_id'] : null,
            'requested_by' => $userId,
            'product_id' => (int) $product['id'],
            'quantity' => $qty,
            'unit' => substr((string) ($product['cost_unit'] ?: 'unit'), 0, 20),
            'required_by' => $this->date($input['required_by'] ?? null),
            'reason' => blank_to_null($input['reason'] ?? null),
            'status' => PurchaseRequestStatus::Requested->value,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function decideRequest(int $requestId, string $status, int $userId): array
    {
        if (!can('purchasing.approve') && $status !== PurchaseRequestStatus::Cancelled->value) {
            return ['_form' => 'You cannot approve a purchase request.'];
        }
        $next = PurchaseRequestStatus::tryFrom($status);
        if ($next === null || !in_array($next, [PurchaseRequestStatus::Approved, PurchaseRequestStatus::Rejected, PurchaseRequestStatus::Cancelled], true)) {
            return ['_form' => 'That decision is not available.'];
        }
        $row = $this->orders->request($requestId);
        if ($row === null || (string) $row['status'] !== PurchaseRequestStatus::Requested->value && $next !== PurchaseRequestStatus::Cancelled) {
            return ['_form' => 'That request is no longer open.'];
        }
        $this->orders->setRequestStatus($requestId, $next->value, $userId);

        return [];
    }

    /**
     * @param list<int> $requestIds
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function consolidate(array $requestIds, int $supplierId, int $userId): array
    {
        if (!can('purchasing.create')) {
            return ['errors' => ['_form' => 'You cannot create a purchase order.'], 'id' => null];
        }
        $made = $this->createOrder(['supplier_id' => $supplierId], $userId);
        if ($made['id'] === null) {
            return $made;
        }
        $orderId = (int) $made['id'];
        try {
            Database::transaction(function () use ($requestIds, $orderId, $supplierId, $userId): void {
                /** @var array<int, array{qty: string, unit: string, product: array<string, mixed>, requests: list<int>, job: int|null}> $groups */
                $groups = [];
                foreach ($requestIds as $requestId) {
                    $row = $this->orders->request((int) $requestId);
                    if ($row === null || (string) $row['status'] !== PurchaseRequestStatus::Approved->value) {
                        throw new StockRejected(['_form' => 'Only approved requests can be added to a purchase order.']);
                    }
                    $productId = (int) $row['product_id'];
                    if (!isset($groups[$productId])) {
                        $product = $this->products->find($productId);
                        if ($product === null) {
                            throw new StockRejected(['_form' => 'A requested product is missing.']);
                        }
                        $groups[$productId] = [
                            'qty' => '0.0000',
                            'unit' => (string) $row['unit'],
                            'product' => $product,
                            'requests' => [],
                            'job' => $row['job_id'] !== null ? (int) $row['job_id'] : null,
                        ];
                    }
                    $groups[$productId]['qty'] = Decimal::add($groups[$productId]['qty'], (string) $row['quantity'], 4);
                    $groups[$productId]['requests'][] = (int) $row['id'];
                    if ($groups[$productId]['job'] !== null && (int) ($row['job_id'] ?? 0) !== $groups[$productId]['job']) {
                        $groups[$productId]['job'] = null;
                    }
                }
                foreach ($groups as $group) {
                    $supplierProduct = null;
                    foreach ($this->inventory->supplierProductsForProduct((int) $group['product']['id']) as $link) {
                        if ((int) $link['supplier_id'] === $supplierId && (int) $link['active'] === 1) {
                            $supplierProduct = $link;
                            break;
                        }
                    }
                    $unitCost = $supplierProduct !== null
                        ? Decimal::round((string) $supplierProduct['cost_price'], 4)
                        : Decimal::round((string) $group['product']['cost_price'], 4);
                    $itemId = $this->orders->insertItem([
                        'purchase_order_id' => $orderId,
                        'product_id' => (int) $group['product']['id'],
                        'supplier_product_id' => $supplierProduct !== null ? (int) $supplierProduct['id'] : null,
                        'description' => (string) $group['product']['name'],
                        'ordered_quantity' => $group['qty'],
                        'unit' => $group['unit'],
                        'unit_cost' => $unitCost,
                        'line_total' => Decimal::money(Decimal::mul($group['qty'], $unitCost)),
                        'expected_date' => null,
                        'job_id' => $group['job'],
                        'notes' => 'Combined purchase requests',
                    ]);
                    foreach ($group['requests'] as $requestId) {
                        $this->orders->setRequestStatus($requestId, PurchaseRequestStatus::Ordered->value, $userId, $orderId, $itemId);
                    }
                }
                $this->recalculate($orderId);
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => $orderId];
        }

        return ['errors' => [], 'id' => $orderId];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function saveSupplierPrice(array $input, int $userId): array
    {
        if (!can('supplier_prices.edit')) {
            return ['_form' => 'You cannot change supplier prices.'];
        }
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        $productId = (int) ($input['product_id'] ?? 0);
        if ($this->suppliers->find($supplierId) === null || $this->products->find($productId) === null) {
            return ['_form' => 'Choose a supplier and a product.'];
        }
        $priceText = str_replace(',', '.', trim((string) ($input['cost_price'] ?? '')));
        if (!Decimal::isNumeric($priceText) || Decimal::cmp($priceText, '0') < 0) {
            return ['cost_price' => 'Enter a supplier cost of zero or more.'];
        }
        $price = Decimal::round($priceText, 4);
        Database::transaction(function () use ($input, $userId, $supplierId, $productId, $price): void {
            $existing = null;
            foreach ($this->inventory->supplierProductsForProduct($productId) as $row) {
                if ((int) $row['supplier_id'] === $supplierId) {
                    $existing = $row;
                }
            }
            $id = $this->inventory->saveSupplierProduct([
                'supplier_id' => $supplierId,
                'product_id' => $productId,
                'supplier_sku' => blank_to_null($input['supplier_sku'] ?? null),
                'supplier_description' => blank_to_null($input['supplier_description'] ?? null),
                'cost_price' => $price,
                'minimum_order_quantity' => $this->optionalNumber($input['minimum_order_quantity'] ?? null),
                'lead_time_days' => trim((string) ($input['lead_time_days'] ?? '')) === '' ? null : (int) $input['lead_time_days'],
                'preferred_supplier' => posted_flag($input, 'preferred_supplier', 0),
                'active' => posted_flag($input, 'active', 1),
                'last_price_update' => date('Y-m-d H:i:s'),
            ]);
            if ($existing !== null && Decimal::cmp((string) $existing['cost_price'], $price) !== 0) {
                $this->inventory->addSupplierPriceHistory($id, Decimal::round((string) $existing['cost_price'], 4), $price, $userId);
                $this->audit->record('supplier_product', $id, 'SUPPLIER_PRICE_CHANGED', [
                    'cost_price' => (string) $existing['cost_price'],
                ], ['cost_price' => $price], $userId);
            }
        });

        return [];
    }

    private function refreshReceiptStatus(int $orderId): void
    {
        $all = true;
        $any = false;
        foreach ($this->orders->items($orderId) as $item) {
            if (Decimal::cmp((string) $item['received_quantity'], '0') > 0) {
                $any = true;
            }
            if (Decimal::cmp((string) $item['received_quantity'], (string) $item['ordered_quantity']) < 0) {
                $all = false;
            }
        }
        if ($all && $any) {
            $this->orders->setOrderStatus($orderId, PurchaseOrderStatus::Received->value);
        } elseif ($any) {
            $this->orders->setOrderStatus($orderId, PurchaseOrderStatus::PartiallyReceived->value);
        }
    }

    private function recalculate(int $orderId): void
    {
        $order = $this->orders->order($orderId);
        if ($order === null) {
            return;
        }
        $subtotal = '0.00';
        foreach ($this->orders->items($orderId) as $item) {
            $subtotal = Decimal::money(Decimal::add($subtotal, (string) $item['line_total']));
        }
        $rate = Decimal::round((string) $order['vat_rate'], 2);
        $vat = Decimal::money(Decimal::mul($subtotal, Decimal::div($rate, '100', 8)));
        $this->orders->updateOrderHeader($orderId, [
            'expected_date' => $order['expected_date'],
            'supplier_reference' => $order['supplier_reference'],
            'notes' => $order['notes'],
            'internal_notes' => $order['internal_notes'],
            'subtotal' => $subtotal,
            'vat_rate' => $rate,
            'vat_amount' => $vat,
            'total' => Decimal::money(Decimal::add($subtotal, $vat)),
        ]);
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     */
    private function lineCost(array $product, int $supplierProductId, array $input): string
    {
        if (($input['cost_source'] ?? '') === 'SUPPLIER_QUOTATION') {
            $posted = str_replace(',', '.', trim((string) ($input['unit_cost'] ?? '')));
            if (Decimal::isNumeric($posted) && Decimal::cmp($posted, '0') >= 0) {
                return Decimal::round($posted, 4);
            }
        }
        if ($supplierProductId > 0) {
            $link = $this->inventory->supplierProduct($supplierProductId);
            if ($link !== null && (int) $link['product_id'] === (int) $product['id']) {
                return Decimal::round((string) $link['cost_price'], 4);
            }
        }

        return Decimal::round((string) $product['cost_price'], 4);
    }

    private function positive(mixed $value, string $field): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if (!Decimal::isNumeric($text) || Decimal::cmp($text, '0') <= 0) {
            throw new StockRejected([$field => 'Enter a quantity greater than zero.']);
        }

        return Decimal::round($text, 4);
    }

    private function optionalNumber(mixed $value): ?string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return Decimal::round($text, 4);
    }

    private function date(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $text);

        return $date instanceof \DateTimeImmutable ? $date->format('Y-m-d') : null;
    }
}

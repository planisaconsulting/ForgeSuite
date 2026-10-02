<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ProcurementRepository;

/**
 * Request, RFQ, supplier quotation, and award stay separate from the purchase order.
 * The lowest price is evidence. A person awards the quantity.
 */
final class SupplierRfqService
{
    public function __construct(
        private readonly ProcurementRepository $repo = new ProcurementRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly PurchasingService $orders = new PurchasingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, tokens?: array<int, string>}
     */
    public function create(array $input, int $userId): array
    {
        if (!can('procurement.rfq.create') && !can('purchasing.create')) {
            return ['errors' => ['_form' => 'You cannot create a supplier RFQ.'], 'id' => null];
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['errors' => ['title' => 'Give the RFQ a title.'], 'id' => null];
        }
        $id = Database::transaction(function () use ($input, $userId, $title): int {
            $id = $this->repo->insertRfq([
                'rfq_number' => $this->numbers->supplierRfq(),
                'status' => 'DRAFT',
                'title' => mb_substr($title, 0, 180),
                'purchase_request_id' => $this->positiveId($input['purchase_request_id'] ?? 0),
                'project_id' => $this->positiveId($input['project_id'] ?? 0),
                'job_id' => $this->positiveId($input['job_id'] ?? 0),
                'required_by_date' => $this->date($input['required_by_date'] ?? null),
                'response_deadline' => $this->dateTime($input['response_deadline'] ?? null),
                'delivery_location_id' => $this->positiveId($input['delivery_location_id'] ?? 0),
                'currency_code' => 'ZAR',
                'instructions' => blank_to_null($input['instructions'] ?? null),
                'terms' => blank_to_null($input['terms'] ?? null),
                'created_by' => $userId,
            ]);
            $this->audit->record('supplier_rfq', $id, 'RFQ_CREATED', null, [], $userId);
            BusinessEventDispatcher::emit('RFQ_CREATED', 'SUPPLIER_RFQ', $id, $userId, []);

            return $id;
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param list<array<string, mixed>> $sources
     * @return array<string, string>
     */
    public function addItem(int $rfqId, array $item, array $sources, int $userId): array
    {
        if (!can('procurement.rfq.create') && !can('purchasing.create')) {
            return ['_form' => 'You cannot add an RFQ line.'];
        }
        $check = $this->consolidationAllowed($sources);
        if ($check !== null) {
            return ['_form' => $check];
        }
        $qty = '0.0000';
        foreach ($sources as $source) {
            $qty = Decimal::add($qty, (string) ($source['quantity'] ?? '0'), 4);
        }
        if ($sources === []) {
            $qty = (string) ($item['quantity'] ?? '0');
        }
        if (!Decimal::isNumeric($qty) || Decimal::cmp($qty, '0') <= 0) {
            return ['quantity' => 'Quantity must be greater than zero.'];
        }
        Database::transaction(function () use ($rfqId, $item, $sources, $qty): void {
            $itemId = $this->repo->insertRfqItem([
                'rfq_id' => $rfqId,
                'product_id' => $this->positiveId($item['product_id'] ?? 0),
                'description' => mb_substr(trim((string) ($item['description'] ?? 'Material')), 0, 180),
                'specification' => blank_to_null($item['specification'] ?? null),
                'quantity' => Decimal::round($qty, 4),
                'unit' => mb_substr((string) ($item['unit'] ?? 'unit'), 0, 20),
                'preferred_brand' => blank_to_null($item['preferred_brand'] ?? null),
                'equivalent_allowed' => !empty($item['equivalent_allowed']) ? 1 : 0,
                'required_date' => $this->date($item['required_date'] ?? null),
                'project_id' => $this->positiveId($item['project_id'] ?? 0),
                'job_id' => $this->positiveId($item['job_id'] ?? 0),
                'material_requirement_id' => $this->positiveId($item['material_requirement_id'] ?? 0),
                'source_kind' => strtoupper((string) ($item['source_kind'] ?? 'MANUAL')),
            ]);
            foreach ($sources as $source) {
                $this->repo->insertSource([
                    'rfq_item_id' => $itemId,
                    'source_kind' => strtoupper((string) ($source['source_kind'] ?? 'MANUAL')),
                    'quantity' => Decimal::round((string) $source['quantity'], 4),
                    'job_id' => $this->positiveId($source['job_id'] ?? 0),
                    'project_id' => $this->positiveId($source['project_id'] ?? 0),
                    'purchase_request_id' => $this->positiveId($source['purchase_request_id'] ?? 0),
                    'production_release_id' => $this->positiveId($source['production_release_id'] ?? 0),
                    'required_date' => $this->date($source['required_date'] ?? null),
                    'brand_restriction' => blank_to_null($source['brand_restriction'] ?? null),
                ]);
            }
        });

        return [];
    }

    /**
     * @param list<int> $supplierIds
     * @return array{errors: array<string, string>, tokens: array<int, string>}
     */
    public function invite(int $rfqId, array $supplierIds, int $userId, ?string $expiresAt = null): array
    {
        if (!can('procurement.rfq.send') && !can('purchasing.create')) {
            return ['errors' => ['_form' => 'You cannot invite suppliers.'], 'tokens' => []];
        }
        $tokens = [];
        Database::transaction(function () use ($rfqId, $supplierIds, $userId, $expiresAt, &$tokens): void {
            foreach ($supplierIds as $supplierId) {
                $raw = bin2hex(random_bytes(32));
                $this->repo->insertInvitation([
                    'rfq_id' => $rfqId,
                    'supplier_id' => (int) $supplierId,
                    'token_hash' => hash('sha256', $raw),
                    'status' => 'SENT',
                    'expires_at' => $expiresAt,
                ]);
                $tokens[(int) $supplierId] = $raw;
            }
            $this->repo->markRfqSent($rfqId);
            $this->audit->record('supplier_rfq', $rfqId, 'RFQ_SENT', null, ['suppliers' => count($supplierIds)], $userId);
            BusinessEventDispatcher::emit('RFQ_SENT', 'SUPPLIER_RFQ', $rfqId, $userId, ['suppliers' => count($supplierIds)]);
        });

        return ['errors' => [], 'tokens' => $tokens];
    }

    /**
     * @return array<string, mixed>
     */
    public function openToken(string $token): array
    {
        $invite = $this->repo->invitationByHash(hash('sha256', $token));
        if ($invite === null) {
            return ['found' => false, 'error' => 'That supplier link is not valid.'];
        }
        if ((string) $invite['status'] === 'REVOKED') {
            return ['found' => false, 'error' => 'That supplier link has been revoked.'];
        }
        if ($invite['expires_at'] !== null && (string) $invite['expires_at'] < date('Y-m-d H:i:s')) {
            $this->repo->touchInvitation((int) $invite['id'], 'EXPIRED');

            return ['found' => false, 'error' => 'That supplier link has expired.'];
        }
        if ((string) $invite['status'] === 'SENT') {
            $this->repo->touchInvitation((int) $invite['id'], 'VIEWED');
        }
        $rfq = $this->repo->rfq((int) $invite['rfq_id']);

        return [
            'found' => true,
            'invitation' => $invite,
            'rfq' => $rfq,
            'items' => $rfq === null ? [] : $this->repo->rfqItems((int) $rfq['id']),
            'own_quotes' => $this->repo->quotationsForSupplier((int) $invite['supplier_id']),
        ];
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function respond(string $token, array $header, array $lines): array
    {
        $opened = $this->openToken($token);
        if (!$opened['found']) {
            return ['errors' => ['_form' => (string) $opened['error']], 'id' => null];
        }
        $invite = $opened['invitation'];
        $late = $opened['rfq']['response_deadline'] !== null && (string) $opened['rfq']['response_deadline'] < date('Y-m-d H:i:s');
        $policy = (string) SettingsService::get('rfq_late_response', 'FLAG');
        if ($late && $policy === 'BLOCK') {
            return ['errors' => ['_form' => 'The response deadline has passed.'], 'id' => null];
        }
        $id = Database::transaction(function () use ($invite, $header, $lines, $late): int {
            $subtotal = '0.00';
            $prepared = [];
            foreach ($lines as $line) {
                $price = Decimal::round((string) ($line['unit_price'] ?? '0'), 4);
                $available = Decimal::round((string) ($line['available_quantity'] ?? '0'), 4);
                $lineTotal = Decimal::money(Decimal::mul($available, $price));
                $subtotal = Decimal::money(Decimal::add($subtotal, $lineTotal));
                $prepared[] = [$line, $price, $available];
            }
            $delivery = Decimal::money((string) ($header['delivery_amount'] ?? '0'));
            $tax = Decimal::money((string) ($header['tax_amount'] ?? '0'));
            $id = $this->repo->insertQuotation([
                'quote_number' => $this->numbers->supplierQuotation(),
                'supplier_id' => (int) $invite['supplier_id'],
                'rfq_id' => (int) $invite['rfq_id'],
                'invitation_id' => (int) $invite['id'],
                'supplier_reference' => blank_to_null($header['supplier_reference'] ?? null),
                'quote_date' => date('Y-m-d'),
                'valid_until' => $this->date($header['valid_until'] ?? null),
                'currency_code' => 'ZAR',
                'subtotal' => $subtotal,
                'delivery_amount' => $delivery,
                'tax_amount' => $tax,
                'total' => Decimal::money(Decimal::add(Decimal::add($subtotal, $delivery), $tax)),
                'lead_time_days' => ($header['lead_time_days'] ?? '') === '' ? null : (int) $header['lead_time_days'],
                'earliest_delivery' => $this->date($header['earliest_delivery'] ?? null),
                'notes' => blank_to_null($late ? 'Late response. ' . (string) ($header['notes'] ?? '') : ($header['notes'] ?? null)),
                'status' => 'SUBMITTED',
                'submitted_at' => date('Y-m-d H:i:s'),
            ]);
            foreach ($prepared as [$line, $price, $available]) {
                $alternative = !empty($line['alternative']) ? 1 : 0;
                $this->repo->insertQuotationItem([
                    'quotation_id' => $id,
                    'rfq_item_id' => (int) $line['rfq_item_id'],
                    'product_id' => $this->positiveId($line['product_id'] ?? 0),
                    'offered_description' => mb_substr((string) ($line['offered_description'] ?? 'Offered item'), 0, 180),
                    'offered_code' => blank_to_null($line['offered_code'] ?? null),
                    'brand' => blank_to_null($line['brand'] ?? null),
                    'requested_quantity' => Decimal::round((string) ($line['requested_quantity'] ?? $available), 4),
                    'available_quantity' => $available,
                    'moq' => ($line['moq'] ?? '') === '' ? null : Decimal::round((string) $line['moq'], 4),
                    'pack_size' => ($line['pack_size'] ?? '') === '' ? null : Decimal::round((string) $line['pack_size'], 4),
                    'unit_price' => $price,
                    'discount_amount' => '0.00',
                    'lead_time_days' => ($line['lead_time_days'] ?? '') === '' ? null : (int) $line['lead_time_days'],
                    'delivery_date' => $this->date($line['delivery_date'] ?? null),
                    'alternative' => $alternative,
                    'technical_status' => $alternative ? 'REVIEW_REQUIRED' : 'NOT_REQUIRED',
                    'notes' => blank_to_null($line['notes'] ?? null),
                ]);
            }
            $this->repo->touchInvitation((int) $invite['id'], 'RESPONDED');
            $this->refreshRfqResponseStatus((int) $invite['rfq_id']);
            BusinessEventDispatcher::emit('RFQ_RESPONSE_RECEIVED', 'SUPPLIER_QUOTATION', $id, null, ['supplier_id' => (int) $invite['supplier_id']]);

            return $id;
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * Facts only. There is no selected supplier.
     *
     * @return array{rows: list<array<string, mixed>>, auto_selected: false}
     */
    public function compare(int $rfqId): array
    {
        if (!can('procurement.quotes.compare') && !can('procurement.quotes.view') && !can('procurement.rfq.view')) {
            return ['rows' => [], 'auto_selected' => false, 'denied' => true];
        }
        $rows = [];
        foreach ($this->repo->quotationsForRfq($rfqId) as $quote) {
            foreach ($this->repo->quotationItems((int) $quote['id']) as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $variance = $productId > 0
                    ? $this->contractVariance((int) $quote['supplier_id'], $productId, (string) $item['unit_price'])
                    : null;
                $unit = (string) $item['unit_price'];
                $available = (string) $item['available_quantity'];
                $rows[] = [
                    'quotation_item_id' => (int) $item['id'],
                    'supplier_id' => (int) $quote['supplier_id'],
                    'supplier' => (string) $quote['supplier_name'],
                    'product' => (string) $item['offered_description'],
                    'brand' => (string) ($item['brand'] ?? ''),
                    'available' => $available,
                    'unit_price' => $unit,
                    'line_total' => Decimal::money(Decimal::mul($available, $unit)),
                    'delivery' => (string) $quote['delivery_amount'],
                    'landed_note' => 'Landed cost is the line total plus this quotation delivery amount. Unknown charges are not added.',
                    'lead_time_days' => $item['lead_time_days'],
                    'delivery_date' => $item['delivery_date'],
                    'valid_until' => $quote['valid_until'],
                    'moq' => $item['moq'],
                    'alternative' => (int) $item['alternative'] === 1,
                    'contract_variance' => $variance,
                ];
            }
        }

        return ['rows' => $rows, 'auto_selected' => false];
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array{errors: array<string, string>, purchase_orders: list<int>}
     */
    public function award(int $rfqId, array $lines, int $userId): array
    {
        if (!can('procurement.rfq.award') && !can('purchasing.create')) {
            return ['errors' => ['_form' => 'You cannot award an RFQ.'], 'purchase_orders' => []];
        }
        if ($lines === []) {
            return ['errors' => ['_form' => 'Choose a supplier and a quantity. Nothing is awarded automatically.'], 'purchase_orders' => []];
        }
        $orders = [];
        $grouped = [];
        foreach ($lines as $line) {
            $item = $this->repo->quotationItem((int) ($line['quotation_item_id'] ?? 0));
            if ($item === null) {
                return ['errors' => ['_form' => 'That quotation line was not found.'], 'purchase_orders' => []];
            }
            $quote = $this->repo->quotation((int) $item['quotation_id']);
            if ($quote === null || (int) $quote['rfq_id'] !== $rfqId) {
                return ['errors' => ['_form' => 'That quotation is not on this RFQ.'], 'purchase_orders' => []];
            }
            if ((int) $item['alternative'] === 1 && (string) $item['technical_status'] === 'REVIEW_REQUIRED') {
                return ['errors' => ['_form' => 'An alternative product needs technical review before it can be awarded.'], 'purchase_orders' => []];
            }
            $supplierId = (int) $quote['supplier_id'];
            $grouped[$supplierId][] = ['item' => $item, 'quantity' => (string) $line['quantity'], 'reason' => strtoupper((string) ($line['reason'] ?? 'OTHER'))];
        }
        foreach ($grouped as $supplierId => $group) {
            $made = $this->orders->createOrder(['supplier_id' => $supplierId, 'notes' => 'Awarded from RFQ ' . $rfqId], $userId);
            if ($made['id'] === null) {
                return ['errors' => $made['errors'], 'purchase_orders' => $orders];
            }
            $orderId = (int) $made['id'];
            foreach ($group as $row) {
                $item = $row['item'];
                $rfqItem = $this->repo->rfqItem((int) $item['rfq_item_id']);
                $added = $this->orders->addLine($orderId, [
                    'product_id' => (int) ($item['product_id'] ?: ($rfqItem['product_id'] ?? 0)),
                    'quantity' => $row['quantity'],
                    'description' => (string) $item['offered_description'],
                    'unit_cost' => (string) $item['unit_price'],
                    'cost_source' => 'SUPPLIER_QUOTATION',
                    'job_id' => (int) ($rfqItem['job_id'] ?? 0),
                ], $userId);
                if ($added !== []) {
                    return ['errors' => $added, 'purchase_orders' => $orders];
                }
                $this->repo->insertAward([
                    'rfq_id' => $rfqId,
                    'quotation_item_id' => (int) $item['id'],
                    'supplier_id' => $supplierId,
                    'quantity' => Decimal::round($row['quantity'], 4),
                    'reason_code' => $row['reason'],
                    'purchase_order_id' => $orderId,
                    'awarded_by' => $userId,
                ]);
            }
            $orders[] = $orderId;
        }
        $this->repo->setRfqStatus($rfqId, 'AWARDED');
        $this->audit->record('supplier_rfq', $rfqId, 'RFQ_AWARDED', null, ['orders' => count($orders)], $userId);
        BusinessEventDispatcher::emit('RFQ_AWARDED', 'SUPPLIER_RFQ', $rfqId, $userId, ['orders' => count($orders)]);

        return ['errors' => [], 'purchase_orders' => $orders];
    }

    /**
     * @return array{suggested: string, explanation: string}
     */
    public function suggestOrderQuantity(string $need, string $pack): array
    {
        if (!Decimal::isNumeric($need) || !Decimal::isNumeric($pack) || Decimal::cmp($pack, '0') <= 0) {
            return ['suggested' => $need, 'explanation' => 'No pack size was supplied. The buyer confirms the quantity.'];
        }
        $packs = (int) ceil((float) $need / (float) $pack);
        if ($packs < 1) {
            $packs = 1;
        }
        $suggested = Decimal::round((string) ($packs * (float) $pack), 4);

        return [
            'suggested' => $suggested,
            'explanation' => 'Rounded to supplier pack size. Required ' . $need . ', pack ' . $pack . ', suggested ' . $suggested . '. A person confirms the order.',
        ];
    }

    public function requirementTiming(?string $required, ?string $expected): string
    {
        if ($required === null || $required === '' || $expected === null || $expected === '') {
            return 'UNSCHEDULED';
        }
        if ($expected > $required) {
            return 'LATE_FOR_REQUIREMENT';
        }

        return 'ON_TIME_FOR_REQUIREMENT';
    }

    /**
     * @return array{warning: bool, contract: string, quoted: string, difference: string, percent: string}|null
     */
    public function contractVariance(int $supplierId, int $productId, string $quoted): ?array
    {
        $contract = $this->repo->activeContract($supplierId, $productId, date('Y-m-d'));
        if ($contract === null) {
            return null;
        }
        $agreed = (string) $contract['agreed_price'];
        $difference = Decimal::sub($quoted, $agreed, 4);
        $percent = Decimal::cmp($agreed, '0') === 0 ? '0.00' : Decimal::round(Decimal::mul(Decimal::div($difference, $agreed, 6), '100'), 2);

        return [
            'warning' => Decimal::cmp($difference, '0') !== 0,
            'contract' => $agreed,
            'quoted' => Decimal::round($quoted, 4),
            'difference' => $difference,
            'percent' => $percent,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function saveContract(array $input, int $userId): array
    {
        if (!can('procurement.contract_prices.manage') && !can('supplier_prices.edit')) {
            return ['errors' => ['_form' => 'You cannot change a contract price.'], 'id' => null];
        }
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        $productId = (int) ($input['product_id'] ?? 0);
        $from = $this->date($input['effective_from'] ?? null) ?? date('Y-m-d');
        $current = $this->repo->activeContract($supplierId, $productId, $from);
        $id = Database::transaction(function () use ($input, $userId, $supplierId, $productId, $from, $current): int {
            if ($current !== null) {
                $this->repo->expireContract((int) $current['id'], $from);
            }
            $id = $this->repo->insertContract([
                'supplier_id' => $supplierId,
                'product_id' => $productId,
                'agreed_price' => Decimal::round((string) $input['agreed_price'], 4),
                'currency_code' => 'ZAR',
                'moq' => null,
                'pack_size' => ($input['pack_size'] ?? '') === '' ? null : Decimal::round((string) $input['pack_size'], 4),
                'effective_from' => $from,
                'effective_to' => $this->date($input['effective_to'] ?? null),
                'contract_reference' => blank_to_null($input['contract_reference'] ?? null),
                'status' => 'ACTIVE',
                'created_by' => $userId,
            ]);
            $this->audit->record('supplier_contract_price', $id, 'CONTRACT_PRICE_SAVED', null, ['price' => (string) $input['agreed_price']], $userId);

            return $id;
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{alternative: bool, technical: string, production: string}
     */
    public function reviewAlternative(int $quotationItemId, ?int $jobId, int $userId): array
    {
        $item = $this->repo->quotationItem($quotationItemId);
        if ($item === null || (int) $item['alternative'] !== 1) {
            return ['alternative' => false, 'technical' => 'NOT_REQUIRED', 'production' => 'NO_PRODUCTION_IMPACT'];
        }
        $this->repo->setQuotationItemReview($quotationItemId, 'REVIEW_REQUIRED');
        $impact = 'NO_PRODUCTION_IMPACT';
        if ($jobId !== null && $jobId > 0) {
            $recorded = (new ProductionChangeImpactService())->record($jobId, 'MATERIAL', 'SUPPLIER', 'Alternative material offered', (string) $item['offered_description'], $userId);
            $impact = (string) ($recorded['impact'] ?? 'REVIEW_REQUIRED');
        }

        return ['alternative' => true, 'technical' => 'REVIEW_REQUIRED', 'production' => $impact];
    }

    public function acceptAlternative(int $quotationItemId, int $userId): array
    {
        if (!can('procurement.rfq.award') && !can('purchasing.approve')) {
            return ['_form' => 'You cannot accept an alternative material.'];
        }
        $this->repo->setQuotationItemReview($quotationItemId, 'ACCEPTED');
        $this->audit->record('supplier_quotation_item', $quotationItemId, 'ALTERNATIVE_ACCEPTED', null, [], $userId);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function confirmPurchaseOrder(string $token, int $orderId, array $input): array
    {
        $opened = $this->openToken($token);
        if (!$opened['found']) {
            return ['errors' => ['_form' => (string) $opened['error']], 'id' => null];
        }
        $order = (new \App\Repositories\PurchasingRepository())->order($orderId);
        if ($order === null || (int) $order['supplier_id'] !== (int) $opened['invitation']['supplier_id']) {
            return ['errors' => ['_form' => 'That purchase order is not yours.'], 'id' => null];
        }
        $id = $this->repo->insertConfirmation([
            'purchase_order_id' => $orderId,
            'supplier_id' => (int) $order['supplier_id'],
            'confirmed_quantity' => ($input['quantity'] ?? '') === '' ? null : Decimal::round((string) $input['quantity'], 4),
            'confirmed_delivery_date' => $this->date($input['delivery_date'] ?? null),
            'supplier_reference' => blank_to_null($input['supplier_reference'] ?? null),
            'notes' => blank_to_null($input['notes'] ?? null),
            'confirmed_at' => date('Y-m-d H:i:s'),
        ]);
        BusinessEventDispatcher::emit('PO_CONFIRMED', 'PURCHASE_ORDER', $orderId, null, []);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function requestPurchaseChange(string $token, int $orderId, string $type, string $detail): array
    {
        $opened = $this->openToken($token);
        if (!$opened['found']) {
            return ['errors' => ['_form' => (string) $opened['error']], 'id' => null];
        }
        $order = (new \App\Repositories\PurchasingRepository())->order($orderId);
        if ($order === null || (int) $order['supplier_id'] !== (int) $opened['invitation']['supplier_id']) {
            return ['errors' => ['_form' => 'That purchase order is not yours.'], 'id' => null];
        }
        $id = $this->repo->insertPoChange([
            'purchase_order_id' => $orderId,
            'supplier_id' => (int) $order['supplier_id'],
            'change_type' => strtoupper($type),
            'detail' => mb_substr($detail, 0, 255),
        ]);
        BusinessEventDispatcher::emit('PO_CHANGE_REQUESTED', 'PURCHASE_ORDER', $orderId, null, ['type' => strtolower($type)]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{errors: array<string, string>, stored: string|null}
     */
    public function storeSupplierFile(string $token, int $quotationId, string $originalName, string $bytes): array
    {
        $opened = $this->openToken($token);
        if (!$opened['found']) {
            return ['errors' => ['_form' => (string) $opened['error']], 'stored' => null];
        }
        $quote = $this->repo->quotation($quotationId);
        if ($quote === null || (int) $quote['supplier_id'] !== (int) $opened['invitation']['supplier_id']) {
            return ['errors' => ['_form' => 'That quotation is not yours.'], 'stored' => null];
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (in_array($ext, ['php', 'phtml', 'exe', 'sh', 'js', 'html', 'htm'], true)) {
            return ['errors' => ['_form' => 'That file type is not accepted.'], 'stored' => null];
        }
        if ($ext === 'pdf' && !str_starts_with($bytes, '%PDF')) {
            return ['errors' => ['_form' => 'That file is not a PDF.'], 'stored' => null];
        }
        if (strlen($bytes) > 5_000_000) {
            return ['errors' => ['_form' => 'That file is larger than 5 MB.'], 'stored' => null];
        }
        $stored = bin2hex(random_bytes(16)) . ($ext === '' ? '' : '.' . $ext);
        $dir = dirname(__DIR__, 2) . '/storage/supplier';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $stored, $bytes);
        $this->repo->storeQuotationFile($quotationId, mb_substr($originalName, 0, 180), $stored);

        return ['errors' => [], 'stored' => $stored];
    }

    public function supplierCanSeeQuotation(string $token, int $quotationId): bool
    {
        $opened = $this->openToken($token);
        if (!$opened['found']) {
            return false;
        }
        $quote = $this->repo->quotation($quotationId);

        return $quote !== null && (int) $quote['supplier_id'] === (int) $opened['invitation']['supplier_id'];
    }

    /**
     * @param list<array<string, mixed>> $sources
     */
    private function consolidationAllowed(array $sources): ?string
    {
        $dates = [];
        $brands = [];
        foreach ($sources as $source) {
            $date = $this->date($source['required_date'] ?? null);
            if ($date !== null) {
                $dates[$date] = true;
            }
            $brand = trim((string) ($source['brand_restriction'] ?? ''));
            if ($brand !== '') {
                $brands[$brand] = true;
            }
        }
        if (count($dates) > 1) {
            return 'Those lines have different required dates and were not consolidated.';
        }
        if (count($brands) > 1) {
            return 'Those lines restrict different brands and were not consolidated.';
        }

        return null;
    }

    private function refreshRfqResponseStatus(int $rfqId): void
    {
        $rows = $this->repo->invitations($rfqId);
        $responded = 0;
        foreach ($rows as $row) {
            if ((string) $row['status'] === 'RESPONDED') {
                $responded++;
            }
        }
        if ($responded === 0) {
            return;
        }
        $this->repo->setRfqStatus($rfqId, $responded === count($rows) ? 'RESPONDED' : 'PARTIALLY_RESPONDED');
    }

    private function positiveId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function date(mixed $value): ?string
    {
        $text = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : null;
    }

    private function dateTime(mixed $value): ?string
    {
        $text = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text . ' 23:59:59';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $text)) {
            return $text;
        }

        return null;
    }
}

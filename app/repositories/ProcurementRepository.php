<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Supplier RFQs, quotations, contracts, bins, lots, and receiving exceptions.
 * Purchase orders and goods receipts stay in PurchasingRepository.
 */
final class ProcurementRepository extends Repository
{
    public function insertRfq(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_rfqs (
                rfq_number, status, title, purchase_request_id, project_id, job_id, required_by_date,
                response_deadline, delivery_location_id, currency_code, instructions, terms, created_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['rfq_number'], $row['status'], $row['title'], $row['purchase_request_id'], $row['project_id'],
                $row['job_id'], $row['required_by_date'], $row['response_deadline'], $row['delivery_location_id'],
                $row['currency_code'], $row['instructions'], $row['terms'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function rfq(int $id): ?array
    {
        return $this->one('SELECT * FROM supplier_rfqs WHERE id = ?', [$id]);
    }

    public function lockRfq(int $id): ?array
    {
        return $this->one('SELECT * FROM supplier_rfqs WHERE id = ? FOR UPDATE', [$id]);
    }

    public function setRfqStatus(int $id, string $status): void
    {
        $this->run('UPDATE supplier_rfqs SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function markRfqSent(int $id): void
    {
        $this->run("UPDATE supplier_rfqs SET status = 'SENT', sent_at = NOW() WHERE id = ?", [$id]);
    }

    public function insertRfqItem(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_rfq_items (
                rfq_id, product_id, description, specification, quantity, unit, preferred_brand,
                equivalent_allowed, required_date, project_id, job_id, material_requirement_id, source_kind
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['rfq_id'], $row['product_id'], $row['description'], $row['specification'], $row['quantity'],
                $row['unit'], $row['preferred_brand'], $row['equivalent_allowed'], $row['required_date'],
                $row['project_id'], $row['job_id'], $row['material_requirement_id'], $row['source_kind'],
            ]
        );

        return $this->insertId();
    }

    public function rfqItems(int $rfqId): array
    {
        return $this->rows('SELECT * FROM supplier_rfq_items WHERE rfq_id = ? ORDER BY id', [$rfqId]);
    }

    public function rfqItem(int $id): ?array
    {
        return $this->one('SELECT * FROM supplier_rfq_items WHERE id = ?', [$id]);
    }

    public function insertSource(array $row): void
    {
        $this->run(
            'INSERT INTO supplier_rfq_item_sources (
                rfq_item_id, source_kind, quantity, job_id, project_id, purchase_request_id, production_release_id, required_date, brand_restriction
             ) VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $row['rfq_item_id'], $row['source_kind'], $row['quantity'], $row['job_id'], $row['project_id'],
                $row['purchase_request_id'], $row['production_release_id'], $row['required_date'], $row['brand_restriction'],
            ]
        );
    }

    public function sources(int $rfqItemId): array
    {
        return $this->rows('SELECT * FROM supplier_rfq_item_sources WHERE rfq_item_id = ? ORDER BY id', [$rfqItemId]);
    }

    public function insertInvitation(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_rfq_invitations (rfq_id, supplier_id, token_hash, status, expires_at)
             VALUES (?,?,?,?,?)',
            [$row['rfq_id'], $row['supplier_id'], $row['token_hash'], $row['status'], $row['expires_at']]
        );

        return $this->insertId();
    }

    public function invitations(int $rfqId): array
    {
        return $this->rows(
            'SELECT i.*, s.name AS supplier_name FROM supplier_rfq_invitations i
             JOIN suppliers s ON s.id = i.supplier_id WHERE i.rfq_id = ? ORDER BY i.id',
            [$rfqId]
        );
    }

    public function invitationByHash(string $hash): ?array
    {
        return $this->one(
            'SELECT i.*, s.name AS supplier_name, s.email AS supplier_email
             FROM supplier_rfq_invitations i JOIN suppliers s ON s.id = i.supplier_id
             WHERE i.token_hash = ?',
            [$hash]
        );
    }

    public function touchInvitation(int $id, string $status): void
    {
        $column = match ($status) {
            'VIEWED' => 'viewed_at',
            'RESPONDED' => 'responded_at',
            'SENT' => 'sent_at',
            default => null,
        };
        if ($column === null) {
            $this->run('UPDATE supplier_rfq_invitations SET status = ? WHERE id = ?', [$status, $id]);

            return;
        }
        $this->run(
            "UPDATE supplier_rfq_invitations SET status = ?, {$column} = COALESCE({$column}, NOW()) WHERE id = ?",
            [$status, $id]
        );
    }

    public function insertQuotation(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_quotations (
                quote_number, supplier_id, rfq_id, invitation_id, supplier_reference, quote_date, valid_until,
                currency_code, subtotal, delivery_amount, tax_amount, total, lead_time_days, earliest_delivery,
                notes, status, submitted_at
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['quote_number'], $row['supplier_id'], $row['rfq_id'], $row['invitation_id'], $row['supplier_reference'],
                $row['quote_date'], $row['valid_until'], $row['currency_code'], $row['subtotal'], $row['delivery_amount'],
                $row['tax_amount'], $row['total'], $row['lead_time_days'], $row['earliest_delivery'], $row['notes'],
                $row['status'], $row['submitted_at'],
            ]
        );

        return $this->insertId();
    }

    public function quotation(int $id): ?array
    {
        return $this->one('SELECT * FROM supplier_quotations WHERE id = ?', [$id]);
    }

    public function quotationsForRfq(int $rfqId): array
    {
        return $this->rows(
            'SELECT q.*, s.name AS supplier_name FROM supplier_quotations q
             JOIN suppliers s ON s.id = q.supplier_id WHERE q.rfq_id = ? ORDER BY q.id',
            [$rfqId]
        );
    }

    public function quotationsForSupplier(int $supplierId): array
    {
        return $this->rows('SELECT * FROM supplier_quotations WHERE supplier_id = ? ORDER BY id DESC', [$supplierId]);
    }

    public function insertQuotationItem(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_quotation_items (
                quotation_id, rfq_item_id, product_id, offered_description, offered_code, brand,
                requested_quantity, available_quantity, moq, pack_size, unit_price, discount_amount,
                lead_time_days, delivery_date, alternative, technical_status, notes
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['quotation_id'], $row['rfq_item_id'], $row['product_id'], $row['offered_description'],
                $row['offered_code'], $row['brand'], $row['requested_quantity'], $row['available_quantity'],
                $row['moq'], $row['pack_size'], $row['unit_price'], $row['discount_amount'], $row['lead_time_days'],
                $row['delivery_date'], $row['alternative'], $row['technical_status'], $row['notes'],
            ]
        );

        return $this->insertId();
    }

    public function quotationItems(int $quotationId): array
    {
        return $this->rows('SELECT * FROM supplier_quotation_items WHERE quotation_id = ? ORDER BY id', [$quotationId]);
    }

    public function quotationItem(int $id): ?array
    {
        return $this->one('SELECT * FROM supplier_quotation_items WHERE id = ?', [$id]);
    }

    public function setQuotationItemReview(int $id, string $status): void
    {
        $this->run('UPDATE supplier_quotation_items SET technical_status = ? WHERE id = ?', [$status, $id]);
    }

    public function setQuotationStatus(int $id, string $status): void
    {
        $this->run('UPDATE supplier_quotations SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function storeQuotationFile(int $id, string $original, string $stored): void
    {
        $this->run('UPDATE supplier_quotations SET file_name = ?, stored_name = ? WHERE id = ?', [$original, $stored, $id]);
    }

    public function insertAward(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_rfq_awards (rfq_id, quotation_item_id, supplier_id, quantity, reason_code, purchase_order_id, awarded_by)
             VALUES (?,?,?,?,?,?,?)',
            [
                $row['rfq_id'], $row['quotation_item_id'], $row['supplier_id'], $row['quantity'],
                $row['reason_code'], $row['purchase_order_id'], $row['awarded_by'],
            ]
        );

        return $this->insertId();
    }

    public function awards(int $rfqId): array
    {
        return $this->rows('SELECT * FROM supplier_rfq_awards WHERE rfq_id = ? ORDER BY id', [$rfqId]);
    }

    public function insertContract(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_contract_prices (
                supplier_id, product_id, agreed_price, currency_code, moq, pack_size, effective_from, effective_to,
                contract_reference, status, created_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['supplier_id'], $row['product_id'], $row['agreed_price'], $row['currency_code'], $row['moq'],
                $row['pack_size'], $row['effective_from'], $row['effective_to'], $row['contract_reference'],
                $row['status'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function activeContract(int $supplierId, int $productId, string $on): ?array
    {
        return $this->one(
            "SELECT * FROM supplier_contract_prices
             WHERE supplier_id = ? AND product_id = ? AND status = 'ACTIVE'
               AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
             ORDER BY effective_from DESC LIMIT 1",
            [$supplierId, $productId, $on, $on]
        );
    }

    public function expireContract(int $id, string $on): void
    {
        $this->run(
            "UPDATE supplier_contract_prices SET status = 'EXPIRED', effective_to = ? WHERE id = ? AND status = 'ACTIVE'",
            [$on, $id]
        );
    }

    public function insertConfirmation(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_po_confirmations (
                purchase_order_id, supplier_id, confirmed_quantity, confirmed_delivery_date, supplier_reference, notes, confirmed_at
             ) VALUES (?,?,?,?,?,?,?)',
            [
                $row['purchase_order_id'], $row['supplier_id'], $row['confirmed_quantity'], $row['confirmed_delivery_date'],
                $row['supplier_reference'], $row['notes'], $row['confirmed_at'],
            ]
        );

        return $this->insertId();
    }

    public function insertPoChange(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_po_change_requests (purchase_order_id, supplier_id, change_type, detail, status)
             VALUES (?,?,?,?,?)',
            [$row['purchase_order_id'], $row['supplier_id'], $row['change_type'], $row['detail'], 'OPEN']
        );

        return $this->insertId();
    }

    public function insertException(array $row): int
    {
        $this->run(
            'INSERT INTO receiving_exceptions (
                goods_receipt_id, purchase_order_id, product_id, exception_type, quantity, status, notes, created_by
             ) VALUES (?,?,?,?,?,?,?,?)',
            [
                $row['goods_receipt_id'], $row['purchase_order_id'], $row['product_id'], $row['exception_type'],
                $row['quantity'], $row['status'], $row['notes'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function insertReturn(array $row): int
    {
        $this->run(
            'INSERT INTO supplier_returns (return_number, supplier_id, purchase_order_id, goods_receipt_id, reason_code, credit_reference, credit_value, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $row['return_number'], $row['supplier_id'], $row['purchase_order_id'], $row['goods_receipt_id'],
                $row['reason_code'], $row['credit_reference'], $row['credit_value'], $row['notes'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function insertReturnItem(int $returnId, int $productId, string $qty, int $locationId): void
    {
        $this->run(
            'INSERT INTO supplier_return_items (return_id, product_id, quantity, stock_location_id) VALUES (?,?,?,?)',
            [$returnId, $productId, $qty, $locationId]
        );
    }

    public function insertLot(array $row): int
    {
        $this->run(
            'INSERT INTO inventory_lots (product_id, lot_code, supplier_id, goods_receipt_id, quantity_received, quantity_remaining, received_date)
             VALUES (?,?,?,?,?,?,?)',
            [
                $row['product_id'], $row['lot_code'], $row['supplier_id'], $row['goods_receipt_id'],
                $row['quantity_received'], $row['quantity_remaining'], $row['received_date'],
            ]
        );

        return $this->insertId();
    }

    public function lotByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM inventory_lots WHERE lot_code = ? ORDER BY id DESC LIMIT 1', [$code]);
    }

    public function useLot(int $lotId, ?int $jobId, ?int $assetId, string $qty): void
    {
        $this->run(
            'INSERT INTO inventory_lot_uses (lot_id, job_id, asset_id, quantity) VALUES (?,?,?,?)',
            [$lotId, $jobId, $assetId, $qty]
        );
        $this->run(
            'UPDATE inventory_lots SET quantity_remaining = GREATEST(quantity_remaining - ?, 0) WHERE id = ?',
            [$qty, $lotId]
        );
    }

    public function lotUses(int $lotId): array
    {
        return $this->rows('SELECT * FROM inventory_lot_uses WHERE lot_id = ? ORDER BY id', [$lotId]);
    }

    public function serialByNumber(string $serial): ?array
    {
        return $this->one('SELECT id, serial_number FROM inventory_serials WHERE serial_number = ? LIMIT 1', [$serial]);
    }

    public function insertSerial(array $row): int
    {
        $this->run(
            'INSERT INTO inventory_serials (product_id, serial_number, manufacturer_serial, goods_receipt_id, stock_location_id, status)
             VALUES (?,?,?,?,?,?)',
            [
                $row['product_id'], $row['serial_number'], $row['manufacturer_serial'], $row['goods_receipt_id'],
                $row['stock_location_id'], 'IN_STOCK',
            ]
        );

        return $this->insertId();
    }

    public function insertLocation(array $row): int
    {
        $this->run(
            'INSERT INTO stock_locations (parent_id, code, name, location_type, description, active) VALUES (?,?,?,?,?,1)',
            [$row['parent_id'], $row['code'], $row['name'], $row['location_type'], $row['description']]
        );

        return $this->insertId();
    }

    public function locationByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM stock_locations WHERE code = ?', [$code]);
    }

    public function childLocations(int $parentId): array
    {
        return $this->rows('SELECT * FROM stock_locations WHERE parent_id = ? ORDER BY code', [$parentId]);
    }

    public function insertPutawayRule(int $productId, int $locationId): void
    {
        $this->run(
            'INSERT INTO putaway_rules (product_id, stock_location_id, active) VALUES (?,?,1)',
            [$productId, $locationId]
        );
    }

    public function putawayLocation(int $productId): ?array
    {
        return $this->one(
            'SELECT l.* FROM putaway_rules r JOIN stock_locations l ON l.id = r.stock_location_id
             WHERE r.product_id = ? AND r.active = 1 ORDER BY r.id DESC LIMIT 1',
            [$productId]
        );
    }

    public function likelySuppliers(int $productId): array
    {
        return $this->rows(
            'SELECT s.id, s.name, sp.cost_price, sp.minimum_order_quantity, sp.lead_time_days, sp.preferred_supplier
             FROM supplier_products sp JOIN suppliers s ON s.id = sp.supplier_id
             WHERE sp.product_id = ? AND sp.active = 1 AND s.active = 1
             ORDER BY sp.preferred_supplier DESC, sp.cost_price',
            [$productId]
        );
    }

    public function workbench(): array
    {
        $one = function (string $sql): int {
            $row = $this->one($sql);

            return (int) ($row['n'] ?? 0);
        };

        return [
            'open_requests' => $one("SELECT COUNT(*) AS n FROM purchase_requests WHERE status = 'REQUESTED'"),
            'open_rfqs' => $one("SELECT COUNT(*) AS n FROM supplier_rfqs WHERE status IN ('DRAFT','READY','SENT','PARTIALLY_RESPONDED','RESPONDED','UNDER_REVIEW')"),
            'awaiting_response' => $one("SELECT COUNT(*) AS n FROM supplier_rfq_invitations WHERE status = 'SENT'"),
            'quotes_to_review' => $one("SELECT COUNT(*) AS n FROM supplier_quotations WHERE status = 'SUBMITTED'"),
            'late_pos' => $one("SELECT COUNT(*) AS n FROM purchase_orders WHERE status IN ('ORDERED','PARTIALLY_RECEIVED') AND expected_date IS NOT NULL AND expected_date < CURDATE()"),
            'exceptions' => $one("SELECT COUNT(*) AS n FROM receiving_exceptions WHERE status IN ('OPEN','QUARANTINED')"),
            'returns' => $one("SELECT COUNT(*) AS n FROM supplier_returns WHERE status = 'OPEN'"),
        ];
    }

    public function rfqPage(int $limit, int $offset): array
    {
        return $this->rows(
            'SELECT id, rfq_number, status, title, required_by_date FROM supplier_rfqs ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
    }

    public function onTimeDeliveries(): array
    {
        $row = $this->one(
            "SELECT
                SUM(CASE WHEN gr.received_date <= po.expected_date THEN 1 ELSE 0 END) AS on_time,
                COUNT(*) AS eligible
             FROM goods_receipts gr
             JOIN purchase_orders po ON po.id = gr.purchase_order_id
             WHERE po.expected_date IS NOT NULL AND gr.status = 'CONFIRMED'"
        );

        return [
            'on_time' => (int) ($row['on_time'] ?? 0),
            'eligible' => (int) ($row['eligible'] ?? 0),
            'formula' => 'receipts on or before the purchase order expected date / receipts with an expected date',
        ];
    }
}

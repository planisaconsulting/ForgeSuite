<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Purchase orders, receipts, and purchase requests.
 * Header totals are written by the service after it recalculates them.
 */
final class PurchasingRepository extends Repository
{
    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function orders(array $filters = [], int $limit = 200): array
    {
        $sql = 'SELECT po.*, s.name AS supplier_name
                FROM purchase_orders po
                INNER JOIN suppliers s ON s.id = po.supplier_id
                WHERE 1 = 1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND po.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['supplier_id'])) {
            $sql .= ' AND po.supplier_id = ?';
            $params[] = (int) $filters['supplier_id'];
        }
        if (!empty($filters['q'])) {
            $like = like_term((string) $filters['q']);
            $sql .= ' AND (po.po_number LIKE ? ESCAPE \'\\\\\' OR s.name LIKE ? ESCAPE \'\\\\\' OR po.supplier_reference LIKE ? ESCAPE \'\\\\\')';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY po.id DESC LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function order(int $id): ?array
    {
        return $this->one(
            'SELECT po.*, s.name AS supplier_name, s.email AS supplier_email, s.phone AS supplier_phone,
                    s.address AS supplier_address
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockOrder(int $id): ?array
    {
        return $this->one('SELECT * FROM purchase_orders WHERE id = ? FOR UPDATE', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertOrder(array $data): int
    {
        $this->run(
            'INSERT INTO purchase_orders (
                po_number, supplier_id, order_date, expected_date, status, subtotal, vat_rate, vat_amount, total,
                supplier_reference, notes, internal_notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['po_number'], $data['supplier_id'], $data['order_date'], $data['expected_date'], $data['status'],
                $data['subtotal'], $data['vat_rate'], $data['vat_amount'], $data['total'],
                $data['supplier_reference'], $data['notes'], $data['internal_notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateOrderHeader(int $id, array $data): void
    {
        $this->run(
            'UPDATE purchase_orders SET
                expected_date = ?, supplier_reference = ?, notes = ?, internal_notes = ?,
                subtotal = ?, vat_rate = ?, vat_amount = ?, total = ?, version_number = version_number + 1
             WHERE id = ?',
            [
                $data['expected_date'], $data['supplier_reference'], $data['notes'], $data['internal_notes'],
                $data['subtotal'], $data['vat_rate'], $data['vat_amount'], $data['total'], $id,
            ]
        );
    }

    public function setOrderStatus(int $id, string $status, ?int $approver = null): void
    {
        if ($approver !== null) {
            $this->run(
                'UPDATE purchase_orders SET status = ?, approved_by = ?, approved_at = ?, version_number = version_number + 1 WHERE id = ?',
                [$status, $approver, date('Y-m-d H:i:s'), $id]
            );

            return;
        }
        $this->run(
            'UPDATE purchase_orders SET status = ?, version_number = version_number + 1 WHERE id = ?',
            [$status, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $orderId): array
    {
        return $this->rows(
            'SELECT i.*, p.name AS product_name, p.sku, p.inventory_method, j.job_number
             FROM purchase_order_items i
             INNER JOIN products p ON p.id = i.product_id
             LEFT JOIN jobs j ON j.id = i.job_id
             WHERE i.purchase_order_id = ?
             ORDER BY i.id',
            [$orderId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function item(int $id): ?array
    {
        return $this->one('SELECT * FROM purchase_order_items WHERE id = ? FOR UPDATE', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertItem(array $data): int
    {
        $this->run(
            'INSERT INTO purchase_order_items (
                purchase_order_id, product_id, supplier_product_id, description, ordered_quantity, received_quantity,
                unit, unit_cost, line_total, expected_date, job_id, notes
             ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)',
            [
                $data['purchase_order_id'], $data['product_id'], $data['supplier_product_id'], $data['description'],
                $data['ordered_quantity'], $data['unit'], $data['unit_cost'], $data['line_total'],
                $data['expected_date'], $data['job_id'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    public function addReceived(int $itemId, string $received): void
    {
        $this->run(
            'UPDATE purchase_order_items SET received_quantity = ? WHERE id = ?',
            [$received, $itemId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertReceipt(array $data): int
    {
        $this->run(
            'INSERT INTO goods_receipts (
                grn_number, purchase_order_id, supplier_id, received_date, supplier_delivery_note,
                supplier_invoice_number, received_by, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['grn_number'], $data['purchase_order_id'], $data['supplier_id'], $data['received_date'],
                $data['supplier_delivery_note'], $data['supplier_invoice_number'], $data['received_by'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertReceiptItem(array $data): int
    {
        $this->run(
            'INSERT INTO goods_receipt_items (
                goods_receipt_id, purchase_order_item_id, product_id, quantity_received, unit, unit_cost,
                stock_location_id, width_mm, length_mm, track_each
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['goods_receipt_id'], $data['purchase_order_item_id'], $data['product_id'],
                $data['quantity_received'], $data['unit'], $data['unit_cost'], $data['stock_location_id'],
                $data['width_mm'], $data['length_mm'], $data['track_each'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function receiptsForOrder(int $orderId): array
    {
        return $this->rows(
            'SELECT g.*, u.name AS received_by_name
             FROM goods_receipts g
             LEFT JOIN users u ON u.id = g.received_by
             WHERE g.purchase_order_id = ?
             ORDER BY g.id DESC',
            [$orderId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentReceipts(int $limit = 8): array
    {
        return $this->rows(
            'SELECT g.*, s.name AS supplier_name, po.po_number
             FROM goods_receipts g
             INNER JOIN suppliers s ON s.id = g.supplier_id
             INNER JOIN purchase_orders po ON po.id = g.purchase_order_id
             ORDER BY g.id DESC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function requests(array $filters = []): array
    {
        $sql = 'SELECT r.*, p.name AS product_name, p.sku, j.job_number, u.name AS requested_by_name
                FROM purchase_requests r
                INNER JOIN products p ON p.id = r.product_id
                LEFT JOIN jobs j ON j.id = r.job_id
                LEFT JOIN users u ON u.id = r.requested_by
                WHERE 1 = 1';
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' AND r.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['job_id'])) {
            $sql .= ' AND r.job_id = ?';
            $params[] = (int) $filters['job_id'];
        }
        $sql .= ' ORDER BY r.id DESC LIMIT 200';

        return $this->rows($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function request(int $id): ?array
    {
        return $this->one('SELECT * FROM purchase_requests WHERE id = ? FOR UPDATE', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRequest(array $data): int
    {
        $this->run(
            'INSERT INTO purchase_requests (job_id, requested_by, product_id, quantity, unit, required_by, reason, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['requested_by'], $data['product_id'], $data['quantity'],
                $data['unit'], $data['required_by'], $data['reason'], $data['status'],
            ]
        );

        return $this->insertId();
    }

    public function setRequestStatus(int $id, string $status, ?int $userId = null, ?int $orderId = null, ?int $itemId = null): void
    {
        $this->run(
            'UPDATE purchase_requests SET status = ?, approved_by = COALESCE(?, approved_by),
                purchase_order_id = COALESCE(?, purchase_order_id),
                purchase_order_item_id = COALESCE(?, purchase_order_item_id)
             WHERE id = ?',
            [$status, $userId, $orderId, $itemId, $id]
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function desk(): array
    {
        $row = $this->one(
            "SELECT
                SUM(status = 'DRAFT') AS drafts,
                SUM(status = 'PENDING_APPROVAL') AS pending,
                SUM(status = 'ORDERED') AS ordered,
                SUM(status = 'PARTIALLY_RECEIVED') AS partial,
                SUM(status IN ('ORDERED', 'PARTIALLY_RECEIVED') AND expected_date IS NOT NULL AND expected_date < CURDATE()) AS overdue,
                SUM(status IN ('ORDERED', 'PARTIALLY_RECEIVED') AND expected_date IS NOT NULL AND expected_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS due_week
             FROM purchase_orders"
        ) ?? [];
        $month = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS value
             FROM purchase_orders
             WHERE status NOT IN ('DRAFT', 'CANCELLED') AND order_date >= ?",
            [date('Y-m-01')]
        );

        return [
            'drafts' => (int) ($row['drafts'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'ordered' => (int) ($row['ordered'] ?? 0),
            'partial' => (int) ($row['partial'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'due_week' => (int) ($row['due_week'] ?? 0),
            'month_value' => (string) ($month['value'] ?? '0'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topSuppliers(): array
    {
        return $this->rows(
            "SELECT s.id, s.name, COALESCE(SUM(po.total), 0) AS value
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.status NOT IN ('DRAFT', 'CANCELLED') AND po.order_date >= ?
             GROUP BY s.id
             ORDER BY value DESC
             LIMIT 5",
            [date('Y-m-01')]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ordersForSupplier(int $supplierId): array
    {
        return $this->orders(['supplier_id' => $supplierId], 30);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ordersForProduct(int $productId): array
    {
        return $this->rows(
            'SELECT po.po_number, po.status, po.order_date, i.ordered_quantity, i.received_quantity, i.unit, i.unit_cost, s.name AS supplier_name
             FROM purchase_order_items i
             INNER JOIN purchase_orders po ON po.id = i.purchase_order_id
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE i.product_id = ?
             ORDER BY po.id DESC
             LIMIT 30',
            [$productId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ordersForJob(int $jobId): array
    {
        return $this->rows(
            'SELECT po.po_number, po.status, i.description, i.ordered_quantity, i.received_quantity, i.unit
             FROM purchase_order_items i
             INNER JOIN purchase_orders po ON po.id = i.purchase_order_id
             WHERE i.job_id = ?
             ORDER BY po.id DESC',
            [$jobId]
        );
    }

    /**
     * Open ordered quantity not yet received, for a product, optionally a job.
     */
    public function outstandingForProduct(int $productId, ?int $jobId = null): string
    {
        $sql = "SELECT COALESCE(SUM(i.ordered_quantity - i.received_quantity), 0) AS qty
                FROM purchase_order_items i
                INNER JOIN purchase_orders po ON po.id = i.purchase_order_id
                WHERE i.product_id = ?
                  AND po.status IN ('APPROVED', 'ORDERED', 'PARTIALLY_RECEIVED')
                  AND i.ordered_quantity > i.received_quantity";
        $params = [$productId];
        if ($jobId !== null) {
            $sql .= ' AND i.job_id = ?';
            $params[] = $jobId;
        }
        $row = $this->one($sql, $params);

        return (string) ($row['qty'] ?? '0');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requirementBoard(): array
    {
        return $this->rows(
            "SELECT r.id, r.job_id, r.product_id, r.required_quantity, r.unit, r.final_required_quantity,
                    j.job_number, j.title AS job_title, c.company_name, c.first_name, c.last_name, c.customer_type,
                    p.name AS product_name,
                    COALESCE((
                        SELECT SUM(sr.quantity) FROM stock_reservations sr
                        WHERE sr.job_id = r.job_id AND sr.product_id = r.product_id AND sr.status = 'RESERVED'
                    ), 0) AS reserved_qty,
                    COALESCE((
                        SELECT SUM(u.quantity) FROM job_material_usage u
                        WHERE u.job_id = r.job_id AND u.product_id = r.product_id AND u.quantity > 0
                    ), 0) AS issued_qty,
                    COALESCE((
                        SELECT SUM(i.ordered_quantity - i.received_quantity)
                        FROM purchase_order_items i
                        INNER JOIN purchase_orders po ON po.id = i.purchase_order_id
                        WHERE i.job_id = r.job_id AND i.product_id = r.product_id
                          AND po.status IN ('APPROVED', 'ORDERED', 'PARTIALLY_RECEIVED')
                    ), 0) AS ordered_qty,
                    COALESCE((
                        SELECT SUM(i.received_quantity)
                        FROM purchase_order_items i
                        WHERE i.job_id = r.job_id AND i.product_id = r.product_id
                    ), 0) AS received_qty
             FROM job_material_requirements r
             INNER JOIN jobs j ON j.id = r.job_id
             INNER JOIN customers c ON c.id = j.customer_id
             LEFT JOIN products p ON p.id = r.product_id
             WHERE j.status NOT IN ('COMPLETED', 'CANCELLED')
             ORDER BY j.target_date, j.id
             LIMIT 200"
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Decimal;

/**
 * Stock ledger queries. Balances are sums of movements, not a quantity column.
 */
final class InventoryRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function locations(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM stock_locations';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }

        return $this->rows($sql . ' ORDER BY name');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function location(int $id): ?array
    {
        return $this->one('SELECT * FROM stock_locations WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertLocation(array $data): int
    {
        $this->run(
            'INSERT INTO stock_locations (code, name, description, active) VALUES (?, ?, ?, ?)',
            [$data['code'], $data['name'], $data['description'], $data['active']]
        );

        return $this->insertId();
    }

    public function updateLocation(int $id, string $name, ?string $description, int $active): void
    {
        $this->run(
            'UPDATE stock_locations SET name = ?, description = ?, active = ? WHERE id = ?',
            [$name, $description, $active, $id]
        );
    }

    public function lockProduct(int $productId): void
    {
        $this->one('SELECT id FROM products WHERE id = ? FOR UPDATE', [$productId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockItem(int $itemId): ?array
    {
        return $this->one('SELECT * FROM inventory_items WHERE id = ? FOR UPDATE', [$itemId]);
    }

    public function onHand(int $productId, ?int $locationId = null, bool $includeOffcuts = false, ?int $itemId = null): string
    {
        $sql = 'SELECT COALESCE(SUM(m.quantity), 0) AS qty
                FROM stock_movements m
                LEFT JOIN inventory_items i ON i.id = m.inventory_item_id
                WHERE m.product_id = ?';
        $params = [$productId];
        if (!$includeOffcuts) {
            $sql .= " AND (m.inventory_item_id IS NULL OR i.inventory_type <> 'OFFCUT')";
        }
        if ($locationId !== null && $locationId > 0) {
            $sql .= ' AND m.stock_location_id = ?';
            $params[] = $locationId;
        }
        if ($itemId !== null && $itemId > 0) {
            $sql .= ' AND m.inventory_item_id = ?';
            $params[] = $itemId;
        }
        $row = $this->one($sql, $params);

        return Decimal::round((string) ($row['qty'] ?? '0'), 4);
    }

    public function reserved(int $productId, ?int $locationId = null, ?int $itemId = null, bool $offcutsOnly = false): string
    {
        $sql = "SELECT COALESCE(SUM(r.quantity), 0) AS qty
                FROM stock_reservations r
                LEFT JOIN inventory_items i ON i.id = r.inventory_item_id
                WHERE r.product_id = ? AND r.status = 'RESERVED'";
        $params = [$productId];
        if ($offcutsOnly) {
            $sql .= " AND i.inventory_type = 'OFFCUT'";
        } else {
            $sql .= " AND (r.inventory_item_id IS NULL OR i.inventory_type <> 'OFFCUT')";
        }
        if ($locationId !== null && $locationId > 0) {
            $sql .= ' AND r.stock_location_id = ?';
            $params[] = $locationId;
        }
        if ($itemId !== null && $itemId > 0) {
            $sql .= ' AND r.inventory_item_id = ?';
            $params[] = $itemId;
        }
        $row = $this->one($sql, $params);

        return Decimal::round((string) ($row['qty'] ?? '0'), 4);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertMovement(array $data): int
    {
        $this->run(
            'INSERT INTO stock_movements (
                product_id, stock_location_id, inventory_item_id, movement_type, quantity, unit,
                unit_cost_snapshot, total_cost, reference_type, reference_id, job_id, purchase_order_id,
                job_material_usage_id, reason, notes, movement_date, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['product_id'], $data['stock_location_id'], $data['inventory_item_id'],
                $data['movement_type'], $data['quantity'], $data['unit'],
                $data['unit_cost_snapshot'], $data['total_cost'], $data['reference_type'], $data['reference_id'],
                $data['job_id'], $data['purchase_order_id'], $data['job_material_usage_id'],
                $data['reason'], $data['notes'], $data['movement_date'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function movements(array $filters, int $limit = 200): array
    {
        $sql = 'SELECT m.*, p.name AS product_name, p.sku, l.name AS location_name,
                       i.inventory_code, u.name AS created_by_name, j.job_number
                FROM stock_movements m
                INNER JOIN products p ON p.id = m.product_id
                INNER JOIN stock_locations l ON l.id = m.stock_location_id
                LEFT JOIN inventory_items i ON i.id = m.inventory_item_id
                LEFT JOIN users u ON u.id = m.created_by
                LEFT JOIN jobs j ON j.id = m.job_id
                WHERE 1 = 1';
        $params = [];
        if (!empty($filters['product_id'])) {
            $sql .= ' AND m.product_id = ?';
            $params[] = (int) $filters['product_id'];
        }
        if (!empty($filters['item'])) {
            $sql .= ' AND m.inventory_item_id = ?';
            $params[] = (int) $filters['item'];
        }
        if (!empty($filters['location_id'])) {
            $sql .= ' AND m.stock_location_id = ?';
            $params[] = (int) $filters['location_id'];
        }
        if (!empty($filters['movement_type'])) {
            $sql .= ' AND m.movement_type = ?';
            $params[] = (string) $filters['movement_type'];
        }
        if (!empty($filters['job_id'])) {
            $sql .= ' AND m.job_id = ?';
            $params[] = (int) $filters['job_id'];
        }
        if (!empty($filters['purchase_order_id'])) {
            $sql .= ' AND m.purchase_order_id = ?';
            $params[] = (int) $filters['purchase_order_id'];
        }
        if (!empty($filters['user_id'])) {
            $sql .= ' AND m.created_by = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['supplier_id'])) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM purchase_orders po
                WHERE po.id = m.purchase_order_id AND po.supplier_id = ?
            )';
            $params[] = (int) $filters['supplier_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND m.movement_date >= ?';
            $params[] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND m.movement_date <= ?';
            $params[] = (string) $filters['to'];
        }
        $sql .= ' ORDER BY m.id DESC LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function movementsForItem(int $itemId): array
    {
        return $this->movements(['item' => $itemId], 100);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertItem(array $data): int
    {
        $this->run(
            'INSERT INTO inventory_items (
                product_id, stock_location_id, inventory_type, inventory_code, supplier_id,
                purchase_order_item_id, source_inventory_item_id, source_job_id, status,
                received_date, expiry_date, original_quantity, remaining_quantity, unit,
                unit_cost, acquisition_cost, valuation_treatment, width_mm, length_mm, height_mm,
                batch_number, supplier_reference, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['product_id'], $data['stock_location_id'], $data['inventory_type'], $data['inventory_code'],
                $data['supplier_id'], $data['purchase_order_item_id'], $data['source_inventory_item_id'],
                $data['source_job_id'], $data['status'], $data['received_date'], $data['expiry_date'],
                $data['original_quantity'], $data['remaining_quantity'], $data['unit'],
                $data['unit_cost'], $data['acquisition_cost'], $data['valuation_treatment'],
                $data['width_mm'], $data['length_mm'], $data['height_mm'],
                $data['batch_number'], $data['supplier_reference'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function item(int $id): ?array
    {
        return $this->one(
            'SELECT i.*, p.name AS product_name, p.sku, p.cost_unit, p.inventory_method,
                    l.name AS location_name, s.name AS supplier_name
             FROM inventory_items i
             INNER JOIN products p ON p.id = i.product_id
             INNER JOIN stock_locations l ON l.id = i.stock_location_id
             LEFT JOIN suppliers s ON s.id = i.supplier_id
             WHERE i.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function itemByCode(string $code): ?array
    {
        $row = $this->one('SELECT id FROM inventory_items WHERE inventory_code = ? LIMIT 1', [$code]);

        return $row === null ? null : $this->item((int) $row['id']);
    }

    public function setOriginalQuantity(int $id, string $original): void
    {
        $this->run('UPDATE inventory_items SET original_quantity = ? WHERE id = ?', [$original, $id]);
    }

    public function updateItemQuantity(int $id, string $remaining, string $status, ?int $locationId = null): void
    {
        if ($locationId !== null) {
            $this->run(
                'UPDATE inventory_items SET remaining_quantity = ?, status = ?, stock_location_id = ? WHERE id = ?',
                [$remaining, $status, $locationId, $id]
            );

            return;
        }
        $this->run(
            'UPDATE inventory_items SET remaining_quantity = ?, status = ? WHERE id = ?',
            [$remaining, $status, $id]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function searchItems(array $filters, int $limit = 100): array
    {
        $sql = 'SELECT i.*, p.name AS product_name, p.sku, l.name AS location_name
                FROM inventory_items i
                INNER JOIN products p ON p.id = i.product_id
                INNER JOIN stock_locations l ON l.id = i.stock_location_id
                WHERE 1 = 1';
        $params = [];
        if (!empty($filters['q'])) {
            $like = like_term((string) $filters['q']);
            $sql .= ' AND (i.inventory_code LIKE ? ESCAPE \'\\\\\' OR p.name LIKE ? ESCAPE \'\\\\\'
                      OR p.sku LIKE ? ESCAPE \'\\\\\' OR i.supplier_reference LIKE ? ESCAPE \'\\\\\')';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['product_id'])) {
            $sql .= ' AND i.product_id = ?';
            $params[] = (int) $filters['product_id'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND i.inventory_type = ?';
            $params[] = (string) $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND i.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['open_only'])) {
            $sql .= " AND i.status IN ('AVAILABLE', 'RESERVED') AND i.remaining_quantity > 0";
        }
        $sql .= ' ORDER BY i.id DESC LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function offcuts(int $productId, string $minWidth, string $minHeight, bool $allowRotation): array
    {
        $rows = $this->rows(
            "SELECT i.*, p.name AS product_name, l.name AS location_name
             FROM inventory_items i
             INNER JOIN products p ON p.id = i.product_id
             INNER JOIN stock_locations l ON l.id = i.stock_location_id
             WHERE i.inventory_type = 'OFFCUT' AND i.status = 'AVAILABLE' AND i.product_id = ?
             ORDER BY i.width_mm, i.height_mm",
            [$productId]
        );
        $matched = [];
        foreach ($rows as $row) {
            $width = (string) ($row['width_mm'] ?? '0');
            $height = (string) ($row['height_mm'] ?? $row['length_mm'] ?? '0');
            $fits = Decimal::cmp($width, $minWidth) >= 0 && Decimal::cmp($height, $minHeight) >= 0;
            $rotated = $allowRotation && Decimal::cmp($width, $minHeight) >= 0 && Decimal::cmp($height, $minWidth) >= 0;
            if ($fits || $rotated) {
                $row['fits_rotated'] = $fits ? 0 : 1;
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertReservation(array $data): int
    {
        $this->run(
            'INSERT INTO stock_reservations (
                job_id, job_material_requirement_id, product_id, inventory_item_id, stock_location_id,
                quantity, unit, status, reserved_by, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_material_requirement_id'], $data['product_id'],
                $data['inventory_item_id'], $data['stock_location_id'], $data['quantity'],
                $data['unit'], $data['status'], $data['reserved_by'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reservation(int $id): ?array
    {
        return $this->one('SELECT * FROM stock_reservations WHERE id = ? FOR UPDATE', [$id]);
    }

    public function setReservation(int $id, string $quantity, string $status): void
    {
        $released = in_array($status, ['RELEASED', 'CANCELLED', 'CONSUMED'], true) ? date('Y-m-d H:i:s') : null;
        $this->run(
            'UPDATE stock_reservations SET quantity = ?, status = ?, released_at = COALESCE(released_at, ?) WHERE id = ?',
            [$quantity, $status, $released, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reservationsForJob(int $jobId): array
    {
        return $this->rows(
            'SELECT r.*, p.name AS product_name, l.name AS location_name, i.inventory_code
             FROM stock_reservations r
             INNER JOIN products p ON p.id = r.product_id
             INNER JOIN stock_locations l ON l.id = r.stock_location_id
             LEFT JOIN inventory_items i ON i.id = r.inventory_item_id
             WHERE r.job_id = ?
             ORDER BY r.id DESC',
            [$jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function balances(int $limit = 300): array
    {
        return $this->rows(
            'SELECT p.id AS product_id, p.sku, p.name AS product_name, p.cost_unit, p.inventory_method,
                    p.track_stock, p.minimum_stock_level, p.reorder_level, p.preferred_order_quantity,
                    p.cost_price, p.average_cost, p.costing_method,
                    l.id AS stock_location_id, l.name AS location_name,
                    COALESCE(SUM(CASE WHEN i.inventory_type = \'OFFCUT\' THEN 0 ELSE m.quantity END), 0) AS on_hand,
                    COALESCE(SUM(CASE WHEN i.inventory_type = \'OFFCUT\' THEN m.quantity ELSE 0 END), 0) AS offcut_qty,
                    COALESCE(SUM(m.total_cost), 0) AS movement_value
             FROM stock_movements m
             INNER JOIN products p ON p.id = m.product_id
             INNER JOIN stock_locations l ON l.id = m.stock_location_id
             LEFT JOIN inventory_items i ON i.id = m.inventory_item_id
             GROUP BY p.id, l.id
             ORDER BY p.name, l.name
             LIMIT ' . (int) $limit
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function productBalances(int $productId): array
    {
        return $this->rows(
            'SELECT l.id AS stock_location_id, l.name AS location_name,
                    COALESCE(SUM(CASE WHEN i.inventory_type = \'OFFCUT\' THEN 0 ELSE m.quantity END), 0) AS on_hand,
                    COALESCE(SUM(m.total_cost), 0) AS movement_value
             FROM stock_movements m
             INNER JOIN stock_locations l ON l.id = m.stock_location_id
             LEFT JOIN inventory_items i ON i.id = m.inventory_item_id
             WHERE m.product_id = ?
             GROUP BY l.id
             ORDER BY l.name',
            [$productId]
        );
    }

    public function stockValue(): string
    {
        $row = $this->one('SELECT COALESCE(SUM(total_cost), 0) AS value FROM stock_movements');

        return Decimal::money((string) ($row['value'] ?? '0'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function trackedProducts(): array
    {
        return $this->rows(
            "SELECT id, sku, name, cost_unit, cost_price, average_cost, costing_method, inventory_method,
                    minimum_stock_level, reorder_level, preferred_order_quantity, track_stock
             FROM products
             WHERE active = 1 AND inventory_method <> 'NONE'
             ORDER BY name"
        );
    }

    public function setAverageCost(int $productId, string $average): void
    {
        $this->run('UPDATE products SET average_cost = ? WHERE id = ?', [$average, $productId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTransfer(array $data): int
    {
        $this->run(
            'INSERT INTO stock_transfers (
                product_id, inventory_item_id, from_location_id, to_location_id, quantity, unit, reason, notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['product_id'], $data['inventory_item_id'], $data['from_location_id'], $data['to_location_id'],
                $data['quantity'], $data['unit'], $data['reason'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCount(array $data): int
    {
        $this->run(
            'INSERT INTO stock_counts (reference_code, stock_location_id, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?)',
            [$data['reference_code'], $data['stock_location_id'], $data['status'], $data['notes'], $data['created_by']]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCountItem(array $data): void
    {
        $this->run(
            'INSERT INTO stock_count_items (
                stock_count_id, product_id, inventory_item_id, stock_location_id,
                system_quantity, physical_quantity, unit, unit_cost_snapshot, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['stock_count_id'], $data['product_id'], $data['inventory_item_id'], $data['stock_location_id'],
                $data['system_quantity'], $data['physical_quantity'], $data['unit'], $data['unit_cost_snapshot'], $data['notes'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function counts(): array
    {
        return $this->rows(
            'SELECT c.*, l.name AS location_name, u.name AS created_by_name
             FROM stock_counts c
             LEFT JOIN stock_locations l ON l.id = c.stock_location_id
             LEFT JOIN users u ON u.id = c.created_by
             ORDER BY c.id DESC
             LIMIT 100'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function count(int $id): ?array
    {
        return $this->one(
            'SELECT c.*, l.name AS location_name FROM stock_counts c
             LEFT JOIN stock_locations l ON l.id = c.stock_location_id
             WHERE c.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function countItems(int $countId): array
    {
        return $this->rows(
            'SELECT i.*, p.name AS product_name, p.sku, l.name AS location_name, n.inventory_code
             FROM stock_count_items i
             INNER JOIN products p ON p.id = i.product_id
             INNER JOIN stock_locations l ON l.id = i.stock_location_id
             LEFT JOIN inventory_items n ON n.id = i.inventory_item_id
             WHERE i.stock_count_id = ?
             ORDER BY p.name',
            [$countId]
        );
    }

    public function savePhysical(int $itemId, string $physical): void
    {
        $this->run('UPDATE stock_count_items SET physical_quantity = ? WHERE id = ?', [$physical, $itemId]);
    }

    public function finishCount(int $id, string $status, ?int $userId): void
    {
        $this->run(
            'UPDATE stock_counts SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?',
            [$status, $userId, $status === 'COMPLETED' ? date('Y-m-d H:i:s') : null, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function supplierProductsForProduct(int $productId): array
    {
        return $this->rows(
            'SELECT sp.*, s.name AS supplier_name
             FROM supplier_products sp
             INNER JOIN suppliers s ON s.id = sp.supplier_id
             WHERE sp.product_id = ?
             ORDER BY sp.preferred_supplier DESC, s.name',
            [$productId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function supplierProductsForSupplier(int $supplierId): array
    {
        return $this->rows(
            'SELECT sp.*, p.name AS product_name, p.sku
             FROM supplier_products sp
             INNER JOIN products p ON p.id = sp.product_id
             WHERE sp.supplier_id = ?
             ORDER BY p.name',
            [$supplierId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function supplierProduct(int $id): ?array
    {
        return $this->one('SELECT * FROM supplier_products WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveSupplierProduct(array $data): int
    {
        $existing = $this->one(
            'SELECT id FROM supplier_products WHERE supplier_id = ? AND product_id = ? LIMIT 1',
            [$data['supplier_id'], $data['product_id']]
        );
        if ($existing !== null) {
            $this->run(
                'UPDATE supplier_products SET supplier_sku = ?, supplier_description = ?, cost_price = ?,
                    minimum_order_quantity = ?, lead_time_days = ?, preferred_supplier = ?, active = ?, last_price_update = ?
                 WHERE id = ?',
                [
                    $data['supplier_sku'], $data['supplier_description'], $data['cost_price'],
                    $data['minimum_order_quantity'], $data['lead_time_days'], $data['preferred_supplier'],
                    $data['active'], $data['last_price_update'], $existing['id'],
                ]
            );

            return (int) $existing['id'];
        }
        $this->run(
            'INSERT INTO supplier_products (
                supplier_id, product_id, supplier_sku, supplier_description, cost_price,
                minimum_order_quantity, lead_time_days, preferred_supplier, active, last_price_update
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['supplier_id'], $data['product_id'], $data['supplier_sku'], $data['supplier_description'],
                $data['cost_price'], $data['minimum_order_quantity'], $data['lead_time_days'],
                $data['preferred_supplier'], $data['active'], $data['last_price_update'],
            ]
        );

        return $this->insertId();
    }

    public function addSupplierPriceHistory(int $supplierProductId, string $old, string $new, int $userId): void
    {
        $this->run(
            'INSERT INTO supplier_price_history (supplier_product_id, old_price, new_price, effective_date, changed_by)
             VALUES (?, ?, ?, ?, ?)',
            [$supplierProductId, $old, $new, date('Y-m-d'), $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function supplierPriceHistory(int $supplierProductId): array
    {
        return $this->rows(
            'SELECT h.*, u.name AS changed_by_name
             FROM supplier_price_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.supplier_product_id = ?
             ORDER BY h.id DESC',
            [$supplierProductId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentPriceChanges(int $limit = 8): array
    {
        return $this->rows(
            'SELECT h.*, sp.supplier_id, sp.product_id, s.name AS supplier_name, p.name AS product_name
             FROM supplier_price_history h
             INNER JOIN supplier_products sp ON sp.id = h.supplier_product_id
             INNER JOIN suppliers s ON s.id = sp.supplier_id
             INNER JOIN products p ON p.id = sp.product_id
             ORDER BY h.id DESC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function highValueItems(int $limit = 8): array
    {
        return $this->rows(
            "SELECT i.*, p.name AS product_name, (i.remaining_quantity * i.unit_cost) AS line_value
             FROM inventory_items i
             INNER JOIN products p ON p.id = i.product_id
             WHERE i.status IN ('AVAILABLE', 'RESERVED') AND i.remaining_quantity > 0
             ORDER BY line_value DESC
             LIMIT " . (int) $limit
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mostUsed(int $limit = 8): array
    {
        return $this->rows(
            "SELECT p.id, p.name, p.sku, COALESCE(SUM(CASE WHEN m.quantity < 0 THEN -m.quantity ELSE 0 END), 0) AS used_qty
             FROM stock_movements m
             INNER JOIN products p ON p.id = m.product_id
             WHERE m.movement_type IN ('JOB_CONSUMPTION', 'WASTE', 'DAMAGE', 'OFFCUT_CONSUMED')
             GROUP BY p.id
             ORDER BY used_qty DESC
             LIMIT " . (int) $limit
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function wasteByProduct(): array
    {
        return $this->rows(
            "SELECT p.id, p.name, p.sku,
                    COALESCE(SUM(CASE WHEN u.usage_type = 'PRODUCTION' THEN u.quantity ELSE 0 END), 0) AS production_qty,
                    COALESCE(SUM(CASE WHEN u.usage_type IN ('WASTE', 'REWORK', 'DAMAGE', 'TEST_PRINT') THEN u.quantity ELSE 0 END), 0) AS waste_qty,
                    COALESCE(SUM(CASE WHEN u.usage_type IN ('WASTE', 'REWORK', 'DAMAGE', 'TEST_PRINT') THEN u.total_cost ELSE 0 END), 0) AS waste_value
             FROM job_material_usage u
             INNER JOIN products p ON p.id = u.product_id
             GROUP BY p.id
             HAVING waste_qty > 0
             ORDER BY waste_value DESC
             LIMIT 50"
        );
    }

    public function codeTaken(string $code): bool
    {
        return $this->one('SELECT id FROM inventory_items WHERE inventory_code = ? LIMIT 1', [$code]) !== null;
    }
}

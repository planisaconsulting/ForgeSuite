<?php

declare(strict_types=1);

namespace App\Repositories;

final class ProductRepository extends Repository
{
    private const SELECT = 'SELECT p.*, c.name AS category_name, s.name AS supplier_name, u.name AS created_by_name
        FROM products p
        INNER JOIN product_categories c ON c.id = p.category_id
        LEFT JOIN suppliers s ON s.id = p.supplier_id
        LEFT JOIN users u ON u.id = p.created_by';

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one(self::SELECT . ' WHERE p.id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, string $status, ?int $categoryId, int $limit = 100): array
    {
        $sql = self::SELECT . ' WHERE 1 = 1';
        $params = [];
        if ($status === 'active') {
            $sql .= ' AND p.active = 1';
        } elseif ($status === 'inactive') {
            $sql .= ' AND p.active = 0';
        }
        if ($categoryId !== null && $categoryId > 0) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($term !== '') {
            $like = like_term($term);
            $sql .= ' AND (p.name LIKE ? ESCAPE \'\\\\\' OR p.sku LIKE ? ESCAPE \'\\\\\'
                      OR p.supplier_code LIKE ? ESCAPE \'\\\\\' OR s.name LIKE ? ESCAPE \'\\\\\')';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY p.active DESC, p.name LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * Active products for the calculator. Cost is loaded again when pricing.
     *
     * @return list<array<string, mixed>>
     */
    public function calculatorCatalogue(): array
    {
        return $this->rows(
            'SELECT p.id, p.category_id, p.sku, p.name, p.pricing_method, p.cost_unit,
                    p.roll_width_mm, p.sheet_width_mm, p.sheet_height_mm,
                    p.standard_waste_percent, p.waste_threshold_percent, p.default_waste_policy,
                    p.allow_rotation, p.allow_nesting
             FROM products p
             WHERE p.active = 1
             ORDER BY p.name'
        );
    }

    public function skuTaken(string $sku, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM products WHERE sku = ?';
        $params = [$sku];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return $this->one($sql . ' LIMIT 1', $params) !== null;
    }

    public function countActive(): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM products WHERE active = 1');

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO products (
                category_id, supplier_id, sku, name, description, product_type, pricing_method,
                cost_price, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
                standard_waste_percent, waste_threshold_percent, default_waste_policy,
                allow_rotation, allow_nesting, track_stock, minimum_stock_level, supplier_code,
                active, notes, created_by
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?
             )',
            $this->values($data)
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $params = $this->values($data);
        array_pop($params);
        $params[] = $id;
        $this->run(
            'UPDATE products SET
                category_id = ?, supplier_id = ?, sku = ?, name = ?, description = ?, product_type = ?,
                pricing_method = ?, cost_price = ?, cost_unit = ?, roll_width_mm = ?, sheet_width_mm = ?,
                sheet_height_mm = ?, standard_waste_percent = ?, waste_threshold_percent = ?,
                default_waste_policy = ?, allow_rotation = ?, allow_nesting = ?, track_stock = ?,
                minimum_stock_level = ?, supplier_code = ?, active = ?, notes = ?
             WHERE id = ?',
            $params
        );
    }

    public function setActive(int $id, int $active): void
    {
        $this->run('UPDATE products SET active = ? WHERE id = ?', [$active, $id]);
    }

    public function addPriceHistory(int $productId, string $oldCost, string $newCost, ?int $userId): void
    {
        $this->run(
            'INSERT INTO product_price_history (product_id, old_cost, new_cost, changed_by)
             VALUES (?, ?, ?, ?)',
            [$productId, $oldCost, $newCost, $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function priceHistory(int $productId): array
    {
        return $this->rows(
            'SELECT h.id, h.old_cost, h.new_cost, h.changed_at, u.name AS changed_by_name
             FROM product_price_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.product_id = ?
             ORDER BY h.id DESC',
            [$productId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentPriceChanges(int $limit = 8): array
    {
        return $this->rows(
            'SELECT h.id, h.product_id, h.old_cost, h.new_cost, h.changed_at,
                    p.name AS product_name, p.sku, u.name AS changed_by_name
             FROM product_price_history h
             INNER JOIN products p ON p.id = h.product_id
             LEFT JOIN users u ON u.id = h.changed_by
             ORDER BY h.id DESC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    private function values(array $data): array
    {
        return [
            $data['category_id'], $data['supplier_id'], $data['sku'], $data['name'], $data['description'],
            $data['product_type'], $data['pricing_method'], $data['cost_price'], $data['cost_unit'],
            $data['roll_width_mm'], $data['sheet_width_mm'], $data['sheet_height_mm'],
            $data['standard_waste_percent'], $data['waste_threshold_percent'], $data['default_waste_policy'],
            $data['allow_rotation'], $data['allow_nesting'], $data['track_stock'], $data['minimum_stock_level'],
            $data['supplier_code'], $data['active'], $data['notes'], $data['created_by'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

final class RecipeRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->rows('SELECT * FROM recipe_categories ORDER BY sort_order, name');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(string $term = '', bool $activeOnly = false): array
    {
        $sql = 'SELECT r.*, c.name AS category_name, p.name AS product_name
                FROM recipes r
                LEFT JOIN recipe_categories c ON c.id = r.category_id
                LEFT JOIN products p ON p.id = r.finished_product_id
                WHERE 1 = 1';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND r.active = 1';
        }
        if ($term !== '') {
            $sql .= ' AND (r.name LIKE ? OR r.code LIKE ?)';
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
        }
        $sql .= ' ORDER BY r.name ASC';

        return $this->rows($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT r.*, c.name AS category_name FROM recipes r
             LEFT JOIN recipe_categories c ON c.id = r.category_id
             WHERE r.id = ? LIMIT 1',
            [$id]
        );
    }

    public function findByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM recipes WHERE code = ? LIMIT 1', [$code]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO recipes (
                code, name, description, category_id, finished_product_id, recipe_type, pricing_method,
                production_route_template_id, active, version_number, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['code'], $data['name'], $data['description'], $data['category_id'],
                $data['finished_product_id'], $data['recipe_type'], $data['pricing_method'],
                $data['production_route_template_id'], $data['active'], $data['version_number'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->run(
            'UPDATE recipes SET code = ?, name = ?, description = ?, category_id = ?, finished_product_id = ?,
                recipe_type = ?, pricing_method = ?, production_route_template_id = ?, active = ?, version_number = ?
             WHERE id = ?',
            [
                $data['code'], $data['name'], $data['description'], $data['category_id'],
                $data['finished_product_id'], $data['recipe_type'], $data['pricing_method'],
                $data['production_route_template_id'], $data['active'], $data['version_number'], $id,
            ]
        );
    }

    public function setActive(int $id, int $active): void
    {
        $this->run('UPDATE recipes SET active = ? WHERE id = ?', [$active, $id]);
    }

    public function setVersion(int $id, int $version): void
    {
        $this->run('UPDATE recipes SET version_number = ? WHERE id = ?', [$version, $id]);
    }

    public function setItemWaste(int $recipeId, int $itemId, string $waste): void
    {
        $this->run(
            'UPDATE recipe_items SET waste_percent_override = ? WHERE id = ? AND recipe_id = ?',
            [$waste, $itemId, $recipeId]
        );
    }

    public function setItemFormula(int $recipeId, int $itemId, string $formula): void
    {
        $this->run(
            'UPDATE recipe_items SET quantity_formula = ? WHERE id = ? AND recipe_id = ?',
            [$formula, $itemId, $recipeId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inputs(int $recipeId): array
    {
        return $this->rows('SELECT * FROM recipe_inputs WHERE recipe_id = ? ORDER BY sort_order, id', [$recipeId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $recipeId): array
    {
        return $this->rows(
            'SELECT i.*, p.name AS product_name, p.cost_price, p.cost_unit, p.standard_waste_percent,
                    p.roll_width_mm, p.sheet_width_mm, p.sheet_height_mm, p.pricing_method AS product_pricing_method
             FROM recipe_items i
             LEFT JOIN products p ON p.id = i.product_id
             WHERE i.recipe_id = ? ORDER BY i.sort_order, i.id',
            [$recipeId]
        );
    }

    public function replaceChildren(int $recipeId, array $inputs, array $items): void
    {
        $this->run('DELETE FROM recipe_inputs WHERE recipe_id = ?', [$recipeId]);
        $this->run('DELETE FROM recipe_items WHERE recipe_id = ?', [$recipeId]);
        foreach ($inputs as $row) {
            $this->run(
                'INSERT INTO recipe_inputs (
                    recipe_id, code, label, input_type, unit, required, default_value, min_value, max_value, options_json, sort_order
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $recipeId, $row['code'], $row['label'], $row['input_type'], $row['unit'], $row['required'],
                    $row['default_value'], $row['min_value'], $row['max_value'], $row['options_json'], $row['sort_order'],
                ]
            );
        }
        foreach ($items as $row) {
            $this->run(
                'INSERT INTO recipe_items (
                    recipe_id, component_type, product_id, nested_recipe_id, description, quantity_formula,
                    waste_percent_override, unit, cost_calculation_method, rounding_rule, pack_size, yield_mode,
                    condition_json, sort_order, optional
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $recipeId, $row['component_type'], $row['product_id'], $row['nested_recipe_id'], $row['description'],
                    $row['quantity_formula'], $row['waste_percent_override'], $row['unit'], $row['cost_calculation_method'],
                    $row['rounding_rule'], $row['pack_size'], $row['yield_mode'], $row['condition_json'],
                    $row['sort_order'], $row['optional'],
                ]
            );
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function insertVersion(int $recipeId, int $version, array $snapshot, ?int $userId): void
    {
        $this->run(
            'INSERT INTO recipe_versions (recipe_id, version_number, snapshot_json, created_by) VALUES (?, ?, ?, ?)',
            [$recipeId, $version, json_encode($snapshot, JSON_THROW_ON_ERROR), $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function versions(int $recipeId): array
    {
        return $this->rows(
            'SELECT id, recipe_id, version_number, created_at, created_by FROM recipe_versions
             WHERE recipe_id = ? ORDER BY version_number DESC',
            [$recipeId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(bool $activeOnly = false): array
    {
        $sql = 'SELECT t.*, r.name AS recipe_name, r.code AS recipe_code
                FROM signage_templates t
                LEFT JOIN recipes r ON r.id = t.recipe_id';
        if ($activeOnly) {
            $sql .= ' WHERE t.active = 1';
        }
        $sql .= ' ORDER BY t.name';

        return $this->rows($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function template(int $id): ?array
    {
        return $this->one('SELECT * FROM signage_templates WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTemplate(array $data): int
    {
        $this->run(
            'INSERT INTO signage_templates (
                code, name, category, finished_product_id, recipe_id, default_inputs_json,
                customer_description, internal_description, notes, production_route_template_id, active, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['code'], $data['name'], $data['category'], $data['finished_product_id'], $data['recipe_id'],
                $data['default_inputs_json'], $data['customer_description'], $data['internal_description'],
                $data['notes'], $data['production_route_template_id'], $data['active'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTemplate(int $id, array $data): void
    {
        $this->run(
            'UPDATE signage_templates SET code = ?, name = ?, category = ?, finished_product_id = ?, recipe_id = ?,
                default_inputs_json = ?, customer_description = ?, internal_description = ?, notes = ?,
                production_route_template_id = ?, active = ? WHERE id = ?',
            [
                $data['code'], $data['name'], $data['category'], $data['finished_product_id'], $data['recipe_id'],
                $data['default_inputs_json'], $data['customer_description'], $data['internal_description'],
                $data['notes'], $data['production_route_template_id'], $data['active'], $id,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertSnapshot(int $quoteItemId, array $data): void
    {
        $this->run(
            'INSERT INTO quote_recipe_snapshots (
                quote_item_id, recipe_id, recipe_version, input_snapshot_json, component_snapshot_json,
                cost_snapshot_json, production_route_snapshot_json
             ) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $quoteItemId, $data['recipe_id'], $data['recipe_version'],
                json_encode($data['inputs'], JSON_THROW_ON_ERROR),
                json_encode($data['components'], JSON_THROW_ON_ERROR),
                json_encode($data['cost'], JSON_THROW_ON_ERROR),
                json_encode($data['route'], JSON_THROW_ON_ERROR),
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function snapshotForItem(int $quoteItemId): ?array
    {
        $row = $this->one('SELECT * FROM quote_recipe_snapshots WHERE quote_item_id = ? LIMIT 1', [$quoteItemId]);
        if ($row === null) {
            return null;
        }
        foreach (['input_snapshot_json', 'component_snapshot_json', 'cost_snapshot_json', 'production_route_snapshot_json'] as $key) {
            $decoded = json_decode((string) ($row[$key] ?? 'null'), true);
            $row[$key] = is_array($decoded) ? $decoded : [];
        }

        return $row;
    }

    public function copySnapshot(int $fromItemId, int $toItemId): void
    {
        $row = $this->one('SELECT * FROM quote_recipe_snapshots WHERE quote_item_id = ? LIMIT 1', [$fromItemId]);
        if ($row === null) {
            return;
        }
        $this->run(
            'INSERT INTO quote_recipe_snapshots (
                quote_item_id, recipe_id, recipe_version, input_snapshot_json, component_snapshot_json,
                cost_snapshot_json, production_route_snapshot_json
             ) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $toItemId, $row['recipe_id'], $row['recipe_version'], $row['input_snapshot_json'],
                $row['component_snapshot_json'], $row['cost_snapshot_json'], $row['production_route_snapshot_json'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term): array
    {
        return $this->all($term, false);
    }
}

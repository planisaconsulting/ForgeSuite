<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Estimates, yield history, and pricing recommendations.
 *
 * Report queries are limited. A screen does not scan every completed job.
 */
final class EstimatingRepository extends Repository
{
    /**
     * @param array<string, mixed> $data
     */
    public function insertEstimate(array $data): int
    {
        $this->run(
            'INSERT INTO estimates (
                estimate_number, revision_number, customer_id, opportunity_id, quote_id, job_id,
                recipe_id, recipe_version, estimate_type, status, subtotal_cost, material_cost,
                labour_cost, machine_cost, installation_cost, travel_cost, subcontract_cost, other_cost,
                recommended_sell_price, expected_gross_profit, expected_margin, confidence_basis,
                snapshot_json, notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['estimate_number'], $data['revision_number'], $data['customer_id'], $data['opportunity_id'],
                $data['quote_id'], $data['job_id'], $data['recipe_id'], $data['recipe_version'],
                $data['estimate_type'], $data['status'], $data['subtotal_cost'], $data['material_cost'],
                $data['labour_cost'], $data['machine_cost'], $data['installation_cost'], $data['travel_cost'],
                $data['subcontract_cost'], $data['other_cost'], $data['recommended_sell_price'],
                $data['expected_gross_profit'], $data['expected_margin'], $data['confidence_basis'],
                $data['snapshot_json'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateEstimate(int $id, array $data): void
    {
        $this->run(
            'UPDATE estimates SET revision_number = ?, customer_id = ?, opportunity_id = ?, quote_id = ?, job_id = ?,
                recipe_id = ?, recipe_version = ?, estimate_type = ?, status = ?, subtotal_cost = ?, material_cost = ?,
                labour_cost = ?, machine_cost = ?, installation_cost = ?, travel_cost = ?, subcontract_cost = ?,
                other_cost = ?, recommended_sell_price = ?, expected_gross_profit = ?, expected_margin = ?,
                confidence_basis = ?, snapshot_json = ?, notes = ? WHERE id = ?',
            [
                $data['revision_number'], $data['customer_id'], $data['opportunity_id'], $data['quote_id'], $data['job_id'],
                $data['recipe_id'], $data['recipe_version'], $data['estimate_type'], $data['status'],
                $data['subtotal_cost'], $data['material_cost'], $data['labour_cost'], $data['machine_cost'],
                $data['installation_cost'], $data['travel_cost'], $data['subcontract_cost'], $data['other_cost'],
                $data['recommended_sell_price'], $data['expected_gross_profit'], $data['expected_margin'],
                $data['confidence_basis'], $data['snapshot_json'], $data['notes'], $id,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT e.*, c.company_name, q.quote_number, j.job_number, r.name AS recipe_name
             FROM estimates e
             LEFT JOIN customers c ON c.id = e.customer_id
             LEFT JOIN quotes q ON q.id = e.quote_id
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN recipes r ON r.id = e.recipe_id
             WHERE e.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term): array
    {
        $sql = 'SELECT e.id, e.estimate_number, e.estimate_type, e.status, e.subtotal_cost, e.expected_margin, e.updated_at,
                       c.company_name, q.quote_number, j.job_number
                FROM estimates e
                LEFT JOIN customers c ON c.id = e.customer_id
                LEFT JOIN quotes q ON q.id = e.quote_id
                LEFT JOIN jobs j ON j.id = e.job_id
                WHERE 1 = 1';
        $params = [];
        if ($term !== '') {
            $sql .= ' AND (e.estimate_number LIKE ? OR c.company_name LIKE ? OR q.quote_number LIKE ? OR j.job_number LIKE ? OR e.notes LIKE ?)';
            $like = '%' . $term . '%';
            $params = [$like, $like, $like, $like, $like];
        }
        $sql .= ' ORDER BY e.id DESC LIMIT 80';

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function components(int $estimateId): array
    {
        return $this->rows('SELECT * FROM estimate_components WHERE estimate_id = ? ORDER BY id', [$estimateId]);
    }

    public function deleteComponents(int $estimateId): void
    {
        $this->run('DELETE FROM estimate_components WHERE estimate_id = ?', [$estimateId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertComponent(array $row): void
    {
        $this->run(
            'INSERT INTO estimate_components (
                estimate_id, component_type, product_id, recipe_item_id, description, billable_quantity,
                estimated_quantity, actual_quantity, unit, unit_cost_snapshot, estimated_cost, calculation_method,
                calculation_details_json, manual_override, override_reason
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $row['estimate_id'], $row['component_type'], $row['product_id'], $row['recipe_item_id'],
                $row['description'], $row['billable_quantity'], $row['estimated_quantity'], $row['actual_quantity'],
                $row['unit'], $row['unit_cost_snapshot'], $row['estimated_cost'], $row['calculation_method'],
                $row['calculation_details_json'], $row['manual_override'], $row['override_reason'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function insertRevision(int $estimateId, int $revision, array $snapshot, int $userId): void
    {
        $this->run(
            'INSERT INTO estimate_revisions (estimate_id, revision_number, snapshot_json, created_by) VALUES (?, ?, ?, ?)',
            [$estimateId, $revision, json_encode($snapshot, JSON_THROW_ON_ERROR), $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function availableOffcuts(int $productId): array
    {
        return $this->rows(
            'SELECT id, inventory_code, width_mm, length_mm AS height_mm, stock_location_id, unit_cost
             FROM inventory_items
             WHERE product_id = ? AND inventory_type = \'OFFCUT\' AND status = \'AVAILABLE\'
             ORDER BY id DESC LIMIT 40',
            [$productId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rolls(int $productId): array
    {
        return $this->rows(
            'SELECT id, inventory_code, width_mm, length_mm, unit_cost
             FROM inventory_items
             WHERE product_id = ? AND inventory_type = \'ROLL\' AND status = \'AVAILABLE\' AND width_mm IS NOT NULL
             ORDER BY width_mm LIMIT 12',
            [$productId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accessLevels(): array
    {
        return $this->rows('SELECT * FROM installation_access_levels ORDER BY id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function accessLevel(string $code): ?array
    {
        return $this->one('SELECT * FROM installation_access_levels WHERE code = ? LIMIT 1', [$code]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function heightCategories(): array
    {
        return $this->rows('SELECT * FROM installation_height_categories ORDER BY id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function heightCategory(string $code): ?array
    {
        return $this->one('SELECT * FROM installation_height_categories WHERE code = ? LIMIT 1', [$code]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRecommendation(array $data): int
    {
        $this->run(
            'INSERT INTO pricing_recommendations (
                recommendation_type, recipe_id, recipe_item_id, product_id, current_value, suggested_value,
                sample_size, confidence_basis, reason, statistic_json, status
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['recommendation_type'], $data['recipe_id'], $data['recipe_item_id'], $data['product_id'],
                $data['current_value'], $data['suggested_value'], $data['sample_size'], $data['confidence_basis'],
                $data['reason'], $data['statistic_json'], $data['status'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recommendations(string $status = ''): array
    {
        $sql = 'SELECT p.*, r.name AS recipe_name FROM pricing_recommendations p
                LEFT JOIN recipes r ON r.id = p.recipe_id WHERE 1 = 1';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY p.id DESC LIMIT 50';

        return $this->rows($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recommendation(int $id): ?array
    {
        return $this->one('SELECT * FROM pricing_recommendations WHERE id = ? LIMIT 1', [$id]);
    }

    public function reviewRecommendation(int $id, string $status, int $userId): void
    {
        $this->run(
            'UPDATE pricing_recommendations SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
            [$status, $userId, $id]
        );
    }

    /**
     * @param array<string, mixed> $warnings
     */
    public function insertRisk(array $warnings, string $level, ?int $quoteId, ?int $estimateId, ?string $reason, ?int $userId): void
    {
        $this->run(
            'INSERT INTO quote_risk_reviews (quote_id, estimate_id, level, warnings_json, override_reason, override_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$quoteId, $estimateId, $level, json_encode($warnings, JSON_THROW_ON_ERROR), $reason, $userId]
        );
    }

    /**
     * @return array<string, string>
     */
    public function jobMoney(int $jobId): array
    {
        $material = $this->one('SELECT COALESCE(SUM(total_cost), 0) AS amount FROM job_material_usage WHERE job_id = ?', [$jobId]);
        $labour = $this->one('SELECT COALESCE(SUM(total_cost), 0) AS amount, COALESCE(SUM(minutes), 0) AS minutes FROM job_time_entries WHERE job_id = ?', [$jobId]);
        $other = $this->one('SELECT COALESCE(SUM(total_cost), 0) AS amount FROM job_other_costs WHERE job_id = ?', [$jobId]);
        $reserved = $this->one(
            'SELECT COALESCE(SUM(r.quantity * COALESCE(i.unit_cost, p.cost_price, 0)), 0) AS amount
             FROM stock_reservations r
             LEFT JOIN inventory_items i ON i.id = r.inventory_item_id
             LEFT JOIN products p ON p.id = r.product_id
             WHERE r.job_id = ? AND r.status = \'RESERVED\'',
            [$jobId]
        );
        $expected = $this->one(
            'SELECT COALESCE(SUM(expected_minutes), 0) AS minutes,
                    COALESCE(SUM(expected_minutes / 60 * hourly_cost_snapshot), 0) AS amount
             FROM job_expected_labour WHERE job_id = ?',
            [$jobId]
        );
        $required = $this->one(
            'SELECT COALESCE(SUM(r.final_required_quantity * COALESCE(p.cost_price, 0)), 0) AS amount,
                    COALESCE(SUM(r.final_required_quantity), 0) AS quantity
             FROM job_material_requirements r
             LEFT JOIN products p ON p.id = r.product_id
             WHERE r.job_id = ?',
            [$jobId]
        );
        $usedQty = $this->one('SELECT COALESCE(SUM(quantity), 0) AS quantity FROM job_material_usage WHERE job_id = ?', [$jobId]);

        return [
            'actual_material' => (string) ($material['amount'] ?? '0'),
            'actual_labour' => (string) ($labour['amount'] ?? '0'),
            'actual_labour_minutes' => (string) ($labour['minutes'] ?? '0'),
            'actual_other' => (string) ($other['amount'] ?? '0'),
            'committed_material' => (string) ($reserved['amount'] ?? '0'),
            'estimated_labour' => (string) ($expected['amount'] ?? '0'),
            'estimated_labour_minutes' => (string) ($expected['minutes'] ?? '0'),
            'estimated_material' => (string) ($required['amount'] ?? '0'),
            'estimated_material_qty' => (string) ($required['quantity'] ?? '0'),
            'actual_material_qty' => (string) ($usedQty['quantity'] ?? '0'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function completedComparisons(int $limit = 80): array
    {
        return $this->rows(
            'SELECT j.id, j.job_number, j.quoted_cost_snapshot, j.quoted_revenue_snapshot, j.actual_total_cost,
                    j.completed_at, s.recipe_id, s.recipe_version, r.name AS recipe_name
             FROM jobs j
             LEFT JOIN quote_items qi ON qi.quote_id = j.quote_id
             LEFT JOIN quote_recipe_snapshots s ON s.quote_item_id = qi.id
             LEFT JOIN recipes r ON r.id = s.recipe_id
             WHERE j.status = \'COMPLETED\'
             GROUP BY j.id, j.job_number, j.quoted_cost_snapshot, j.quoted_revenue_snapshot, j.actual_total_cost,
                      j.completed_at, s.recipe_id, s.recipe_version, r.name
             ORDER BY j.id DESC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function similarJobs(int $recipeId, int $limit = 15): array
    {
        return $this->rows(
            'SELECT j.id, j.job_number, j.quoted_revenue_snapshot, j.quoted_cost_snapshot, j.actual_total_cost, j.status
             FROM jobs j
             INNER JOIN quote_items qi ON qi.quote_id = j.quote_id
             INNER JOIN quote_recipe_snapshots s ON s.quote_item_id = qi.id
             WHERE s.recipe_id = ? AND j.status = \'COMPLETED\'
             GROUP BY j.id, j.job_number, j.quoted_revenue_snapshot, j.quoted_cost_snapshot, j.actual_total_cost, j.status
             ORDER BY j.id DESC
             LIMIT ' . (int) $limit,
            [$recipeId]
        );
    }

    /**
     * @return array{age_days: int, move_percent: string, volatile: bool}
     */
    public function priceMovement(int $productId, int $days): array
    {
        $latest = $this->one(
            'SELECT DATEDIFF(CURDATE(), DATE(changed_at)) AS age_days, old_cost, new_cost
             FROM product_price_history WHERE product_id = ? ORDER BY id DESC LIMIT 1',
            [$productId]
        );
        $window = $this->rows(
            'SELECT old_cost, new_cost FROM product_price_history
             WHERE product_id = ? AND changed_at >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)
             ORDER BY id ASC LIMIT 20',
            [$productId]
        );
        $move = '0';
        if ($window !== []) {
            $first = (string) $window[0]['old_cost'];
            $last = (string) $window[count($window) - 1]['new_cost'];
            if ((float) $first > 0) {
                $move = (string) round(((float) $last - (float) $first) / (float) $first * 100, 2);
            }
        }

        return [
            'age_days' => (int) ($latest['age_days'] ?? 0),
            'move_percent' => $move,
            'volatile' => abs((float) $move) >= 5,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Sign specifications, vehicle templates, and saved estimator snapshots.
 */
final class SignageRepository extends Repository
{
    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function specifications(array $filters, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM sign_specifications WHERE 1=1';
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (code LIKE ? OR name LIKE ? OR category LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['estimator_type'] ?? '') !== '') {
            $sql .= ' AND estimator_type = ?';
            $params[] = $filters['estimator_type'];
        }
        if (!empty($filters['approved_only'])) {
            $sql .= " AND status = 'APPROVED' AND superseded_by_id IS NULL";
        }
        if (!empty($filters['engineering'])) {
            $sql .= ' AND engineering_review_required = 1';
        }
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return $this->rows($sql . ' ORDER BY code, version DESC LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function specification(int $id): ?array
    {
        return $this->one('SELECT * FROM sign_specifications WHERE id = ?', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentByCode(string $code): ?array
    {
        return $this->one(
            "SELECT * FROM sign_specifications WHERE code = ? AND status = 'APPROVED' AND superseded_by_id IS NULL ORDER BY version DESC LIMIT 1",
            [$code]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertSpecification(array $data): int
    {
        $this->run(
            'INSERT INTO sign_specifications (
                code, version, name, category, estimator_type, description, status, effective_from, effective_to,
                default_recipe_id, default_production_route_id, engineering_review_required, electrical_review_required,
                height_review_mm, max_width_mm, max_height_mm, max_post_height_mm, waste_percent, manufacturing_allowance_percent,
                construction_type, frame_profile, face_material, return_material, back_material, illumination_type,
                mounting_method, finishing, created_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['code'], $data['version'], $data['name'], $data['category'], $data['estimator_type'],
                $data['description'], $data['status'], $data['effective_from'], $data['effective_to'],
                $data['default_recipe_id'], $data['default_production_route_id'], $data['engineering_review_required'],
                $data['electrical_review_required'], $data['height_review_mm'], $data['max_width_mm'], $data['max_height_mm'],
                $data['max_post_height_mm'], $data['waste_percent'], $data['manufacturing_allowance_percent'],
                $data['construction_type'], $data['frame_profile'], $data['face_material'], $data['return_material'],
                $data['back_material'], $data['illumination_type'], $data['mounting_method'], $data['finishing'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateSpecification(int $id, array $data): void
    {
        $this->run(
            'UPDATE sign_specifications SET
                name = ?, category = ?, estimator_type = ?, description = ?, effective_from = ?, effective_to = ?,
                engineering_review_required = ?, electrical_review_required = ?, height_review_mm = ?, max_width_mm = ?,
                max_height_mm = ?, max_post_height_mm = ?, waste_percent = ?, manufacturing_allowance_percent = ?,
                construction_type = ?, frame_profile = ?, face_material = ?, return_material = ?, back_material = ?,
                illumination_type = ?, mounting_method = ?, finishing = ?
             WHERE id = ? AND status IN (\'DRAFT\', \'REVIEW\')',
            [
                $data['name'], $data['category'], $data['estimator_type'], $data['description'], $data['effective_from'],
                $data['effective_to'], $data['engineering_review_required'], $data['electrical_review_required'],
                $data['height_review_mm'], $data['max_width_mm'], $data['max_height_mm'], $data['max_post_height_mm'],
                $data['waste_percent'], $data['manufacturing_allowance_percent'], $data['construction_type'],
                $data['frame_profile'], $data['face_material'], $data['return_material'], $data['back_material'],
                $data['illumination_type'], $data['mounting_method'], $data['finishing'], $id,
            ]
        );
    }

    public function markApproved(int $id, int $userId): void
    {
        $this->run(
            "UPDATE sign_specifications SET status = 'APPROVED', approved_by = ?, approved_at = ? WHERE id = ?",
            [$userId, date('Y-m-d H:i:s'), $id]
        );
    }

    public function markSuperseded(int $id, int $successorId): void
    {
        $this->run(
            "UPDATE sign_specifications SET status = 'SUPERSEDED', superseded_by_id = ? WHERE id = ?",
            [$successorId, $id]
        );
    }

    public function nextVersion(string $code): int
    {
        $row = $this->one('SELECT MAX(version) AS version FROM sign_specifications WHERE code = ?', [$code]);

        return (int) ($row['version'] ?? 0) + 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materials(int $specificationId): array
    {
        return $this->rows('SELECT * FROM specification_materials WHERE specification_id = ? ORDER BY id', [$specificationId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function components(int $specificationId): array
    {
        return $this->rows('SELECT * FROM specification_components WHERE specification_id = ? ORDER BY id', [$specificationId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function labour(int $specificationId): array
    {
        return $this->rows('SELECT * FROM specification_labour WHERE specification_id = ? ORDER BY id', [$specificationId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function operations(int $specificationId): array
    {
        return $this->rows('SELECT * FROM specification_operations WHERE specification_id = ? ORDER BY sequence_no', [$specificationId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rules(int $specificationId): array
    {
        return $this->rows('SELECT * FROM specification_rules WHERE specification_id = ? AND active = 1 ORDER BY id', [$specificationId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notes(int $specificationId): array
    {
        return $this->rows('SELECT * FROM specification_notes WHERE specification_id = ? ORDER BY id', [$specificationId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertMaterial(array $row): void
    {
        $this->run(
            'INSERT INTO specification_materials (specification_id, role_code, product_id, description, thickness_mm, quantity_formula, unit_code, waste_percent, notes)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$row['specification_id'], $row['role_code'], $row['product_id'], $row['description'], $row['thickness_mm'], $row['quantity_formula'], $row['unit_code'], $row['waste_percent'], $row['notes']]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertComponent(array $row): void
    {
        $this->run(
            'INSERT INTO specification_components (specification_id, role_code, product_id, electrical_profile_id, description, quantity_formula, notes)
             VALUES (?,?,?,?,?,?,?)',
            [$row['specification_id'], $row['role_code'], $row['product_id'], $row['electrical_profile_id'], $row['description'], $row['quantity_formula'], $row['notes']]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertLabour(array $row): void
    {
        $this->run(
            'INSERT INTO specification_labour (specification_id, operation_code, description, minutes_formula, hourly_rate, installer_count)
             VALUES (?,?,?,?,?,?)',
            [$row['specification_id'], $row['operation_code'], $row['description'], $row['minutes_formula'], $row['hourly_rate'], $row['installer_count']]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertOperation(array $row): void
    {
        $this->run(
            'INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
             VALUES (?,?,?,?,?,?,?)',
            [$row['specification_id'], $row['sequence_no'], $row['operation_code'], $row['description'], $row['setup_minutes'], $row['run_minutes'], $row['hourly_rate']]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertRule(array $row): void
    {
        $this->run(
            'INSERT INTO specification_rules (specification_id, rule_type, hardness, when_key, when_op, when_value, and_key, and_op, and_value, then_key, then_value, message)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['specification_id'], $row['rule_type'], $row['hardness'], $row['when_key'], $row['when_op'], $row['when_value'],
                $row['and_key'], $row['and_op'], $row['and_value'], $row['then_key'], $row['then_value'], $row['message'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertNote(array $row): void
    {
        $this->run(
            'INSERT INTO specification_notes (specification_id, note_type, body, customer_visible) VALUES (?,?,?,?)',
            [$row['specification_id'], $row['note_type'], $row['body'], $row['customer_visible']]
        );
    }

    public function deleteChildren(int $specificationId): void
    {
        foreach (['specification_materials', 'specification_components', 'specification_labour', 'specification_operations', 'specification_rules', 'specification_notes'] as $table) {
            $this->run('DELETE FROM ' . $table . ' WHERE specification_id = ?', [$specificationId]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function profile(int $id): ?array
    {
        return $this->one('SELECT * FROM electrical_component_profiles WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function profiles(?string $kind = null): array
    {
        if ($kind === null) {
            return $this->rows('SELECT * FROM electrical_component_profiles WHERE active = 1 ORDER BY name');
        }

        return $this->rows('SELECT * FROM electrical_component_profiles WHERE active = 1 AND kind = ? ORDER BY name', [$kind]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertProfile(array $data): int
    {
        $this->run(
            'INSERT INTO electrical_component_profiles (
                code, name, kind, product_id, module_watts, rated_watts, max_load_percent, spacing_mm, modules_per_m2,
                modules_per_letter, max_chain, voltage, method_code, environmental_rating, notes
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['code'], $data['name'], $data['kind'], $data['product_id'], $data['module_watts'], $data['rated_watts'],
                $data['max_load_percent'], $data['spacing_mm'], $data['modules_per_m2'], $data['modules_per_letter'],
                $data['max_chain'], $data['voltage'], $data['method_code'], $data['environmental_rating'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(bool $verifiedOnly = false): array
    {
        $sql = 'SELECT * FROM vehicle_templates WHERE superseded_by_id IS NULL';
        if ($verifiedOnly) {
            $sql .= ' AND verified = 1';
        }

        return $this->rows($sql . ' ORDER BY make_name, model_name, year_from');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function template(int $id): ?array
    {
        return $this->one('SELECT * FROM vehicle_templates WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTemplate(array $data): int
    {
        $this->run(
            'INSERT INTO vehicle_templates (make_name, model_name, variant_name, year_from, year_to, body_type, notes, source, verified, version)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $data['make_name'], $data['model_name'], $data['variant_name'], $data['year_from'], $data['year_to'],
                $data['body_type'], $data['notes'], $data['source'], $data['verified'], $data['version'],
            ]
        );

        return $this->insertId();
    }

    public function verifyTemplate(int $id, bool $verified): void
    {
        $this->run('UPDATE vehicle_templates SET verified = ? WHERE id = ?', [$verified ? 1 : 0, $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function panels(int $templateId): array
    {
        return $this->rows('SELECT * FROM vehicle_template_panels WHERE template_id = ? ORDER BY panel_code', [$templateId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertPanel(array $row): void
    {
        $this->run(
            'INSERT INTO vehicle_template_panels (template_id, panel_code, width_mm, height_mm, area_m2, complexity_factor, bleed_mm, overlap_mm, orientation_locked, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $row['template_id'], $row['panel_code'], $row['width_mm'], $row['height_mm'], $row['area_m2'],
                $row['complexity_factor'], $row['bleed_mm'], $row['overlap_mm'], $row['orientation_locked'], $row['notes'],
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function product(int $id): ?array
    {
        return $this->one(
            'SELECT id, name, sku, cost_price, pricing_method, cost_unit, roll_width_mm, sheet_width_mm, sheet_height_mm,
                    allow_rotation, direction_sensitive, kerf_mm, sheet_edge_margin_mm, horizontal_spacing_mm,
                    vertical_spacing_mm, print_edge_margin_mm, thickness_mm, density_kg_m3, standard_waste_percent, active
             FROM products WHERE id = ?',
            [$id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCalculation(array $data): int
    {
        $this->run(
            'INSERT INTO sign_calculations (
                estimate_id, parent_calculation_id, estimator_type, specification_id, specification_code, specification_version,
                customer_id, project_id, project_site_id, quote_id, job_id, status, outside_specification, technical_review_required,
                input_json, output_json, input_hash, material_cost, labour_cost, machine_cost, installation_cost, total_cost,
                sell_price, gross_profit, margin_percent, created_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['estimate_id'], $data['parent_calculation_id'], $data['estimator_type'], $data['specification_id'],
                $data['specification_code'], $data['specification_version'], $data['customer_id'], $data['project_id'],
                $data['project_site_id'], $data['quote_id'], $data['job_id'], $data['status'], $data['outside_specification'],
                $data['technical_review_required'], $data['input_json'], $data['output_json'], $data['input_hash'],
                $data['material_cost'], $data['labour_cost'], $data['machine_cost'], $data['installation_cost'], $data['total_cost'],
                $data['sell_price'], $data['gross_profit'], $data['margin_percent'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function calculation(int $id): ?array
    {
        return $this->one('SELECT * FROM sign_calculations WHERE id = ?', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function calculationForQuote(int $quoteId): ?array
    {
        return $this->one('SELECT * FROM sign_calculations WHERE quote_id = ? ORDER BY id DESC LIMIT 1', [$quoteId]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function calculations(array $filters, int $limit = 50): array
    {
        $sql = 'SELECT id, estimator_type, specification_code, specification_version, status, outside_specification, technical_review_required, total_cost, sell_price, created_at FROM sign_calculations WHERE 1=1';
        $params = [];
        if (($filters['estimator_type'] ?? '') !== '') {
            $sql .= ' AND estimator_type = ?';
            $params[] = $filters['estimator_type'];
        }
        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND status = ?';
            $params[] = $filters['status'];
        }
        $limit = max(1, min(100, $limit));

        return $this->rows($sql . ' ORDER BY id DESC LIMIT ' . $limit, $params);
    }

    public function setQuote(int $id, int $quoteId): void
    {
        $this->run("UPDATE sign_calculations SET quote_id = ?, status = 'QUOTED' WHERE id = ?", [$quoteId, $id]);
    }

    public function setJob(int $id, int $jobId): void
    {
        $this->run("UPDATE sign_calculations SET job_id = ?, status = 'CONVERTED' WHERE id = ?", [$jobId, $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE sign_calculations SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertOverride(array $row): void
    {
        $this->run(
            'INSERT INTO sign_calculation_overrides (calculation_id, field_key, calculated_value, overridden_value, reason, created_by)
             VALUES (?,?,?,?,?,?)',
            [$row['calculation_id'], $row['field_key'], $row['calculated_value'], $row['overridden_value'], $row['reason'], $row['created_by']]
        );
    }

    public function replaceOutput(int $id, string $outputJson, string $materialCost, string $totalCost, string $sell, string $profit, ?string $margin): void
    {
        $this->run(
            'UPDATE sign_calculations SET output_json = ?, material_cost = ?, total_cost = ?, sell_price = ?, gross_profit = ?, margin_percent = ? WHERE id = ? AND status NOT IN (\'QUOTED\', \'CONVERTED\')',
            [$outputJson, $materialCost, $totalCost, $sell, $profit, $margin, $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function geometryCache(string $key): ?array
    {
        $row = $this->one('SELECT geometry_json FROM geometry_cache WHERE cache_key = ?', [$key]);
        if ($row === null) {
            return null;
        }
        $decoded = json_decode((string) $row['geometry_json'], true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $geometry
     */
    public function storeGeometryCache(string $key, array $geometry): void
    {
        $json = json_encode($geometry, JSON_THROW_ON_ERROR);
        $this->run(
            'INSERT INTO geometry_cache (cache_key, estimator_version, geometry_json) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE geometry_json = VALUES(geometry_json)',
            [$key, \App\Services\SvgGeometry::VERSION, $json]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertGeometry(array $row): int
    {
        $this->run(
            'INSERT INTO geometry_analyses (calculation_id, original_name, byte_size, sha256, open_paths, duplicate_paths, area_mm2, perimeter_mm, sanitized_svg, warnings_json, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['calculation_id'], $row['original_name'], $row['byte_size'], $row['sha256'], $row['open_paths'],
                $row['duplicate_paths'], $row['area_mm2'], $row['perimeter_mm'], $row['sanitized_svg'], $row['warnings_json'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function stampJob(int $jobId, string $json): void
    {
        $this->run('UPDATE jobs SET technical_snapshot_json = ? WHERE id = ?', [$json, $jobId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jobTechnical(int $jobId): ?array
    {
        $row = $this->one('SELECT technical_snapshot_json FROM jobs WHERE id = ?', [$jobId]);
        if ($row === null || $row['technical_snapshot_json'] === null) {
            return null;
        }
        $decoded = json_decode((string) $row['technical_snapshot_json'], true);

        return is_array($decoded) ? $decoded : null;
    }

    public function stampAsset(int $assetId, ?int $specificationId, string $json): void
    {
        $this->run(
            'UPDATE customer_assets SET specification_id = ?, technical_snapshot_json = ? WHERE id = ?',
            [$specificationId, $json, $assetId]
        );
    }

    /**
     * @return array<string, int>
     */
    public function dashboard(): array
    {
        $rows = $this->rows('SELECT estimator_type, COUNT(*) AS total FROM sign_calculations GROUP BY estimator_type');
        $byType = [];
        foreach ($rows as $row) {
            $byType[(string) $row['estimator_type']] = (int) $row['total'];
        }
        $review = $this->one("SELECT COUNT(*) AS total FROM sign_calculations WHERE technical_review_required = 1 AND status = 'REVIEW'");
        $outside = $this->one('SELECT COUNT(*) AS total FROM sign_calculations WHERE outside_specification = 1');
        $specs = $this->rows(
            'SELECT specification_code, COUNT(*) AS total FROM sign_calculations WHERE specification_code IS NOT NULL GROUP BY specification_code ORDER BY total DESC LIMIT 8'
        );

        return [
            'by_type' => $byType,
            'review' => (int) ($review['total'] ?? 0),
            'outside' => (int) ($outside['total'] ?? 0),
            'specs' => $specs,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function varianceRows(): array
    {
        return $this->rows(
            "SELECT c.estimator_type, c.specification_code, c.specification_version, c.material_cost, c.labour_cost,
                    j.actual_material_cost, j.actual_labour_cost
             FROM sign_calculations c
             INNER JOIN jobs j ON j.id = c.job_id
             WHERE j.status = 'COMPLETED'"
        );
    }
}

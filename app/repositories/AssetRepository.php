<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * SQL for customer assets, warranties, service requests, and maintenance.
 * An asset is the physical item. It is not a product and not a job item.
 */
final class AssetRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function types(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM asset_types';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }

        return $this->rows($sql . ' ORDER BY name');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function type(int $id): ?array
    {
        return $this->one('SELECT * FROM asset_types WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function problemCategories(): array
    {
        return $this->rows('SELECT * FROM service_problem_categories WHERE active = 1 ORDER BY name');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one($this->select() . ' WHERE a.id = ?', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        return $this->one($this->select() . ' WHERE a.tracking_token = ? AND a.archived_at IS NULL', [$token]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumber(string $number): ?array
    {
        return $this->one($this->select() . ' WHERE a.asset_number = ?', [$number]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters, int $limit = 50, int $offset = 0): array
    {
        [$sql, $params] = $this->where($filters);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return $this->rows($sql . ' ORDER BY a.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset, $params);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO customer_assets (
                asset_number, customer_id, project_id, project_site_id, original_job_id, original_job_item_id,
                asset_type_id, name, description, manufacturer, model, serial_number, customer_asset_reference,
                source, status, health, quantity, track_mode, installation_date, commissioned_date,
                expected_service_interval_days, next_service_date, latitude, longitude, gps_accuracy_m, gps_captured_at,
                location_description, fleet_number, registration, vehicle_make, vehicle_model, vehicle_year,
                original_commercial_value, original_internal_cost, replaces_asset_id, tracking_token, created_by
             ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
             )',
            [
                $data['asset_number'], $data['customer_id'], $data['project_id'], $data['project_site_id'],
                $data['original_job_id'], $data['original_job_item_id'], $data['asset_type_id'], $data['name'],
                $data['description'], $data['manufacturer'], $data['model'], $data['serial_number'],
                $data['customer_asset_reference'], $data['source'], $data['status'], $data['health'],
                $data['quantity'], $data['track_mode'], $data['installation_date'], $data['commissioned_date'],
                $data['expected_service_interval_days'], $data['next_service_date'], $data['latitude'],
                $data['longitude'], $data['gps_accuracy_m'], $data['gps_captured_at'], $data['location_description'],
                $data['fleet_number'], $data['registration'], $data['vehicle_make'], $data['vehicle_model'],
                $data['vehicle_year'], $data['original_commercial_value'], $data['original_internal_cost'],
                $data['replaces_asset_id'], $data['tracking_token'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, int $version): int
    {
        $this->run(
            'UPDATE customer_assets SET
                asset_type_id = ?, name = ?, description = ?, manufacturer = ?, model = ?, serial_number = ?,
                customer_asset_reference = ?, status = ?, health = ?, quantity = ?, location_description = ?,
                installation_date = ?, commissioned_date = ?, next_service_date = ?, latitude = ?, longitude = ?,
                gps_accuracy_m = ?, gps_captured_at = ?, fleet_number = ?, registration = ?, vehicle_make = ?,
                vehicle_model = ?, vehicle_year = ?, project_site_id = ?, version = version + 1
             WHERE id = ? AND version = ?',
            [
                $data['asset_type_id'], $data['name'], $data['description'], $data['manufacturer'], $data['model'],
                $data['serial_number'], $data['customer_asset_reference'], $data['status'], $data['health'],
                $data['quantity'], $data['location_description'], $data['installation_date'], $data['commissioned_date'],
                $data['next_service_date'], $data['latitude'], $data['longitude'], $data['gps_accuracy_m'],
                $data['gps_captured_at'], $data['fleet_number'], $data['registration'], $data['vehicle_make'],
                $data['vehicle_model'], $data['vehicle_year'], $data['project_site_id'], $id, $version,
            ]
        );

        return $this->affected();
    }

    public function setStatus(int $id, string $status, string $health): void
    {
        $this->run(
            'UPDATE customer_assets SET status = ?, health = ?, version = version + 1 WHERE id = ?',
            [$status, $health, $id]
        );
    }

    public function linkReplacement(int $oldId, int $newId): void
    {
        $this->run('UPDATE customer_assets SET replacement_asset_id = ? WHERE id = ?', [$newId, $oldId]);
    }

    public function markRemoved(int $id, string $date, string $reason, ?string $disposition): void
    {
        $this->run(
            'UPDATE customer_assets SET status = \'REMOVED\', health = \'OUT_OF_SERVICE\', removed_at = ?, removal_reason = ?, disposition = ?, version = version + 1 WHERE id = ?',
            [$date, $reason, $disposition, $id]
        );
    }

    public function archive(int $id): void
    {
        $this->run(
            'UPDATE customer_assets SET status = \'ARCHIVED\', archived_at = NOW(), version = version + 1 WHERE id = ? AND archived_at IS NULL',
            [$id]
        );
    }

    public function move(int $id, int $customerId, ?int $siteId, ?string $location): void
    {
        $this->run(
            'UPDATE customer_assets SET customer_id = ?, project_site_id = ?, location_description = ?, version = version + 1 WHERE id = ?',
            [$customerId, $siteId, $location, $id]
        );
    }

    public function setServiceDates(int $id, ?string $last, ?string $next): void
    {
        $this->run(
            'UPDATE customer_assets SET last_service_date = ?, next_service_date = ? WHERE id = ?',
            [$last, $next, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId, int $limit = 100): array
    {
        return $this->rows(
            $this->select() . ' WHERE a.customer_id = ? AND a.archived_at IS NULL ORDER BY a.asset_number DESC LIMIT ' . max(1, min(200, $limit)),
            [$customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forProject(int $projectId, int $limit = 100): array
    {
        return $this->rows(
            $this->select() . ' WHERE a.project_id = ? AND a.archived_at IS NULL ORDER BY a.id DESC LIMIT ' . max(1, min(200, $limit)),
            [$projectId]
        );
    }

    public function countForProject(int $projectId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS total FROM customer_assets WHERE project_id = ? AND archived_at IS NULL',
            [$projectId]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forSite(int $siteId): array
    {
        return $this->rows(
            $this->select() . ' WHERE a.project_site_id = ? AND a.archived_at IS NULL ORDER BY a.asset_number',
            [$siteId]
        );
    }

    public function countForSite(int $siteId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS total FROM customer_assets WHERE project_site_id = ? AND archived_at IS NULL',
            [$siteId]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forJobItem(int $jobItemId): array
    {
        return $this->rows(
            'SELECT id, asset_number, quantity, track_mode FROM customer_assets WHERE original_job_item_id = ? AND archived_at IS NULL',
            [$jobItemId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jobItem(int $id): ?array
    {
        return $this->one(
            'SELECT ji.*, j.customer_id, j.project_id, j.project_site_id, j.job_number, j.installation_date AS job_installation_date,
                    j.title AS job_title, p.creates_customer_asset, p.name AS product_name, p.product_type
             FROM job_items ji
             JOIN jobs j ON j.id = ji.job_id
             LEFT JOIN products p ON p.id = ji.product_id
             WHERE ji.id = ?',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function eligibleItems(int $jobId): array
    {
        return $this->rows(
            'SELECT ji.id, ji.description, ji.quantity, ji.production_status, p.name AS product_name
             FROM job_items ji
             JOIN products p ON p.id = ji.product_id
             WHERE ji.job_id = ? AND p.creates_customer_asset = 1 AND ji.production_status = \'COMPLETE\'
               AND NOT EXISTS (
                    SELECT 1 FROM customer_assets a
                    WHERE a.original_job_item_id = ji.id AND a.archived_at IS NULL
               )
             ORDER BY ji.sort_order, ji.id',
            [$jobId]
        );
    }

    /**
     * @return list<int>
     */
    public function duplicateIds(int $customerId, ?int $siteId, ?string $reference, ?string $serial, ?int $jobItemId, ?int $ignoreId): array
    {
        $parts = [];
        $params = [];
        if ($reference !== null && $reference !== '') {
            $parts[] = '(customer_id = ? AND customer_asset_reference = ? AND (? = 0 OR project_site_id = ?))';
            array_push($params, $customerId, $reference, (int) ($siteId ?? 0), (int) ($siteId ?? 0));
        }
        if ($serial !== null && $serial !== '') {
            $parts[] = '(customer_id = ? AND serial_number = ?)';
            array_push($params, $customerId, $serial);
        }
        if ($jobItemId !== null && $jobItemId > 0) {
            $parts[] = 'original_job_item_id = ?';
            $params[] = $jobItemId;
        }
        if ($parts === []) {
            return [];
        }
        $sql = 'SELECT id FROM customer_assets WHERE archived_at IS NULL AND (' . implode(' OR ', $parts) . ')';
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $ids = [];
        foreach ($this->rows($sql . ' LIMIT 5', $params) as $row) {
            $ids[] = (int) $row['id'];
        }

        return $ids;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function components(int $assetId): array
    {
        return $this->rows(
            'SELECT * FROM asset_components WHERE asset_id = ? ORDER BY id',
            [$assetId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function component(int $id): ?array
    {
        return $this->one('SELECT * FROM asset_components WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertComponent(array $data): int
    {
        $this->run(
            'INSERT INTO asset_components (
                asset_id, component_type, product_id, inventory_item_id, description, manufacturer, model,
                serial_number, quantity, installed_date, warranty_start_date, warranty_end_date, status,
                replaces_component_id, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['asset_id'], $data['component_type'], $data['product_id'], $data['inventory_item_id'],
                $data['description'], $data['manufacturer'], $data['model'], $data['serial_number'],
                $data['quantity'], $data['installed_date'], $data['warranty_start_date'], $data['warranty_end_date'],
                $data['status'], $data['replaces_component_id'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    public function setComponentStatus(int $id, string $status): void
    {
        $this->run('UPDATE asset_components SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function warranties(int $assetId): array
    {
        return $this->rows('SELECT * FROM asset_warranties WHERE asset_id = ? ORDER BY end_date', [$assetId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function warranty(int $id): ?array
    {
        return $this->one('SELECT * FROM asset_warranties WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertWarranty(array $data): int
    {
        $this->run(
            'INSERT INTO asset_warranties (
                asset_id, asset_component_id, warranty_type, provider_type, supplier_id, provider_name,
                start_date, end_date, terms, exclusions, status
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['asset_id'], $data['asset_component_id'], $data['warranty_type'], $data['provider_type'],
                $data['supplier_id'], $data['provider_name'], $data['start_date'], $data['end_date'],
                $data['terms'], $data['exclusions'], $data['status'],
            ]
        );

        return $this->insertId();
    }

    public function setWarrantyStatus(int $id, string $status): void
    {
        $this->run('UPDATE asset_warranties SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function setWarrantySpan(int $assetId, ?string $start, ?string $end): void
    {
        $this->run(
            'UPDATE customer_assets SET warranty_start_date = ?, warranty_end_date = ? WHERE id = ?',
            [$start, $end, $assetId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimsForAsset(int $assetId): array
    {
        return $this->rows('SELECT * FROM warranty_claims WHERE asset_id = ? ORDER BY id DESC', [$assetId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function claim(int $id): ?array
    {
        return $this->one('SELECT * FROM warranty_claims WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertClaim(array $data): int
    {
        $this->run(
            'INSERT INTO warranty_claims (
                claim_number, asset_id, asset_component_id, warranty_id, service_request_id, status,
                reported_date, failure_description, assessment, supplier_reference, cost_recovery_amount, notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['claim_number'], $data['asset_id'], $data['asset_component_id'], $data['warranty_id'],
                $data['service_request_id'], $data['status'], $data['reported_date'], $data['failure_description'],
                $data['assessment'], $data['supplier_reference'], $data['cost_recovery_amount'], $data['notes'],
                $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateClaim(int $id, array $data): void
    {
        $this->run(
            'UPDATE warranty_claims SET status = ?, assessment = ?, claim_outcome = ?, supplier_reference = ?,
                cost_recovery_amount = ?, recovered_amount = ?, notes = ? WHERE id = ?',
            [
                $data['status'], $data['assessment'], $data['claim_outcome'], $data['supplier_reference'],
                $data['cost_recovery_amount'], $data['recovered_amount'], $data['notes'], $id,
            ]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function requests(array $filters, int $limit = 80): array
    {
        $where = ['1=1'];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = like_term($q);
            $where[] = '(r.request_number LIKE ? OR r.description LIKE ? OR c.company_name LIKE ? OR a.asset_number LIKE ? OR a.serial_number LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $limit = max(1, min(100, $limit));

        return $this->rows(
            'SELECT r.*, c.company_name, a.asset_number, a.name AS asset_name,
                    DATEDIFF(CURDATE(), DATE(r.reported_at)) AS age_days
             FROM service_requests r
             JOIN customers c ON c.id = r.customer_id
             LEFT JOIN customer_assets a ON a.id = r.asset_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(r.priority, \'URGENT\', \'HIGH\', \'NORMAL\', \'LOW\'), r.reported_at
             LIMIT ' . $limit,
            $params
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function request(int $id): ?array
    {
        return $this->one(
            'SELECT r.*, c.company_name, a.asset_number, a.name AS asset_name, a.customer_id AS asset_customer_id
             FROM service_requests r
             JOIN customers c ON c.id = r.customer_id
             LEFT JOIN customer_assets a ON a.id = r.asset_id
             WHERE r.id = ?',
            [$id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRequest(array $data): int
    {
        $this->run(
            'INSERT INTO service_requests (
                request_number, customer_id, project_site_id, asset_id, asset_component_id, reported_by,
                contact_detail, source, problem_category, description, priority, status, classification,
                warranty_candidate, assigned_user_id, reported_at, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['request_number'], $data['customer_id'], $data['project_site_id'], $data['asset_id'],
                $data['asset_component_id'], $data['reported_by'], $data['contact_detail'], $data['source'],
                $data['problem_category'], $data['description'], $data['priority'], $data['status'],
                $data['classification'], $data['warranty_candidate'], $data['assigned_user_id'],
                $data['reported_at'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateRequest(int $id, array $data, int $version): int
    {
        $this->run(
            'UPDATE service_requests SET status = ?, priority = ?, classification = ?, assigned_user_id = ?,
                first_response_at = ?, assessed_at = ?, resolved_at = ?, work_performed = ?, outstanding_issue = ?,
                recommendations = ?, customer_signoff_name = ?, signed_at = ?, version = version + 1
             WHERE id = ? AND version = ?',
            [
                $data['status'], $data['priority'], $data['classification'], $data['assigned_user_id'],
                $data['first_response_at'], $data['assessed_at'], $data['resolved_at'], $data['work_performed'],
                $data['outstanding_issue'], $data['recommendations'], $data['customer_signoff_name'],
                $data['signed_at'], $id, $version,
            ]
        );

        return $this->affected();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requestsForAsset(int $assetId): array
    {
        return $this->rows(
            'SELECT * FROM service_requests WHERE asset_id = ? ORDER BY reported_at, id',
            [$assetId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requestsForCustomer(int $customerId): array
    {
        return $this->rows(
            'SELECT id, request_number, status, problem_category, reported_at, asset_id
             FROM service_requests WHERE customer_id = ? ORDER BY id DESC LIMIT 50',
            [$customerId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertInspection(array $data): int
    {
        $this->run(
            'INSERT INTO asset_inspections (
                asset_id, service_request_id, job_id, inspection_type, result, findings, recommendations,
                inspected_on, technician_user_id, customer_visible
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['asset_id'], $data['service_request_id'], $data['job_id'], $data['inspection_type'],
                $data['result'], $data['findings'], $data['recommendations'], $data['inspected_on'],
                $data['technician_user_id'], $data['customer_visible'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertInspectionItem(array $data): void
    {
        $this->run(
            'INSERT INTO asset_inspection_items (inspection_id, label, result, note) VALUES (?, ?, ?, ?)',
            [$data['inspection_id'], $data['label'], $data['result'], $data['note']]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inspections(int $assetId, bool $customerVisibleOnly = false): array
    {
        $sql = 'SELECT * FROM asset_inspections WHERE asset_id = ?';
        if ($customerVisibleOnly) {
            $sql .= ' AND customer_visible = 1';
        }

        return $this->rows($sql . ' ORDER BY inspected_on, id', [$assetId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inspectionItems(int $inspectionId): array
    {
        return $this->rows(
            'SELECT * FROM asset_inspection_items WHERE inspection_id = ? ORDER BY id',
            [$inspectionId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function plans(): array
    {
        return $this->rows(
            'SELECT p.*, t.name AS type_name FROM maintenance_plans p
             LEFT JOIN asset_types t ON t.id = p.asset_type_id
             ORDER BY p.name'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function plan(int $id): ?array
    {
        return $this->one('SELECT * FROM maintenance_plans WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertPlan(array $data): int
    {
        $this->run(
            'INSERT INTO maintenance_plans (name, asset_type_id, interval_days, interval_months, checklist_name, recipe_id, auto_request, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
            [
                $data['name'], $data['asset_type_id'], $data['interval_days'], $data['interval_months'],
                $data['checklist_name'], $data['recipe_id'], $data['auto_request'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assignments(int $assetId): array
    {
        return $this->rows(
            'SELECT s.*, p.name, p.interval_days, p.interval_months, p.checklist_name, p.auto_request
             FROM asset_plan_assignments s
             JOIN maintenance_plans p ON p.id = s.plan_id
             WHERE s.asset_id = ? ORDER BY s.next_due_on',
            [$assetId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assignPlan(array $data): void
    {
        $this->run(
            'INSERT INTO asset_plan_assignments (asset_id, plan_id, last_completed_on, next_due_on, active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE next_due_on = VALUES(next_due_on), active = 1',
            [$data['asset_id'], $data['plan_id'], $data['last_completed_on'], $data['next_due_on']]
        );
    }

    public function completeAssignment(int $id, string $completed, string $next): void
    {
        $this->run(
            'UPDATE asset_plan_assignments SET last_completed_on = ?, next_due_on = ? WHERE id = ?',
            [$completed, $next, $id]
        );
    }

    public function overrideDue(int $id, string $next): void
    {
        $this->run('UPDATE asset_plan_assignments SET next_due_on = ? WHERE id = ?', [$next, $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function assignment(int $id): ?array
    {
        return $this->one(
            'SELECT s.*, p.interval_days, p.interval_months, p.name, p.auto_request, p.checklist_name
             FROM asset_plan_assignments s JOIN maintenance_plans p ON p.id = s.plan_id WHERE s.id = ?',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueAssignments(string $until): array
    {
        return $this->rows(
            'SELECT s.*, a.asset_number, a.customer_id, a.name AS asset_name, p.name AS plan_name, p.auto_request
             FROM asset_plan_assignments s
             JOIN customer_assets a ON a.id = s.asset_id
             JOIN maintenance_plans p ON p.id = s.plan_id
             WHERE s.active = 1 AND a.archived_at IS NULL AND s.next_due_on IS NOT NULL AND s.next_due_on <= ?
             ORDER BY s.next_due_on LIMIT 200',
            [$until]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertAgreement(array $data): int
    {
        $this->run(
            'INSERT INTO service_agreements (
                agreement_number, customer_id, project_id, start_date, end_date, status, description,
                included_services, response_target_hours, billing_notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['agreement_number'], $data['customer_id'], $data['project_id'], $data['start_date'],
                $data['end_date'], $data['status'], $data['description'], $data['included_services'],
                $data['response_target_hours'], $data['billing_notes'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function agreementsForCustomer(int $customerId): array
    {
        return $this->rows(
            'SELECT * FROM service_agreements WHERE customer_id = ? ORDER BY start_date DESC',
            [$customerId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertLocation(array $data): void
    {
        $this->run(
            'INSERT INTO asset_location_history (asset_id, from_site_id, to_site_id, from_location, to_location, reason, job_id, changed_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['asset_id'], $data['from_site_id'], $data['to_site_id'], $data['from_location'],
                $data['to_location'], $data['reason'], $data['job_id'], $data['changed_by'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function locations(int $assetId): array
    {
        return $this->rows(
            'SELECT * FROM asset_location_history WHERE asset_id = ? ORDER BY changed_at, id',
            [$assetId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertEvent(array $data): void
    {
        $this->run(
            'INSERT INTO asset_events (asset_id, event_type, summary, related_type, related_id, happened_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['asset_id'], $data['event_type'], $data['summary'], $data['related_type'],
                $data['related_id'], $data['happened_at'], $data['created_by'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(int $assetId, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return $this->rows(
            'SELECT * FROM asset_events WHERE asset_id = ? ORDER BY happened_at, id LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$assetId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function partByUuid(string $uuid): ?array
    {
        return $this->one('SELECT * FROM service_part_usages WHERE operation_uuid = ?', [$uuid]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertPart(array $data): int
    {
        $this->run(
            'INSERT INTO service_part_usages (operation_uuid, job_id, asset_id, old_component_id, product_id, quantity)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['operation_uuid'], $data['job_id'], $data['asset_id'], $data['old_component_id'],
                $data['product_id'], $data['quantity'],
            ]
        );

        return $this->insertId();
    }

    public function finishPart(int $id, ?int $newComponentId, ?int $movementId): void
    {
        $this->run(
            'UPDATE service_part_usages SET new_component_id = ?, stock_movement_id = ? WHERE id = ?',
            [$newComponentId, $movementId, $id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function syncByUuid(string $uuid): ?array
    {
        return $this->one('SELECT * FROM asset_sync_operations WHERE operation_uuid = ?', [$uuid]);
    }

    public function insertSync(string $uuid, int $assetId, int $userId, string $kind): void
    {
        $this->run(
            'INSERT INTO asset_sync_operations (operation_uuid, asset_id, user_id, kind) VALUES (?, ?, ?, ?)',
            [$uuid, $assetId, $userId, $kind]
        );
    }

    public function stampJob(int $jobId, array $data): void
    {
        $this->run(
            'UPDATE jobs SET job_type = ?, service_request_id = ?, customer_asset_id = ?, asset_component_id = ?,
                warranty_claim_id = ?, service_classification = ?, service_reason = ?,
                project_id = COALESCE(project_id, ?), project_site_id = COALESCE(project_site_id, ?)
             WHERE id = ?',
            [
                $data['job_type'], $data['service_request_id'], $data['customer_asset_id'], $data['asset_component_id'],
                $data['warranty_claim_id'], $data['classification'], $data['service_reason'],
                $data['project_id'], $data['project_site_id'], $jobId,
            ]
        );
    }

    public function linkQuote(int $quoteId, ?int $requestId, ?int $assetId): void
    {
        $this->run(
            'UPDATE quotes SET service_request_id = ?, customer_asset_id = ? WHERE id = ?',
            [$requestId, $assetId, $quoteId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serviceJobs(int $assetId): array
    {
        return $this->rows(
            'SELECT id, job_number, job_type, status, service_classification, quoted_revenue_snapshot, actual_total_cost, actual_material_cost, actual_labour_cost, actual_other_cost
             FROM jobs WHERE customer_asset_id = ? AND archived = 0 ORDER BY id',
            [$assetId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function assetDashboard(): array
    {
        $row = $this->one(
            'SELECT
                SUM(status = \'ACTIVE\') AS active_count,
                SUM(status = \'UNDER_REPAIR\') AS under_repair,
                SUM(status = \'OUT_OF_SERVICE\') AS out_of_service,
                SUM(next_service_date IS NOT NULL AND next_service_date <= CURDATE() AND status NOT IN (\'REPLACED\',\'REMOVED\',\'DECOMMISSIONED\',\'ARCHIVED\',\'LOST\')) AS service_due,
                COUNT(*) AS total
             FROM customer_assets WHERE archived_at IS NULL'
        ) ?? [];

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function countsByType(): array
    {
        return $this->rows(
            'SELECT t.name, COUNT(*) AS total FROM customer_assets a
             JOIN asset_types t ON t.id = a.asset_type_id
             WHERE a.archived_at IS NULL GROUP BY t.id, t.name ORDER BY total DESC'
        );
    }

    /**
     * @return array<string, int>
     */
    public function requestCards(): array
    {
        $row = $this->one(
            'SELECT
                SUM(status = \'NEW\') AS new_count,
                SUM(status = \'TRIAGE\') AS triage,
                SUM(status = \'AWAITING_QUOTE\') AS awaiting_quote,
                SUM(status = \'READY_TO_SCHEDULE\') AS ready,
                SUM(status = \'IN_PROGRESS\') AS in_progress,
                SUM(status IN (\'NEW\',\'TRIAGE\',\'AWAITING_CUSTOMER\',\'AWAITING_ASSESSMENT\',\'AWAITING_QUOTE\',\'AWAITING_APPROVAL\',\'READY_TO_SCHEDULE\',\'IN_PROGRESS\') AND reported_at < DATE_SUB(NOW(), INTERVAL 14 DAY)) AS overdue,
                SUM(warranty_candidate = 1 AND status NOT IN (\'CLOSED\',\'CANCELLED\',\'RESOLVED\')) AS warranty_open,
                SUM(status IN (\'RESOLVED\',\'CLOSED\') AND resolved_at >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')) AS completed_month
             FROM service_requests'
        ) ?? [];
        $cards = [];
        foreach ($row as $key => $value) {
            $cards[(string) $key] = (int) $value;
        }

        return $cards;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expiringWarranties(string $until): array
    {
        return $this->rows(
            'SELECT w.*, a.asset_number, a.customer_id FROM asset_warranties w
             JOIN customer_assets a ON a.id = w.asset_id
             WHERE w.status NOT IN (\'VOID\') AND w.end_date >= CURDATE() AND w.end_date <= ?
             ORDER BY w.end_date LIMIT 200',
            [$until]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function warrantyDashboard(): array
    {
        $row = $this->one(
            'SELECT
                SUM(status = \'ACTIVE\') AS active_count,
                SUM(status = \'EXPIRING\') AS expiring,
                SUM(status = \'EXPIRED\') AS expired
             FROM asset_warranties'
        ) ?? [];
        $claims = $this->one(
            'SELECT
                SUM(status NOT IN (\'RESOLVED\',\'CLOSED\',\'REJECTED\')) AS open_claims,
                COALESCE(SUM(cost_recovery_amount), 0) AS recoverable,
                COALESCE(SUM(recovered_amount), 0) AS recovered
             FROM warranty_claims'
        ) ?? [];

        return array_merge($row, $claims);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rowsForReport(string $sql, array $params = []): array
    {
        return $this->rows($sql, $params);
    }

    public function customerByName(string $name): ?array
    {
        return $this->one(
            'SELECT id, company_name FROM customers WHERE company_name = ? AND active = 1 ORDER BY id DESC LIMIT 1',
            [$name]
        );
    }

    public function siteForCustomer(int $customerId, string $name): ?array
    {
        return $this->one(
            'SELECT ps.id, ps.project_id FROM project_sites ps
             JOIN projects p ON p.id = ps.project_id
             WHERE p.customer_id = ? AND ps.site_name = ? AND ps.archived_at IS NULL
             ORDER BY ps.id DESC LIMIT 1',
            [$customerId, $name]
        );
    }

    public function openRequestForAsset(int $assetId): ?array
    {
        return $this->one(
            'SELECT id FROM service_requests WHERE asset_id = ? AND status NOT IN (\'RESOLVED\',\'CLOSED\',\'CANCELLED\')
             AND source = \'INSPECTION\' ORDER BY id DESC LIMIT 1',
            [$assetId]
        );
    }

    private function select(): string
    {
        return 'SELECT a.*, c.company_name, t.code AS type_code, t.name AS type_name,
                ps.site_name, ps.site_code, j.job_number, p.project_number
             FROM customer_assets a
             JOIN customers c ON c.id = a.customer_id
             JOIN asset_types t ON t.id = a.asset_type_id
             LEFT JOIN project_sites ps ON ps.id = a.project_site_id
             LEFT JOIN jobs j ON j.id = a.original_job_id
             LEFT JOIN projects p ON p.id = a.project_id';
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function where(array $filters): array
    {
        $where = ['a.archived_at IS NULL'];
        $params = [];
        if (!empty($filters['customer_id'])) {
            $where[] = 'a.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['project_id'])) {
            $where[] = 'a.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        if (!empty($filters['project_site_id'])) {
            $where[] = 'a.project_site_id = ?';
            $params[] = (int) $filters['project_site_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['type_id'])) {
            $where[] = 'a.asset_type_id = ?';
            $params[] = (int) $filters['type_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = like_term($q);
            $where[] = '(a.asset_number LIKE ? OR a.name LIKE ? OR a.customer_asset_reference LIKE ? OR a.serial_number LIKE ?
                OR a.registration LIKE ? OR c.company_name LIKE ? OR ps.site_name LIKE ? OR j.job_number LIKE ?
                OR p.project_number LIKE ? OR EXISTS (
                    SELECT 1 FROM asset_components ac WHERE ac.asset_id = a.id AND ac.serial_number LIKE ?
                ))';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        return [$this->select() . ' WHERE ' . implode(' AND ', $where), $params];
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Contractors are an external party. A supplier row may be linked, but the portal
 * only reads work orders for the signed-in contractor.
 */
final class ContractorRepository extends Repository
{
    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO contractors (supplier_id, company_name, contractor_type, status, contact_name, email, phone, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['supplier_id'], $data['company_name'], $data['contractor_type'], $data['status'],
                $data['contact_name'], $data['email'], $data['phone'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM contractors WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(int $limit, int $offset): array
    {
        return $this->rows(
            'SELECT id, company_name, contractor_type, status, contact_name, email, phone FROM contractors ORDER BY company_name LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
    }

    public function setStatus(int $id, string $status): void
    {
        $this->run('UPDATE contractors SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertDocument(array $data): int
    {
        $this->run(
            'INSERT INTO contractor_documents (contractor_id, document_type, title, issue_date, expiry_date, file_path) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['contractor_id'], $data['document_type'], $data['title'], $data['issue_date'], $data['expiry_date'], $data['file_path']]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documents(int $contractorId): array
    {
        return $this->rows('SELECT * FROM contractor_documents WHERE contractor_id = ? ORDER BY expiry_date, id', [$contractorId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expiringDocuments(string $until): array
    {
        return $this->rows(
            'SELECT d.*, c.company_name FROM contractor_documents d INNER JOIN contractors c ON c.id = d.contractor_id
             WHERE d.expiry_date IS NOT NULL AND d.expiry_date <= ? AND d.reminder_sent = 0',
            [$until]
        );
    }

    public function markReminded(int $id): void
    {
        $this->run('UPDATE contractor_documents SET reminder_sent = 1 WHERE id = ?', [$id]);
    }

    public function insertArea(int $contractorId, ?string $province, ?string $city, ?string $radius): void
    {
        $this->run(
            'INSERT INTO contractor_service_areas (contractor_id, province, city, radius_text) VALUES (?, ?, ?, ?)',
            [$contractorId, $province, $city, $radius]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function areas(int $contractorId): array
    {
        return $this->rows('SELECT province, city, radius_text FROM contractor_service_areas WHERE contractor_id = ?', [$contractorId]);
    }

    public function setAvailability(int $contractorId, string $date, int $available, ?string $note): void
    {
        $this->run(
            'INSERT INTO contractor_availability (contractor_id, available_date, available, note) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE available = VALUES(available), note = VALUES(note)',
            [$contractorId, $date, $available, $note]
        );
    }

    public function insertRate(int $contractorId, string $method, string $amount, string $label): int
    {
        $this->run(
            'INSERT INTO contractor_rates (contractor_id, pricing_method, amount, label) VALUES (?, ?, ?, ?)',
            [$contractorId, $method, $amount, $label]
        );

        return $this->insertId();
    }

    public function rate(int $id): ?array
    {
        return $this->one('SELECT * FROM contractor_rates WHERE id = ?', [$id]);
    }

    public function updateRate(int $id, string $amount): void
    {
        $this->run('UPDATE contractor_rates SET amount = ? WHERE id = ?', [$amount, $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertUser(array $data): int
    {
        $this->run(
            'INSERT INTO contractor_users (contractor_id, email, password_hash, display_name, active) VALUES (?, ?, ?, ?, 1)',
            [$data['contractor_id'], $data['email'], $data['password_hash'], $data['display_name']]
        );

        return $this->insertId();
    }

    public function userByEmail(string $email): ?array
    {
        return $this->one('SELECT * FROM contractor_users WHERE email = ?', [$email]);
    }

    public function user(int $id): ?array
    {
        return $this->one('SELECT * FROM contractor_users WHERE id = ?', [$id]);
    }

    public function grant(int $userId, string $code): void
    {
        $this->run('INSERT IGNORE INTO contractor_user_permissions (contractor_user_id, code) VALUES (?, ?)', [$userId, $code]);
    }

    public function allows(int $userId, string $code): bool
    {
        $row = $this->one('SELECT 1 AS ok FROM contractor_user_permissions WHERE contractor_user_id = ? AND code = ?', [$userId, $code]);

        return $row !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertWorkOrder(array $data): int
    {
        $this->run(
            'INSERT INTO contractor_work_orders (
                work_order_number, contractor_id, job_id, project_id, project_site_id, service_request_id,
                fulfilment_requirement_id, installation_id, work_type, status, scope, quantity, site_address,
                contact_name, contact_phone, required_date, instructions, pricing_method, agreed_cost,
                material_supply, internal_notes, visible_terms, exposed, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'DRAFT\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
            [
                $data['work_order_number'], $data['contractor_id'], $data['job_id'], $data['project_id'], $data['project_site_id'],
                $data['service_request_id'], $data['fulfilment_requirement_id'], $data['installation_id'], $data['work_type'],
                $data['scope'], $data['quantity'], $data['site_address'], $data['contact_name'], $data['contact_phone'],
                $data['required_date'], $data['instructions'], $data['pricing_method'], $data['agreed_cost'],
                $data['material_supply'], $data['internal_notes'], $data['visible_terms'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentWork(int $limit): array
    {
        return $this->rows(
            'SELECT id, work_order_number, contractor_id, job_id, project_id, work_type, status, required_date, agreed_cost, actual_cost
             FROM contractor_work_orders ORDER BY id DESC LIMIT ' . (int) $limit
        );
    }

    public function workOrder(int $id): ?array
    {
        return $this->one('SELECT * FROM contractor_work_orders WHERE id = ?', [$id]);
    }

    public function lockWorkOrder(int $id): ?array
    {
        return $this->one('SELECT * FROM contractor_work_orders WHERE id = ? FOR UPDATE', [$id]);
    }

    public function workOrderForContractor(int $id, int $contractorId): ?array
    {
        return $this->one(
            'SELECT id, work_order_number, contractor_id, job_id, project_site_id, work_type, status, scope, quantity,
                    site_address, contact_name, contact_phone, required_date, instructions, pricing_method, agreed_cost,
                    visible_terms, material_supply, started_at, submitted_at
             FROM contractor_work_orders
             WHERE id = ? AND contractor_id = ? AND exposed = 1',
            [$id, $contractorId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function workForContractor(int $contractorId): array
    {
        return $this->rows(
            'SELECT id, work_order_number, work_type, status, required_date, site_address, quantity
             FROM contractor_work_orders
             WHERE contractor_id = ? AND exposed = 1
             ORDER BY required_date, id',
            [$contractorId]
        );
    }

    public function setWorkStatus(int $id, string $status, array $extra = []): void
    {
        $sets = ['status = ?'];
        $params = [$status];
        foreach ($extra as $column => $value) {
            if (!preg_match('/^[a-z_]+$/', (string) $column)) {
                continue;
            }
            $sets[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $this->run('UPDATE contractor_work_orders SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public function insertResponse(int $workOrderId, string $action, ?string $kind, ?string $message): void
    {
        $this->run(
            'INSERT INTO contractor_responses (work_order_id, action, change_kind, message) VALUES (?, ?, ?, ?)',
            [$workOrderId, $action, $kind, $message]
        );
    }

    public function linkFile(int $workOrderId, ?int $productionFileId, string $role, string $title): void
    {
        $this->run(
            'INSERT INTO contractor_work_files (work_order_id, production_file_id, file_role, title) VALUES (?, ?, ?, ?)',
            [$workOrderId, $productionFileId, $role, $title]
        );
    }

    public function fileForContractor(int $productionFileId, int $contractorId): ?array
    {
        return $this->one(
            'SELECT f.id FROM contractor_work_files f
             INNER JOIN contractor_work_orders w ON w.id = f.work_order_id
             WHERE f.production_file_id = ? AND w.contractor_id = ? AND w.exposed = 1',
            [$productionFileId, $contractorId]
        );
    }

    public function insertIssue(array $data): int
    {
        $this->run(
            'INSERT INTO contractor_issues (work_order_id, severity, description, suggested_action, photo_path, reason) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['work_order_id'], $data['severity'], $data['description'], $data['suggested_action'], $data['photo_path'], $data['reason']]
        );

        return $this->insertId();
    }

    public function insertInvoice(array $data): int
    {
        $this->run(
            'INSERT INTO contractor_invoices (work_order_id, invoice_number, invoice_date, amount, file_path) VALUES (?, ?, ?, ?, ?)',
            [$data['work_order_id'], $data['invoice_number'], $data['invoice_date'], $data['amount'], $data['file_path']]
        );

        return $this->insertId();
    }

    public function insertRework(array $data): int
    {
        $this->run(
            'INSERT INTO contractor_rework (work_order_id, original_work_order_id, reason, cost, delay_days, resolution) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['work_order_id'], $data['original_work_order_id'], $data['reason'], $data['cost'], $data['delay_days'], $data['resolution']]
        );

        return $this->insertId();
    }

    public function insertReceipt(array $data): int
    {
        $this->run(
            'INSERT INTO outsourced_receipts (work_order_id, quantity_received, quantity_accepted, quantity_rejected, qc_status, photo_path, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['work_order_id'], $data['quantity_received'], $data['quantity_accepted'], $data['quantity_rejected'],
                $data['qc_status'], $data['photo_path'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function insertMaterial(array $data): void
    {
        $this->run(
            'INSERT INTO contractor_material_ledger (work_order_id, product_id, movement, quantity, stock_location_id, transfer_reference, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['work_order_id'], $data['product_id'], $data['movement'], $data['quantity'],
                $data['stock_location_id'], $data['transfer_reference'], $data['created_by'],
            ]
        );
    }

    public function materialBalance(int $workOrderId, int $productId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(CASE movement WHEN 'ISSUE' THEN quantity WHEN 'RETURN' THEN -quantity WHEN 'CONSUME' THEN -quantity ELSE 0 END), 0) AS qty
             FROM contractor_material_ledger WHERE work_order_id = ? AND product_id = ?",
            [$workOrderId, $productId]
        );

        return (string) ($row['qty'] ?? '0');
    }

    public function ensureLocation(int $contractorId, string $name): int
    {
        $code = 'EXT-C' . $contractorId;
        $existing = $this->one('SELECT id FROM stock_locations WHERE code = ?', [$code]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $this->run(
            'INSERT INTO stock_locations (code, name, location_type, active) VALUES (?, ?, \'EXTERNAL_CONTRACTOR\', 1)',
            [$code, mb_substr('Contractor ' . $name, 0, 120)]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, int|string>
     */
    public function performance(int $contractorId): array
    {
        $row = $this->one(
            "SELECT
                SUM(status = 'ACCEPTED' OR status IN ('SCHEDULED','IN_PROGRESS','BLOCKED','SUBMITTED_COMPLETE','REVIEW_REQUIRED','APPROVED_COMPLETE')) AS accepted,
                SUM(status = 'DECLINED') AS declined,
                SUM(status = 'APPROVED_COMPLETE' AND (required_date IS NULL OR DATE(approved_at) <= required_date)) AS on_time,
                AVG(CASE WHEN approved_at IS NOT NULL THEN DATEDIFF(DATE(approved_at), DATE(created_at)) END) AS average_turnaround,
                SUM(CASE WHEN variance_reason IS NOT NULL THEN 1 ELSE 0 END) AS cost_variance_count
             FROM contractor_work_orders WHERE contractor_id = ?",
            [$contractorId]
        );
        $rework = $this->one(
            'SELECT COUNT(*) AS n FROM contractor_rework r INNER JOIN contractor_work_orders w ON w.id = r.work_order_id WHERE w.contractor_id = ?',
            [$contractorId]
        );
        $snags = $this->one(
            "SELECT COUNT(*) AS n FROM job_snags s
             INNER JOIN job_installations i ON i.id = s.installation_id
             INNER JOIN contractor_work_orders w ON w.id = i.contractor_work_order_id
             WHERE w.contractor_id = ?",
            [$contractorId]
        );

        return [
            'accepted' => (int) ($row['accepted'] ?? 0),
            'declined' => (int) ($row['declined'] ?? 0),
            'on_time' => (int) ($row['on_time'] ?? 0),
            'average_turnaround' => $row['average_turnaround'] === null ? null : (string) $row['average_turnaround'],
            'rework' => (int) ($rework['n'] ?? 0),
            'snags' => (int) ($snags['n'] ?? 0),
            'cost_variance_count' => (int) ($row['cost_variance_count'] ?? 0),
        ];
    }

    public function setServiceSplit(int $requestId, string $charge, string $internal, string $recovery, ?int $contractorId, ?int $originalWorkOrderId): void
    {
        $this->run(
            'UPDATE service_requests SET customer_charge = ?, internal_cost = ?, recovery_amount = ?, assigned_contractor_id = ?, original_work_order_id = ? WHERE id = ?',
            [$charge, $internal, $recovery, $contractorId, $originalWorkOrderId, $requestId]
        );
    }

    public function serviceSplit(int $requestId): ?array
    {
        return $this->one(
            'SELECT customer_charge, internal_cost, recovery_amount, assigned_contractor_id, original_work_order_id FROM service_requests WHERE id = ?',
            [$requestId]
        );
    }

    public function otherCostCount(int $jobId, string $reference): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM job_other_costs WHERE job_id = ? AND reference = ?', [$jobId, $reference]);

        return (int) ($row['n'] ?? 0);
    }
}

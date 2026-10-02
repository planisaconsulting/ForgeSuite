<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Expense, mileage, trip, calendar feed, and merge rows.
 */
final class ExpenseRepository extends Repository
{
    /** @var list<string> */
    private const CUSTOMER_TABLES = [
        'quotes', 'jobs', 'invoices', 'projects', 'customer_assets', 'customer_contacts',
        'communications', 'portal_users', 'sales_intakes', 'service_requests', 'leads',
        'customer_catalogues',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function categoryByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM expense_categories WHERE code = ? AND active = 1', [$code]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->rows('SELECT * FROM expense_categories WHERE active = 1 ORDER BY name');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertExpense(array $data): int
    {
        $this->run(
            'INSERT INTO expenses (
                expense_number, expense_date, submitted_by, incurred_by, expense_category_id, description,
                merchant_name, supplier_id, amount_ex_vat, vat_amount, amount_inc_vat, currency_code,
                payment_method, status, reimbursement_status, receipt_required, receipt_sha256, receipt_name,
                reference_text, notes, source, represented_by_type, represented_by_id, duplicate_of_id,
                idempotency_key, client_local_id, trip_id
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['expense_number'], $data['expense_date'], $data['submitted_by'], $data['incurred_by'],
                $data['expense_category_id'], $data['description'], $data['merchant_name'], $data['supplier_id'],
                $data['amount_ex_vat'], $data['vat_amount'], $data['amount_inc_vat'], $data['currency_code'],
                $data['payment_method'], $data['status'], $data['reimbursement_status'], $data['receipt_required'],
                $data['receipt_sha256'], $data['receipt_name'], $data['reference_text'], $data['notes'],
                $data['source'], $data['represented_by_type'], $data['represented_by_id'], $data['duplicate_of_id'],
                $data['idempotency_key'], $data['client_local_id'], $data['trip_id'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT e.*, c.code AS category_code, c.name AS category_name, c.prevent_self_approval,
                    c.approval_threshold, c.costing_behaviour, c.other_cost_type, c.reimbursable AS category_reimbursable
             FROM expenses e
             JOIN expense_categories c ON c.id = e.expense_category_id
             WHERE e.id = ?',
            [$id]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $column, string $value): ?array
    {
        if (!in_array($column, ['idempotency_key', 'client_local_id', 'expense_number'], true)) {
            return null;
        }

        return $this->one('SELECT * FROM expenses WHERE ' . $column . ' = ?', [$value]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function duplicate(int $userId, string $amount, string $date, string $merchant, ?string $hash): ?array
    {
        if ($hash !== null && $hash !== '') {
            $byHash = $this->one('SELECT * FROM expenses WHERE receipt_sha256 = ? ORDER BY id LIMIT 1', [$hash]);
            if ($byHash !== null) {
                return $byHash;
            }
        }
        if ($merchant === '') {
            return null;
        }

        return $this->one(
            'SELECT * FROM expenses
             WHERE submitted_by = ? AND amount_inc_vat = ? AND expense_date = ? AND merchant_name = ?
             ORDER BY id LIMIT 1',
            [$userId, $amount, $date, $merchant]
        );
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updateExpense(int $id, array $fields): void
    {
        $allowed = [
            'status', 'reimbursement_status', 'submitted_at', 'approved_by', 'approved_at', 'rejected_by',
            'rejection_reason', 'reversed_at', 'reversed_by', 'duplicate_of_id', 'vat_amount', 'amount_ex_vat',
            'amount_inc_vat', 'extraction_json', 'confirmed_json', 'receipt_sha256', 'receipt_name', 'receipt_path',
            'merchant_name', 'reference_text',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }
            $sets[] = $key . ' = ?';
            $params[] = $fields[$key];
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $this->run('UPDATE expenses SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public function clearAllocations(int $expenseId): void
    {
        $this->run('DELETE FROM expense_allocations WHERE expense_id = ?', [$expenseId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertAllocation(array $row): int
    {
        $this->run(
            'INSERT INTO expense_allocations
                (expense_id, line_no, target_type, target_id, job_id, project_id, method, percent, amount, calculation_note)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $row['expense_id'], $row['line_no'], $row['target_type'], $row['target_id'], $row['job_id'],
                $row['project_id'], $row['method'], $row['percent'], $row['amount'], $row['calculation_note'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allocations(int $expenseId): array
    {
        return $this->rows('SELECT * FROM expense_allocations WHERE expense_id = ? ORDER BY line_no', [$expenseId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertPosting(array $row): int
    {
        $this->run(
            'INSERT INTO expense_cost_postings
                (expense_id, allocation_id, job_id, project_id, other_cost_id, direct_cost_id, amount)
             VALUES (?,?,?,?,?,?,?)',
            [
                $row['expense_id'], $row['allocation_id'], $row['job_id'], $row['project_id'],
                $row['other_cost_id'], $row['direct_cost_id'], $row['amount'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function postingForAllocation(int $allocationId): ?array
    {
        return $this->one('SELECT * FROM expense_cost_postings WHERE allocation_id = ?', [$allocationId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function postings(int $expenseId): array
    {
        return $this->rows('SELECT * FROM expense_cost_postings WHERE expense_id = ? ORDER BY id', [$expenseId]);
    }

    public function markPostingReversed(int $id, ?int $otherId, ?int $directId): void
    {
        $this->run(
            'UPDATE expense_cost_postings
             SET reversed_at = NOW(), reversal_other_cost_id = ?, reversal_direct_cost_id = ?
             WHERE id = ? AND reversed_at IS NULL',
            [$otherId, $directId, $id]
        );
    }

    public function insertDirectCost(int $projectId, string $description, string $amount, string $date, int $userId): int
    {
        $this->run(
            'INSERT INTO project_direct_costs (project_id, category, description, amount, cost_date, created_by)
             VALUES (?, \'EXPENSE\', ?, ?, ?, ?)',
            [$projectId, $description, $amount, $date, $userId]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function page(int $limit, int $offset, ?int $onlyUserId): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        $sql = 'SELECT e.id, e.expense_number, e.expense_date, e.description, e.merchant_name, e.amount_inc_vat,
                       e.status, e.reimbursement_status, e.submitted_by, c.name AS category_name
                FROM expenses e
                JOIN expense_categories c ON c.id = e.expense_category_id';
        $params = [];
        if ($onlyUserId !== null) {
            $sql .= ' WHERE e.submitted_by = ? OR e.incurred_by = ?';
            $params = [$onlyUserId, $onlyUserId];
        }
        $sql .= ' ORDER BY e.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        return $this->rows($sql, $params);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $row = $this->one(
            "SELECT
                SUM(status = 'DRAFT') AS drafts,
                SUM(status = 'SUBMITTED') AS submitted,
                SUM(status = 'APPROVED' AND reversed_at IS NULL) AS approved,
                SUM(status = 'REJECTED') AS rejected,
                SUM(reimbursement_status = 'NOT_SUBMITTED' AND status = 'APPROVED') AS reimbursable
             FROM expenses"
        ) ?? [];

        return [
            'drafts' => (int) ($row['drafts'] ?? 0),
            'submitted' => (int) ($row['submitted'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'reimbursable' => (int) ($row['reimbursable'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function awaitingApproval(int $limit): array
    {
        return $this->rows(
            "SELECT id, expense_number, description, amount_inc_vat, submitted_by, expense_date
             FROM expenses WHERE status = 'SUBMITTED' ORDER BY expense_date, id LIMIT " . max(1, $limit)
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertBatch(array $data): int
    {
        $this->run(
            'INSERT INTO reimbursement_batches (batch_number, status, notes, created_by) VALUES (?,?,?,?)',
            [$data['batch_number'], 'DRAFT', $data['notes'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function addBatchItem(int $batchId, int $expenseId, string $amount): void
    {
        $this->run(
            'INSERT INTO reimbursement_batch_items (batch_id, expense_id, amount) VALUES (?,?,?)',
            [$batchId, $expenseId, $amount]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function batch(int $id): ?array
    {
        return $this->one('SELECT * FROM reimbursement_batches WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function batchItems(int $batchId): array
    {
        return $this->rows(
            'SELECT i.*, e.expense_number, e.description, e.merchant_name, e.expense_date
             FROM reimbursement_batch_items i
             JOIN expenses e ON e.id = i.expense_id
             WHERE i.batch_id = ?
             ORDER BY e.expense_number',
            [$batchId]
        );
    }

    public function markBatch(int $id, string $status): void
    {
        $exported = $status === 'EXPORTED' ? ', exported_at = NOW()' : '';
        $this->run('UPDATE reimbursement_batches SET status = ?' . $exported . ' WHERE id = ?', [$status, $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertMileage(array $data): int
    {
        $this->run(
            'INSERT INTO mileage_records (
                user_id, vehicle_label, vehicle_use, trip_date, origin_text, destination_text,
                start_odometer, end_odometer, distance_km, purpose, job_id, project_id, trip_id,
                rate_per_km, cost, source, reimbursable, anomaly, anomaly_note, created_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['user_id'], $data['vehicle_label'], $data['vehicle_use'], $data['trip_date'],
                $data['origin_text'], $data['destination_text'], $data['start_odometer'], $data['end_odometer'],
                $data['distance_km'], $data['purpose'], $data['job_id'], $data['project_id'], $data['trip_id'],
                $data['rate_per_km'], $data['cost'], $data['source'], $data['reimbursable'], $data['anomaly'],
                $data['anomaly_note'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mileage(int $id): ?array
    {
        return $this->one('SELECT * FROM mileage_records WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mileageForTrip(int $tripId): array
    {
        return $this->rows('SELECT * FROM mileage_records WHERE trip_id = ? ORDER BY id', [$tripId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mileageForJob(int $jobId): array
    {
        return $this->rows('SELECT * FROM mileage_records WHERE job_id = ? ORDER BY id', [$jobId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTrip(array $data): int
    {
        $this->run(
            'INSERT INTO field_trips (trip_number, trip_date, driver_user_id, vehicle_label, purpose, origin_text, allocation_method, created_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $data['trip_number'], $data['trip_date'], $data['driver_user_id'], $data['vehicle_label'],
                $data['purpose'], $data['origin_text'], $data['allocation_method'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function trip(int $id): ?array
    {
        return $this->one('SELECT * FROM field_trips WHERE id = ?', [$id]);
    }

    public function addStop(int $tripId, ?int $jobId, string $label, int $sort): void
    {
        $this->run(
            'INSERT INTO field_trip_stops (trip_id, job_id, label, sort_order) VALUES (?,?,?,?)',
            [$tripId, $jobId, $label, $sort]
        );
    }

    public function addTripAllocation(int $tripId, int $jobId, ?string $percent, ?string $amount): void
    {
        $this->run(
            'INSERT INTO field_trip_allocations (trip_id, job_id, percent, amount) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE percent = VALUES(percent), amount = VALUES(amount)',
            [$tripId, $jobId, $percent, $amount]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tripAllocations(int $tripId): array
    {
        return $this->rows('SELECT * FROM field_trip_allocations WHERE trip_id = ? ORDER BY id', [$tripId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function tripPosting(int $tripId, int $jobId): ?array
    {
        return $this->one('SELECT * FROM field_trip_postings WHERE trip_id = ? AND job_id = ?', [$tripId, $jobId]);
    }

    public function insertTripPosting(int $tripId, int $jobId, ?int $otherId, ?int $logisticsId, string $amount): void
    {
        $this->run(
            'INSERT INTO field_trip_postings (trip_id, job_id, other_cost_id, logistics_cost_id, amount) VALUES (?,?,?,?,?)',
            [$tripId, $jobId, $otherId, $logisticsId, $amount]
        );
    }

    public function completeTrip(int $id): void
    {
        $this->run("UPDATE field_trips SET status = 'COMPLETED' WHERE id = ? AND status = 'OPEN'", [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function logisticsForJob(int $jobId): ?array
    {
        return $this->one('SELECT * FROM logistics_costs WHERE job_id = ? ORDER BY id LIMIT 1', [$jobId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function logisticsLines(int $jobId): array
    {
        return $this->rows('SELECT * FROM logistics_costs WHERE job_id = ? ORDER BY id', [$jobId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function otherLines(int $jobId): array
    {
        return $this->rows(
            'SELECT id, cost_type, description, total_cost, reference FROM job_other_costs WHERE job_id = ? ORDER BY id',
            [$jobId]
        );
    }

    public function insertFeed(int $userId, string $scope, string $detail, string $hash, ?int $projectId): int
    {
        $this->run(
            'INSERT INTO calendar_feeds (user_id, scope, detail_level, token_hash, project_id) VALUES (?,?,?,?,?)',
            [$userId, $scope, $detail, $hash, $projectId]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function feedByHash(string $hash): ?array
    {
        return $this->one('SELECT * FROM calendar_feeds WHERE token_hash = ?', [$hash]);
    }

    public function revokeFeed(int $id): void
    {
        $this->run('UPDATE calendar_feeds SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installations(string $from, string $to, ?int $userId): array
    {
        $sql = 'SELECT i.id, i.scheduled_date, i.scheduled_start_time, i.status, i.site_address, i.assigned_user_id,
                       j.job_number, j.title, j.customer_id
                FROM job_installations i
                JOIN jobs j ON j.id = i.job_id
                WHERE i.scheduled_date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($userId !== null) {
            $sql .= ' AND i.assigned_user_id = ?';
            $params[] = $userId;
        }
        $sql .= ' ORDER BY i.scheduled_date, i.id';

        return $this->rows($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function installation(int $id): ?array
    {
        return $this->one(
            'SELECT i.id, i.scheduled_date, i.scheduled_start_time, i.status, i.site_address, i.assigned_user_id,
                    j.job_number, j.title, j.customer_id
             FROM job_installations i
             JOIN jobs j ON j.id = i.job_id
             WHERE i.id = ?',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tasksBetween(string $from, string $to, ?int $userId): array
    {
        $sql = 'SELECT t.id, t.title, t.due_date, t.status, t.priority, t.assigned_to, j.job_number
                FROM job_tasks t
                JOIN jobs j ON j.id = t.job_id
                WHERE t.due_date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($userId !== null) {
            $sql .= ' AND t.assigned_to = ?';
            $params[] = $userId;
        }

        return $this->rows($sql . ' ORDER BY t.due_date, t.id', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchExpenses(string $prefix, int $limit, ?int $onlyUserId): array
    {
        $sql = 'SELECT id, expense_number, description, merchant_name, status, expense_date
                FROM expenses WHERE expense_number LIKE ?';
        $params = [$prefix];
        if ($onlyUserId !== null) {
            $sql .= ' AND (submitted_by = ? OR incurred_by = ?)';
            $params[] = $onlyUserId;
            $params[] = $onlyUserId;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . max(1, $limit);

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchInvoices(string $prefix, int $limit): array
    {
        return $this->rows(
            'SELECT id, invoice_number, status, invoice_date, customer_name_snapshot
             FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT ' . max(1, $limit),
            [$prefix]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchJobs(string $prefix, int $limit): array
    {
        return $this->rows(
            'SELECT id, job_number, title, status, created_at FROM jobs WHERE job_number LIKE ? ORDER BY id DESC LIMIT ' . max(1, $limit),
            [$prefix]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function customer(int $id): ?array
    {
        return $this->one('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    /**
     * @return array<string, int>
     */
    public function customerLinks(int $customerId): array
    {
        $out = [];
        foreach (self::CUSTOMER_TABLES as $table) {
            if (!$this->hasColumn($table, 'customer_id')) {
                continue;
            }
            $row = $this->one('SELECT COUNT(*) AS n FROM ' . $table . ' WHERE customer_id = ?', [$customerId]);
            $out[$table] = (int) ($row['n'] ?? 0);
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    public function reassignCustomer(int $from, int $to): array
    {
        $moved = [];
        foreach (self::CUSTOMER_TABLES as $table) {
            if (!$this->hasColumn($table, 'customer_id')) {
                continue;
            }
            $this->run('UPDATE ' . $table . ' SET customer_id = ? WHERE customer_id = ?', [$to, $from]);
            $moved[$table] = $this->affected();
        }
        $this->run(
            'UPDATE customers SET active = 0, merged_into_id = ? WHERE id = ? AND merged_into_id IS NULL',
            [$to, $from]
        );

        return $moved;
    }

    public function insertMerge(string $type, int $source, int $target, string $preview, int $userId): int
    {
        $this->run(
            'INSERT INTO entity_merges (entity_type, source_id, target_id, preview_json, executed_by, executed_at)
             VALUES (?,?,?,?,?,NOW())',
            [$type, $source, $target, $preview, $userId]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staleProductionFiles(): array
    {
        return $this->rows(
            "SELECT f.id AS file_id, j.id AS job_id, j.job_number, f.version_label
             FROM production_files f
             JOIN production_releases r ON r.id = f.release_id
             JOIN jobs j ON j.id = r.job_id
             WHERE r.status = 'RELEASED' AND f.status = 'SUPERSEDED'"
        );
    }

    public function insertIssue(string $key, string $severity, string $entityType, int $entityId, string $message, string $href): bool
    {
        $this->run(
            'INSERT IGNORE INTO data_quality_issues (issue_key, severity, entity_type, entity_id, message, href)
             VALUES (?,?,?,?,?,?)',
            [$key, $severity, $entityType, $entityId, $message, $href]
        );

        return $this->affected() === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openIssues(int $limit): array
    {
        return $this->rows(
            "SELECT * FROM data_quality_issues WHERE status = 'OPEN' ORDER BY id DESC LIMIT " . max(1, $limit)
        );
    }

    public function favourite(int $userId, string $type, int $entityId, string $label): void
    {
        $this->run(
            'INSERT IGNORE INTO user_favourites (user_id, entity_type, entity_id, label) VALUES (?,?,?,?)',
            [$userId, $type, $entityId, $label]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function favourites(int $userId): array
    {
        return $this->rows('SELECT * FROM user_favourites WHERE user_id = ? ORDER BY id DESC', [$userId]);
    }

    /**
     * @return list<array<string, string>>
     */
    public function categoryReport(): array
    {
        return $this->rows(
            "SELECT c.name AS category, COUNT(*) AS expenses, COALESCE(SUM(e.amount_inc_vat), 0) AS total
             FROM expenses e
             JOIN expense_categories c ON c.id = e.expense_category_id
             WHERE e.status = 'APPROVED' AND e.reversed_at IS NULL
             GROUP BY c.id, c.name
             ORDER BY c.name"
        );
    }

    private function hasColumn(string $table, string $column): bool
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return (int) ($row['n'] ?? 0) > 0;
    }
}

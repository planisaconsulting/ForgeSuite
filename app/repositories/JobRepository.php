<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Jobs created from an accepted quotation, plus the figures the costing
 * service caches from usage, time, and other costs.
 */
final class JobRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one($this->select() . ' WHERE j.id = ? LIMIT 1', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lock(int $id): ?array
    {
        return $this->one('SELECT * FROM jobs WHERE id = ? FOR UPDATE', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByQuote(int $quoteId): ?array
    {
        return $this->one($this->select() . ' WHERE j.quote_id = ? LIMIT 1', [$quoteId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        return $this->rows($this->select() . ' WHERE j.customer_id = ? ORDER BY j.id DESC', [$customerId]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters): array
    {
        $sql = $this->select() . ' WHERE 1 = 1';
        $params = [];
        if (empty($filters['archived'])) {
            $sql .= ' AND j.archived = 0';
        }
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = like_term($term);
            $sql .= ' AND (j.job_number LIKE ? ESCAPE \'\\\\\'
                      OR j.title LIKE ? ESCAPE \'\\\\\'
                      OR j.description LIKE ? ESCAPE \'\\\\\'
                      OR j.customer_po_number LIKE ? ESCAPE \'\\\\\'
                      OR c.company_name LIKE ? ESCAPE \'\\\\\'
                      OR c.last_name LIKE ? ESCAPE \'\\\\\')';
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && $status !== 'ALL') {
            $sql .= ' AND j.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['priority'])) {
            $sql .= ' AND j.priority = ?';
            $params[] = strtoupper((string) $filters['priority']);
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND (j.assigned_to = ? OR j.project_manager_id = ?)';
            $params[] = (int) $filters['assigned_to'];
            $params[] = (int) $filters['assigned_to'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND j.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['overdue'])) {
            $sql .= ' AND j.target_date IS NOT NULL AND j.target_date < CURDATE()
                      AND j.status NOT IN (\'COMPLETED\', \'CANCELLED\')';
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND j.target_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND j.target_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['install_from'])) {
            $sql .= ' AND j.installation_date >= ?';
            $params[] = $filters['install_from'];
        }
        if (!empty($filters['install_to'])) {
            $sql .= ' AND j.installation_date <= ?';
            $params[] = $filters['install_to'];
        }
        if (!empty($filters['team_id'])) {
            $sql .= ' AND (
                EXISTS (SELECT 1 FROM team_members tm WHERE tm.team_id = ? AND tm.user_id = j.assigned_to)
                OR EXISTS (SELECT 1 FROM job_tasks jt WHERE jt.job_id = j.id AND jt.assigned_team_id = ?)
            )';
            $params[] = (int) $filters['team_id'];
            $params[] = (int) $filters['team_id'];
        }
        if (!empty($filters['stage'])) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM job_production_stages jps
                INNER JOIN production_stages ps ON ps.id = jps.production_stage_id
                WHERE jps.job_id = j.id AND ps.name = ? AND jps.status <> \'COMPLETE\'
            )';
            $params[] = (string) $filters['stage'];
        }

        return $this->rows($sql . ' ORDER BY j.updated_at DESC LIMIT 200', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function board(): array
    {
        return $this->rows(
            $this->select() . ' WHERE j.archived = 0 AND j.status IN (
                \'READY_FOR_PRODUCTION\', \'IN_PRODUCTION\', \'QUALITY_CONTROL\',
                \'READY_FOR_INSTALLATION\', \'INSTALLATION_SCHEDULED\', \'COMPLETED\'
            ) ORDER BY j.target_date IS NULL, j.target_date ASC, j.id DESC LIMIT 300'
        );
    }

    /**
     * @return array<string, string>
     */
    public function operationsDesk(): array
    {
        $row = $this->one(
            'SELECT
                SUM(status NOT IN (\'COMPLETED\', \'CANCELLED\') AND target_date = CURDATE()) AS today,
                SUM(status NOT IN (\'COMPLETED\', \'CANCELLED\') AND target_date IS NOT NULL AND target_date < CURDATE()) AS overdue,
                SUM(status = \'AWAITING_ARTWORK\') AS artwork,
                SUM(status = \'AWAITING_CUSTOMER_APPROVAL\') AS approval,
                SUM(status = \'READY_FOR_PRODUCTION\') AS ready,
                SUM(status = \'IN_PRODUCTION\') AS producing,
                SUM(status = \'QUALITY_CONTROL\') AS qc,
                SUM(status = \'COMPLETED\' AND completed_at >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')) AS completed_month
             FROM jobs WHERE archived = 0'
        );
        $install = $this->one(
            'SELECT
                SUM(scheduled_date = CURDATE() AND status NOT IN (\'COMPLETE\', \'CANCELLED\')) AS today,
                SUM(scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                    AND status NOT IN (\'COMPLETE\', \'CANCELLED\')) AS week,
                SUM(status = \'RETURN_REQUIRED\') AS returns_due
             FROM job_installations'
        );

        return [
            'today' => (string) ($row['today'] ?? 0),
            'overdue' => (string) ($row['overdue'] ?? 0),
            'artwork' => (string) ($row['artwork'] ?? 0),
            'approval' => (string) ($row['approval'] ?? 0),
            'ready' => (string) ($row['ready'] ?? 0),
            'producing' => (string) ($row['producing'] ?? 0),
            'qc' => (string) ($row['qc'] ?? 0),
            'completed_month' => (string) ($row['completed_month'] ?? 0),
            'install_today' => (string) ($install['today'] ?? 0),
            'install_week' => (string) ($install['week'] ?? 0),
            'returns' => (string) ($install['returns_due'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO jobs (
                job_number, customer_id, quote_id, quote_revision_number, title,
                status, priority, assigned_to, target_date, created_by
             ) VALUES (?, ?, ?, ?, ?, \'NEW\', ?, ?, ?, ?)',
            [
                $data['job_number'], $data['customer_id'], $data['quote_id'],
                $data['quote_revision_number'], $data['title'], $data['priority'],
                $data['assigned_to'], $data['target_date'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function storeBaseline(int $id, array $data): void
    {
        $this->run(
            'UPDATE jobs SET opportunity_id = ?, description = ?, delivery_method = ?,
                quoted_revenue_snapshot = ?, quoted_cost_snapshot = ?, production_due_date = ?,
                installation_date = ?, site_address = ?, site_contact_name = ?, site_contact_phone = ?,
                customer_po_number = ?
             WHERE id = ?',
            [
                $data['opportunity_id'], $data['description'], $data['delivery_method'],
                $data['quoted_revenue'], $data['quoted_cost'], $data['production_due_date'],
                $data['installation_date'], $data['site_address'], $data['site_contact_name'],
                $data['site_contact_phone'], $data['customer_po_number'], $id,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateOverview(int $id, array $data, int $version): void
    {
        $this->run(
            'UPDATE jobs SET title = ?, description = ?, priority = ?, assigned_to = ?, project_manager_id = ?,
                target_date = ?, production_due_date = ?, installation_date = ?, delivery_method = ?,
                site_address = ?, site_contact_name = ?, site_contact_phone = ?, customer_po_number = ?,
                customer_notes = ?, production_notes = ?, installation_notes = ?, internal_notes = ?,
                version_number = version_number + 1
             WHERE id = ? AND version_number = ?',
            [
                $data['title'], $data['description'], $data['priority'], $data['assigned_to'],
                $data['project_manager_id'], $data['target_date'], $data['production_due_date'],
                $data['installation_date'], $data['delivery_method'], $data['site_address'],
                $data['site_contact_name'], $data['site_contact_phone'], $data['customer_po_number'],
                $data['customer_notes'], $data['production_notes'], $data['installation_notes'],
                $data['internal_notes'], $id, $version,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStatus(int $id, array $data, int $version): void
    {
        $this->run(
            'UPDATE jobs SET status = ?, artwork_override_by = COALESCE(?, artwork_override_by),
                artwork_override_reason = COALESCE(?, artwork_override_reason),
                artwork_override_at = COALESCE(?, artwork_override_at),
                completion_override_by = COALESCE(?, completion_override_by),
                completion_override_reason = COALESCE(?, completion_override_reason),
                completion_override_at = COALESCE(?, completion_override_at),
                completed_at = ?, completed_by = ?, version_number = version_number + 1
             WHERE id = ? AND version_number = ?',
            [
                $data['status'], $data['artwork_override_by'], $data['artwork_override_reason'],
                $data['artwork_override_at'], $data['completion_override_by'], $data['completion_override_reason'],
                $data['completion_override_at'], $data['completed_at'], $data['completed_by'], $id, $version,
            ]
        );
    }

    public function setArchived(int $id, int $archived): void
    {
        $this->run('UPDATE jobs SET archived = ?, version_number = version_number + 1 WHERE id = ?', [$archived, $id]);
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function cacheActuals(int $id, array $summary): void
    {
        $this->run(
            'UPDATE jobs SET actual_material_cost = ?, actual_labour_cost = ?, actual_other_cost = ?, actual_total_cost = ?
             WHERE id = ?',
            [
                $summary['actual_material_cost'], $summary['actual_labour_cost'],
                $summary['actual_other_cost'], $summary['actual_total_cost'], $id,
            ]
        );
    }

    public function materialTotal(int $jobId): string
    {
        $row = $this->one('SELECT COALESCE(SUM(total_cost), 0) AS n FROM job_material_usage WHERE job_id = ?', [$jobId]);

        return (string) ($row['n'] ?? '0');
    }

    public function labourTotal(int $jobId): string
    {
        $row = $this->one('SELECT COALESCE(SUM(total_cost), 0) AS n FROM job_time_entries WHERE job_id = ?', [$jobId]);

        return (string) ($row['n'] ?? '0');
    }

    public function otherTotal(int $jobId): string
    {
        $row = $this->one('SELECT COALESCE(SUM(total_cost), 0) AS n FROM job_other_costs WHERE job_id = ?', [$jobId]);

        return (string) ($row['n'] ?? '0');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materialLines(int $jobId): array
    {
        return $this->rows(
            'SELECT r.product_id, COALESCE(p.name, \'Material\') AS name, r.unit,
                    r.final_required_quantity AS quantity, \'estimated\' AS kind
             FROM job_material_requirements r
             LEFT JOIN products p ON p.id = r.product_id
             WHERE r.job_id = ?
             UNION ALL
             SELECT u.product_id, COALESCE(p.name, \'Material\') AS name, u.unit, u.quantity,
                    CASE WHEN u.usage_type IN (\'WASTE\', \'REWORK\', \'DAMAGE\', \'TEST_PRINT\') THEN \'waste\' ELSE \'production\' END AS kind
             FROM job_material_usage u
             LEFT JOIN products p ON p.id = u.product_id
             WHERE u.job_id = ?',
            [$jobId, $jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function labourLines(int $jobId): array
    {
        return $this->rows(
            'SELECT COALESCE(estimated_minutes, 0) AS estimated_minutes, 0 AS actual_minutes
             FROM job_tasks WHERE job_id = ?
             UNION ALL
             SELECT 0 AS estimated_minutes, minutes AS actual_minutes
             FROM job_time_entries WHERE job_id = ?',
            [$jobId, $jobId]
        );
    }

    public function insertHistory(int $jobId, ?string $old, string $new, ?int $userId, ?string $notes): void
    {
        $this->run(
            'INSERT INTO job_status_history (job_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)',
            [$jobId, $old, $new, $userId, $notes]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $jobId): array
    {
        return $this->rows(
            'SELECT h.*, u.name AS user_name
             FROM job_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.job_id = ? ORDER BY h.id DESC',
            [$jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(int $jobId): array
    {
        return $this->rows(
            'SELECT created_at, \'status\' AS kind, new_status AS label, notes, changed_by AS user_id
             FROM job_status_history WHERE job_id = ?
             UNION ALL
             SELECT created_at, \'audit\' AS kind, action AS label, NULL AS notes, user_id
             FROM audit_log WHERE entity_type = \'job\' AND entity_id = ?
             ORDER BY created_at DESC, kind DESC LIMIT 80',
            [$jobId, $jobId]
        );
    }

    private function select(): string
    {
        return 'SELECT j.*, c.company_name, c.first_name, c.last_name, c.customer_type,
                       c.phone AS customer_phone, c.email AS customer_email,
                       q.quote_number, u.name AS assignee_name, manager.name AS manager_name,
                       creator.name AS created_by_name,
                       (SELECT ps.name FROM job_production_stages jps
                        INNER JOIN production_stages ps ON ps.id = jps.production_stage_id
                        WHERE jps.job_id = j.id AND jps.status <> \'COMPLETE\'
                        ORDER BY jps.sort_order ASC LIMIT 1) AS current_stage
                FROM jobs j
                INNER JOIN customers c ON c.id = j.customer_id
                INNER JOIN quotes q ON q.id = j.quote_id
                LEFT JOIN users u ON u.id = j.assigned_to
                LEFT JOIN users manager ON manager.id = j.project_manager_id
                LEFT JOIN users creator ON creator.id = j.created_by';
    }
}

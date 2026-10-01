<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Decimal;

/**
 * SQL for projects, sites, and the rows that hang off them.
 * Job, invoice, and stock tables stay the source for operational facts.
 */
final class ProjectRepository extends Repository
{
    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO projects (
                project_number, customer_id, name, description, project_type_id, template_id,
                template_version_copied, status, priority, account_manager_user_id,
                project_manager_user_id, salesperson_user_id, primary_contact_id, start_date,
                original_target_date, current_target_date, currency_code, commercial_mode,
                source_opportunity_id, source_quote_id, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['project_number'], $data['customer_id'], $data['name'], $data['description'],
                $data['project_type_id'], $data['template_id'], $data['template_version_copied'],
                $data['status'], $data['priority'], $data['account_manager_user_id'],
                $data['project_manager_user_id'], $data['salesperson_user_id'], $data['primary_contact_id'],
                $data['start_date'], $data['original_target_date'], $data['current_target_date'],
                $data['currency_code'], $data['commercial_mode'], $data['source_opportunity_id'],
                $data['source_quote_id'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, int $version): bool
    {
        $this->run(
            'UPDATE projects SET name = ?, description = ?, project_type_id = ?, status = ?, priority = ?,
                account_manager_user_id = ?, project_manager_user_id = ?, salesperson_user_id = ?,
                primary_contact_id = ?, start_date = ?, current_target_date = ?, commercial_mode = ?,
                version = version + 1
             WHERE id = ? AND version = ? AND archived_at IS NULL',
            [
                $data['name'], $data['description'], $data['project_type_id'], $data['status'], $data['priority'],
                $data['account_manager_user_id'], $data['project_manager_user_id'], $data['salesperson_user_id'],
                $data['primary_contact_id'], $data['start_date'], $data['current_target_date'],
                $data['commercial_mode'], $id, $version,
            ]
        );

        return $this->affected() === 1;
    }

    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT p.*, c.company_name, c.first_name, c.last_name, c.customer_type,
                    t.name AS type_name, t.code AS type_code,
                    pm.name AS manager_name, am.name AS account_manager_name, sp.name AS salesperson_name
             FROM projects p
             JOIN customers c ON c.id = p.customer_id
             LEFT JOIN project_types t ON t.id = p.project_type_id
             LEFT JOIN users pm ON pm.id = p.project_manager_user_id
             LEFT JOIN users am ON am.id = p.account_manager_user_id
             LEFT JOIN users sp ON sp.id = p.salesperson_user_id
             WHERE p.id = ?',
            [$id]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters, int $limit, int $offset, ?int $onlyUserId): array
    {
        [$where, $params] = $this->filters($filters, $onlyUserId);

        return $this->rows(
            'SELECT p.id, p.project_number, p.name, p.status, p.project_health, p.priority,
                    p.current_target_date, p.original_target_date, p.progress_percent_cached,
                    p.commercial_value_cached, p.actual_cost_cached, p.customer_id,
                    c.company_name, c.first_name, c.last_name, c.customer_type,
                    t.name AS type_name, pm.name AS manager_name,
                    (SELECT COUNT(*) FROM project_sites s WHERE s.project_id = p.id AND s.archived_at IS NULL) AS site_count
             FROM projects p
             JOIN customers c ON c.id = p.customer_id
             LEFT JOIN project_types t ON t.id = p.project_type_id
             LEFT JOIN users pm ON pm.id = p.project_manager_user_id
             WHERE ' . $where . '
             ORDER BY p.updated_at DESC, p.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function portfolio(bool $includeMoney): array
    {
        $row = $this->one(
            'SELECT
                SUM(status = \'ACTIVE\') AS active_count,
                SUM(project_health = \'AT_RISK\') AS at_risk_count,
                SUM(project_health = \'OVERDUE\') AS overdue_count,
                SUM(status NOT IN (\'COMPLETED\', \'CANCELLED\', \'ARCHIVED\')) AS open_count
             FROM projects WHERE archived_at IS NULL'
        ) ?? [];
        if ($includeMoney) {
            $money = $this->one(
                'SELECT COALESCE(SUM(commercial_value_cached), 0) AS commercial_value,
                        COALESCE(SUM(commercial_value_cached - actual_cost_cached), 0) AS gross_profit
                 FROM projects
                 WHERE archived_at IS NULL AND status NOT IN (\'CANCELLED\', \'ARCHIVED\')'
            ) ?? [];
            $row = array_merge($row, $money);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertSite(array $data): int
    {
        $this->run(
            'INSERT INTO project_sites (
                project_id, wave_id, site_code, site_name, customer_site_reference, address_line_1,
                address_line_2, city, province, postal_code, latitude, longitude, primary_contact_id,
                group_kind, group_label, status, sequence, survey_required, original_target_date,
                current_target_date, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['project_id'], $data['wave_id'], $data['site_code'], $data['site_name'],
                $data['customer_site_reference'], $data['address_line_1'], $data['address_line_2'],
                $data['city'], $data['province'], $data['postal_code'], $data['latitude'], $data['longitude'],
                $data['primary_contact_id'], $data['group_kind'], $data['group_label'], $data['status'],
                $data['sequence'], $data['survey_required'], $data['original_target_date'],
                $data['current_target_date'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    public function site(int $id): ?array
    {
        return $this->one(
            'SELECT s.*, p.project_number, p.name AS project_name, p.customer_id, w.name AS wave_name
             FROM project_sites s
             JOIN projects p ON p.id = s.project_id
             LEFT JOIN project_waves w ON w.id = s.wave_id
             WHERE s.id = ?',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sites(int $projectId): array
    {
        return $this->rows(
            'SELECT s.*, w.name AS wave_name,
                    (SELECT COUNT(*) FROM jobs j WHERE j.project_site_id = s.id AND j.archived = 0) AS job_count
             FROM project_sites s
             LEFT JOIN project_waves w ON w.id = s.wave_id
             WHERE s.project_id = ? AND s.archived_at IS NULL
             ORDER BY s.sequence, s.site_code',
            [$projectId]
        );
    }

    public function siteCodeExists(int $projectId, string $code): bool
    {
        return $this->one(
            'SELECT id FROM project_sites WHERE project_id = ? AND site_code = ? LIMIT 1',
            [$projectId, $code]
        ) !== null;
    }

    public function referenceExists(int $projectId, string $reference): bool
    {
        return $this->one(
            'SELECT id FROM project_sites WHERE project_id = ? AND customer_site_reference = ? LIMIT 1',
            [$projectId, $reference]
        ) !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateSite(int $id, array $data, int $version): bool
    {
        $this->run(
            'UPDATE project_sites SET site_name = ?, status = ?, wave_id = ?, group_kind = ?, group_label = ?,
                current_target_date = ?, survey_required = ?, notes = ?, version = version + 1
             WHERE id = ? AND version = ?',
            [
                $data['site_name'], $data['status'], $data['wave_id'], $data['group_kind'], $data['group_label'],
                $data['current_target_date'], $data['survey_required'], $data['notes'], $id, $version,
            ]
        );

        return $this->affected() === 1;
    }

    public function setSiteStatus(int $id, string $status): void
    {
        $this->run('UPDATE project_sites SET status = ? WHERE id = ?', [$status, $id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertWave(array $data): int
    {
        $this->run(
            'INSERT INTO project_waves (project_id, name, sequence, planned_start, planned_end, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$data['project_id'], $data['name'], $data['sequence'], $data['planned_start'], $data['planned_end'], $data['status']]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function waves(int $projectId): array
    {
        return $this->rows(
            'SELECT w.*,
                (SELECT COUNT(*) FROM project_sites s WHERE s.wave_id = w.id AND s.archived_at IS NULL) AS site_count,
                (SELECT COUNT(*) FROM project_sites s WHERE s.wave_id = w.id AND s.status = \'COMPLETED\') AS complete_count
             FROM project_waves w WHERE w.project_id = ? ORDER BY w.sequence, w.id',
            [$projectId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertMilestone(array $data): int
    {
        $this->run(
            'INSERT INTO project_milestones (
                project_id, project_site_id, name, description, milestone_type, status, weight,
                original_due_date, current_due_date, depends_on_milestone_id, responsible_user_id, blocking, sequence
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['project_id'], $data['project_site_id'], $data['name'], $data['description'],
                $data['milestone_type'], $data['status'], $data['weight'], $data['original_due_date'],
                $data['current_due_date'], $data['depends_on_milestone_id'], $data['responsible_user_id'],
                $data['blocking'], $data['sequence'],
            ]
        );

        return $this->insertId();
    }

    public function milestone(int $id): ?array
    {
        return $this->one('SELECT * FROM project_milestones WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function milestones(int $projectId): array
    {
        return $this->rows(
            'SELECT m.*, u.name AS responsible_name
             FROM project_milestones m
             LEFT JOIN users u ON u.id = m.responsible_user_id
             WHERE m.project_id = ?
             ORDER BY m.sequence, m.current_due_date, m.id',
            [$projectId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateMilestone(int $id, array $data, int $version): bool
    {
        $this->run(
            'UPDATE project_milestones SET name = ?, status = ?, current_due_date = ?, weight = ?,
                blocking = ?, completed_at = ?, responsible_user_id = ?, version = version + 1
             WHERE id = ? AND version = ?',
            [
                $data['name'], $data['status'], $data['current_due_date'], $data['weight'], $data['blocking'],
                $data['completed_at'], $data['responsible_user_id'], $id, $version,
            ]
        );

        return $this->affected() === 1;
    }

    public function predecessorComplete(int $milestoneId): bool
    {
        $row = $this->one(
            'SELECT d.status FROM project_milestones m
             LEFT JOIN project_milestones d ON d.id = m.depends_on_milestone_id
             WHERE m.id = ?',
            [$milestoneId]
        );
        if ($row === null || $row['status'] === null) {
            return true;
        }

        return (string) $row['status'] === 'COMPLETE';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRisk(array $data): int
    {
        $this->run(
            'INSERT INTO project_risks (
                project_id, project_site_id, title, description, category, probability, impact, owner_user_id, mitigation, due_date
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['project_id'], $data['project_site_id'], $data['title'], $data['description'], $data['category'],
                $data['probability'], $data['impact'], $data['owner_user_id'], $data['mitigation'], $data['due_date'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function risks(int $projectId): array
    {
        return $this->rows(
            'SELECT r.*, s.site_code, u.name AS owner_name
             FROM project_risks r
             LEFT JOIN project_sites s ON s.id = r.project_site_id
             LEFT JOIN users u ON u.id = r.owner_user_id
             WHERE r.project_id = ? ORDER BY (r.probability * r.impact) DESC, r.id DESC',
            [$projectId]
        );
    }

    public function resolveRisk(int $id, int $version): bool
    {
        $this->run(
            'UPDATE project_risks SET status = \'RESOLVED\', resolved_at = NOW(), version = version + 1
             WHERE id = ? AND version = ? AND status <> \'RESOLVED\'',
            [$id, $version]
        );

        return $this->affected() === 1;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertIssue(array $data): int
    {
        $this->run(
            'INSERT INTO project_issues (
                project_id, project_site_id, job_id, milestone_id, title, description, category, owner_user_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['project_id'], $data['project_site_id'], $data['job_id'], $data['milestone_id'],
                $data['title'], $data['description'], $data['category'], $data['owner_user_id'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function issues(int $projectId): array
    {
        return $this->rows(
            'SELECT i.*, s.site_code, j.job_number
             FROM project_issues i
             LEFT JOIN project_sites s ON s.id = i.project_site_id
             LEFT JOIN jobs j ON j.id = i.job_id
             WHERE i.project_id = ? ORDER BY i.id DESC',
            [$projectId]
        );
    }

    public function setIssueStatus(int $id, string $status, int $version): bool
    {
        $resolved = in_array($status, ['RESOLVED', 'CLOSED'], true) ? date('Y-m-d H:i:s') : null;
        $this->run(
            'UPDATE project_issues SET status = ?, resolved_at = ?, version = version + 1 WHERE id = ? AND version = ?',
            [$status, $resolved, $id, $version]
        );

        return $this->affected() === 1;
    }

    public function issue(int $id): ?array
    {
        return $this->one('SELECT * FROM project_issues WHERE id = ?', [$id]);
    }

    public function risk(int $id): ?array
    {
        return $this->one('SELECT * FROM project_risks WHERE id = ?', [$id]);
    }

    public function insertNote(int $projectId, ?int $siteId, string $visibility, string $body, int $userId): void
    {
        $this->run(
            'INSERT INTO project_notes (project_id, project_site_id, visibility, body, created_by) VALUES (?, ?, ?, ?, ?)',
            [$projectId, $siteId, $visibility, $body, $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notes(int $projectId, bool $customerVisibleOnly): array
    {
        $sql = 'SELECT n.*, u.name AS author_name FROM project_notes n
                LEFT JOIN users u ON u.id = n.created_by WHERE n.project_id = ?';
        if ($customerVisibleOnly) {
            $sql .= ' AND n.visibility = \'CUSTOMER\'';
        }

        return $this->rows($sql . ' ORDER BY n.id DESC', [$projectId]);
    }

    public function insertCost(int $projectId, string $category, string $description, string $amount, string $date, int $userId): int
    {
        $this->run(
            'INSERT INTO project_direct_costs (project_id, category, description, amount, cost_date, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$projectId, $category, $description, $amount, $date, $userId]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function costs(int $projectId): array
    {
        return $this->rows(
            'SELECT * FROM project_direct_costs WHERE project_id = ? ORDER BY cost_date DESC, id DESC',
            [$projectId]
        );
    }

    public function saveBudget(int $projectId, string $category, string $amount, ?string $notes): void
    {
        $this->run(
            'INSERT INTO project_budget_lines (project_id, category, amount, notes) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), notes = VALUES(notes)',
            [$projectId, $category, $amount, $notes]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function budgets(int $projectId): array
    {
        return $this->rows('SELECT * FROM project_budget_lines WHERE project_id = ? ORDER BY category', [$projectId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertChange(array $data): int
    {
        $this->run(
            'INSERT INTO project_changes (
                project_id, change_number, title, description, status, commercial_impact, cost_impact,
                schedule_impact_days, sites_affected, approval_request_id, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['project_id'], $data['change_number'], $data['title'], $data['description'], $data['status'],
                $data['commercial_impact'], $data['cost_impact'], $data['schedule_impact_days'],
                $data['sites_affected'], $data['approval_request_id'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function changes(int $projectId): array
    {
        return $this->rows('SELECT * FROM project_changes WHERE project_id = ? ORDER BY id DESC', [$projectId]);
    }

    public function change(int $id): ?array
    {
        return $this->one('SELECT * FROM project_changes WHERE id = ?', [$id]);
    }

    public function setChangeStatus(int $id, string $status, int $version): bool
    {
        $this->run(
            'UPDATE project_changes SET status = ?, version = version + 1 WHERE id = ? AND version = ?',
            [$status, $id, $version]
        );

        return $this->affected() === 1;
    }

    public function nextChangeNumber(int $projectId): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM project_changes WHERE project_id = ?', [$projectId]);

        return ((int) ($row['n'] ?? 0)) + 1;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCommercial(array $data): int
    {
        $this->run(
            'INSERT INTO project_commercial_lines (
                project_id, source_type, source_id, project_site_id, job_id, label, amount, counts_as_value, superseded
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['project_id'], $data['source_type'], $data['source_id'], $data['project_site_id'],
                $data['job_id'], $data['label'], $data['amount'], $data['counts_as_value'], $data['superseded'],
            ]
        );

        return $this->insertId();
    }

    public function supersedeCommercial(int $id): void
    {
        $this->run('UPDATE project_commercial_lines SET superseded = 1, counts_as_value = 0 WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function commercialLines(int $projectId, bool $valuedOnly): array
    {
        $sql = 'SELECT * FROM project_commercial_lines WHERE project_id = ?';
        if ($valuedOnly) {
            $sql .= ' AND counts_as_value = 1 AND superseded = 0';
        }

        return $this->rows($sql . ' ORDER BY id', [$projectId]);
    }

    public function sumCommercial(int $projectId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM project_commercial_lines
             WHERE project_id = ? AND counts_as_value = 1 AND superseded = 0',
            [$projectId]
        );

        return $this->money($row['total'] ?? null);
    }

    public function additionalJobRevenue(int $projectId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(quoted_revenue_snapshot), 0) AS total FROM jobs
             WHERE project_id = ? AND archived = 0 AND value_treatment = \'ADDITIONAL\'',
            [$projectId]
        );

        return $this->money($row['total'] ?? null);
    }

    public function approvedVariationTotal(int $projectId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(v.total), 0) AS total
             FROM job_variations v
             JOIN jobs j ON j.id = v.job_id
             WHERE j.project_id = ? AND j.archived = 0 AND (v.status = \'APPROVED\' OR v.customer_approved = 1)
               AND NOT EXISTS (
                    SELECT 1 FROM project_commercial_lines l
                    WHERE l.source_type = \'JOB_VARIATION\' AND l.source_id = v.id AND l.superseded = 0
               )',
            [$projectId]
        );

        return $this->money($row['total'] ?? null);
    }

    public function jobCostSum(int $projectId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(actual_total_cost), 0) AS total FROM jobs WHERE project_id = ? AND archived = 0',
            [$projectId]
        );

        return $this->money($row['total'] ?? null);
    }

    public function estimatedJobCost(int $projectId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(quoted_cost_snapshot), 0) AS total FROM jobs WHERE project_id = ? AND archived = 0',
            [$projectId]
        );

        return $this->money($row['total'] ?? null);
    }

    public function directCostSum(int $projectId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM project_direct_costs WHERE project_id = ?',
            [$projectId]
        );

        return $this->money($row['total'] ?? null);
    }

    public function budgetSum(int $projectId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM project_budget_lines WHERE project_id = ?',
            [$projectId]
        );

        return $this->money($row['total'] ?? null);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertDateChange(array $data): void
    {
        $this->run(
            'INSERT INTO project_date_changes (
                project_id, entity_type, entity_id, original_date, previous_date, new_date, reason_code, notes, changed_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['project_id'], $data['entity_type'], $data['entity_id'], $data['original_date'],
                $data['previous_date'], $data['new_date'], $data['reason_code'], $data['notes'], $data['changed_by'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dateChanges(int $projectId): array
    {
        return $this->rows(
            'SELECT * FROM project_date_changes WHERE project_id = ? ORDER BY id DESC LIMIT 50',
            [$projectId]
        );
    }

    public function shiftProjectDate(int $id, ?string $current, int $version): bool
    {
        $this->run(
            'UPDATE projects SET current_target_date = ?, version = version + 1 WHERE id = ? AND version = ?',
            [$current, $id, $version]
        );

        return $this->affected() === 1;
    }

    public function shiftSiteDate(int $id, ?string $current, int $version): bool
    {
        $this->run(
            'UPDATE project_sites SET current_target_date = ?, version = version + 1 WHERE id = ? AND version = ?',
            [$current, $id, $version]
        );

        return $this->affected() === 1;
    }

    public function assignTeam(int $projectId, int $userId, string $role): void
    {
        $this->run(
            'INSERT IGNORE INTO project_team (project_id, user_id, role_code) VALUES (?, ?, ?)',
            [$projectId, $userId, $role]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function team(int $projectId): array
    {
        return $this->rows(
            'SELECT t.*, u.name FROM project_team t JOIN users u ON u.id = t.user_id WHERE t.project_id = ? ORDER BY t.role_code',
            [$projectId]
        );
    }

    public function assignContact(int $projectId, ?int $siteId, int $contactId, string $role): void
    {
        $this->run(
            'INSERT IGNORE INTO project_contacts (project_id, project_site_id, contact_id, role_code) VALUES (?, ?, ?, ?)',
            [$projectId, $siteId, $contactId, $role]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contacts(int $projectId): array
    {
        return $this->rows(
            'SELECT pc.role_code, pc.project_site_id, c.id, c.name, c.email, c.phone, c.mobile
             FROM project_contacts pc
             JOIN customer_contacts c ON c.id = pc.contact_id
             WHERE pc.project_id = ?
             ORDER BY pc.role_code, c.name',
            [$projectId]
        );
    }

    public function insertContact(int $customerId, string $name, ?string $email, ?string $phone): int
    {
        $this->run(
            'INSERT INTO customer_contacts (customer_id, name, email, phone, primary_contact) VALUES (?, ?, ?, ?, 0)',
            [$customerId, $name, $email, $phone]
        );

        return $this->insertId();
    }

    public function attachJob(int $jobId, int $projectId, ?int $siteId, string $treatment, string $rollout, ?string $address, ?string $revenue): void
    {
        $this->run(
            'UPDATE jobs SET project_id = ?, project_site_id = ?, value_treatment = ?, rollout_state = ?,
                site_address = COALESCE(?, site_address),
                quoted_revenue_snapshot = COALESCE(?, quoted_revenue_snapshot)
             WHERE id = ?',
            [$projectId, $siteId, $treatment, $rollout, $address, $revenue, $jobId]
        );
    }

    public function insertDraftQuote(string $number, int $customerId, int $projectId, ?int $siteId, string $notes, int $userId): int
    {
        $this->run(
            'INSERT INTO quotes (quote_number, customer_id, quote_date, status, project_id, project_site_id, internal_notes, created_by)
             VALUES (?, ?, CURDATE(), \'DRAFT\', ?, ?, ?, ?)',
            [$number, $customerId, $projectId, $siteId, $notes, $userId]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jobs(int $projectId, ?int $siteId): array
    {
        $sql = 'SELECT id, job_number, title, status, rollout_state, value_treatment, target_date,
                       quoted_revenue_snapshot, quoted_cost_snapshot, actual_total_cost, project_site_id, quote_id
                FROM jobs WHERE project_id = ? AND archived = 0';
        $params = [$projectId];
        if ($siteId !== null) {
            $sql .= ' AND project_site_id = ?';
            $params[] = $siteId;
        }

        return $this->rows($sql . ' ORDER BY id', $params);
    }

    /**
     * @return array<string, int|string>
     */
    public function counts(int $projectId): array
    {
        $sites = $this->one(
            'SELECT COUNT(*) AS sites,
                    SUM(status = \'COMPLETED\') AS sites_complete,
                    SUM(status NOT IN (\'COMPLETED\', \'CANCELLED\') AND current_target_date IS NOT NULL AND current_target_date < CURDATE()) AS sites_late
             FROM project_sites WHERE project_id = ? AND archived_at IS NULL',
            [$projectId]
        ) ?? [];
        $jobs = $this->one(
            'SELECT COUNT(*) AS jobs,
                    SUM(status = \'COMPLETED\') AS jobs_complete,
                    SUM(status IN (\'IN_PRODUCTION\', \'QUALITY_CONTROL\', \'READY_FOR_PRODUCTION\', \'MATERIALS_REQUIRED\')) AS jobs_production
             FROM jobs WHERE project_id = ? AND archived = 0',
            [$projectId]
        ) ?? [];

        return [
            'sites' => (int) ($sites['sites'] ?? 0),
            'sites_complete' => (int) ($sites['sites_complete'] ?? 0),
            'sites_late' => (int) ($sites['sites_late'] ?? 0),
            'jobs' => (int) ($jobs['jobs'] ?? 0),
            'jobs_complete' => (int) ($jobs['jobs_complete'] ?? 0),
            'jobs_production' => (int) ($jobs['jobs_production'] ?? 0),
        ];
    }

    public function nextMilestone(int $projectId): ?array
    {
        return $this->one(
            'SELECT * FROM project_milestones
             WHERE project_id = ? AND status NOT IN (\'COMPLETE\', \'CANCELLED\')
             ORDER BY current_due_date IS NULL, current_due_date, sequence LIMIT 1',
            [$projectId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attentionSites(int $projectId): array
    {
        return $this->rows(
            'SELECT id, site_code, site_name, status, current_target_date FROM project_sites
             WHERE project_id = ? AND archived_at IS NULL
               AND (status IN (\'ON_HOLD\', \'SNAGGED\', \'AWAITING_APPROVAL\', \'AWAITING_ARTWORK\')
                    OR (current_target_date IS NOT NULL AND current_target_date < CURDATE() AND status NOT IN (\'COMPLETED\', \'CANCELLED\')))
             ORDER BY current_target_date LIMIT 12',
            [$projectId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function attentionJobs(int $projectId): array
    {
        return $this->rows(
            'SELECT id, job_number, title, status, target_date FROM jobs
             WHERE project_id = ? AND archived = 0
               AND (status IN (\'ON_HOLD\', \'AWAITING_ARTWORK\', \'AWAITING_CUSTOMER_APPROVAL\', \'MATERIALS_REQUIRED\')
                    OR (target_date IS NOT NULL AND target_date < CURDATE() AND status NOT IN (\'COMPLETED\', \'CANCELLED\')))
             ORDER BY target_date LIMIT 12',
            [$projectId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activity(int $projectId, string $filter): array
    {
        $sql = 'SELECT a.created_at, a.action, a.entity_type, a.entity_id, u.name AS user_name
                FROM audit_log a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE (a.entity_type = \'project\' AND a.entity_id = ?)
                   OR (a.entity_type = \'project_site\' AND a.entity_id IN (SELECT id FROM project_sites WHERE project_id = ?))';
        $params = [$projectId, $projectId];
        $needle = match ($filter) {
            'COMMERCIAL' => 'COMMERCIAL',
            'SITE' => 'SITE',
            'PRODUCTION' => 'PRODUCTION',
            'INSTALLATION' => 'INSTALL',
            'FINANCE' => 'FINANCE',
            'DOCUMENTS' => 'DOCUMENT',
            'SYSTEM' => 'SYSTEM',
            default => '',
        };
        if ($needle !== '') {
            $sql .= ' AND a.action LIKE ?';
            $params[] = '%' . $needle . '%';
        }

        return $this->rows($sql . ' ORDER BY a.id DESC LIMIT 80', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materialDemand(int $projectId, ?int $siteId, ?int $waveId): array
    {
        $sql = 'SELECT r.product_id, p.name AS product_name, r.unit, SUM(r.final_required_quantity) AS required_qty
                FROM job_material_requirements r
                JOIN jobs j ON j.id = r.job_id
                LEFT JOIN products p ON p.id = r.product_id
                LEFT JOIN project_sites s ON s.id = j.project_site_id
                WHERE j.project_id = ? AND j.archived = 0';
        $params = [$projectId];
        if ($siteId !== null && $siteId > 0) {
            $sql .= ' AND j.project_site_id = ?';
            $params[] = $siteId;
        }
        if ($waveId !== null && $waveId > 0) {
            $sql .= ' AND s.wave_id = ?';
            $params[] = $waveId;
        }

        return $this->rows($sql . ' GROUP BY r.product_id, p.name, r.unit ORDER BY p.name', $params);
    }

    /**
     * @return array<string, int|string>
     */
    public function purchaseSummary(int $projectId): array
    {
        $row = $this->one(
            'SELECT COUNT(*) AS requests,
                    SUM(pr.status = \'REQUESTED\') AS open_requests
             FROM purchase_requests pr
             JOIN jobs j ON j.id = pr.job_id
             WHERE j.project_id = ?',
            [$projectId]
        );

        return [
            'requests' => (int) ($row['requests'] ?? 0),
            'open_requests' => (int) ($row['open_requests'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function productionCounts(int $projectId): array
    {
        return $this->rows(
            'SELECT status, COUNT(*) AS n FROM jobs WHERE project_id = ? AND archived = 0 GROUP BY status ORDER BY status',
            [$projectId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installationCounts(int $projectId): array
    {
        return $this->rows(
            'SELECT i.status, COUNT(*) AS n
             FROM job_installations i
             JOIN jobs j ON j.id = i.job_id
             WHERE j.project_id = ? AND j.archived = 0
             GROUP BY i.status',
            [$projectId]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snagSummary(int $projectId): array
    {
        $row = $this->one(
            'SELECT COUNT(*) AS open_snags,
                    SUM(s.priority = \'CRITICAL\' OR s.priority = \'URGENT\') AS critical_snags,
                    MIN(s.created_at) AS oldest
             FROM job_snags s
             JOIN jobs j ON j.id = s.job_id
             WHERE j.project_id = ? AND s.status NOT IN (\'RESOLVED\', \'CLOSED\')',
            [$projectId]
        ) ?? [];

        return [
            'open_snags' => (int) ($row['open_snags'] ?? 0),
            'critical_snags' => (int) ($row['critical_snags'] ?? 0),
            'oldest' => $row['oldest'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function invoiceSummary(int $projectId): array
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(i.total), 0) AS invoiced
             FROM invoices i
             WHERE i.status NOT IN (\'DRAFT\', \'CANCELLED\', \'VOID\')
               AND (i.project_id = ? OR i.job_id IN (SELECT id FROM jobs WHERE project_id = ?))',
            [$projectId, $projectId]
        );
        $paid = $this->one(
            'SELECT COALESCE(SUM(a.amount), 0) AS paid
             FROM payment_allocations a
             JOIN invoices i ON i.id = a.invoice_id
             WHERE a.reversed_at IS NULL
               AND i.status NOT IN (\'DRAFT\', \'CANCELLED\', \'VOID\')
               AND (i.project_id = ? OR i.job_id IN (SELECT id FROM jobs WHERE project_id = ?))',
            [$projectId, $projectId]
        );

        return [
            'invoiced' => $this->money($row['invoiced'] ?? null),
            'paid' => $this->money($paid['paid'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function gantt(int $projectId): array
    {
        return [
            'milestones' => $this->milestones($projectId),
            'waves' => $this->waves($projectId),
            'sites' => $this->rows(
                'SELECT id, site_code, site_name, status, original_target_date, current_target_date, wave_id
                 FROM project_sites WHERE project_id = ? AND archived_at IS NULL ORDER BY sequence LIMIT 80',
                [$projectId]
            ),
            'task_rows' => 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function calendar(int $projectId, string $from, string $to): array
    {
        return $this->rows(
            'SELECT current_due_date AS event_date, \'MILESTONE\' AS kind, name AS label, id
             FROM project_milestones
             WHERE project_id = ? AND current_due_date BETWEEN ? AND ?
             UNION ALL
             SELECT scheduled_date, \'INSTALLATION\', j.job_number, i.id
             FROM job_installations i
             JOIN jobs j ON j.id = i.job_id
             WHERE j.project_id = ? AND i.scheduled_date BETWEEN ? AND ?
             UNION ALL
             SELECT survey_date, \'SURVEY\', site_name, id
             FROM site_surveys
             WHERE project_site_id IN (SELECT id FROM project_sites WHERE project_id = ?)
               AND survey_date BETWEEN ? AND ?
             ORDER BY event_date LIMIT 200',
            [$projectId, $from, $to, $projectId, $from, $to, $projectId, $from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mapSites(int $projectId, int $limit): array
    {
        return $this->rows(
            'SELECT id, site_code, site_name, status, latitude, longitude, city
             FROM project_sites
             WHERE project_id = ? AND archived_at IS NULL AND latitude IS NOT NULL AND longitude IS NOT NULL
             ORDER BY sequence LIMIT ' . max(1, min(200, $limit)),
            [$projectId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function types(): array
    {
        return $this->rows('SELECT * FROM project_types WHERE active = 1 ORDER BY name');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(): array
    {
        return $this->rows(
            'SELECT t.*, pt.name AS type_name FROM project_templates t
             LEFT JOIN project_types pt ON pt.id = t.project_type_id
             WHERE t.active = 1 ORDER BY t.name'
        );
    }

    public function template(int $id): ?array
    {
        return $this->one('SELECT * FROM project_templates WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templateMilestones(int $templateId): array
    {
        return $this->rows(
            'SELECT * FROM project_template_milestones WHERE template_id = ? ORDER BY sequence',
            [$templateId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function delayReasons(): array
    {
        return $this->rows('SELECT * FROM delay_reasons WHERE active = 1 ORDER BY name');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        return $this->rows(
            'SELECT id, project_number, name, status, project_health, commercial_value_cached, current_target_date
             FROM projects WHERE customer_id = ? AND archived_at IS NULL ORDER BY id DESC',
            [$customerId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function label(int $projectId, int $siteId): ?array
    {
        $project = $this->one('SELECT id, project_number, name FROM projects WHERE id = ?', [$projectId]);
        if ($project === null) {
            return null;
        }
        if ($siteId > 0) {
            $site = $this->one('SELECT id, site_code, site_name, wave_id FROM project_sites WHERE id = ? AND project_id = ?', [$siteId, $projectId]);
            $project['site'] = $site;
            if ($site !== null && $site['wave_id'] !== null) {
                $project['wave'] = $this->one('SELECT id, name FROM project_waves WHERE id = ?', [(int) $site['wave_id']]);
            }
        }

        return $project;
    }

    public function userAssigned(int $projectId, int $userId): bool
    {
        $row = $this->one(
            'SELECT p.id FROM projects p
             WHERE p.id = ?
               AND (
                    p.project_manager_user_id = ? OR p.account_manager_user_id = ? OR p.salesperson_user_id = ?
                    OR EXISTS (SELECT 1 FROM project_team t WHERE t.project_id = p.id AND t.user_id = ?)
                    OR EXISTS (SELECT 1 FROM jobs j WHERE j.project_id = p.id AND (j.assigned_to = ? OR j.project_manager_id = ?))
               )',
            [$projectId, $userId, $userId, $userId, $userId, $userId, $userId]
        );

        return $row !== null;
    }

    public function cacheFigures(int $id, ?string $commercial, ?string $estimated, ?string $actual, ?string $progress, ?string $health, ?string $reasons): void
    {
        $this->run(
            'UPDATE projects SET commercial_value_cached = ?, estimated_cost_cached = ?, actual_cost_cached = ?,
                progress_percent_cached = ?, project_health = ?, health_reasons = ?
             WHERE id = ?',
            [$commercial, $estimated, $actual, $progress, $health, $reasons, $id]
        );
    }

    public function complete(int $id, int $userId, string $notes, string $date): void
    {
        $this->run(
            'UPDATE projects SET status = \'COMPLETED\', actual_completion_date = ?, completed_at = NOW(),
                completed_by = ?, completion_notes = ?, project_health = \'COMPLETE\', version = version + 1
             WHERE id = ?',
            [$date, $userId, $notes, $id]
        );
    }

    public function archive(int $id): void
    {
        $this->run(
            'UPDATE projects SET status = \'ARCHIVED\', archived_at = NOW(), version = version + 1 WHERE id = ?',
            [$id]
        );
    }

    public function linkQuote(int $quoteId, int $projectId, ?int $siteId): void
    {
        $this->run('UPDATE quotes SET project_id = ?, project_site_id = ? WHERE id = ?', [$projectId, $siteId, $quoteId]);
    }

    public function linkOpportunity(int $opportunityId, int $projectId): void
    {
        $this->run('UPDATE sales_opportunities SET project_id = ? WHERE id = ?', [$projectId, $opportunityId]);
    }

    public function linkSurvey(int $surveyId, int $siteId): void
    {
        $this->run(
            'UPDATE site_surveys SET project_site_id = ? WHERE id = ?',
            [$siteId, $surveyId]
        );
        $this->run('UPDATE project_sites SET survey_id = ?, survey_status = (SELECT status FROM site_surveys WHERE id = ?) WHERE id = ?', [$surveyId, $surveyId, $siteId]);
    }

    public function recordDocument(int $projectId, ?int $siteId, int $attachmentId, string $category, bool $share, bool $customerVisible): void
    {
        $this->run(
            'INSERT INTO project_documents (project_id, project_site_id, attachment_id, category, share_with_jobs, customer_visible)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$projectId, $siteId, $attachmentId, $category, $share ? 1 : 0, $customerVisible ? 1 : 0]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documents(int $projectId, bool $customerOnly): array
    {
        $sql = 'SELECT d.*, a.original_filename, a.mime_type, a.created_at AS uploaded_at
                FROM project_documents d
                JOIN attachments a ON a.id = d.attachment_id
                WHERE d.project_id = ?';
        if ($customerOnly) {
            $sql .= ' AND d.customer_visible = 1';
        }

        return $this->rows($sql . ' ORDER BY d.id DESC', [$projectId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sharedDocumentsForJob(int $projectId): array
    {
        return $this->rows(
            'SELECT d.category, a.original_filename, a.id AS attachment_id
             FROM project_documents d
             JOIN attachments a ON a.id = d.attachment_id
             WHERE d.project_id = ? AND d.share_with_jobs = 1',
            [$projectId]
        );
    }

    public function incompleteSites(int $projectId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM project_sites
             WHERE project_id = ? AND archived_at IS NULL AND status NOT IN (\'COMPLETED\', \'CANCELLED\')',
            [$projectId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function incompleteJobs(int $projectId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM jobs
             WHERE project_id = ? AND archived = 0 AND status NOT IN (\'COMPLETED\', \'CANCELLED\')',
            [$projectId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function openRisks(int $projectId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM project_risks WHERE project_id = ? AND status = \'OPEN\'',
            [$projectId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function openIssues(int $projectId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM project_issues WHERE project_id = ? AND status IN (\'OPEN\', \'IN_PROGRESS\')',
            [$projectId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function openPurchaseCommitments(int $projectId): int
    {
        return (int) ($this->purchaseSummary($projectId)['open_requests'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueMilestones(int $withinDays): array
    {
        return $this->rows(
            'SELECT m.*, p.project_number, p.project_manager_user_id
             FROM project_milestones m
             JOIN projects p ON p.id = m.project_id
             WHERE m.status NOT IN (\'COMPLETE\', \'CANCELLED\')
               AND m.current_due_date IS NOT NULL
               AND m.current_due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND p.archived_at IS NULL
               AND p.status NOT IN (\'COMPLETED\', \'CANCELLED\', \'ARCHIVED\')',
            [$withinDays]
        );
    }

    public function maxSiteSequence(int $projectId): int
    {
        $row = $this->one('SELECT COALESCE(MAX(sequence), 0) AS n FROM project_sites WHERE project_id = ?', [$projectId]);

        return (int) ($row['n'] ?? 0);
    }

    private function money(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '' || !Decimal::isNumeric($raw)) {
            return '0.00';
        }

        return Decimal::round($raw, 2);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function filters(array $filters, ?int $onlyUserId): array
    {
        $where = ['p.archived_at IS NULL'];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'p.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['health'] ?? '') !== '') {
            $where[] = 'p.project_health = ?';
            $params[] = $filters['health'];
        }
        if ((int) ($filters['manager'] ?? 0) > 0) {
            $where[] = 'p.project_manager_user_id = ?';
            $params[] = (int) $filters['manager'];
        }
        if ((int) ($filters['customer_id'] ?? 0) > 0) {
            $where[] = 'p.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if ((int) ($filters['type_id'] ?? 0) > 0) {
            $where[] = 'p.project_type_id = ?';
            $params[] = (int) $filters['type_id'];
        }
        if ((int) ($filters['salesperson'] ?? 0) > 0) {
            $where[] = 'p.salesperson_user_id = ?';
            $params[] = (int) $filters['salesperson'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.project_number LIKE ? OR p.name LIKE ? OR c.company_name LIKE ?
                OR EXISTS (SELECT 1 FROM project_sites s WHERE s.project_id = p.id AND (s.site_code LIKE ? OR s.site_name LIKE ? OR s.address_line_1 LIKE ?))
                OR EXISTS (SELECT 1 FROM jobs j WHERE j.project_id = p.id AND j.job_number LIKE ?))';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like, $like, $like);
        }
        if ($onlyUserId !== null) {
            $where[] = '(p.project_manager_user_id = ? OR p.account_manager_user_id = ? OR p.salesperson_user_id = ?
                OR EXISTS (SELECT 1 FROM project_team t WHERE t.project_id = p.id AND t.user_id = ?)
                OR EXISTS (SELECT 1 FROM jobs j WHERE j.project_id = p.id AND (j.assigned_to = ? OR j.project_manager_id = ?)))';
            array_push($params, $onlyUserId, $onlyUserId, $onlyUserId, $onlyUserId, $onlyUserId, $onlyUserId);
        }

        return [implode(' AND ', $where), $params];
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Operational rows that hang off a job: items, tasks, artwork, materials,
 * time, quality checks, and installations.
 */
final class OperationsRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $jobId): array
    {
        return $this->rows('SELECT * FROM job_items WHERE job_id = ? ORDER BY sort_order ASC, id ASC', [$jobId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertItem(array $data): int
    {
        $this->run(
            'INSERT INTO job_items (
                job_id, quote_item_id, sort_order, product_id, description, internal_description,
                width_mm, height_mm, length_mm, quantity, production_status, artwork_required, installation_required, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['quote_item_id'], $data['sort_order'], $data['product_id'],
                $data['description'], $data['internal_description'], $data['width_mm'], $data['height_mm'],
                $data['length_mm'], $data['quantity'], $data['production_status'], $data['artwork_required'],
                $data['installation_required'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    public function updateItemStatus(int $id, int $jobId, string $status): void
    {
        $this->run('UPDATE job_items SET production_status = ? WHERE id = ? AND job_id = ?', [$status, $id, $jobId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tasks(int $jobId): array
    {
        return $this->rows(
            'SELECT t.*, u.name AS assignee_name, tm.name AS team_name
             FROM job_tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             LEFT JOIN teams tm ON tm.id = t.assigned_team_id
             WHERE t.job_id = ? ORDER BY t.sort_order ASC, t.id ASC',
            [$jobId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function task(int $id): ?array
    {
        return $this->one('SELECT * FROM job_tasks WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTask(array $data): int
    {
        $this->run(
            'INSERT INTO job_tasks (
                job_id, job_item_id, title, description, task_type, assigned_to, assigned_team_id,
                priority, status, due_date, estimated_minutes, sort_order, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['title'], $data['description'], $data['task_type'],
                $data['assigned_to'], $data['assigned_team_id'], $data['priority'], $data['status'],
                $data['due_date'], $data['estimated_minutes'], $data['sort_order'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTask(int $id, int $jobId, array $data, int $version): int
    {
        $this->run(
            'UPDATE job_tasks SET status = ?, assigned_to = ?, started_at = ?, completed_at = ?,
                actual_minutes = ?, version_number = version_number + 1
             WHERE id = ? AND job_id = ? AND version_number = ?',
            [
                $data['status'], $data['assigned_to'], $data['started_at'], $data['completed_at'],
                $data['actual_minutes'], $id, $jobId, $version,
            ]
        );

        return $this->affected();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function workshop(int $limit = 80): array
    {
        return $this->rows(
            'SELECT t.*, j.job_number, j.title AS job_title, j.priority AS job_priority, j.target_date,
                    c.company_name, c.first_name, c.last_name, c.customer_type, i.description AS item_description
             FROM job_tasks t
             INNER JOIN jobs j ON j.id = t.job_id
             INNER JOIN customers c ON c.id = j.customer_id
             LEFT JOIN job_items i ON i.id = t.job_item_id
             WHERE j.archived = 0 AND j.status NOT IN (\'COMPLETED\', \'CANCELLED\')
               AND t.status IN (\'TODO\', \'READY\', \'IN_PROGRESS\', \'BLOCKED\')
             ORDER BY t.due_date IS NULL, t.due_date ASC, t.id ASC
             LIMIT ' . (int) $limit
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function schedule(): array
    {
        return $this->rows(
            'SELECT t.*, j.job_number, j.priority AS job_priority, c.company_name, c.first_name, c.last_name, c.customer_type,
                    u.name AS assignee_name, tm.name AS team_name
             FROM job_tasks t
             INNER JOIN jobs j ON j.id = t.job_id
             INNER JOIN customers c ON c.id = j.customer_id
             LEFT JOIN users u ON u.id = t.assigned_to
             LEFT JOIN teams tm ON tm.id = t.assigned_team_id
             WHERE j.archived = 0 AND t.status NOT IN (\'COMPLETE\', \'CANCELLED\')
               AND t.due_date IS NOT NULL
             ORDER BY t.due_date ASC, t.id ASC LIMIT 200'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stages(int $jobId): array
    {
        return $this->rows(
            'SELECT jps.*, ps.name AS stage_name
             FROM job_production_stages jps
             INNER JOIN production_stages ps ON ps.id = jps.production_stage_id
             WHERE jps.job_id = ? ORDER BY jps.sort_order ASC, jps.id ASC',
            [$jobId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function stage(int $id): ?array
    {
        return $this->one(
            'SELECT jps.*, ps.name AS stage_name FROM job_production_stages jps
             INNER JOIN production_stages ps ON ps.id = jps.production_stage_id
             WHERE jps.id = ? LIMIT 1',
            [$id]
        );
    }

    public function insertStage(int $jobId, ?int $itemId, int $stageId, int $sort): int
    {
        $this->run(
            'INSERT INTO job_production_stages (job_id, job_item_id, production_stage_id, sort_order, status)
             VALUES (?, ?, ?, ?, \'NOT_STARTED\')',
            [$jobId, $itemId, $stageId, $sort]
        );

        return $this->insertId();
    }

    public function updateStage(int $id, int $jobId, string $status, ?string $started, ?string $completed, ?int $userId): void
    {
        $this->run(
            'UPDATE job_production_stages SET status = ?, started_at = ?, completed_at = ?, assigned_to = COALESCE(?, assigned_to)
             WHERE id = ? AND job_id = ?',
            [$status, $started, $completed, $userId, $id, $jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(): array
    {
        return $this->rows('SELECT * FROM production_route_templates WHERE active = 1 ORDER BY name ASC');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templateStages(int $templateId): array
    {
        return $this->rows(
            'SELECT ts.*, ps.name
             FROM production_route_template_stages ts
             INNER JOIN production_stages ps ON ps.id = ts.production_stage_id
             WHERE ts.template_id = ? ORDER BY ts.sort_order ASC',
            [$templateId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function artworks(int $jobId): array
    {
        return $this->rows(
            'SELECT a.*, u.name AS uploader_name
             FROM job_artworks a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.job_id = ? ORDER BY a.title ASC, a.revision_number DESC',
            [$jobId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function artwork(int $id): ?array
    {
        return $this->one('SELECT * FROM job_artworks WHERE id = ? LIMIT 1', [$id]);
    }

    public function nextArtworkRevision(int $jobId, string $title): int
    {
        $row = $this->one(
            'SELECT COALESCE(MAX(revision_number), 0) AS n FROM job_artworks WHERE job_id = ? AND title = ?',
            [$jobId, $title]
        );

        return ((int) ($row['n'] ?? 0)) + 1;
    }

    public function supersedeTitle(int $jobId, string $title, int $exceptId): void
    {
        $this->run(
            'UPDATE job_artworks SET status = \'SUPERSEDED\'
             WHERE job_id = ? AND title = ? AND id <> ? AND status <> \'SUPERSEDED\'',
            [$jobId, $title, $exceptId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertArtwork(array $data): int
    {
        $this->run(
            'INSERT INTO job_artworks (
                job_id, job_item_id, title, revision_number, original_filename, stored_filename,
                mime_type, file_size, status, uploaded_by, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['title'], $data['revision_number'],
                $data['original_filename'], $data['stored_filename'], $data['mime_type'], $data['file_size'],
                $data['status'], $data['uploaded_by'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    public function markArtworkApproved(int $id, string $name): void
    {
        $this->run(
            'UPDATE job_artworks SET status = \'APPROVED\', customer_approved = 1, customer_approved_at = NOW(),
                customer_approved_by = ?, version_number = version_number + 1 WHERE id = ?',
            [$name, $id]
        );
    }

    public function setArtworkStatus(int $id, string $status, int $version): int
    {
        $this->run(
            'UPDATE job_artworks SET status = ?, version_number = version_number + 1 WHERE id = ? AND version_number = ?',
            [$status, $id, $version]
        );

        return $this->affected();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertApproval(array $data): void
    {
        $this->run(
            'INSERT INTO artwork_approvals (
                job_artwork_id, approval_status, customer_name, approval_method, reference, notes, approved_at, recorded_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_artwork_id'], $data['approval_status'], $data['customer_name'], $data['approval_method'],
                $data['reference'], $data['notes'], $data['approved_at'], $data['recorded_by'],
            ]
        );
    }

    public function artworkApproved(int $jobId): bool
    {
        $needs = $this->one(
            'SELECT COUNT(*) AS n FROM job_items WHERE job_id = ? AND artwork_required = 1',
            [$jobId]
        );
        if ((int) ($needs['n'] ?? 0) === 0) {
            return true;
        }
        $approved = $this->one(
            'SELECT COUNT(*) AS n FROM job_artworks WHERE job_id = ? AND status = \'APPROVED\'',
            [$jobId]
        );

        return (int) ($approved['n'] ?? 0) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function designQueue(): array
    {
        return $this->rows(
            'SELECT j.id, j.job_number, j.title, j.target_date, j.status, c.company_name, c.first_name, c.last_name, c.customer_type,
                    u.name AS assignee_name,
                    (SELECT a.status FROM job_artworks a WHERE a.job_id = j.id ORDER BY a.revision_number DESC LIMIT 1) AS proof_status
             FROM jobs j
             INNER JOIN customers c ON c.id = j.customer_id
             LEFT JOIN users u ON u.id = j.assigned_to
             WHERE j.archived = 0 AND j.status IN (\'AWAITING_ARTWORK\', \'AWAITING_CUSTOMER_APPROVAL\', \'NEW\')
             ORDER BY j.target_date IS NULL, j.target_date ASC LIMIT 100'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requirements(int $jobId): array
    {
        return $this->rows(
            'SELECT r.*, p.name AS product_name
             FROM job_material_requirements r
             LEFT JOIN products p ON p.id = r.product_id
             WHERE r.job_id = ? ORDER BY r.id ASC',
            [$jobId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRequirement(array $data): void
    {
        $this->run(
            'INSERT INTO job_material_requirements (
                job_id, job_item_id, product_id, required_quantity, unit, calculated_quantity,
                manual_adjustment, final_required_quantity, purchase_quantity, pack_size, source, notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['product_id'], $data['required_quantity'],
                $data['unit'], $data['calculated_quantity'], $data['manual_adjustment'],
                $data['final_required_quantity'], $data['purchase_quantity'] ?? null, $data['pack_size'] ?? null,
                $data['source'], $data['notes'], $data['created_by'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertExpectedLabour(array $data): void
    {
        $this->run(
            'INSERT INTO job_expected_labour (job_id, job_item_id, description, expected_minutes, hourly_cost_snapshot, source)
             VALUES (?, ?, ?, ?, ?, \'RECIPE\')',
            [
                $data['job_id'], $data['job_item_id'], $data['description'],
                $data['expected_minutes'], $data['hourly_cost_snapshot'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expectedLabour(int $jobId): array
    {
        return $this->rows(
            'SELECT * FROM job_expected_labour WHERE job_id = ? ORDER BY id',
            [$jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usage(int $jobId): array
    {
        return $this->rows(
            'SELECT u.*, p.name AS product_name, usr.name AS recorder_name
             FROM job_material_usage u
             LEFT JOIN products p ON p.id = u.product_id
             LEFT JOIN users usr ON usr.id = u.recorded_by
             WHERE u.job_id = ? ORDER BY u.id DESC',
            [$jobId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertUsage(array $data): int
    {
        $this->run(
            'INSERT INTO job_material_usage (
                job_id, job_item_id, product_id, usage_type, quantity, unit, unit_cost_snapshot,
                total_cost, reason, notes, recorded_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['product_id'], $data['usage_type'],
                $data['quantity'], $data['unit'], $data['unit_cost_snapshot'], $data['total_cost'],
                $data['reason'], $data['notes'], $data['recorded_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeEntries(int $jobId): array
    {
        return $this->rows(
            'SELECT t.*, u.name AS user_name FROM job_time_entries t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.job_id = ? ORDER BY t.id DESC',
            [$jobId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openTimer(int $userId): ?array
    {
        return $this->one(
            'SELECT * FROM job_time_entries WHERE user_id = ? AND started_at IS NOT NULL AND ended_at IS NULL ORDER BY id DESC LIMIT 1',
            [$userId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTime(array $data): int
    {
        $this->run(
            'INSERT INTO job_time_entries (
                job_id, job_item_id, task_id, user_id, work_type, started_at, ended_at, minutes,
                hourly_cost_snapshot, total_cost, description
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['task_id'], $data['user_id'], $data['work_type'],
                $data['started_at'], $data['ended_at'], $data['minutes'], $data['hourly_cost_snapshot'],
                $data['total_cost'], $data['description'],
            ]
        );

        return $this->insertId();
    }

    public function closeTime(int $id, int $userId, string $ended, int $minutes, string $total): int
    {
        $this->run(
            'UPDATE job_time_entries SET ended_at = ?, minutes = ?, total_cost = ?
             WHERE id = ? AND user_id = ? AND ended_at IS NULL',
            [$ended, $minutes, $total, $id, $userId]
        );

        return $this->affected();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function otherCosts(int $jobId): array
    {
        return $this->rows('SELECT * FROM job_other_costs WHERE job_id = ? ORDER BY id DESC', [$jobId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertOther(array $data): int
    {
        $this->run(
            'INSERT INTO job_other_costs (job_id, cost_type, description, supplier_id, quantity, unit_cost, total_cost, reference, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['cost_type'], $data['description'], $data['supplier_id'],
                $data['quantity'], $data['unit_cost'], $data['total_cost'], $data['reference'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function otherCostByReference(int $jobId, string $reference): ?array
    {
        return $this->one(
            'SELECT * FROM job_other_costs WHERE job_id = ? AND reference = ? ORDER BY id DESC LIMIT 1',
            [$jobId, $reference]
        );
    }

    public function productionHasStarted(int $jobId): bool
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM job_production_stages WHERE job_id = ? AND status <> \'NOT_STARTED\'',
            [$jobId]
        );

        return (int) ($row['n'] ?? 0) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installations(int $jobId): array
    {
        return $this->rows(
            'SELECT i.*, u.name AS assignee_name, t.name AS team_name
             FROM job_installations i
             LEFT JOIN users u ON u.id = i.assigned_user_id
             LEFT JOIN teams t ON t.id = i.assigned_team_id
             WHERE i.job_id = ? ORDER BY i.scheduled_date IS NULL, i.scheduled_date ASC, i.id DESC',
            [$jobId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function installation(int $id): ?array
    {
        return $this->one('SELECT * FROM job_installations WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertInstallation(array $data): int
    {
        $this->run(
            'INSERT INTO job_installations (
                job_id, scheduled_date, scheduled_start_time, estimated_duration_minutes, site_address,
                site_contact_name, site_contact_phone, assigned_team_id, assigned_user_id, status,
                installation_notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['scheduled_date'], $data['scheduled_start_time'],
                $data['estimated_duration_minutes'], $data['site_address'], $data['site_contact_name'],
                $data['site_contact_phone'], $data['assigned_team_id'], $data['assigned_user_id'],
                $data['status'], $data['installation_notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateInstallation(int $id, int $jobId, array $data, int $version): int
    {
        $this->run(
            'UPDATE job_installations SET status = ?, scheduled_date = ?, scheduled_start_time = ?,
                assigned_team_id = ?, assigned_user_id = ?, arrival_time = ?, started_at = ?, completed_at = ?,
                completion_notes = ?, customer_signoff_name = ?, signoff_date = ?, signoff_notes = ?,
                version_number = version_number + 1
             WHERE id = ? AND job_id = ? AND version_number = ?',
            [
                $data['status'], $data['scheduled_date'], $data['scheduled_start_time'],
                $data['assigned_team_id'], $data['assigned_user_id'], $data['arrival_time'],
                $data['started_at'], $data['completed_at'], $data['completion_notes'],
                $data['customer_signoff_name'], $data['signoff_date'], $data['signoff_notes'],
                $id, $jobId, $version,
            ]
        );

        return $this->affected();
    }

    public function installationComplete(int $jobId): bool
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM job_installations WHERE job_id = ? AND status = \'COMPLETE\'',
            [$jobId]
        );

        return (int) ($row['n'] ?? 0) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installationBoard(): array
    {
        return $this->rows(
            'SELECT i.*, j.job_number, j.title AS job_title, c.company_name, c.first_name, c.last_name, c.customer_type,
                    u.name AS assignee_name, t.name AS team_name
             FROM job_installations i
             INNER JOIN jobs j ON j.id = i.job_id
             INNER JOIN customers c ON c.id = j.customer_id
             LEFT JOIN users u ON u.id = i.assigned_user_id
             LEFT JOIN teams t ON t.id = i.assigned_team_id
             WHERE j.archived = 0 AND i.status <> \'CANCELLED\'
             ORDER BY i.scheduled_date IS NULL, i.scheduled_date ASC, i.id ASC LIMIT 200'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checklistTemplate(): array
    {
        return $this->rows(
            'SELECT i.label, i.sort_order
             FROM installation_checklist_template_items i
             INNER JOIN installation_checklist_templates t ON t.id = i.template_id
             WHERE t.active = 1 AND (t.applies_value IS NULL OR t.applies_value = \'\')
             ORDER BY i.sort_order ASC'
        );
    }

    public function insertChecklistItem(int $installationId, string $label, int $sort): void
    {
        $this->run(
            'INSERT INTO installation_checklist_items (installation_id, label, sort_order) VALUES (?, ?, ?)',
            [$installationId, $label, $sort]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checklist(int $installationId): array
    {
        return $this->rows(
            'SELECT * FROM installation_checklist_items WHERE installation_id = ? ORDER BY sort_order ASC, id ASC',
            [$installationId]
        );
    }

    public function toggleChecklist(int $id, int $installationId, int $checked, int $userId): void
    {
        $this->run(
            'UPDATE installation_checklist_items SET checked = ?, checked_by = ?, checked_at = IF(? = 1, NOW(), NULL)
             WHERE id = ? AND installation_id = ?',
            [$checked, $checked === 1 ? $userId : null, $checked, $id, $installationId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function qcDefinitions(): array
    {
        return $this->rows('SELECT * FROM qc_check_definitions WHERE active = 1 ORDER BY sort_order ASC');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function qualityChecks(int $jobId): array
    {
        return $this->rows(
            'SELECT q.*, u.name AS checker_name FROM job_quality_checks q
             LEFT JOIN users u ON u.id = q.checked_by
             WHERE q.job_id = ? ORDER BY q.id DESC',
            [$jobId]
        );
    }

    public function qcBlocking(int $jobId): bool
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM job_quality_checks
             WHERE job_id = ? AND status IN (\'FAIL\', \'REWORK_REQUIRED\')
               AND id IN (
                   SELECT MAX(id) FROM job_quality_checks WHERE job_id = ? GROUP BY check_type
               )',
            [$jobId, $jobId]
        );
        $phase11 = $this->one(
            "SELECT COUNT(*) AS n FROM quality_checks WHERE job_id = ? AND status = 'FAIL' AND resolved_at IS NULL",
            [$jobId]
        );

        return (int) ($row['n'] ?? 0) > 0 || (int) ($phase11['n'] ?? 0) > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertQuality(array $data): int
    {
        $this->run(
            'INSERT INTO job_quality_checks (job_id, job_item_id, check_type, status, notes, rework_task_id, checked_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['check_type'], $data['status'],
                $data['notes'], $data['rework_task_id'], $data['checked_by'],
            ]
        );

        return $this->insertId();
    }

    public function linkRework(int $checkId, int $taskId): void
    {
        $this->run('UPDATE job_quality_checks SET rework_task_id = ? WHERE id = ?', [$taskId, $checkId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teams(): array
    {
        return $this->rows('SELECT * FROM teams WHERE active = 1 ORDER BY name ASC');
    }

    public function itemsOpen(int $jobId): bool
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM job_items WHERE job_id = ? AND production_status NOT IN (\'COMPLETE\', \'ON_HOLD\')',
            [$jobId]
        );

        return (int) ($row['n'] ?? 0) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentProduction(int $limit = 8): array
    {
        return $this->rows(
            'SELECT a.created_at, a.action, a.entity_id, j.job_number, u.name AS user_name
             FROM audit_log a
             INNER JOIN jobs j ON j.id = a.entity_id
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.entity_type = \'job\'
               AND a.action IN (\'TASK_STARTED\', \'TASK_COMPLETED\', \'MATERIAL_RECORDED\', \'WASTE_RECORDED\', \'QC_FAILED\', \'JOB_STATUS_CHANGED\')
             ORDER BY a.id DESC LIMIT ' . (int) $limit
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function item(int $id): ?array
    {
        return $this->one('SELECT * FROM job_items WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvals(int $artworkId): array
    {
        return $this->rows(
            'SELECT a.*, u.name AS recorder_name
             FROM artwork_approvals a
             LEFT JOIN users u ON u.id = a.recorded_by
             WHERE a.job_artwork_id = ? ORDER BY a.id DESC',
            [$artworkId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogueStages(): array
    {
        return $this->rows('SELECT * FROM production_stages WHERE active = 1 ORDER BY sort_order ASC, name ASC');
    }

    public function insertCatalogueStage(string $name, string $description, int $sort): void
    {
        $this->run(
            'INSERT INTO production_stages (name, description, sort_order) VALUES (?, ?, ?)',
            [$name, $description === '' ? null : $description, $sort]
        );
    }

    public function insertTemplate(string $code, string $name, ?string $description): int
    {
        $this->run(
            'INSERT INTO production_route_templates (code, name, description) VALUES (?, ?, ?)',
            [$code, $name, $description]
        );

        return $this->insertId();
    }

    public function insertTemplateStage(int $templateId, int $stageId, int $sort): void
    {
        $this->run(
            'INSERT INTO production_route_template_stages (template_id, production_stage_id, sort_order) VALUES (?, ?, ?)',
            [$templateId, $stageId, $sort]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teamMemberships(): array
    {
        return $this->rows('SELECT team_id, user_id FROM team_members');
    }

    public function setTeamMember(int $teamId, int $userId, bool $member): void
    {
        if ($member) {
            $this->run('INSERT IGNORE INTO team_members (team_id, user_id) VALUES (?, ?)', [$teamId, $userId]);

            return;
        }
        $this->run('DELETE FROM team_members WHERE team_id = ? AND user_id = ?', [$teamId, $userId]);
    }

}

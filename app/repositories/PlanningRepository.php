<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Scheduling, capacity, resources, and field-planning queries.
 *
 * Date filters stay on the requested range so a calendar does not load the
 * whole schedule history.
 */
final class PlanningRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function job(int $id): ?array
    {
        return $this->one('SELECT * FROM jobs WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resource(int $id): ?array
    {
        return $this->one('SELECT * FROM resources WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockResource(int $id): ?array
    {
        return $this->one('SELECT * FROM resources WHERE id = ? LIMIT 1 FOR UPDATE', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function resources(?string $type = null, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM resources WHERE 1 = 1';
        $params = [];
        if ($type !== null && $type !== '') {
            $sql .= ' AND resource_type = ?';
            $params[] = $type;
        }
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }
        $sql .= ' ORDER BY resource_type ASC, name ASC';

        return $this->rows($sql, $params);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertResource(array $data): int
    {
        $this->run(
            'INSERT INTO resources (
                resource_type, code, name, description, capacity_type, default_daily_capacity,
                concurrent_capacity, internal_hourly_cost, internal_cost_per_km, linked_user_id,
                linked_team_id, linked_supplier_id, status, active
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['resource_type'], $data['code'], $data['name'], $data['description'],
                $data['capacity_type'], $data['default_daily_capacity'], $data['concurrent_capacity'],
                $data['internal_hourly_cost'], $data['internal_cost_per_km'], $data['linked_user_id'],
                $data['linked_team_id'], $data['linked_supplier_id'], $data['status'], $data['active'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateResource(int $id, array $data): void
    {
        $this->run(
            'UPDATE resources SET resource_type = ?, code = ?, name = ?, description = ?, capacity_type = ?,
                default_daily_capacity = ?, concurrent_capacity = ?, internal_hourly_cost = ?, internal_cost_per_km = ?,
                linked_user_id = ?, linked_team_id = ?, linked_supplier_id = ?, status = ?, active = ?
             WHERE id = ?',
            [
                $data['resource_type'], $data['code'], $data['name'], $data['description'],
                $data['capacity_type'], $data['default_daily_capacity'], $data['concurrent_capacity'],
                $data['internal_hourly_cost'], $data['internal_cost_per_km'], $data['linked_user_id'],
                $data['linked_team_id'], $data['linked_supplier_id'], $data['status'], $data['active'], $id,
            ]
        );
    }

    public function setResourceStatus(int $id, string $status): void
    {
        $this->run('UPDATE resources SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function resourceForUser(int $userId): ?array
    {
        return $this->one('SELECT * FROM resources WHERE linked_user_id = ? LIMIT 1', [$userId]);
    }

    public function resourceForTeam(int $teamId): ?array
    {
        return $this->one('SELECT * FROM resources WHERE linked_team_id = ? LIMIT 1', [$teamId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeUsers(): array
    {
        return $this->rows('SELECT id, name, email, active FROM users WHERE active = 1 ORDER BY name ASC');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teams(): array
    {
        return $this->rows('SELECT * FROM teams ORDER BY name ASC');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function defaultSchedule(): ?array
    {
        return $this->one('SELECT * FROM work_schedules WHERE is_default = 1 AND active = 1 ORDER BY id ASC LIMIT 1');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function schedules(): array
    {
        return $this->rows('SELECT * FROM work_schedules ORDER BY is_default DESC, name ASC');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertSchedule(array $data): int
    {
        $this->run(
            'INSERT INTO work_schedules (
                name, monday_start, monday_end, tuesday_start, tuesday_end, wednesday_start, wednesday_end,
                thursday_start, thursday_end, friday_start, friday_end, saturday_start, saturday_end,
                sunday_start, sunday_end, break_minutes, is_default, active
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['monday_start'], $data['monday_end'], $data['tuesday_start'], $data['tuesday_end'],
                $data['wednesday_start'], $data['wednesday_end'], $data['thursday_start'], $data['thursday_end'],
                $data['friday_start'], $data['friday_end'], $data['saturday_start'], $data['saturday_end'],
                $data['sunday_start'], $data['sunday_end'], $data['break_minutes'], $data['is_default'], $data['active'],
            ]
        );

        return $this->insertId();
    }

    public function clearDefaultSchedule(int $exceptId): void
    {
        $this->run('UPDATE work_schedules SET is_default = 0 WHERE id <> ?', [$exceptId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function scheduleForResource(int $resourceId, string $date): ?array
    {
        return $this->one(
            'SELECT s.* FROM resource_work_schedules r
             JOIN work_schedules s ON s.id = r.work_schedule_id
             WHERE r.resource_id = ? AND r.effective_from <= ? AND (r.effective_to IS NULL OR r.effective_to >= ?)
             ORDER BY r.effective_from DESC LIMIT 1',
            [$resourceId, $date, $date]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assignSchedule(array $data): int
    {
        $this->run(
            'INSERT INTO resource_work_schedules (resource_id, work_schedule_id, effective_from, effective_to)
             VALUES (?, ?, ?, ?)',
            [$data['resource_id'], $data['work_schedule_id'], $data['effective_from'], $data['effective_to']]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertUnavailability(array $data): int
    {
        $this->run(
            'INSERT INTO resource_unavailability (resource_id, start_datetime, end_datetime, reason_type, description, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['resource_id'], $data['start_datetime'], $data['end_datetime'], $data['reason_type'],
                $data['description'], $data['status'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unavailabilityBetween(int $resourceId, string $start, string $end): array
    {
        return $this->rows(
            'SELECT * FROM resource_unavailability
             WHERE resource_id = ? AND status = \'ACTIVE\' AND start_datetime < ? AND end_datetime > ?
             ORDER BY start_datetime ASC',
            [$resourceId, $end, $start]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function exceptionOn(string $date): ?array
    {
        return $this->one('SELECT * FROM calendar_exceptions WHERE exception_date = ? LIMIT 1', [$date]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exceptionsBetween(string $start, string $end): array
    {
        return $this->rows(
            'SELECT * FROM calendar_exceptions WHERE exception_date >= ? AND exception_date <= ? ORDER BY exception_date ASC',
            [$start, $end]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveException(array $data): void
    {
        $existing = $this->exceptionOn((string) $data['exception_date']);
        if ($existing === null) {
            $this->run(
                'INSERT INTO calendar_exceptions (exception_date, name, exception_type, working_day_override, notes)
                 VALUES (?, ?, ?, ?, ?)',
                [$data['exception_date'], $data['name'], $data['exception_type'], $data['working_day_override'], $data['notes']]
            );

            return;
        }
        $this->run(
            'UPDATE calendar_exceptions SET name = ?, exception_type = ?, working_day_override = ?, notes = ? WHERE id = ?',
            [$data['name'], $data['exception_type'], $data['working_day_override'], $data['notes'], $existing['id']]
        );
    }

    public function deleteException(int $id): void
    {
        $this->run('DELETE FROM calendar_exceptions WHERE id = ?', [$id]);
    }

    public function overlapCount(int $resourceId, string $start, string $end, int $excludeId): int
    {
        $row = $this->one(
            'SELECT COUNT(DISTINCT e.id) AS n
             FROM schedule_entries e
             LEFT JOIN schedule_entry_resources er ON er.schedule_entry_id = e.id
             WHERE e.status <> \'CANCELLED\' AND e.id <> ?
               AND e.start_datetime < ? AND e.end_datetime > ?
               AND (e.resource_id = ? OR er.resource_id = ?)',
            [$excludeId, $end, $start, $resourceId, $resourceId]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertEntry(array $data): int
    {
        $this->run(
            'INSERT INTO schedule_entries (
                entity_type, entity_id, job_id, resource_id, start_datetime, end_datetime, estimated_minutes,
                status, locked, customer_visible, notes, override_reason, override_by, override_at, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['entity_type'], $data['entity_id'], $data['job_id'], $data['resource_id'],
                $data['start_datetime'], $data['end_datetime'], $data['estimated_minutes'], $data['status'],
                $data['locked'], $data['customer_visible'], $data['notes'], $data['override_reason'],
                $data['override_by'], $data['override_at'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateEntry(int $id, array $data): void
    {
        $this->run(
            'UPDATE schedule_entries SET resource_id = ?, start_datetime = ?, end_datetime = ?, estimated_minutes = ?,
                status = ?, customer_visible = ?, notes = ?, override_reason = ?, override_by = ?, override_at = ?
             WHERE id = ?',
            [
                $data['resource_id'], $data['start_datetime'], $data['end_datetime'], $data['estimated_minutes'],
                $data['status'], $data['customer_visible'], $data['notes'], $data['override_reason'],
                $data['override_by'], $data['override_at'], $id,
            ]
        );
    }

    public function setEntryStatus(int $id, string $status): void
    {
        $this->run('UPDATE schedule_entries SET status = ? WHERE id = ? AND status <> \'CANCELLED\'', [$status, $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function entry(int $id): ?array
    {
        return $this->one('SELECT * FROM schedule_entries WHERE id = ? LIMIT 1', [$id]);
    }

    public function addEntryResource(int $entryId, int $resourceId, string $role): void
    {
        $this->run(
            'INSERT IGNORE INTO schedule_entry_resources (schedule_entry_id, resource_id, role) VALUES (?, ?, ?)',
            [$entryId, $resourceId, $role]
        );
    }

    public function clearEntryResources(int $entryId): void
    {
        $this->run('DELETE FROM schedule_entry_resources WHERE schedule_entry_id = ?', [$entryId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entryResources(int $entryId): array
    {
        return $this->rows(
            'SELECT er.*, r.name, r.resource_type, r.status FROM schedule_entry_resources er
             JOIN resources r ON r.id = er.resource_id WHERE er.schedule_entry_id = ? ORDER BY er.id ASC',
            [$entryId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertHistory(array $data): void
    {
        $this->run(
            'INSERT INTO schedule_history (schedule_entry_id, old_start, old_end, new_start, new_end, changed_by, reason)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['schedule_entry_id'], $data['old_start'], $data['old_end'], $data['new_start'],
                $data['new_end'], $data['changed_by'], $data['reason'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function entriesBetween(string $start, string $end): array
    {
        return $this->rows(
            'SELECT e.*, r.name AS resource_name, r.resource_type, j.job_number, j.title AS job_title, j.priority,
                    c.company_name, c.first_name, c.last_name, c.customer_type
             FROM schedule_entries e
             JOIN resources r ON r.id = e.resource_id
             LEFT JOIN jobs j ON j.id = e.job_id
             LEFT JOIN customers c ON c.id = j.customer_id
             WHERE e.status <> \'CANCELLED\' AND e.start_datetime < ? AND e.end_datetime > ?
             ORDER BY e.start_datetime ASC, r.name ASC',
            [$end, $start]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function futureEntriesForResource(int $resourceId, string $from): array
    {
        return $this->rows(
            'SELECT DISTINCT e.* FROM schedule_entries e
             LEFT JOIN schedule_entry_resources er ON er.schedule_entry_id = e.id
             WHERE e.status NOT IN (\'CANCELLED\', \'COMPLETED\') AND e.end_datetime > ?
               AND (e.resource_id = ? OR er.resource_id = ?)
             ORDER BY e.start_datetime ASC',
            [$from, $resourceId, $resourceId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function unscheduledStages(): array
    {
        return $this->rows(
            'SELECT s.id, s.job_id, s.status, s.estimated_minutes, s.sort_order, st.name AS stage_name,
                    j.job_number, j.title, j.priority, j.target_date, j.customer_promised_date,
                    c.company_name, c.first_name, c.last_name, c.customer_type
             FROM job_production_stages s
             JOIN jobs j ON j.id = s.job_id
             JOIN production_stages st ON st.id = s.production_stage_id
             LEFT JOIN customers c ON c.id = j.customer_id
             WHERE j.archived = 0 AND s.status NOT IN (\'COMPLETE\', \'CANCELLED\')
               AND NOT EXISTS (
                    SELECT 1 FROM schedule_entries e
                    WHERE e.entity_type = \'PRODUCTION_STAGE\' AND e.entity_id = s.id AND e.status <> \'CANCELLED\'
               )
             ORDER BY j.target_date IS NULL, j.target_date ASC, s.sort_order ASC
             LIMIT 80'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function stage(int $id): ?array
    {
        return $this->one(
            'SELECT s.*, st.name AS stage_name, j.job_number, j.artwork_override_by
             FROM job_production_stages s
             JOIN production_stages st ON st.id = s.production_stage_id
             JOIN jobs j ON j.id = s.job_id
             WHERE s.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stagesForJob(int $jobId): array
    {
        return $this->rows(
            'SELECT s.*, st.name AS stage_name FROM job_production_stages s
             JOIN production_stages st ON st.id = s.production_stage_id
             WHERE s.job_id = ? ORDER BY s.sort_order ASC, s.id ASC',
            [$jobId]
        );
    }

    public function setStageEstimate(int $id, int $minutes): void
    {
        $this->run(
            'UPDATE job_production_stages SET estimated_minutes = ? WHERE id = ? AND estimated_minutes IS NULL',
            [$minutes, $id]
        );
    }

    public function completeStage(int $id, int $actualMinutes): void
    {
        $this->run(
            'UPDATE job_production_stages
             SET status = \'COMPLETE\', completed_at = COALESCE(completed_at, NOW()), actual_minutes = ?
             WHERE id = ?',
            [$actualMinutes, $id]
        );
    }

    public function dependencyExists(int $predecessorStageId, int $successorStageId): bool
    {
        $row = $this->one(
            'SELECT id FROM task_dependencies WHERE predecessor_stage_id = ? AND successor_stage_id = ? LIMIT 1',
            [$predecessorStageId, $successorStageId]
        );

        return $row !== null;
    }

    public function insertStageDependency(int $predecessorStageId, int $successorStageId): void
    {
        $this->run(
            'INSERT INTO task_dependencies (predecessor_stage_id, successor_stage_id, dependency_type)
             VALUES (?, ?, \'FINISH_TO_START\')',
            [$predecessorStageId, $successorStageId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openPredecessors(int $stageId): array
    {
        return $this->rows(
            'SELECT s.id, s.status, st.name AS stage_name
             FROM task_dependencies d
             JOIN job_production_stages s ON s.id = d.predecessor_stage_id
             JOIN production_stages st ON st.id = s.production_stage_id
             WHERE d.successor_stage_id = ? AND d.dependency_type = \'FINISH_TO_START\' AND s.status <> \'COMPLETE\'',
            [$stageId]
        );
    }

    public function openPredecessorCount(int $jobId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM task_dependencies d
             JOIN job_production_stages pred ON pred.id = d.predecessor_stage_id
             JOIN job_production_stages succ ON succ.id = d.successor_stage_id
             WHERE succ.job_id = ? AND pred.status <> \'COMPLETE\' AND succ.status <> \'COMPLETE\'',
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function requiredQuantity(int $jobId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(CASE WHEN final_required_quantity > 0 THEN final_required_quantity ELSE required_quantity END), 0) AS qty
             FROM job_material_requirements WHERE job_id = ?',
            [$jobId]
        );

        return (string) ($row['qty'] ?? '0');
    }

    public function reservedQuantity(int $jobId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(quantity), 0) AS qty FROM stock_reservations WHERE job_id = ? AND status = \'RESERVED\'',
            [$jobId]
        );

        return (string) ($row['qty'] ?? '0');
    }

    public function artworkRequiredCount(int $jobId): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM job_items WHERE job_id = ? AND artwork_required = 1', [$jobId]);

        return (int) ($row['n'] ?? 0);
    }

    public function approvedArtworkCount(int $jobId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM job_artworks WHERE job_id = ? AND status = \'APPROVED\'',
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function artworkFileCount(int $jobId): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM job_artworks WHERE job_id = ?', [$jobId]);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return list<string>
     */
    public function blockedEquipment(int $jobId): array
    {
        $rows = $this->rows(
            'SELECT DISTINCT r.name FROM schedule_entries e
             JOIN resources r ON r.id = e.resource_id
             WHERE e.job_id = ? AND e.status NOT IN (\'CANCELLED\', \'COMPLETED\')
               AND r.status IN (\'OUT_OF_SERVICE\', \'RETIRED\')',
            [$jobId]
        );
        $names = [];
        foreach ($rows as $row) {
            $names[] = (string) $row['name'];
        }

        return $names;
    }

    public function openStageCount(int $jobId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM job_production_stages WHERE job_id = ? AND status <> \'COMPLETE\'',
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function remainingMinutes(int $jobId): int
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(estimated_minutes), 0) AS n FROM job_production_stages
             WHERE job_id = ? AND status <> \'COMPLETE\'',
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function companyMinutesBetween(string $from, string $to): int
    {
        $schedule = $this->defaultSchedule();
        if ($schedule === null) {
            return 0;
        }
        $capacity = new \App\Services\CapacityService();
        $total = 0;
        $cursor = strtotime($from);
        $end = strtotime($to);
        if ($cursor === false || $end === false) {
            return 0;
        }
        $guard = 0;
        while ($cursor <= $end && $guard < 120) {
            $date = date('Y-m-d', $cursor);
            $total += $capacity->availableMinutes($schedule, $date, $this->exceptionOn($date), []);
            $cursor = strtotime($date . ' +1 day') ?: ($cursor + 86400);
            $guard++;
        }

        return $total;
    }

    public function nextInstallationDate(int $jobId): ?string
    {
        $row = $this->one(
            'SELECT scheduled_date FROM job_installations
             WHERE job_id = ? AND status NOT IN (\'CANCELLED\', \'COMPLETE\') AND scheduled_date IS NOT NULL
             ORDER BY scheduled_date ASC LIMIT 1',
            [$jobId]
        );
        if ($row === null || $row['scheduled_date'] === null) {
            return null;
        }

        return (string) $row['scheduled_date'];
    }

    public function openBlock(int $jobId): string
    {
        $row = $this->one(
            'SELECT reason_text FROM work_blocks WHERE job_id = ? AND status = \'OPEN\' ORDER BY id DESC LIMIT 1',
            [$jobId]
        );

        return $row === null ? '' : (string) $row['reason_text'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function blockReasons(): array
    {
        return $this->rows('SELECT * FROM block_reasons WHERE active = 1 ORDER BY name ASC');
    }

    public function blockReason(int $id): ?array
    {
        return $this->one('SELECT * FROM block_reasons WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertBlock(array $data): int
    {
        $this->run(
            'INSERT INTO work_blocks (job_id, task_id, stage_id, reason_id, reason_text, status, created_by)
             VALUES (?, ?, ?, ?, ?, \'OPEN\', ?)',
            [$data['job_id'], $data['task_id'], $data['stage_id'], $data['reason_id'], $data['reason_text'], $data['created_by']]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function task(int $id): ?array
    {
        return $this->one('SELECT * FROM job_tasks WHERE id = ? LIMIT 1', [$id]);
    }

    public function setTaskStatus(int $id, string $status, bool $starting, bool $finishing): void
    {
        $this->run(
            'UPDATE job_tasks SET status = ?,
                started_at = CASE WHEN ? = 1 AND started_at IS NULL THEN NOW() ELSE started_at END,
                completed_at = CASE WHEN ? = 1 THEN NOW() ELSE completed_at END
             WHERE id = ?',
            [$status, $starting ? 1 : 0, $finishing ? 1 : 0, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tasksForUser(int $userId): array
    {
        return $this->rows(
            'SELECT t.*, j.job_number, j.title AS job_title, j.priority AS job_priority
             FROM job_tasks t
             JOIN jobs j ON j.id = t.job_id
             WHERE j.archived = 0 AND t.status NOT IN (\'COMPLETE\', \'CANCELLED\')
               AND (t.assigned_to = ? OR t.assigned_team_id IN (SELECT team_id FROM team_members WHERE user_id = ?))
             ORDER BY t.due_date IS NULL, t.due_date ASC, t.id ASC
             LIMIT 80',
            [$userId, $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installationsBetween(string $start, string $end): array
    {
        return $this->rows(
            'SELECT i.*, j.job_number, j.title, j.site_address AS job_site, c.company_name, c.first_name, c.last_name, c.customer_type,
                    t.name AS team_name
             FROM job_installations i
             JOIN jobs j ON j.id = i.job_id
             JOIN customers c ON c.id = j.customer_id
             LEFT JOIN teams t ON t.id = i.assigned_team_id
             WHERE i.status <> \'CANCELLED\' AND i.scheduled_date IS NOT NULL AND i.scheduled_date >= ? AND i.scheduled_date <= ?
             ORDER BY i.scheduled_date ASC, i.scheduled_start_time ASC',
            [$start, $end]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function installation(int $id): ?array
    {
        return $this->one(
            'SELECT i.*, j.job_number, j.title, j.customer_id, j.site_address AS job_site, j.installation_notes AS job_install_notes,
                    c.company_name, c.first_name, c.last_name, c.customer_type, c.phone AS customer_phone
             FROM job_installations i
             JOIN jobs j ON j.id = i.job_id
             JOIN customers c ON c.id = j.customer_id
             WHERE i.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function expectedLabour(int $jobId): array
    {
        return $this->rows('SELECT * FROM job_expected_labour WHERE job_id = ? ORDER BY id ASC', [$jobId]);
    }

    public function updatePlanningDates(int $jobId, ?string $original, ?string $promised): void
    {
        $this->run(
            'UPDATE jobs SET original_target_date = ?, customer_promised_date = ? WHERE id = ?',
            [$original, $promised, $jobId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertMachine(array $data): void
    {
        $this->run(
            'INSERT INTO machine_details (
                resource_id, manufacturer, model, serial_number, purchase_date, service_interval_days,
                service_interval_hours, last_service_date, next_service_date, meter_hours, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE manufacturer = VALUES(manufacturer), model = VALUES(model),
                serial_number = VALUES(serial_number), purchase_date = VALUES(purchase_date),
                service_interval_days = VALUES(service_interval_days), service_interval_hours = VALUES(service_interval_hours),
                last_service_date = VALUES(last_service_date), next_service_date = VALUES(next_service_date),
                meter_hours = VALUES(meter_hours), notes = VALUES(notes)',
            [
                $data['resource_id'], $data['manufacturer'], $data['model'], $data['serial_number'],
                $data['purchase_date'], $data['service_interval_days'], $data['service_interval_hours'],
                $data['last_service_date'], $data['next_service_date'], $data['meter_hours'], $data['notes'],
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function machine(int $resourceId): ?array
    {
        return $this->one('SELECT * FROM machine_details WHERE resource_id = ? LIMIT 1', [$resourceId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertMaintenance(array $data): int
    {
        $this->run(
            'INSERT INTO maintenance_records (
                resource_id, maintenance_type, scheduled_date, completed_date, downtime_start, downtime_end,
                description, supplier_id, cost, meter_reading, status, notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['resource_id'], $data['maintenance_type'], $data['scheduled_date'], $data['completed_date'],
                $data['downtime_start'], $data['downtime_end'], $data['description'], $data['supplier_id'],
                $data['cost'], $data['meter_reading'], $data['status'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function maintenanceDue(string $until): array
    {
        return $this->rows(
            'SELECT m.*, r.name AS resource_name FROM maintenance_records m
             JOIN resources r ON r.id = m.resource_id
             WHERE m.status NOT IN (\'COMPLETED\', \'CANCELLED\') AND m.scheduled_date IS NOT NULL AND m.scheduled_date <= ?
             ORDER BY m.scheduled_date ASC LIMIT 40',
            [$until]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertDowntime(array $data): int
    {
        $this->run(
            'INSERT INTO resource_downtime (resource_id, maintenance_record_id, started_at, ended_at, reason, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['resource_id'], $data['maintenance_record_id'], $data['started_at'], $data['ended_at'],
                $data['reason'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function downtimeBetween(string $start, string $end): array
    {
        return $this->rows(
            'SELECT d.*, r.name AS resource_name, m.cost, m.maintenance_type
             FROM resource_downtime d
             JOIN resources r ON r.id = d.resource_id
             LEFT JOIN maintenance_records m ON m.id = d.maintenance_record_id
             WHERE d.started_at < ? AND (d.ended_at IS NULL OR d.ended_at > ?)
             ORDER BY d.started_at DESC',
            [$end, $start]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertVehicle(array $data): void
    {
        $this->run(
            'INSERT INTO vehicle_details (
                resource_id, registration_number, make, model, year, vin, odometer, service_interval_km,
                service_interval_days, last_service_date, last_service_odometer, next_service_date,
                next_service_odometer, licence_expiry, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE registration_number = VALUES(registration_number), make = VALUES(make),
                model = VALUES(model), year = VALUES(year), vin = VALUES(vin), odometer = VALUES(odometer),
                service_interval_km = VALUES(service_interval_km), service_interval_days = VALUES(service_interval_days),
                last_service_date = VALUES(last_service_date), last_service_odometer = VALUES(last_service_odometer),
                next_service_date = VALUES(next_service_date), next_service_odometer = VALUES(next_service_odometer),
                licence_expiry = VALUES(licence_expiry), notes = VALUES(notes)',
            [
                $data['resource_id'], $data['registration_number'], $data['make'], $data['model'], $data['year'],
                $data['vin'], $data['odometer'], $data['service_interval_km'], $data['service_interval_days'],
                $data['last_service_date'], $data['last_service_odometer'], $data['next_service_date'],
                $data['next_service_odometer'], $data['licence_expiry'], $data['notes'],
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function vehicle(int $resourceId): ?array
    {
        return $this->one('SELECT * FROM vehicle_details WHERE resource_id = ? LIMIT 1', [$resourceId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function vehicles(): array
    {
        return $this->rows(
            'SELECT r.*, v.registration_number, v.make, v.model, v.odometer, v.next_service_date, v.licence_expiry
             FROM resources r
             JOIN vehicle_details v ON v.resource_id = r.id
             ORDER BY v.registration_number ASC'
        );
    }

    public function setOdometer(int $resourceId, string $odometer): void
    {
        $this->run('UPDATE vehicle_details SET odometer = ? WHERE resource_id = ?', [$odometer, $resourceId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertUsage(array $data): int
    {
        $this->run(
            'INSERT INTO vehicle_usage (
                vehicle_resource_id, job_id, user_id, start_odometer, end_odometer, distance_km,
                rate_per_km_snapshot, travel_cost, other_cost_id, usage_date, notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['vehicle_resource_id'], $data['job_id'], $data['user_id'], $data['start_odometer'],
                $data['end_odometer'], $data['distance_km'], $data['rate_per_km_snapshot'], $data['travel_cost'],
                $data['other_cost_id'], $data['usage_date'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    public function attachUsageCost(int $usageId, int $otherCostId): void
    {
        $this->run('UPDATE vehicle_usage SET other_cost_id = ? WHERE id = ? AND other_cost_id IS NULL', [$otherCostId, $usageId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function serviceDueVehicles(string $until): array
    {
        return $this->rows(
            'SELECT r.id, r.name, v.registration_number, v.next_service_date FROM resources r
             JOIN vehicle_details v ON v.resource_id = r.id
             WHERE r.active = 1 AND v.next_service_date IS NOT NULL AND v.next_service_date <= ?
             ORDER BY v.next_service_date ASC',
            [$until]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRecurring(array $data): int
    {
        $this->run(
            'INSERT INTO recurring_job_templates (
                customer_id, name, description, frequency_type, interval_value, next_run_date, generation_mode,
                default_recipe_id, anchor_job_id, assigned_to, active, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'], $data['name'], $data['description'], $data['frequency_type'],
                $data['interval_value'], $data['next_run_date'], $data['generation_mode'], $data['default_recipe_id'],
                $data['anchor_job_id'], $data['assigned_to'], $data['active'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recurringDue(string $date): array
    {
        return $this->rows(
            'SELECT * FROM recurring_job_templates WHERE active = 1 AND next_run_date <= ? ORDER BY next_run_date ASC, id ASC',
            [$date]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recurringAll(): array
    {
        return $this->rows(
            'SELECT t.*, c.company_name, c.first_name, c.last_name, c.customer_type
             FROM recurring_job_templates t
             JOIN customers c ON c.id = t.customer_id
             ORDER BY t.active DESC, t.next_run_date ASC'
        );
    }

    public function recurringRunExists(int $templateId, string $periodKey): bool
    {
        $row = $this->one(
            'SELECT id FROM recurring_job_runs WHERE template_id = ? AND period_key = ? LIMIT 1',
            [$templateId, $periodKey]
        );

        return $row !== null;
    }

    public function insertFollowup(int $templateId, int $customerId, string $title, string $due): int
    {
        $this->run(
            'INSERT INTO recurring_followups (template_id, customer_id, title, due_date, status) VALUES (?, ?, ?, ?, \'OPEN\')',
            [$templateId, $customerId, $title, $due]
        );

        return $this->insertId();
    }

    public function insertRecurringRun(int $templateId, string $periodKey, int $followupId): void
    {
        $this->run(
            'INSERT INTO recurring_job_runs (template_id, period_key, followup_id) VALUES (?, ?, ?)',
            [$templateId, $periodKey, $followupId]
        );
    }

    public function advanceRecurring(int $id, string $nextDate): void
    {
        $this->run('UPDATE recurring_job_templates SET next_run_date = ? WHERE id = ?', [$nextDate, $id]);
    }

    public function followupCount(int $templateId): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM recurring_followups WHERE template_id = ?', [$templateId]);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertSubcontract(array $data): int
    {
        $this->run(
            'INSERT INTO subcontract_orders (
                order_number, job_id, supplier_id, description, required_date, status, estimated_cost, notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['order_number'], $data['job_id'], $data['supplier_id'], $data['description'],
                $data['required_date'], $data['status'], $data['estimated_cost'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function subcontract(int $id): ?array
    {
        return $this->one('SELECT * FROM subcontract_orders WHERE id = ? LIMIT 1 FOR UPDATE', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function subcontractRead(int $id): ?array
    {
        return $this->one('SELECT * FROM subcontract_orders WHERE id = ? LIMIT 1', [$id]);
    }

    public function completeSubcontract(int $id, string $actual, int $otherCostId): void
    {
        $this->run(
            'UPDATE subcontract_orders SET status = \'COMPLETED\', actual_cost = ?, other_cost_id = ? WHERE id = ? AND other_cost_id IS NULL',
            [$actual, $otherCostId, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subcontracts(): array
    {
        return $this->rows(
            'SELECT o.*, j.job_number, s.name AS supplier_name
             FROM subcontract_orders o
             JOIN jobs j ON j.id = o.job_id
             JOIN suppliers s ON s.id = o.supplier_id
             ORDER BY o.id DESC LIMIT 100'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deliveriesOn(string $date): array
    {
        return $this->rows(
            'SELECT j.id, j.job_number, j.title, j.delivery_method, j.target_date, c.company_name, c.first_name, c.last_name, c.customer_type
             FROM jobs j JOIN customers c ON c.id = j.customer_id
             WHERE j.archived = 0 AND j.target_date = ? AND j.delivery_method IN (\'DELIVERY\', \'COURIER\', \'COLLECTION\')
               AND j.status NOT IN (\'COMPLETED\', \'CANCELLED\')
             ORDER BY j.job_number ASC',
            [$date]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function surveysOn(string $date): array
    {
        return $this->rows(
            'SELECT id, survey_number, site_name, status, survey_date FROM site_surveys
             WHERE survey_date = ? AND status <> \'CANCELLED\' ORDER BY survey_number ASC',
            [$date]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerVisibleEntries(int $jobId): array
    {
        return $this->rows(
            'SELECT id, entity_type, start_datetime, end_datetime, status, notes
             FROM schedule_entries
             WHERE job_id = ? AND customer_visible = 1 AND status <> \'CANCELLED\'
             ORDER BY start_datetime ASC',
            [$jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scheduledMinutesByResource(string $start, string $end): array
    {
        return $this->rows(
            'SELECT resource_id, start_datetime, end_datetime, estimated_minutes, status
             FROM schedule_entries
             WHERE status <> \'CANCELLED\' AND start_datetime < ? AND end_datetime > ?',
            [$end, $start]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function actualMinutesByUser(string $start, string $end): array
    {
        return $this->rows(
            'SELECT user_id, COALESCE(SUM(minutes), 0) AS minutes
             FROM job_time_entries
             WHERE started_at >= ? AND started_at < ? AND ended_at IS NOT NULL
             GROUP BY user_id',
            [$start, $end]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lateJobs(string $start, string $end): array
    {
        return $this->rows(
            'SELECT j.id, j.job_number, j.title, j.target_date, j.customer_promised_date, j.completed_at, j.status,
                    c.company_name, c.first_name, c.last_name, c.customer_type
             FROM jobs j JOIN customers c ON c.id = j.customer_id
             WHERE j.completed_at IS NOT NULL AND j.target_date IS NOT NULL
               AND DATE(j.completed_at) > j.target_date
               AND j.completed_at >= ? AND j.completed_at < ?
             ORDER BY j.completed_at DESC LIMIT 100',
            [$start, $end]
        );
    }

    /**
     * @return list<string>
     */
    public function blockReasonsForJob(int $jobId): array
    {
        $rows = $this->rows(
            'SELECT DISTINCT reason_text FROM work_blocks WHERE job_id = ? ORDER BY id ASC',
            [$jobId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = (string) $row['reason_text'];
        }

        return $out;
    }

    public function roleId(string $code): ?int
    {
        $row = $this->one('SELECT id FROM roles WHERE code = ? LIMIT 1', [$code]);

        return $row === null ? null : (int) $row['id'];
    }
}

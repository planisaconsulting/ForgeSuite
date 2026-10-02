<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Release, fulfilment, and workshop-queue reads.
 * Release does not write stock movements.
 */
final class ProductionControlRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function lockJob(int $jobId): ?array
    {
        return $this->one('SELECT * FROM jobs WHERE id = ? FOR UPDATE', [$jobId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function job(int $jobId): ?array
    {
        return $this->one(
            'SELECT j.*, c.company_name, c.first_name, c.last_name
             FROM jobs j JOIN customers c ON c.id = j.customer_id WHERE j.id = ?',
            [$jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $jobId): array
    {
        return $this->rows('SELECT * FROM job_items WHERE job_id = ? ORDER BY sort_order, id', [$jobId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function item(int $itemId): ?array
    {
        return $this->one('SELECT * FROM job_items WHERE id = ?', [$itemId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requirements(int $jobId): array
    {
        return $this->rows(
            'SELECT r.*, p.name AS product_name FROM job_material_requirements r
             LEFT JOIN products p ON p.id = r.product_id
             WHERE r.job_id = ? ORDER BY r.id',
            [$jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stages(int $jobId): array
    {
        return $this->rows(
            'SELECT s.*, st.name AS stage_name FROM job_production_stages s
             JOIN production_stages st ON st.id = s.production_stage_id
             WHERE s.job_id = ? ORDER BY s.sort_order, s.id',
            [$jobId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function stage(int $stageId): ?array
    {
        return $this->one(
            'SELECT s.*, st.name AS stage_name FROM job_production_stages s
             JOIN production_stages st ON st.id = s.production_stage_id WHERE s.id = ?',
            [$stageId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function policyFor(string $jobType, ?string $specificationCode): ?array
    {
        $specific = $this->one(
            "SELECT * FROM production_release_policies
             WHERE active = 1 AND applies_to = 'SPECIFICATION' AND applies_value = ? ORDER BY id LIMIT 1",
            [$specificationCode ?? '']
        );
        if ($specific !== null) {
            return $specific;
        }
        $typed = $this->one(
            "SELECT * FROM production_release_policies
             WHERE active = 1 AND applies_to = 'JOB_TYPE' AND applies_value = ? ORDER BY id LIMIT 1",
            [$jobType]
        );

        return $typed ?? $this->one(
            "SELECT * FROM production_release_policies WHERE active = 1 AND applies_to = 'DEFAULT' ORDER BY id LIMIT 1"
        );
    }

    /**
     * @return array<string, string>
     */
    public function severities(int $policyId): array
    {
        $default = $this->one("SELECT id FROM production_release_policies WHERE code = 'DEFAULT' LIMIT 1");
        $rows = [];
        if ($default !== null) {
            $rows = $this->rows(
                'SELECT check_code, severity FROM production_release_policy_checks WHERE policy_id = ? AND active = 1',
                [(int) $default['id']]
            );
        }
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['check_code']] = (string) $row['severity'];
        }
        if ($policyId > 0 && ($default === null || (int) $default['id'] !== $policyId)) {
            foreach ($this->rows(
                'SELECT check_code, severity FROM production_release_policy_checks WHERE policy_id = ? AND active = 1',
                [$policyId]
            ) as $row) {
                $map[(string) $row['check_code']] = (string) $row['severity'];
            }
        }

        return $map;
    }

    public function setSeverity(string $policyCode, string $check, string $severity): void
    {
        $this->run(
            'UPDATE production_release_policy_checks c
             JOIN production_release_policies p ON p.id = c.policy_id
             SET c.severity = ? WHERE p.code = ? AND c.check_code = ?',
            [$severity, $policyCode, $check]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function releaseByKey(string $key): ?array
    {
        return $this->one('SELECT * FROM production_releases WHERE idempotency_key = ?', [$key]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function release(int $id): ?array
    {
        return $this->one('SELECT * FROM production_releases WHERE id = ?', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function releaseByNumber(string $number): ?array
    {
        return $this->one('SELECT * FROM production_releases WHERE release_number = ?', [$number]);
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array<string, mixed>|null
     */
    public function reviewRelease(int $jobId): ?array
    {
        return $this->one(
            "SELECT * FROM production_releases
             WHERE job_id = ? AND status = 'REVIEW_REQUIRED' ORDER BY release_version DESC LIMIT 1",
            [$jobId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentRelease(int $jobId): ?array
    {
        return $this->one(
            "SELECT * FROM production_releases
             WHERE job_id = ? AND status = 'RELEASED' ORDER BY release_version DESC LIMIT 1",
            [$jobId]
        );
    }

    public function nextVersion(int $jobId): int
    {
        $row = $this->one('SELECT COALESCE(MAX(release_version), 0) AS n FROM production_releases WHERE job_id = ?', [$jobId]);

        return (int) ($row['n'] ?? 0) + 1;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertRelease(array $row): int
    {
        $this->run(
            'INSERT INTO production_releases (
                release_number, job_id, release_version, status, requested_by, requested_at,
                released_by, released_at, release_notes, override_reason, snapshot_json, idempotency_key
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['release_number'], $row['job_id'], $row['release_version'], $row['status'],
                $row['requested_by'], $row['requested_at'], $row['released_by'], $row['released_at'],
                $row['release_notes'], $row['override_reason'], $row['snapshot_json'], $row['idempotency_key'],
            ]
        );

        return $this->insertId();
    }

    public function setReleaseStatus(int $id, string $status, ?int $supersededBy = null): void
    {
        $this->run(
            'UPDATE production_releases SET status = ?, superseded_by_id = COALESCE(?, superseded_by_id) WHERE id = ?',
            [$status, $supersededBy, $id]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertReleaseItem(array $row): void
    {
        $this->run(
            'INSERT INTO production_release_items (release_id, job_item_id, quantity, status) VALUES (?,?,?,?)',
            [$row['release_id'], $row['job_item_id'], $row['quantity'], $row['status']]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertCheck(array $row): void
    {
        $this->run(
            'INSERT INTO production_release_checks (release_id, check_code, result, severity, message) VALUES (?,?,?,?,?)',
            [$row['release_id'], $row['check_code'], $row['result'], $row['severity'], $row['message']]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertOverride(array $row): void
    {
        $this->run(
            'INSERT INTO production_release_overrides (release_id, check_code, original_result, reason, created_by) VALUES (?,?,?,?,?)',
            [$row['release_id'], $row['check_code'], $row['original_result'], $row['reason'], $row['created_by']]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checks(int $releaseId): array
    {
        return $this->rows('SELECT * FROM production_release_checks WHERE release_id = ? ORDER BY id', [$releaseId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overrides(int $releaseId): array
    {
        return $this->rows('SELECT * FROM production_release_overrides WHERE release_id = ? ORDER BY id', [$releaseId]);
    }

    public function releasedQuantity(int $jobItemId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(i.quantity), 0) AS qty
             FROM production_release_items i
             JOIN production_releases r ON r.id = i.release_id
             WHERE i.job_item_id = ? AND r.status = 'RELEASED'",
            [$jobItemId]
        );

        return (string) ($row['qty'] ?? '0');
    }

    public function markPack(int $releaseId, int $superseded): void
    {
        $this->run('INSERT INTO production_packs (release_id, superseded) VALUES (?, ?)', [$releaseId, $superseded]);
    }

    public function supersedePacks(int $releaseId): void
    {
        $this->run('UPDATE production_packs SET superseded = 1 WHERE release_id = ?', [$releaseId]);
    }

    public function preparation(int $jobId, string $status): void
    {
        $this->run('UPDATE jobs SET preparation_status = ? WHERE id = ?', [$status, $jobId]);
    }

    public function updateItemRelease(int $itemId, string $status, string $released): void
    {
        $this->run(
            'UPDATE job_items SET release_status = ?, released_quantity = ? WHERE id = ?',
            [$status, $released, $itemId]
        );
    }

    public function addGood(int $itemId, string $good, string $waste, string $rework): void
    {
        $this->run(
            'UPDATE job_items SET good_quantity = ?, waste_quantity = ?, rework_quantity = ? WHERE id = ?',
            [$good, $waste, $rework, $itemId]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertChange(array $row): int
    {
        $this->run(
            'INSERT INTO production_changes (
                job_id, release_id, source, reason, requested_change, artwork_impact, material_impact,
                schedule_impact, cost_impact, impact, status, requested_by
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['job_id'], $row['release_id'], $row['source'], $row['reason'], $row['requested_change'],
                $row['artwork_impact'], $row['material_impact'], $row['schedule_impact'], $row['cost_impact'],
                $row['impact'], $row['status'], $row['requested_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function changes(int $jobId): array
    {
        return $this->rows('SELECT * FROM production_changes WHERE job_id = ? ORDER BY id', [$jobId]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function queue(array $filters, int $limit, int $offset): array
    {
        $sql = "SELECT s.id, j.job_number, j.customer_id, c.company_name, j.priority, j.target_date, j.preparation_status,
                       st.name AS stage_name, s.status, s.sort_order, i.description AS item_description, i.quantity
                FROM job_production_stages s
                JOIN jobs j ON j.id = s.job_id
                JOIN customers c ON c.id = j.customer_id
                JOIN production_stages st ON st.id = s.production_stage_id
                LEFT JOIN job_items i ON i.id = s.job_item_id
                WHERE j.preparation_status IN ('RELEASED', 'IN_PRODUCTION')
                  AND j.status NOT IN ('COMPLETED', 'CANCELLED')";
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND s.status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['priority'] ?? '') !== '') {
            $sql .= ' AND j.priority = ?';
            $params[] = $filters['priority'];
        }
        if ((int) ($filters['project_id'] ?? 0) > 0) {
            $sql .= ' AND j.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        $sql .= ' ORDER BY j.target_date IS NULL, j.target_date, s.sort_order, s.id LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        return $this->rows($sql, $params);
    }

    /**
     * @return array{overdue: int, blocked: int, not_released: int}
     */
    public function watchdogCounts(): array
    {
        $overdue = $this->one(
            "SELECT COUNT(*) AS n FROM jobs
             WHERE preparation_status IN ('RELEASED', 'IN_PRODUCTION')
               AND target_date IS NOT NULL AND target_date < CURDATE()
               AND status NOT IN ('COMPLETED', 'CANCELLED') AND archived = 0"
        );
        $blocked = $this->one(
            "SELECT COUNT(DISTINCT job_id) AS n FROM job_production_stages WHERE status = 'BLOCKED'"
        );
        $waiting = $this->one(
            "SELECT COUNT(*) AS n FROM jobs
             WHERE preparation_status = 'NOT_READY' AND status NOT IN ('COMPLETED', 'CANCELLED') AND archived = 0"
        );

        return [
            'overdue' => (int) ($overdue['n'] ?? 0),
            'blocked' => (int) ($blocked['n'] ?? 0),
            'not_released' => (int) ($waiting['n'] ?? 0),
        ];
    }

    public function upcomingCount(): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM jobs WHERE preparation_status = 'NOT_READY' AND status NOT IN ('COMPLETED', 'CANCELLED') AND archived = 0"
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertFulfilment(array $row): int
    {
        $this->run(
            'INSERT INTO fulfilment_requirements (
                job_id, job_item_id, fulfilment_type, quantity, project_site_id, address, contact_name,
                required_date, status, packing_status, instructions
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $row['job_id'], $row['job_item_id'], $row['fulfilment_type'], $row['quantity'],
                $row['project_site_id'], $row['address'], $row['contact_name'], $row['required_date'],
                $row['status'], $row['packing_status'], $row['instructions'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fulfilment(int $id): ?array
    {
        return $this->one('SELECT * FROM fulfilment_requirements WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fulfilments(int $jobId): array
    {
        return $this->rows('SELECT * FROM fulfilment_requirements WHERE job_id = ? ORDER BY id', [$jobId]);
    }

    public function openFulfilmentForItem(int $itemId): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM fulfilment_requirements
             WHERE job_item_id = ? AND status NOT IN ('FULFILLED', 'CANCELLED')",
            [$itemId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function openFulfilmentForJob(int $jobId): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM fulfilment_requirements
             WHERE job_id = ? AND status NOT IN ('FULFILLED', 'CANCELLED')",
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function updateFulfilment(int $id, string $fulfilled, string $status, ?string $collectedBy, ?string $collectedAt): void
    {
        $this->run(
            'UPDATE fulfilment_requirements SET fulfilled_quantity = ?, status = ?, collected_by = COALESCE(?, collected_by), collected_at = COALESCE(?, collected_at) WHERE id = ?',
            [$fulfilled, $status, $collectedBy, $collectedAt, $id]
        );
    }

    public function openQcFail(int $jobId): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM quality_checks WHERE job_id = ? AND status = 'FAIL' AND resolved_at IS NULL",
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertRework(array $row): int
    {
        $this->run(
            'INSERT INTO production_rework (job_id, job_item_id, stage_id, reason_code, material_cost, labour_cost, machine_cost, other_cost, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $row['job_id'], $row['job_item_id'], $row['stage_id'], $row['reason_code'],
                $row['material_cost'], $row['labour_cost'], $row['machine_cost'], $row['other_cost'],
                $row['notes'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function addReworkCost(int $jobId, string $material, string $labour, string $other): void
    {
        $this->run(
            'UPDATE jobs SET
                actual_total_cost = actual_material_cost + actual_labour_cost + actual_other_cost + ? + ? + ?,
                actual_material_cost = actual_material_cost + ?,
                actual_labour_cost = actual_labour_cost + ?,
                actual_other_cost = actual_other_cost + ?
             WHERE id = ?',
            [$material, $labour, $other, $material, $labour, $other, $jobId]
        );
    }

    public function stageActionExists(string $key): bool
    {
        $row = $this->one('SELECT id FROM production_stage_actions WHERE idempotency_key = ?', [$key]);

        return $row !== null;
    }

    public function insertStageAction(int $stageId, string $action, ?string $key, int $userId): void
    {
        $this->run(
            'INSERT INTO production_stage_actions (stage_id, action, idempotency_key, user_id) VALUES (?,?,?,?)',
            [$stageId, $action, $key, $userId]
        );
    }

    public function updateStage(int $stageId, string $status, ?string $started, ?string $completed, ?int $userId, ?string $pause, ?string $blocked, ?int $releaseId): void
    {
        $this->run(
            'UPDATE job_production_stages
             SET status = ?, started_at = COALESCE(?, started_at), completed_at = ?, started_by = COALESCE(?, started_by),
                 pause_reason = ?, blocked_reason = ?, release_id = COALESCE(?, release_id)
             WHERE id = ?',
            [$status, $started, $completed, $userId, $pause, $blocked, $releaseId, $stageId]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function releaseQueue(array $filters): array
    {
        $sql = "SELECT j.id, j.job_number, j.preparation_status, j.target_date, j.project_id, c.company_name
                FROM jobs j JOIN customers c ON c.id = j.customer_id
                WHERE j.archived = 0 AND j.status NOT IN ('COMPLETED', 'CANCELLED')";
        $params = [];
        if ((int) ($filters['project_id'] ?? 0) > 0) {
            $sql .= ' AND j.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        if (($filters['preparation'] ?? '') !== '') {
            $sql .= ' AND j.preparation_status = ?';
            $params[] = $filters['preparation'];
        }
        $sql .= ' ORDER BY j.target_date IS NULL, j.target_date, j.id LIMIT 200';

        return $this->rows($sql, $params);
    }

    public function linkReservation(int $reservationId, int $releaseId): void
    {
        $this->run('UPDATE stock_reservations SET source_release_id = ? WHERE id = ?', [$releaseId, $reservationId]);
    }

    public function reservationsForRelease(int $releaseId): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM stock_reservations WHERE source_release_id = ? AND status = 'RESERVED'",
            [$releaseId]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertFile(array $row): int
    {
        $this->run(
            'INSERT INTO production_files (job_id, job_item_id, release_id, category, status, version_label, artwork_revision, original_name, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                $row['job_id'], $row['job_item_id'], $row['release_id'], $row['category'], $row['status'],
                $row['version_label'], $row['artwork_revision'], $row['original_name'], $row['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function files(int $jobId): array
    {
        return $this->rows('SELECT * FROM production_files WHERE job_id = ? ORDER BY id', [$jobId]);
    }

    public function approvedFileCount(int $jobId, string $category): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM production_files WHERE job_id = ? AND category = ? AND status = 'APPROVED_FOR_PRODUCTION'",
            [$jobId, $category]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function artworkFacts(int $jobId): array
    {
        $row = $this->one(
            'SELECT
                (SELECT COUNT(*) FROM job_items WHERE job_id = ? AND artwork_required = 1) AS required_items,
                (SELECT COUNT(*) FROM job_artworks WHERE job_id = ? AND customer_approved = 1) AS approved,
                (SELECT MAX(revision_number) FROM job_artworks WHERE job_id = ?) AS revision',
            [$jobId, $jobId, $jobId]
        );

        return $row ?? ['required_items' => 0, 'approved' => 0, 'revision' => null];
    }

    public function technicalOpen(int $jobId): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM sign_calculations
             WHERE job_id = ? AND technical_review_required = 1 AND status = 'REVIEW'",
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function qcChecklistCount(int $jobId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM qc_checklist_items q
             JOIN job_items i ON i.product_id = q.product_id
             WHERE i.job_id = ? AND q.active = 1',
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }
}

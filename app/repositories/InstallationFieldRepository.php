<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Field installation rows on the existing job_installations table.
 */
final class InstallationFieldRepository extends Repository
{
    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO job_installations (
                job_id, project_id, project_site_id, fulfilment_requirement_id, service_request_id, contractor_work_order_id,
                scheduled_date, site_address, site_contact_name, site_contact_phone, status, installation_notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'PLANNED\', ?, ?)',
            [
                $data['job_id'], $data['project_id'], $data['project_site_id'], $data['fulfilment_requirement_id'],
                $data['service_request_id'], $data['contractor_work_order_id'], $data['scheduled_date'],
                $data['site_address'], $data['site_contact_name'], $data['site_contact_phone'],
                $data['installation_notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM job_installations WHERE id = ?', [$id]);
    }

    public function jobStatus(int $jobId): ?string
    {
        $row = $this->one('SELECT status FROM jobs WHERE id = ?', [$jobId]);

        return $row === null ? null : (string) $row['status'];
    }

    public function setStatus(int $id, string $status, array $extra = []): void
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
        $this->run('UPDATE job_installations SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function template(string $appliesValue): array
    {
        return $this->rows(
            'SELECT i.label, i.sort_order
             FROM installation_checklist_template_items i
             INNER JOIN installation_checklist_templates t ON t.id = i.template_id
             WHERE t.active = 1 AND t.applies_value = ?
             ORDER BY i.sort_order',
            [$appliesValue]
        );
    }

    public function copyCheck(int $installationId, string $label, int $sort): void
    {
        $this->run(
            'INSERT INTO installation_checklist_items (installation_id, label, sort_order) VALUES (?, ?, ?)',
            [$installationId, $label, $sort]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function checks(int $installationId): array
    {
        return $this->rows('SELECT * FROM installation_checklist_items WHERE installation_id = ? ORDER BY sort_order, id', [$installationId]);
    }

    public function checkItem(int $id, int $userId): void
    {
        $this->run(
            'UPDATE installation_checklist_items SET checked = 1, checked_by = ?, checked_at = NOW() WHERE id = ?',
            [$userId, $id]
        );
    }

    public function insertPhoto(string $uuid, string $entityType, int $entityId, string $category, string $path, int $userId): int
    {
        $this->run(
            'INSERT INTO field_photos (local_uuid, entity_type, entity_id, category, display_path, thumb_path, file_size, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
            [$uuid, $entityType, $entityId, $category, $path, $path, $userId]
        );

        return $this->insertId();
    }

    public function insertSnag(array $data): int
    {
        $this->run(
            'INSERT INTO job_snags (job_id, installation_id, project_site_id, description, snag_type, severity, source_category, priority, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'OPEN\', ?)',
            [
                $data['job_id'], $data['installation_id'], $data['project_site_id'], $data['description'],
                $data['snag_type'], $data['severity'], $data['source_category'], $data['priority'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function openBlocking(int $installationId): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM job_snags WHERE installation_id = ? AND severity = 'BLOCKING' AND status IN ('OPEN','IN_PROGRESS')",
            [$installationId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function insertSignoff(array $data): int
    {
        $this->run(
            'INSERT INTO installation_signoffs (installation_id, job_item_id, project_site_id, signer_name, signer_role, statement_version, signature_id, device_signed_at, signed_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['installation_id'], $data['job_item_id'], $data['project_site_id'], $data['signer_name'],
                $data['signer_role'], $data['statement_version'], $data['signature_id'], $data['device_signed_at'],
                $data['signed_at'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function signoffCount(int $installationId): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM installation_signoffs WHERE installation_id = ?', [$installationId]);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function jobContext(int $jobId): ?array
    {
        return $this->one(
            'SELECT j.id, j.job_number, j.title, j.status, j.customer_id, j.project_id, c.company_name, c.first_name, c.last_name
             FROM jobs j INNER JOIN customers c ON c.id = j.customer_id WHERE j.id = ?',
            [$jobId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function artworkTitles(int $jobId): array
    {
        return $this->rows(
            'SELECT title, status FROM job_artworks WHERE job_id = ? AND status NOT IN (\'ARCHIVED\') ORDER BY id DESC LIMIT 5',
            [$jobId]
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Administration reads: audit, sign-in history, backups, documents, and exports.
 */
final class SystemRepository extends Repository
{
    /**
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public function audit(array $filters): array
    {
        $sql = 'SELECT a.*, u.name AS user_name
                FROM audit_log a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE 1 = 1';
        $params = [];
        if (($filters['user_id'] ?? '') !== '') {
            $sql .= ' AND a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (($filters['action'] ?? '') !== '') {
            $sql .= ' AND a.action = ?';
            $params[] = $filters['action'];
        }
        if (($filters['entity'] ?? '') !== '') {
            $sql .= ' AND a.entity_type = ?';
            $params[] = $filters['entity'];
        }
        if (($filters['from'] ?? '') !== '') {
            $sql .= ' AND DATE(a.created_at) >= ?';
            $params[] = $filters['from'];
        }
        if (($filters['to'] ?? '') !== '') {
            $sql .= ' AND DATE(a.created_at) <= ?';
            $params[] = $filters['to'];
        }
        if (($filters['ip'] ?? '') !== '') {
            $sql .= ' AND a.ip_address = ?';
            $params[] = $filters['ip'];
        }

        return $this->rows($sql . ' ORDER BY a.id DESC LIMIT 200', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function logins(int $limit = 80): array
    {
        return $this->rows(
            'SELECT l.*, u.name AS user_name
             FROM login_events l
             LEFT JOIN users u ON u.id = l.user_id
             ORDER BY l.id DESC
             LIMIT ' . (int) $limit
        );
    }

    public function recentFailedLogins(string $ip, int $minutes): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM login_events
             WHERE ip_address = ? AND success = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL ' . max(1, $minutes) . ' MINUTE)',
            [$ip]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function recordLogin(?int $userId, string $email, ?string $ip, bool $success): void
    {
        $this->run(
            'INSERT INTO login_events (user_id, email, ip_address, success) VALUES (?, ?, ?, ?)',
            [$userId, $email, $ip, $success ? 1 : 0]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertBackup(array $data): int
    {
        $this->run(
            'INSERT INTO system_backups (backup_type, filename, file_size, status, created_by, notes)
             VALUES (?, ?, 0, \'STARTED\', ?, ?)',
            [$data['backup_type'], $data['filename'], $data['created_by'], $data['notes']]
        );

        return $this->insertId();
    }

    public function finishBackup(int $id, string $status, int $size, string $notes): void
    {
        $this->run(
            'UPDATE system_backups SET status = ?, file_size = ?, completed_at = NOW(), notes = ? WHERE id = ?',
            [$status, $size, $notes, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function backups(): array
    {
        return $this->rows(
            'SELECT b.*, u.name AS user_name
             FROM system_backups b
             LEFT JOIN users u ON u.id = b.created_by
             ORDER BY b.id DESC
             LIMIT 50'
        );
    }

    public function backup(int $id): ?array
    {
        return $this->one('SELECT * FROM system_backups WHERE id = ?', [$id]);
    }

    public function latestSuccessfulBackup(): ?array
    {
        return $this->one(
            "SELECT * FROM system_backups WHERE status = 'SUCCESS' ORDER BY id DESC LIMIT 1"
        );
    }

    /**
     * @param array<string, string> $filters
     * @return list<array<string, mixed>>
     */
    public function documents(array $filters): array
    {
        $sql = 'SELECT a.*, u.name AS uploaded_name
                FROM attachments a
                LEFT JOIN users u ON u.id = a.uploaded_by
                WHERE 1 = 1';
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (a.original_filename LIKE ? OR a.purpose LIKE ? OR a.entity_type LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (($filters['entity_type'] ?? '') !== '') {
            $sql .= ' AND a.entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (($filters['from'] ?? '') !== '') {
            $sql .= ' AND DATE(a.created_at) >= ?';
            $params[] = $filters['from'];
        }
        if (($filters['to'] ?? '') !== '') {
            $sql .= ' AND DATE(a.created_at) <= ?';
            $params[] = $filters['to'];
        }

        return $this->rows($sql . ' ORDER BY a.id DESC LIMIT 150', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchJobs(string $term): array
    {
        $like = '%' . $term . '%';

        return $this->rows(
            'SELECT j.id, j.job_number, j.title, j.status, c.company_name, c.first_name, c.last_name, c.customer_type
             FROM jobs j
             INNER JOIN customers c ON c.id = j.customer_id
             WHERE j.job_number LIKE ? OR j.title LIKE ? OR c.company_name LIKE ?
             ORDER BY j.id DESC LIMIT 20',
            [$like, $like, $like]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchContacts(string $term): array
    {
        $like = '%' . $term . '%';

        return $this->rows(
            'SELECT c.id, c.customer_id, c.name, c.email, c.phone, cu.company_name, cu.first_name, cu.last_name, cu.customer_type
             FROM customer_contacts c
             INNER JOIN customers cu ON cu.id = c.customer_id
             WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?
             ORDER BY c.id DESC LIMIT 20',
            [$like, $like, $like]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchOrders(string $term): array
    {
        $like = '%' . $term . '%';

        return $this->rows(
            'SELECT po.id, po.po_number, po.status, s.name AS supplier_name
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.po_number LIKE ? OR s.name LIKE ? OR po.supplier_reference LIKE ?
             ORDER BY po.id DESC LIMIT 20',
            [$like, $like, $like]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function communications(int $customerId): array
    {
        return $this->rows(
            'SELECT c.*, u.name AS user_name
             FROM communications c
             LEFT JOIN users u ON u.id = c.sent_by
             WHERE c.customer_id = ?
             ORDER BY c.id DESC LIMIT 30',
            [$customerId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCommunication(array $data): int
    {
        $this->run(
            'INSERT INTO communications (
                customer_id, contact_id, lead_id, opportunity_id, quote_id, job_id, invoice_id,
                entity_type, entity_id, channel, direction, subject, message_summary, message_body,
                status, failure_reason, sent_by, sent_at, received_at, external_reference,
                external_message_id, thread_id, template_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'] ?? null,
                $data['contact_id'] ?? null,
                $data['lead_id'] ?? null,
                $data['opportunity_id'] ?? null,
                $data['quote_id'] ?? null,
                $data['job_id'] ?? null,
                $data['invoice_id'] ?? null,
                $data['entity_type'] ?? null,
                $data['entity_id'] ?? null,
                $data['channel'],
                $data['direction'] ?? 'OUTBOUND',
                $data['subject'],
                $data['message_summary'] ?? null,
                $data['message_body'] ?? null,
                $data['status'] ?? 'LOGGED',
                $data['failure_reason'] ?? null,
                $data['sent_by'] ?? null,
                $data['sent_at'] ?? date('Y-m-d H:i:s'),
                $data['received_at'] ?? null,
                $data['external_reference'] ?? null,
                $data['external_message_id'] ?? null,
                $data['thread_id'] ?? null,
                $data['template_id'] ?? null,
            ]
        );

        return $this->insertId();
    }

    public function roleId(string $code): ?int
    {
        $row = $this->one('SELECT id FROM roles WHERE code = ?', [$code]);

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roles(): array
    {
        return $this->rows(
            'SELECT r.id, r.code, r.name, r.description, COUNT(rp.permission_id) AS permissions
             FROM roles r
             LEFT JOIN role_permissions rp ON rp.role_id = r.id
             GROUP BY r.id, r.code, r.name, r.description
             ORDER BY r.name'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rolePermissions(int $roleId): array
    {
        return $this->rows(
            'SELECT p.code, p.name, p.module
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?
             ORDER BY p.module, p.code',
            [$roleId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function targets(): array
    {
        return $this->rows('SELECT * FROM kpi_targets ORDER BY name');
    }

    public function updateTarget(int $id, string $value, bool $active): void
    {
        $this->run(
            'UPDATE kpi_targets SET target_value = ?, active = ? WHERE id = ?',
            [$value, $active ? 1 : 0, $id]
        );
    }

    public function databaseVersion(): string
    {
        $row = $this->one('SELECT VERSION() AS v');

        return (string) ($row['v'] ?? '');
    }
}

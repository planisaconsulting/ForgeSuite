<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Every read is filtered by the portal user's customer. Callers must pass
 * that id from the session, never from the browser.
 */
final class PortalRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function userByEmail(string $email): ?array
    {
        return $this->one('SELECT * FROM portal_users WHERE email = ? LIMIT 1', [strtolower(trim($email))]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(int $id): ?array
    {
        return $this->one('SELECT * FROM portal_users WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function usersForCustomer(int $customerId): array
    {
        return $this->rows('SELECT id, customer_id, email, active, last_login_at, created_at FROM portal_users WHERE customer_id = ? ORDER BY email', [$customerId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertUser(array $data): int
    {
        $this->run(
            'INSERT INTO portal_users (customer_id, customer_contact_id, email, password_hash, active) VALUES (?, ?, ?, ?, ?)',
            [$data['customer_id'], $data['customer_contact_id'], $data['email'], $data['password_hash'], $data['active']]
        );

        return $this->insertId();
    }

    public function touchLogin(int $id): void
    {
        $this->run('UPDATE portal_users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public function setPassword(int $id, string $hash): void
    {
        $this->run('UPDATE portal_users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    public function setActive(int $id, int $active): void
    {
        $this->run('UPDATE portal_users SET active = ? WHERE id = ?', [$active, $id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function quote(int $customerId, int $quoteId): ?array
    {
        return $this->one(
            'SELECT id, quote_number, revision_number, status, quote_date, expiry_date, subtotal, vat_amount, total, terms, customer_notes, version_number
             FROM quotes WHERE id = ? AND customer_id = ? AND archived = 0 LIMIT 1',
            [$quoteId, $customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quotes(int $customerId): array
    {
        return $this->rows(
            'SELECT id, quote_number, revision_number, status, quote_date, expiry_date, total
             FROM quotes WHERE customer_id = ? AND archived = 0 AND status <> \'DRAFT\'
             ORDER BY id DESC LIMIT 50',
            [$customerId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function job(int $customerId, int $jobId): ?array
    {
        return $this->one(
            'SELECT id, job_number, title, status, customer_promised_date, quote_id FROM jobs
             WHERE id = ? AND customer_id = ? LIMIT 1',
            [$jobId, $customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jobs(int $customerId): array
    {
        return $this->rows(
            'SELECT id, job_number, title, status, target_date, installation_date FROM jobs
             WHERE customer_id = ? ORDER BY id DESC LIMIT 50',
            [$customerId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function invoice(int $customerId, int $invoiceId): ?array
    {
        return $this->one(
            "SELECT id, invoice_number, status, invoice_date, due_date, total, amount_paid, balance_due
             FROM invoices WHERE id = ? AND customer_id = ? AND status NOT IN ('DRAFT', 'CANCELLED') LIMIT 1",
            [$invoiceId, $customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function invoices(int $customerId): array
    {
        return $this->rows(
            "SELECT id, invoice_number, status, invoice_date, due_date, total, amount_paid, balance_due
             FROM invoices WHERE customer_id = ? AND status NOT IN ('DRAFT', 'CANCELLED') ORDER BY id DESC LIMIT 50",
            [$customerId]
        );
    }

    public function balance(int $customerId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(balance_due), 0) AS balance FROM invoices
             WHERE customer_id = ? AND status NOT IN ('DRAFT', 'CANCELLED', 'VOID')",
            [$customerId]
        );

        return (string) ($row['balance'] ?? '0');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function artwork(int $customerId, int $artworkId): ?array
    {
        return $this->one(
            "SELECT a.* FROM job_artworks a
             INNER JOIN jobs j ON j.id = a.job_id
             WHERE a.id = ? AND j.customer_id = ?
               AND a.status IN ('SENT_FOR_APPROVAL', 'CHANGES_REQUESTED', 'APPROVED', 'SUPERSEDED')
             LIMIT 1",
            [$artworkId, $customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function artworks(int $customerId): array
    {
        return $this->rows(
            "SELECT a.id, a.job_id, a.title, a.revision_number, a.status, a.notes, j.job_number
             FROM job_artworks a
             INNER JOIN jobs j ON j.id = a.job_id
             WHERE j.customer_id = ?
               AND a.status IN ('SENT_FOR_APPROVAL', 'CHANGES_REQUESTED', 'APPROVED', 'SUPERSEDED')
             ORDER BY a.id DESC LIMIT 50",
            [$customerId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attachment(int $customerId, int $attachmentId): ?array
    {
        return $this->one(
            "SELECT a.* FROM attachments a
             WHERE a.id = ? AND a.visibility IN ('CUSTOMER_VISIBLE', 'CUSTOMER_UPLOADED')
               AND (
                    (a.entity_type = 'customer' AND a.entity_id = ?)
                    OR (a.entity_type = 'quote' AND a.entity_id IN (SELECT id FROM quotes WHERE customer_id = ?))
                    OR (a.entity_type = 'job' AND a.entity_id IN (SELECT id FROM jobs WHERE customer_id = ?))
                    OR (a.entity_type = 'invoice' AND a.entity_id IN (SELECT id FROM invoices WHERE customer_id = ?))
               )
             LIMIT 1",
            [$attachmentId, $customerId, $customerId, $customerId, $customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function documents(int $customerId): array
    {
        return $this->rows(
            "SELECT a.id, a.original_filename, a.visibility, a.entity_type, a.created_at
             FROM attachments a
             WHERE a.visibility IN ('CUSTOMER_VISIBLE', 'CUSTOMER_UPLOADED')
               AND (
                    (a.entity_type = 'customer' AND a.entity_id = ?)
                    OR (a.entity_type = 'quote' AND a.entity_id IN (SELECT id FROM quotes WHERE customer_id = ?))
                    OR (a.entity_type = 'job' AND a.entity_id IN (SELECT id FROM jobs WHERE customer_id = ?))
                    OR (a.entity_type = 'invoice' AND a.entity_id IN (SELECT id FROM invoices WHERE customer_id = ?))
               )
             ORDER BY a.id DESC LIMIT 40",
            [$customerId, $customerId, $customerId, $customerId]
        );
    }

    public function customerLabel(int $customerId): string
    {
        $row = $this->one('SELECT company_name, first_name, last_name, customer_type FROM customers WHERE id = ?', [$customerId]);

        return $row === null ? 'Customer' : customer_label($row);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertToken(array $data): void
    {
        $this->run(
            'INSERT INTO portal_access_tokens (token_hash, portal_user_id, customer_id, purpose, entity_type, entity_id, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['token_hash'], $data['portal_user_id'], $data['customer_id'], $data['purpose'],
                $data['entity_type'], $data['entity_id'], $data['expires_at'],
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function tokenByHash(string $hash): ?array
    {
        return $this->one('SELECT * FROM portal_access_tokens WHERE token_hash = ? LIMIT 1', [$hash]);
    }

    public function useToken(int $id): void
    {
        $this->run('UPDATE portal_access_tokens SET used_at = NOW() WHERE id = ?', [$id]);
    }

    public function revokeToken(int $id): void
    {
        $this->run('UPDATE portal_access_tokens SET revoked_at = NOW() WHERE id = ?', [$id]);
    }

    public function audit(int $customerId, ?int $portalUserId, string $event, ?string $entityType, ?int $entityId, ?string $ip): void
    {
        $this->run(
            'INSERT INTO portal_audit_log (portal_user_id, customer_id, event, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
            [$portalUserId, $customerId, $event, $entityType, $entityId, $ip]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertAction(array $data): void
    {
        $this->run(
            'INSERT INTO portal_actions (
                customer_id, portal_user_id, action, entity_type, entity_id, revision_number,
                statement_version, statement_text, ip_address, payload_json
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'], $data['portal_user_id'], $data['action'], $data['entity_type'],
                $data['entity_id'], $data['revision_number'], $data['statement_version'], $data['statement_text'],
                $data['ip_address'], $data['payload_json'],
            ]
        );
    }

    public function hasAction(int $customerId, string $action, string $entityType, int $entityId, ?int $revision): bool
    {
        $sql = 'SELECT id FROM portal_actions WHERE customer_id = ? AND action = ? AND entity_type = ? AND entity_id = ?';
        $params = [$customerId, $action, $entityType, $entityId];
        if ($revision !== null) {
            $sql .= ' AND revision_number = ?';
            $params[] = $revision;
        }
        $sql .= ' LIMIT 1';

        return $this->one($sql, $params) !== null;
    }

    public function insertMessage(int $customerId, ?int $portalUserId, string $entityType, int $entityId, string $body): void
    {
        $this->run(
            'INSERT INTO portal_messages (customer_id, portal_user_id, entity_type, entity_id, body) VALUES (?, ?, ?, ?, ?)',
            [$customerId, $portalUserId, $entityType, $entityId, $body]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function emailTemplate(string $code): ?array
    {
        return $this->one('SELECT * FROM email_templates WHERE code = ? LIMIT 1', [$code]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchUsers(string $term): array
    {
        $like = '%' . $term . '%';

        return $this->rows(
            'SELECT u.id, u.email, u.active, c.company_name
             FROM portal_users u
             INNER JOIN customers c ON c.id = u.customer_id
             WHERE u.email LIKE ? OR c.company_name LIKE ?
             ORDER BY u.email LIMIT 20',
            [$like, $like]
        );
    }

    public function labelForStatus(string $status): string
    {
        $row = $this->one('SELECT customer_label FROM portal_status_map WHERE internal_status = ?', [$status]);

        return (string) ($row['customer_label'] ?? 'In preparation');
    }

    /**
     * @return array{attempts: int, window_start: string}|null
     */
    public function rate(string $bucket): ?array
    {
        $row = $this->one('SELECT attempts, window_start FROM portal_rate_limits WHERE bucket = ?', [$bucket]);

        return $row === null ? null : ['attempts' => (int) $row['attempts'], 'window_start' => (string) $row['window_start']];
    }

    public function saveRate(string $bucket, int $attempts, string $window): void
    {
        $this->run(
            'INSERT INTO portal_rate_limits (bucket, attempts, window_start) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), window_start = VALUES(window_start)',
            [$bucket, $attempts, $window]
        );
    }
}

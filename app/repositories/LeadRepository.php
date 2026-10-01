<?php

declare(strict_types=1);

namespace App\Repositories;

final class LeadRepository extends Repository
{
    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function inbox(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->where($filters);

        return $this->rows(
            'SELECT l.*, u.name AS assignee_name, c.campaign_code
             FROM leads l
             LEFT JOIN users u ON u.id = l.assigned_to
             LEFT JOIN marketing_campaigns c ON c.id = l.campaign_id
             WHERE ' . $where . '
             ORDER BY l.id DESC
             LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT l.*, u.name AS assignee_name, c.name AS campaign_name, c.campaign_code
             FROM leads l
             LEFT JOIN users u ON u.id = l.assigned_to
             LEFT JOIN marketing_campaigns c ON c.id = l.campaign_id
             WHERE l.id = ?',
            [$id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO leads (
                lead_number, source, source_detail, campaign_id, name, company_name, email, phone,
                phone_normalised, message, message_hash, service_interest, estimated_value, status,
                assigned_to, attribution_json, attribution_model
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'FIRST_TOUCH\')',
            [
                $data['lead_number'], $data['source'], $data['source_detail'], $data['campaign_id'],
                $data['name'], $data['company_name'], $data['email'], $data['phone'],
                $data['phone_normalised'], $data['message'], $data['message_hash'],
                $data['service_interest'], $data['estimated_value'], $data['status'],
                $data['assigned_to'], $data['attribution_json'],
            ]
        );

        return $this->insertId();
    }

    public function assign(int $id, int $userId): void
    {
        $this->run(
            'UPDATE leads SET assigned_to = ?, status = CASE WHEN status IN (\'NEW\', \'UNASSIGNED\') THEN \'ASSIGNED\' ELSE status END WHERE id = ?',
            [$userId, $id]
        );
    }

    public function touch(int $id, string $when): void
    {
        $this->run(
            'UPDATE leads
             SET first_contact_at = COALESCE(first_contact_at, ?),
                 last_contact_at = ?,
                 status = CASE WHEN status IN (\'NEW\', \'UNASSIGNED\', \'ASSIGNED\') THEN \'CONTACTED\' ELSE status END
             WHERE id = ?',
            [$when, $when, $id]
        );
    }

    public function setFollowUp(int $id, string $when): void
    {
        $this->run('UPDATE leads SET next_followup_at = ? WHERE id = ?', [$when, $id]);
    }

    public function mark(int $id, string $status, ?string $reason): void
    {
        $this->run('UPDATE leads SET status = ?, lost_reason = ? WHERE id = ?', [$status, $reason, $id]);
    }

    /**
     * @param array<string, mixed> $links
     */
    public function convert(int $id, array $links, int $userId, string $when): void
    {
        $this->run(
            'UPDATE leads
             SET status = \'CONVERTED\', customer_id = ?, contact_id = ?, opportunity_id = ?,
                 converted_at = ?, converted_by = ?
             WHERE id = ?',
            [$links['customer_id'], $links['contact_id'], $links['opportunity_id'], $when, $userId, $id]
        );
    }

    public function linkCustomer(int $id, int $customerId): void
    {
        $this->run('UPDATE leads SET customer_id = ? WHERE id = ?', [$customerId, $id]);
    }

    public function duplicateHash(string $hash): bool
    {
        $row = $this->one(
            'SELECT id FROM leads WHERE message_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 1',
            [$hash]
        );

        return $row !== null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sources(): array
    {
        return $this->rows('SELECT * FROM lead_sources WHERE active = 1 ORDER BY label');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function outcomes(): array
    {
        return $this->rows('SELECT * FROM call_outcomes WHERE active = 1 ORDER BY label');
    }

    public function campaignByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM marketing_campaigns WHERE campaign_code = ? LIMIT 1', [$code]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function phoneMatches(string $key): array
    {
        if ($key === '') {
            return [];
        }

        return $this->rows(
            'SELECT \'contact\' AS kind, c.id, c.customer_id, c.name AS label, cu.company_name, c.email, c.phone, \'phone\' AS reason
             FROM customer_contacts c
             INNER JOIN customers cu ON cu.id = c.customer_id
             WHERE c.active = 1
               AND (
                    RIGHT(REGEXP_REPLACE(IFNULL(c.phone, \'\'), \'[^0-9]\', \'\'), 9) = ?
                    OR RIGHT(REGEXP_REPLACE(IFNULL(c.mobile, \'\'), \'[^0-9]\', \'\'), 9) = ?
               )
             LIMIT 8',
            [$key, $key]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function emailMatches(string $email): array
    {
        return $this->rows(
            'SELECT \'contact\' AS kind, c.id, c.customer_id, c.name AS label, cu.company_name, c.email, c.phone, \'email\' AS reason
             FROM customer_contacts c
             INNER JOIN customers cu ON cu.id = c.customer_id
             WHERE c.active = 1 AND c.email = ?
             UNION ALL
             SELECT \'customer\', cu.id, cu.id, COALESCE(cu.company_name, CONCAT(cu.first_name, \' \', cu.last_name)),
                    cu.company_name, cu.email, cu.phone, \'email\'
             FROM customers cu
             WHERE cu.active = 1 AND cu.email = ?
             LIMIT 8',
            [$email, $email]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function companyMatches(string $company): array
    {
        return $this->rows(
            'SELECT \'customer\' AS kind, id, id AS customer_id, company_name AS label, company_name, email, phone, \'company\' AS reason
             FROM customers
             WHERE active = 1 AND company_name IS NOT NULL AND LOWER(company_name) = LOWER(?)
             LIMIT 8',
            [$company]
        );
    }

    public function countOpen(): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM leads WHERE status IN (\'NEW\', \'UNASSIGNED\', \'ASSIGNED\')'
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function where(array $filters): array
    {
        $sql = ['1 = 1'];
        $params = [];
        $status = (string) ($filters['status'] ?? '');
        if ($status === 'FOLLOWUP') {
            $sql[] = 'l.next_followup_at IS NOT NULL AND l.status NOT IN (\'CONVERTED\', \'LOST\', \'SPAM\')';
        } elseif ($status === 'OVERDUE') {
            $sql[] = 'l.next_followup_at IS NOT NULL AND l.next_followup_at < NOW() AND l.status NOT IN (\'CONVERTED\', \'LOST\', \'SPAM\')';
        } elseif ($status === 'UNCONTACTED') {
            $sql[] = 'l.first_contact_at IS NULL AND l.status IN (\'NEW\', \'UNASSIGNED\', \'ASSIGNED\')';
        } elseif ($status !== '') {
            $sql[] = 'l.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['mine'])) {
            $sql[] = '(l.assigned_to = ? OR l.assigned_to IS NULL)';
            $params[] = (int) $filters['mine'];
        }
        if (!empty($filters['assigned_only'])) {
            $sql[] = 'l.assigned_to = ?';
            $params[] = (int) $filters['assigned_only'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql[] = '(l.name LIKE ? OR l.company_name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.lead_number LIKE ?)';
            $like = like_term($q);
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [implode(' AND ', $sql), $params];
    }
}

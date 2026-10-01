<?php

declare(strict_types=1);

namespace App\Repositories;

final class OpportunityRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one($this->select() . ' WHERE o.id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, string $status): array
    {
        $sql = $this->select() . ' WHERE 1 = 1';
        $params = [];
        if ($term !== '') {
            $like = like_term($term);
            $sql .= ' AND (o.opportunity_number LIKE ? ESCAPE \'\\\\\'
                      OR o.title LIKE ? ESCAPE \'\\\\\'
                      OR c.company_name LIKE ? ESCAPE \'\\\\\'
                      OR c.last_name LIKE ? ESCAPE \'\\\\\' )';
            $params = [$like, $like, $like, $like];
        }
        if ($status !== '' && $status !== 'all') {
            $sql .= ' AND o.status = ?';
            $params[] = strtoupper($status);
        }

        return $this->rows($sql . ' ORDER BY o.updated_at DESC LIMIT 200', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        return $this->rows(
            $this->select() . ' WHERE o.customer_id = ? ORDER BY o.updated_at DESC',
            [$customerId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO sales_opportunities (
                opportunity_number, customer_id, contact_id, title, description, source,
                estimated_value, probability_percent, status, assigned_to,
                expected_close_date, next_follow_up_date, notes, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['opportunity_number'], $data['customer_id'], $data['contact_id'], $data['title'],
                $data['description'], $data['source'], $data['estimated_value'], $data['probability_percent'],
                $data['status'], $data['assigned_to'], $data['expected_close_date'],
                $data['next_follow_up_date'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->run(
            'UPDATE sales_opportunities SET
                contact_id = ?, title = ?, description = ?, source = ?, estimated_value = ?,
                probability_percent = ?, status = ?, lost_reason = ?, lost_notes = ?, assigned_to = ?,
                expected_close_date = ?, next_follow_up_date = ?, notes = ?
             WHERE id = ?',
            [
                $data['contact_id'], $data['title'], $data['description'], $data['source'],
                $data['estimated_value'], $data['probability_percent'], $data['status'],
                $data['lost_reason'], $data['lost_notes'], $data['assigned_to'],
                $data['expected_close_date'], $data['next_follow_up_date'], $data['notes'], $id,
            ]
        );
    }

    public function setStatus(int $id, string $status, ?string $lostReason, ?string $lostNotes): void
    {
        $this->run(
            'UPDATE sales_opportunities SET status = ?, lost_reason = ?, lost_notes = ? WHERE id = ?',
            [$status, $lostReason, $lostNotes, $id]
        );
    }

    /**
     * @return array{open_count: string, open_value: string}
     */
    public function openSummary(): array
    {
        $row = $this->one(
            'SELECT COUNT(*) AS open_count, COALESCE(SUM(estimated_value), 0) AS open_value
             FROM sales_opportunities
             WHERE status IN (\'NEW\', \'CONTACTED\', \'QUALIFIED\', \'QUOTED\', \'ON_HOLD\')'
        );

        return [
            'open_count' => (string) ($row['open_count'] ?? 0),
            'open_value' => (string) ($row['open_value'] ?? '0'),
        ];
    }

    private function select(): string
    {
        return 'SELECT o.*, c.company_name, c.first_name, c.last_name, c.customer_type,
                       ct.name AS contact_name, u.name AS salesperson_name
                FROM sales_opportunities o
                INNER JOIN customers c ON c.id = o.customer_id
                LEFT JOIN customer_contacts ct ON ct.id = o.contact_id
                LEFT JOIN users u ON u.id = o.assigned_to';
    }
}

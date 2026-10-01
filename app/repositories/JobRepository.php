<?php

declare(strict_types=1);

namespace App\Repositories;

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
    public function findByQuote(int $quoteId): ?array
    {
        return $this->one($this->select() . ' WHERE j.quote_id = ? LIMIT 1', [$quoteId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        return $this->rows(
            $this->select() . ' WHERE j.customer_id = ? ORDER BY j.id DESC',
            [$customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        return $this->rows($this->select() . ' ORDER BY j.id DESC LIMIT ' . (int) $limit);
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

    private function select(): string
    {
        return 'SELECT j.*, c.company_name, c.first_name, c.last_name, c.customer_type,
                       q.quote_number, u.name AS salesperson_name, creator.name AS created_by_name
                FROM jobs j
                INNER JOIN customers c ON c.id = j.customer_id
                INNER JOIN quotes q ON q.id = j.quote_id
                LEFT JOIN users u ON u.id = j.assigned_to
                LEFT JOIN users creator ON creator.id = j.created_by';
    }
}

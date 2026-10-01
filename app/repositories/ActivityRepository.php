<?php

declare(strict_types=1);

namespace App\Repositories;

final class ActivityRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        return $this->rows(
            'SELECT a.*, u.name AS user_name
             FROM crm_activities a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.customer_id = ?
             ORDER BY a.activity_date DESC, a.id DESC',
            [$customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 30, ?string $term = null): array
    {
        $sql = 'SELECT a.*, u.name AS user_name, c.company_name, c.first_name, c.last_name, c.customer_type
                FROM crm_activities a
                LEFT JOIN users u ON u.id = a.user_id
                INNER JOIN customers c ON c.id = a.customer_id';
        $params = [];
        if ($term !== null && $term !== '') {
            $like = like_term($term);
            $sql .= ' WHERE a.subject LIKE ? ESCAPE \'\\\\\'
                      OR a.description LIKE ? ESCAPE \'\\\\\'
                      OR c.company_name LIKE ? ESCAPE \'\\\\\'
                      OR c.last_name LIKE ? ESCAPE \'\\\\\'';
            $params = [$like, $like, $like, $like];
        }
        $sql .= ' ORDER BY a.activity_date DESC, a.id DESC LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM crm_activities WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO crm_activities
                (customer_id, user_id, activity_type, subject, description, activity_date, follow_up_date, completed)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'], $data['user_id'], $data['activity_type'], $data['subject'],
                $data['description'], $data['activity_date'], $data['follow_up_date'], $data['completed'],
            ]
        );

        return $this->insertId();
    }

    public function setCompleted(int $id, int $completed): void
    {
        $this->run('UPDATE crm_activities SET completed = ? WHERE id = ?', [$completed, $id]);
    }
}

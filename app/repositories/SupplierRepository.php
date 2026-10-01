<?php

declare(strict_types=1);

namespace App\Repositories;

final class SupplierRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM suppliers WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, string $status, int $limit = 100): array
    {
        $sql = 'SELECT id, name, contact_name, email, phone, account_number, active FROM suppliers WHERE 1 = 1';
        $params = [];
        if ($status === 'active') {
            $sql .= ' AND active = 1';
        } elseif ($status === 'inactive') {
            $sql .= ' AND active = 0';
        }
        if ($term !== '') {
            $like = like_term($term);
            $sql .= ' AND (name LIKE ? ESCAPE \'\\\\\' OR contact_name LIKE ? ESCAPE \'\\\\\'
                      OR email LIKE ? ESCAPE \'\\\\\' OR phone LIKE ? ESCAPE \'\\\\\'
                      OR account_number LIKE ? ESCAPE \'\\\\\')';
            $params = array_fill(0, 5, $like);
        }
        $sql .= ' ORDER BY active DESC, name LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function options(): array
    {
        return $this->rows(
            'SELECT id, name, active FROM suppliers ORDER BY active DESC, name'
        );
    }

    public function countActive(): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM suppliers WHERE active = 1');

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO suppliers (name, contact_name, email, phone, website, account_number, address, notes, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['contact_name'], $data['email'], $data['phone'],
                $data['website'], $data['account_number'], $data['address'], $data['notes'], $data['active'],
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
            'UPDATE suppliers
             SET name = ?, contact_name = ?, email = ?, phone = ?, website = ?,
                 account_number = ?, address = ?, notes = ?, active = ?
             WHERE id = ?',
            [
                $data['name'], $data['contact_name'], $data['email'], $data['phone'],
                $data['website'], $data['account_number'], $data['address'], $data['notes'],
                $data['active'], $id,
            ]
        );
    }

    public function setActive(int $id, int $active): void
    {
        $this->run('UPDATE suppliers SET active = ? WHERE id = ?', [$active, $id]);
    }
}

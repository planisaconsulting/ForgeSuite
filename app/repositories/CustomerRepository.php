<?php

declare(strict_types=1);

namespace App\Repositories;

final class CustomerRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT c.*, u.name AS created_by_name
             FROM customers c
             LEFT JOIN users u ON u.id = c.created_by
             WHERE c.id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, string $status, int $limit = 100): array
    {
        $sql = 'SELECT id, customer_type, company_name, first_name, last_name, email, phone, mobile, active, updated_at
                FROM customers
                WHERE 1 = 1';
        $params = [];
        if ($status === 'active') {
            $sql .= ' AND active = 1';
        } elseif ($status === 'inactive') {
            $sql .= ' AND active = 0';
        }
        if ($term !== '') {
            $like = like_term($term);
            $sql .= ' AND (
                company_name LIKE ? ESCAPE \'\\\\\'
                OR first_name LIKE ? ESCAPE \'\\\\\'
                OR last_name LIKE ? ESCAPE \'\\\\\'
                OR email LIKE ? ESCAPE \'\\\\\'
                OR phone LIKE ? ESCAPE \'\\\\\'
                OR mobile LIKE ? ESCAPE \'\\\\\'
                OR vat_number LIKE ? ESCAPE \'\\\\\'
            )';
            $params = array_merge($params, array_fill(0, 7, $like));
        }
        $sql .= ' ORDER BY active DESC, company_name, last_name, first_name LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 5): array
    {
        return $this->rows(
            'SELECT id, customer_type, company_name, first_name, last_name, email, created_at
             FROM customers
             ORDER BY id DESC
             LIMIT ' . (int) $limit
        );
    }

    public function countActive(): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM customers WHERE active = 1');

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO customers (
                customer_type, company_name, first_name, last_name, vat_number, registration_number,
                email, phone, mobile, website, billing_address, physical_address, notes, active, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_type'], $data['company_name'], $data['first_name'], $data['last_name'],
                $data['vat_number'], $data['registration_number'], $data['email'], $data['phone'],
                $data['mobile'], $data['website'], $data['billing_address'], $data['physical_address'],
                $data['notes'], $data['active'], $data['created_by'],
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
            'UPDATE customers SET
                customer_type = ?, company_name = ?, first_name = ?, last_name = ?, vat_number = ?,
                registration_number = ?, email = ?, phone = ?, mobile = ?, website = ?,
                billing_address = ?, physical_address = ?, notes = ?, active = ?
             WHERE id = ?',
            [
                $data['customer_type'], $data['company_name'], $data['first_name'], $data['last_name'],
                $data['vat_number'], $data['registration_number'], $data['email'], $data['phone'],
                $data['mobile'], $data['website'], $data['billing_address'], $data['physical_address'],
                $data['notes'], $data['active'], $id,
            ]
        );
    }

    public function setActive(int $id, int $active): void
    {
        $this->run('UPDATE customers SET active = ? WHERE id = ?', [$active, $id]);
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

final class ContactRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        return $this->rows(
            'SELECT * FROM customer_contacts
             WHERE customer_id = ?
             ORDER BY active DESC, primary_contact DESC, name',
            [$customerId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $contactId): ?array
    {
        return $this->one('SELECT * FROM customer_contacts WHERE id = ? LIMIT 1', [$contactId]);
    }

    public function findForCustomer(int $customerId, int $contactId): ?array
    {
        return $this->one(
            'SELECT * FROM customer_contacts WHERE customer_id = ? AND id = ? LIMIT 1',
            [$customerId, $contactId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO customer_contacts
                (customer_id, name, position, email, phone, mobile, primary_contact, notes, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'], $data['name'], $data['position'], $data['email'],
                $data['phone'], $data['mobile'], $data['primary_contact'], $data['notes'], $data['active'],
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
            'UPDATE customer_contacts
             SET name = ?, position = ?, email = ?, phone = ?, mobile = ?, primary_contact = ?, notes = ?, active = ?
             WHERE id = ? AND customer_id = ?',
            [
                $data['name'], $data['position'], $data['email'], $data['phone'], $data['mobile'],
                $data['primary_contact'], $data['notes'], $data['active'], $id, $data['customer_id'],
            ]
        );
    }

    public function clearPrimary(int $customerId, ?int $exceptId = null): void
    {
        if ($exceptId === null) {
            $this->run('UPDATE customer_contacts SET primary_contact = 0 WHERE customer_id = ?', [$customerId]);

            return;
        }
        $this->run(
            'UPDATE customer_contacts SET primary_contact = 0 WHERE customer_id = ? AND id <> ?',
            [$customerId, $exceptId]
        );
    }
}

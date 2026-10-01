<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends Repository
{
    /**
     * Safe to show on screen. The password hash is not included.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one(
            'SELECT u.id, u.name, u.email, u.role_id, r.code AS role_code, r.name AS role_name,
                    u.active, u.must_change_password, u.hourly_cost, u.last_login_at, u.created_at, u.updated_at
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ?
             LIMIT 1',
            [$id]
        );
    }

    /**
     * Sign-in only. Includes password_hash.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->one(
            'SELECT u.id, u.name, u.email, u.password_hash, u.role_id, r.code AS role_code,
                    r.name AS role_name, u.active, u.must_change_password
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?
             LIMIT 1',
            [$email]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        return $this->rows(
            'SELECT u.id, u.name, u.email, u.role_id, r.code AS role_code, r.name AS role_name,
                    u.active, u.last_login_at, u.created_at
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             ORDER BY u.active DESC, u.name'
        );
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = ?';
        $params = [$email];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        return $this->one($sql, $params) !== null;
    }

    public function countActiveAdmins(?int $exceptId = null): int
    {
        $sql = 'SELECT COUNT(*) AS n
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.active = 1 AND r.code = \'ADMIN\'';
        $params = [];
        if ($exceptId !== null) {
            $sql .= ' AND u.id <> ?';
            $params[] = $exceptId;
        }
        $row = $this->one($sql, $params);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithPassword(int $id): ?array
    {
        return $this->one(
            'SELECT id, email, password_hash, active FROM users WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    public function touchLogin(int $id): void
    {
        $this->run('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->setPassword($id, $passwordHash, 0);
    }

    public function setPassword(int $id, string $passwordHash, int $mustChange): void
    {
        $this->run(
            'UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ?',
            [$passwordHash, $mustChange, $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO users (name, email, password_hash, role_id, active, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['name'],
                $data['email'],
                $data['password_hash'],
                $data['role_id'],
                $data['active'],
                $data['must_change_password'],
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
            'UPDATE users
             SET name = ?, email = ?, role_id = ?, active = ?
             WHERE id = ?',
            [$data['name'], $data['email'], $data['role_id'], $data['active'], $id]
        );
    }

    public function setActive(int $id, int $active): void
    {
        $this->run('UPDATE users SET active = ? WHERE id = ?', [$active, $id]);
    }
}

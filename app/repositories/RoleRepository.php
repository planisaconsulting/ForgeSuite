<?php

declare(strict_types=1);

namespace App\Repositories;

final class RoleRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->rows('SELECT id, code, name, description FROM roles ORDER BY id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one('SELECT id, code, name, description FROM roles WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<string>
     */
    public function permissionCodes(int $roleId): array
    {
        $rows = $this->rows(
            'SELECT p.code
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?
             ORDER BY p.code',
            [$roleId]
        );

        return array_map(static fn (array $row): string => (string) $row['code'], $rows);
    }
}

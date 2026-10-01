<?php

declare(strict_types=1);

namespace App\Repositories;

final class AuditRepository extends Repository
{
    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public function write(
        ?int $userId,
        string $entityType,
        ?int $entityId,
        string $action,
        ?array $old,
        ?array $new,
        ?string $ip
    ): void {
        $this->run(
            'INSERT INTO audit_log (user_id, entity_type, entity_id, action, old_values, new_values, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $entityType,
                $entityId,
                $action,
                $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR),
                $new === null ? null : json_encode($new, JSON_THROW_ON_ERROR),
                $ip,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forEntity(string $entityType, int $entityId, int $limit = 30): array
    {
        return $this->rows(
            'SELECT a.id, a.user_id, a.action, a.old_values, a.new_values, a.ip_address, a.created_at,
                    u.name AS user_name
             FROM audit_log a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.id DESC
             LIMIT ' . (int) $limit,
            [$entityType, $entityId]
        );
    }
}

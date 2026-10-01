<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\WorkshopRepository;

/**
 * Workshop alerts use the existing notification table and a stable dedupe key.
 */
final class WorkshopNotifier
{
    public function send(string $type, string $title, string $message, string $entityType, int $entityId, ?string $roleCode = 'PRODUCTION'): void
    {
        try {
            $roleId = $roleCode === null ? null : (new WorkshopRepository())->roleId($roleCode);
            (new NotificationRepository())->insert([
                'user_id' => null,
                'role_id' => $roleId,
                'type' => $type,
                'title' => $title,
                'message' => mb_substr($message, 0, 255),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'priority' => 'NORMAL',
                'dedupe_key' => $type . ':' . $entityType . ':' . $entityId,
                'expires_at' => null,
            ]);
        } catch (\Throwable $e) {
            error_log('Workshop notification failed: ' . $e->getMessage());
        }
    }
}

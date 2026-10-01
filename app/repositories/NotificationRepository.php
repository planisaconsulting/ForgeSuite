<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Internal notifications, preferences, and reminders.
 * A unique dedupe key stops a cron run from inserting the same alert again.
 */
final class NotificationRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(int $userId, int $roleId, int $limit = 40): array
    {
        return $this->rows(
            'SELECT * FROM notifications
             WHERE (user_id = ? OR (user_id IS NULL AND role_id = ?) OR (user_id IS NULL AND role_id IS NULL))
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY created_at DESC
             LIMIT ' . (int) $limit,
            [$userId, $roleId]
        );
    }

    public function unreadCount(int $userId, int $roleId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM notifications
             WHERE read_at IS NULL
               AND (user_id = ? OR (user_id IS NULL AND role_id = ?) OR (user_id IS NULL AND role_id IS NULL))
               AND (expires_at IS NULL OR expires_at > NOW())',
            [$userId, $roleId]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): bool
    {
        $this->run(
            'INSERT IGNORE INTO notifications
                (user_id, role_id, type, title, message, entity_type, entity_id, priority, dedupe_key, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'], $data['role_id'], $data['type'], $data['title'], $data['message'],
                $data['entity_type'], $data['entity_id'], $data['priority'], $data['dedupe_key'], $data['expires_at'],
            ]
        );

        return $this->affected() === 1;
    }

    public function markRead(int $id, int $userId, int $roleId): void
    {
        $this->run(
            'UPDATE notifications SET read_at = NOW()
             WHERE id = ? AND read_at IS NULL
               AND (user_id = ? OR (user_id IS NULL AND role_id = ?) OR (user_id IS NULL AND role_id IS NULL))',
            [$id, $userId, $roleId]
        );
    }

    public function markAllRead(int $userId, int $roleId): void
    {
        $this->run(
            'UPDATE notifications SET read_at = NOW()
             WHERE read_at IS NULL
               AND (user_id = ? OR (user_id IS NULL AND role_id = ?) OR (user_id IS NULL AND role_id IS NULL))',
            [$userId, $roleId]
        );
    }

    /**
     * @return list<string>
     */
    public function disabledTypes(int $userId): array
    {
        $rows = $this->rows(
            'SELECT notification_type FROM notification_preferences WHERE user_id = ? AND enabled = 0',
            [$userId]
        );

        return array_map(static fn (array $row): string => (string) $row['notification_type'], $rows);
    }

    public function setPreference(int $userId, string $type, bool $enabled): void
    {
        $this->run(
            'INSERT INTO notification_preferences (user_id, notification_type, enabled)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)',
            [$userId, $type, $enabled ? 1 : 0]
        );
    }

    public function countByDedupe(string $prefix): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM notifications WHERE dedupe_key LIKE ?',
            [$prefix . '%']
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertReminder(array $data): bool
    {
        $this->run(
            'INSERT IGNORE INTO reminders (user_id, entity_type, entity_id, title, description, remind_at, status, dedupe_key)
             VALUES (?, ?, ?, ?, ?, ?, \'PENDING\', ?)',
            [
                $data['user_id'], $data['entity_type'], $data['entity_id'], $data['title'],
                $data['description'], $data['remind_at'], $data['dedupe_key'],
            ]
        );

        return $this->affected() === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reminders(int $userId): array
    {
        return $this->rows(
            'SELECT * FROM reminders WHERE user_id = ? ORDER BY status = \'PENDING\' DESC, remind_at LIMIT 80',
            [$userId]
        );
    }

    public function completeReminder(int $id, int $userId, string $status): void
    {
        $this->run(
            'UPDATE reminders SET status = ?, completed_at = NOW()
             WHERE id = ? AND user_id = ? AND status = \'PENDING\'',
            [$status, $id, $userId]
        );
    }

    public function countReminders(string $dedupe): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM reminders WHERE dedupe_key = ?', [$dedupe]);

        return (int) ($row['n'] ?? 0);
    }
}

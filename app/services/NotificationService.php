<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;

/**
 * Internal notifications. SYSTEM notices cannot be switched off.
 * A repeated dedupe key is ignored so hourly cron does not stack copies.
 */
final class NotificationService
{
    /** @var list<string> */
    public const TYPES = [
        'QUOTE_EXPIRING',
        'QUOTE_FOLLOW_UP',
        'ARTWORK_APPROVAL_PENDING',
        'JOB_OVERDUE',
        'TASK_OVERDUE',
        'INSTALLATION_TODAY',
        'LOW_STOCK',
        'MATERIAL_SHORTAGE',
        'PO_OVERDUE',
        'INVOICE_DUE',
        'INVOICE_OVERDUE',
        'CUSTOMER_ACCOUNT_HOLD',
        'LARGE_BALANCE',
        'UNALLOCATED_PAYMENT',
        'SCHEDULED_REPORT',
        'TASK_STARTING_SOON',
        'RESOURCE_CONFLICT',
        'MACHINE_MAINTENANCE_DUE',
        'VEHICLE_SERVICE_DUE',
        'INSTALLATION_TOMORROW',
        'SCHEDULE_CHANGED',
        'JOB_AT_RISK',
        'SYSTEM',
    ];

    /** @var list<string> */
    public const LOCKED = ['SYSTEM'];

    public function __construct(private readonly NotificationRepository $notifications = new NotificationRepository())
    {
    }

    public function send(
        ?int $userId,
        ?int $roleId,
        string $type,
        string $title,
        string $message,
        ?string $entityType,
        ?int $entityId,
        string $priority,
        string $dedupe
    ): bool {
        if ($userId !== null && $this->muted($userId, $type)) {
            return false;
        }

        return $this->notifications->insert([
            'user_id' => $userId,
            'role_id' => $userId === null ? $roleId : null,
            'type' => $type,
            'title' => mb_substr($title, 0, 180),
            'message' => mb_substr($message, 0, 500),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'priority' => $priority,
            'dedupe_key' => mb_substr($dedupe, 0, 190),
            'expires_at' => null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inbox(int $userId, int $roleId): array
    {
        $muted = $this->notifications->disabledTypes($userId);
        $rows = [];
        foreach ($this->notifications->forUser($userId, $roleId) as $row) {
            $type = (string) $row['type'];
            if (in_array($type, $muted, true) && !in_array($type, self::LOCKED, true)) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function unread(int $userId, int $roleId): int
    {
        $count = 0;
        foreach ($this->inbox($userId, $roleId) as $row) {
            if ($row['read_at'] === null) {
                $count++;
            }
        }

        return $count;
    }

    public function markRead(int $id, int $userId, int $roleId): void
    {
        $this->notifications->markRead($id, $userId, $roleId);
    }

    public function markAll(int $userId, int $roleId): void
    {
        $this->notifications->markAllRead($userId, $roleId);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function savePreferences(int $userId, array $input): void
    {
        foreach (self::TYPES as $type) {
            if (in_array($type, self::LOCKED, true)) {
                $this->notifications->setPreference($userId, $type, true);
                continue;
            }
            $enabled = !empty($input['type'][$type]);
            $this->notifications->setPreference($userId, $type, $enabled);
        }
    }

    /**
     * @return array<string, bool>
     */
    public function preferences(int $userId): array
    {
        $disabled = $this->notifications->disabledTypes($userId);
        $prefs = [];
        foreach (self::TYPES as $type) {
            $prefs[$type] = in_array($type, self::LOCKED, true) || !in_array($type, $disabled, true);
        }

        return $prefs;
    }

    private function muted(int $userId, string $type): bool
    {
        if (in_array($type, self::LOCKED, true)) {
            return false;
        }

        return in_array($type, $this->notifications->disabledTypes($userId), true);
    }
}

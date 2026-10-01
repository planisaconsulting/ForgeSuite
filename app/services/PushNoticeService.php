<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\FieldRepository;
use DateTimeImmutable;

/**
 * Push text is a short label. Amounts, margins, and customer financial detail stay in the signed-in app.
 * Delivery to a phone needs a push provider later. Until then the row is queued and an in-app notice is written.
 */
final class PushNoticeService
{
    /** @var array<string, string> */
    public const TITLES = [
        'JOB_ASSIGNED' => 'New job assigned',
        'INSTALL_TOMORROW' => 'Installation tomorrow',
        'APPROVAL_REQUIRED' => 'Approval required',
        'PRODUCTION_BLOCKED' => 'Production blocked',
        'LEAD_ASSIGNED' => 'Lead assigned',
        'JOB_CHANGED' => 'A job you are on was updated',
        'SYNC_CONFLICT' => 'A field update needs review',
        'ARTWORK_CHANGED' => 'Approved artwork changed',
        'JOB_CANCELLED' => 'A job was cancelled',
        'INSTALL_MOVED' => 'An installation was moved',
        'FIELD_PACK_UPDATED' => 'A field pack needs a refresh',
    ];

    public function __construct(private readonly FieldRepository $fields = new FieldRepository())
    {
    }

    /**
     * @return array{status: string, title: string, body: string}
     */
    public function queue(int $userId, string $type, bool $urgent = false, ?DateTimeImmutable $now = null): array
    {
        $type = strtoupper($type);
        $title = self::TITLES[$type] ?? 'Sign-Forge update';
        $now = $now ?? new DateTimeImmutable('now');
        if (!$this->preferenceAllows($userId, $type)) {
            return $this->store($userId, $title, 'SKIPPED_PREF');
        }
        if ($this->quiet($now) && !($urgent && FieldSettings::get('urgent_push_during_quiet') === '1')) {
            return $this->store($userId, $title, 'SKIPPED_QUIET');
        }
        if (FieldSettings::get('push_enabled') !== '1') {
            $this->inApp($userId, $type, $title);

            return $this->store($userId, $title, 'IN_APP_ONLY');
        }
        $this->inApp($userId, $type, $title);

        return $this->store($userId, $title, 'QUEUED');
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, message: string}
     */
    public function subscribe(int $userId, array $input, ?int $deviceId): array
    {
        if (!can('push_notifications.use')) {
            return ['ok' => false, 'message' => 'Your access to this record changed.'];
        }
        $endpoint = trim((string) ($input['endpoint'] ?? ''));
        if (!str_starts_with($endpoint, 'https://') || strlen($endpoint) > 500) {
            return ['ok' => false, 'message' => 'That push endpoint is not valid.'];
        }
        $public = trim((string) ($input['public_key'] ?? ''));
        $auth = trim((string) ($input['auth_token'] ?? ''));
        if ($public === '' || $auth === '' || strlen($public) > 255 || strlen($auth) > 255) {
            return ['ok' => false, 'message' => 'That push subscription is not valid.'];
        }
        $this->fields->insertSubscription([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'endpoint' => $endpoint,
            'public_key' => $public,
            'auth_token' => $auth,
        ]);

        return ['ok' => true, 'message' => 'Push subscription stored. Messages stay short and do not include balances.'];
    }

    private function quiet(DateTimeImmutable $now): bool
    {
        $start = FieldSettings::get('quiet_hours_start');
        $end = FieldSettings::get('quiet_hours_end');
        if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            return false;
        }
        $clock = $now->format('H:i');
        if ($start === $end) {
            return false;
        }
        if ($start < $end) {
            return $clock >= $start && $clock < $end;
        }

        return $clock >= $start || $clock < $end;
    }

    private function preferenceAllows(int $userId, string $type): bool
    {
        return !in_array($type, (new \App\Repositories\NotificationRepository())->disabledTypes($userId), true);
    }

    /**
     * @return array{status: string, title: string, body: string}
     */
    private function store(int $userId, string $title, string $status): array
    {
        $this->fields->insertPushMessage([
            'user_id' => $userId,
            'title' => $title,
            'body' => $title,
            'status' => $status,
        ]);

        return ['status' => $status, 'title' => $title, 'body' => $title];
    }

    private function inApp(int $userId, string $type, string $title): void
    {
        try {
            (new \App\Repositories\NotificationRepository())->insert([
                'user_id' => $userId,
                'role_id' => null,
                'type' => $type,
                'title' => $title,
                'message' => $title,
                'entity_type' => null,
                'entity_id' => null,
                'priority' => 'NORMAL',
                'dedupe_key' => 'push-' . $userId . '-' . $type . '-' . date('Y-m-d-H'),
                'expires_at' => null,
            ]);
        } catch (\Throwable) {
            // A duplicate in-app notice must not block the field sync.
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditRepository;

/**
 * Writes one audit row for a business change.
 *
 * Call this inside the same database transaction as the change it describes.
 * Password fields are removed before anything is stored.
 */
final class AuditService
{
    /** @var list<string> */
    private const SECRET_KEYS = [
        'password',
        'password_hash',
        'current_password',
        'new_password',
        'confirm_password',
        'password_confirm',
        'smtp_password',
        'webhook_secret',
        'secret_value',
    ];

    public function __construct(private readonly AuditRepository $audit = new AuditRepository())
    {
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public function record(
        string $entityType,
        ?int $entityId,
        string $action,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null
    ): void {
        $actor = $userId ?? $this->currentUserId();
        $this->audit->write(
            $actor,
            $entityType,
            $entityId,
            $action,
            $this->clean($old),
            $this->clean($new),
            $this->ip()
        );
    }

    private function currentUserId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        if (is_int($id)) {
            return $id;
        }
        if (is_string($id) && ctype_digit($id)) {
            return (int) $id;
        }

        return null;
    }

    private function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!is_string($ip) || $ip === '') {
            return null;
        }

        return substr($ip, 0, 45);
    }

    /**
     * @param array<string, mixed>|null $values
     * @return array<string, mixed>|null
     */
    private function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }
        foreach (self::SECRET_KEYS as $key) {
            unset($values[$key]);
        }

        return $values;
    }
}

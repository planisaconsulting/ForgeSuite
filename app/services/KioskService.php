<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\WorkshopRepository;

/**
 * Shared workshop sign-in. PINs are hashed. A badge token is not an admin grant.
 */
final class KioskService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly TrackingCodeService $tracking = new TrackingCodeService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function setPin(int $userId, string $pin, int $actorId): array
    {
        if (!can('users.manage') && $actorId !== $userId) {
            return ['_form' => 'You cannot set a workshop PIN.'];
        }
        if (!preg_match('/^\d{4,8}$/', $pin)) {
            return ['pin' => 'Use a PIN of 4 to 8 digits.'];
        }
        $this->workshop->savePin($userId, password_hash($pin, PASSWORD_DEFAULT));
        $this->audit->record('user', $userId, 'KIOSK_PIN_SET', null, [], $actorId);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, user_id: int|null}
     */
    public function identifyByPin(string $email, string $pin): array
    {
        $user = $this->workshop->userByEmail(trim($email));
        if ($user === null) {
            return ['errors' => ['_form' => 'That PIN was not accepted.'], 'user_id' => null];
        }
        if ((string) $user['role_code'] === 'ADMIN') {
            return ['errors' => ['_form' => 'Administrator access is not available from the kiosk.'], 'user_id' => null];
        }
        $row = $this->workshop->pin((int) $user['id']);
        if ($row === null) {
            return ['errors' => ['_form' => 'That PIN was not accepted.'], 'user_id' => null];
        }
        if ($row['locked_until'] !== null && strtotime((string) $row['locked_until']) > time()) {
            return ['errors' => ['_form' => 'Too many attempts. Try again later.'], 'user_id' => null];
        }
        if (!password_verify($pin, (string) $row['pin_hash'])) {
            $attempts = (int) $row['failed_attempts'] + 1;
            $max = (int) SettingsService::get('kiosk_max_pin_attempts', '5');
            $locked = $attempts >= $max ? date('Y-m-d H:i:s', time() + 900) : null;
            $this->workshop->pinFailure((int) $user['id'], $attempts, $locked);

            return ['errors' => ['_form' => 'That PIN was not accepted.'], 'user_id' => null];
        }
        $this->workshop->clearPinFailures((int) $user['id']);
        $this->startSession($user);

        return ['errors' => [], 'user_id' => (int) $user['id']];
    }

    /**
     * @return array{errors: array<string, string>, user_id: int|null}
     */
    public function identifyByBadge(string $token): array
    {
        $resolved = $this->tracking->resolve($token);
        if ($resolved === null || $resolved['entity_type'] !== 'USER') {
            return ['errors' => ['_form' => 'That badge was not accepted.'], 'user_id' => null];
        }
        $user = (new UserRepository())->find((int) $resolved['entity_id']);
        if ($user === null || (int) $user['active'] !== 1) {
            return ['errors' => ['_form' => 'That badge was not accepted.'], 'user_id' => null];
        }
        if ((string) ($user['role_code'] ?? '') === 'ADMIN') {
            return ['errors' => ['_form' => 'Administrator access is not available from a badge.'], 'user_id' => null];
        }
        $this->startSession($user);

        return ['errors' => [], 'user_id' => (int) $user['id']];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function startSession(array $user): void
    {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['kiosk_mode'] = 1;
        forget_auth_user();
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PortalRepository;

/**
 * Portal sign-in is separate from staff accounts. The session key is
 * portal_user_id and is never written into user_id.
 */
final class PortalAuthService
{
    public function __construct(
        private readonly PortalRepository $portal = new PortalRepository(),
        private readonly RateLimiter $limits = new RateLimiter(),
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function login(string $email, string $password): array
    {
        $bucket = 'login:' . hash('sha256', strtolower(trim($email)) . '|' . $this->ip());
        if (!$this->limits->allow($bucket, 8, 900)) {
            return ['_form' => 'Too many sign-in attempts. Wait a few minutes and try again.'];
        }
        $user = $this->portal->userByEmail($email);
        $hash = (string) ($user['password_hash'] ?? '');
        if ($user === null || (int) $user['active'] !== 1 || $hash === '' || !password_verify($password, $hash)) {
            return ['_form' => 'Those sign-in details were not recognised.'];
        }
        session_regenerate_id(true);
        $_SESSION['portal_user_id'] = (int) $user['id'];
        unset($_SESSION['user_id']);
        $this->portal->touchLogin((int) $user['id']);
        $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'LOGIN', null, null, $this->ip());

        return [];
    }

    public function logout(): void
    {
        unset($_SESSION['portal_user_id']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $id = $_SESSION['portal_user_id'] ?? null;
        if (!is_int($id) && !(is_string($id) && ctype_digit((string) $id))) {
            return null;
        }
        $user = $this->portal->user((int) $id);
        if ($user === null || (int) $user['active'] !== 1) {
            unset($_SESSION['portal_user_id']);

            return null;
        }

        return $user;
    }

    /**
     * Issue a single-purpose link. The raw token is returned once and only its hash is stored.
     *
     * @return array{errors: array<string, string>, url: string|null}
     */
    public function issueLink(int $portalUserId, string $purpose, ?string $entityType, ?int $entityId, bool $singleUse): array
    {
        $bucket = 'magic:' . $this->ip();
        if (!$this->limits->allow($bucket, 10, 3600)) {
            return ['errors' => ['_form' => 'Too many access links were requested.'], 'url' => null];
        }
        $user = $this->portal->user($portalUserId);
        if ($user === null || (int) $user['active'] !== 1) {
            return ['errors' => ['_form' => 'That portal user is not active.'], 'url' => null];
        }
        $purpose = strtoupper(preg_replace('/[^A-Z_]/', '', $purpose) ?? '');
        if (!in_array($purpose, ['LOGIN', 'QUOTE', 'ARTWORK', 'INVOICE'], true)) {
            return ['errors' => ['_form' => 'Choose what the link is for.'], 'url' => null];
        }
        $raw = bin2hex(random_bytes(32));
        $hours = (int) (SettingsService::get('portal_link_hours', '72') ?? '72');
        if ($hours < 1) {
            $hours = 72;
        }
        $this->portal->insertToken([
            'token_hash' => hash('sha256', $raw),
            'portal_user_id' => $portalUserId,
            'customer_id' => (int) $user['customer_id'],
            'purpose' => $purpose,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'expires_at' => date('Y-m-d H:i:s', time() + ($hours * 3600)),
        ]);
        $_SESSION['portal_link_single'][$raw] = $singleUse;

        return ['errors' => [], 'url' => url('/portal/access/' . $raw)];
    }

    /**
     * @return array<string, string>
     */
    public function consumeLink(string $raw): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $raw)) {
            return ['_form' => 'That link is not valid.'];
        }
        $row = $this->portal->tokenByHash(hash('sha256', $raw));
        if ($row === null || $row['revoked_at'] !== null) {
            return ['_form' => 'That link is not valid.'];
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return ['_form' => 'That link has expired.'];
        }
        if ($row['used_at'] !== null && (string) $row['purpose'] === 'LOGIN') {
            return ['_form' => 'That link has already been used.'];
        }
        $user = $this->portal->user((int) $row['portal_user_id']);
        if ($user === null || (int) $user['active'] !== 1 || (int) $user['customer_id'] !== (int) $row['customer_id']) {
            return ['_form' => 'That link is not valid.'];
        }
        if ((string) $row['purpose'] === 'LOGIN') {
            $this->portal->useToken((int) $row['id']);
        }
        session_regenerate_id(true);
        $_SESSION['portal_user_id'] = (int) $user['id'];
        unset($_SESSION['user_id']);
        $this->portal->touchLogin((int) $user['id']);
        $this->portal->audit((int) $user['customer_id'], (int) $user['id'], 'LOGIN', null, null, $this->ip());

        return [];
    }

    public function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return substr($ip, 0, 45);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Csrf;
use App\Repositories\UserRepository;

/**
 * Sign-in and password changes.
 *
 * Controllers call this instead of talking to the users table themselves.
 * A failed sign-in always returns the same result, whether the email is
 * unknown, the password is wrong, or the account is inactive.
 */
final class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_SECONDS = 60;

    public function attempt(string $email, string $password, bool $remember = false): string
    {
        if ($this->isLocked() || $this->ipLocked()) {
            return 'throttled';
        }

        $email = strtolower(trim($email));
        $users = new UserRepository();
        $user = $email === '' ? null : $users->findByEmail($email);
        $hash = is_array($user) ? (string) $user['password_hash'] : '';
        $valid = $hash !== '' && password_verify($password, $hash);

        if (!is_array($user) || !$valid || (int) $user['active'] !== 1) {
            $this->registerFailure();
            $this->recordLogin(is_array($user) ? (int) $user['id'] : null, $email, false);

            return $this->isLocked() ? 'throttled' : 'invalid';
        }

        $this->clearFailures();
        $this->recordLogin((int) $user['id'], $email, true);
        $users->touchLogin((int) $user['id']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        remember_login($remember);
        Csrf::rotate();

        return 'ok';
    }

    /**
     * @return list<string> Empty when the password was changed.
     */
    public function changePassword(int $userId, string $current, string $new, string $confirm): array
    {
        $errors = [];
        $user = (new UserRepository())->findByEmail($this->emailFor($userId));

        if ($user === null || !password_verify($current, (string) $user['password_hash'])) {
            $errors[] = 'The current password is not correct.';
        }

        if (strlen($new) < 10) {
            $errors[] = 'The new password must be at least 10 characters.';
        }

        if ($new !== $confirm) {
            $errors[] = 'The new password and the confirmation do not match.';
        }

        if ($errors === [] && $current === $new) {
            $errors[] = 'Choose a password that is different from the current one.';
        }

        if ($errors !== []) {
            return $errors;
        }

        (new UserRepository())->updatePassword($userId, password_hash($new, PASSWORD_DEFAULT));
        (new AuditService())->record('user', $userId, 'password_changed', null, null, $userId);

        return [];
    }

    private function emailFor(int $userId): string
    {
        $user = (new UserRepository())->find($userId);

        return $user === null ? '' : (string) $user['email'];
    }

    private function isLocked(): bool
    {
        $until = $_SESSION['login_locked_until'] ?? 0;

        return is_int($until) && $until > time();
    }

    private function registerFailure(): void
    {
        $count = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $count;

        if ($count >= self::MAX_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + self::LOCK_SECONDS;
            $_SESSION['login_attempts'] = 0;
        }
    }

    private function ipLocked(): bool
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '' || PHP_SAPI === 'cli') {
            return false;
        }

        return (new \App\Repositories\SystemRepository())->recentFailedLogins($ip, 15) >= 20;
    }

    private function clearFailures(): void
    {
        unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
    }

    private function recordLogin(?int $userId, string $email, bool $success): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            (new \App\Repositories\SystemRepository())->recordLogin(
                $userId,
                mb_substr($email, 0, 190),
                is_string($ip) ? mb_substr($ip, 0, 45) : null,
                $success
            );
        } catch (\Throwable $e) {
            error_log('Login history was not stored.');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Cross-site request forgery token stored in the session.
 *
 * The router checks this on every POST before a controller runs, so a new
 * form cannot forget the check. The hidden field is csrf_field().
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public static function rotate(): void
    {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(): void
    {
        $sent = $_POST['_token'] ?? '';
        if ((!is_string($sent) || $sent === '') && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $sent = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        $known = $_SESSION['csrf'] ?? '';

        if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
            $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
            $fetch = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
            if (str_contains($accept, 'application/json') || $fetch === 'fetch') {
                json_response([
                    'ok' => false,
                    'errors' => ['The form token was missing or expired. Reload the page and try again.'],
                ], 403);
            }
            http_response_code(403);
            $loggedIn = auth_user() !== null;
            View::render('errors/403', [
                'title' => 'Request blocked',
                'activeNav' => '',
                'message' => 'The form token was missing or expired. Go back, refresh the page, and submit it again.',
            ], $loggedIn ? 'layouts/app' : 'layouts/auth');
            exit;
        }
    }
}

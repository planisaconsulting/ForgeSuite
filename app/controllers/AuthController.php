<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\AuthService;

/**
 * Sign-in and sign-out screens.
 */
final class AuthController
{
    public function showLogin(): void
    {
        $user = auth_user();
        if ($user !== null) {
            redirect((int) $user['must_change_password'] === 1 ? '/account/password' : '/');
        }

        View::render('auth/login', [
            'title' => 'Sign in',
            'error' => null,
            'email' => '',
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $result = (new AuthService())->attempt($email, $password);

        if ($result === 'throttled') {
            View::render('auth/login', [
                'title' => 'Sign in',
                'error' => 'Too many attempts. Wait a minute and try again.',
                'email' => $email,
            ], 'layouts/auth');

            return;
        }

        if ($result !== 'ok') {
            View::render('auth/login', [
                'title' => 'Sign in',
                'error' => 'Those details do not match an active account.',
                'email' => $email,
            ], 'layouts/auth');

            return;
        }

        $user = auth_user();
        if ($user !== null && (int) $user['must_change_password'] === 1) {
            redirect('/account/password');
        }

        $intended = safe_internal_path((string) ($_SESSION['intended'] ?? '/'));
        unset($_SESSION['intended']);
        if ($intended === '/login' || $intended === '/account/password') {
            $intended = '/';
        }

        redirect($intended);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
        redirect('/login');
    }
}

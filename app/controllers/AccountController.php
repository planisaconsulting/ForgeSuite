<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\AuthService;

/**
 * Password change. The seeded administrator cannot reach the desk until this
 * has been completed once.
 */
final class AccountController
{
    public function showPassword(): void
    {
        $user = auth_user();
        View::render('account/password', [
            'title' => 'Change password',
            'activeNav' => '',
            'errors' => [],
            'forced' => $user !== null && (int) $user['must_change_password'] === 1,
        ], 'layouts/auth');
    }

    public function updatePassword(): void
    {
        $user = auth_user();
        if ($user === null) {
            redirect('/login');
        }

        $errors = (new AuthService())->changePassword(
            (int) $user['id'],
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );

        if ($errors !== []) {
            View::render('account/password', [
                'title' => 'Change password',
                'activeNav' => '',
                'errors' => $errors,
                'forced' => (int) $user['must_change_password'] === 1,
            ], 'layouts/auth');

            return;
        }

        flash('success', 'Password updated.');

        $intended = safe_internal_path((string) ($_SESSION['intended'] ?? '/'));
        unset($_SESSION['intended']);
        if ($intended === '/login' || $intended === '/account/password') {
            $intended = '/';
        }

        redirect($intended);
    }
}

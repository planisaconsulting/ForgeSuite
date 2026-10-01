<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CustomerRepository;
use App\Repositories\PortalRepository;
use App\Services\PortalAuthService;

final class PortalAdminController
{
    public function index(): void
    {
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        View::render('portal/admin', [
            'title' => 'Portal access',
            'activeNav' => 'portal',
            'customers' => (new CustomerRepository())->search(trim((string) ($_GET['q'] ?? '')), 'active', 30),
            'users' => $customerId > 0 ? (new PortalRepository())->usersForCustomer($customerId) : [],
            'customerId' => $customerId,
            'issued' => $_SESSION['issued_portal_url'] ?? null,
        ]);
        unset($_SESSION['issued_portal_url']);
    }

    public function store(): void
    {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ((new CustomerRepository())->find($customerId) === null) {
            flash('error', 'Choose a customer.');
            redirect('/admin/portal');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');
            redirect('/admin/portal?customer_id=' . $customerId);
        }
        if ((new PortalRepository())->userByEmail($email) !== null) {
            flash('error', 'That email is already a portal login.');
            redirect('/admin/portal?customer_id=' . $customerId);
        }
        $id = (new PortalRepository())->insertUser([
            'customer_id' => $customerId,
            'customer_contact_id' => ((int) ($_POST['customer_contact_id'] ?? 0)) > 0 ? (int) $_POST['customer_contact_id'] : null,
            'email' => $email,
            'password_hash' => $password === '' ? null : password_hash($password, PASSWORD_DEFAULT),
            'active' => 1,
        ]);
        (new \App\Services\AuditService())->record('portal_user', $id, 'created', null, ['email' => $email, 'customer_id' => $customerId], (int) auth_user()['id']);
        flash('success', 'Portal access created.');
        redirect('/admin/portal?customer_id=' . $customerId);
    }

    public function link(): void
    {
        $result = (new PortalAuthService())->issueLink((int) ($_POST['portal_user_id'] ?? 0), (string) ($_POST['purpose'] ?? 'LOGIN'), null, null, true);
        if ($result['url'] === null) {
            flash('error', implode(' ', $result['errors']));
        } else {
            $_SESSION['issued_portal_url'] = $result['url'];
            flash('success', 'Copy this link now. It is not stored in a readable form.');
        }
        redirect('/admin/portal?customer_id=' . (int) ($_POST['customer_id'] ?? 0));
    }

    public function deactivate(): void
    {
        $id = (int) ($_POST['portal_user_id'] ?? 0);
        (new PortalRepository())->setActive($id, 0);
        flash('success', 'Portal access deactivated.');
        redirect('/admin/portal?customer_id=' . (int) ($_POST['customer_id'] ?? 0));
    }
}

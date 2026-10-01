<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\UserAdminService;

final class UserController
{
    public function index(): void
    {
        View::render('users/index', [
            'title' => 'Users',
            'activeNav' => 'users',
            'rows' => (new UserRepository())->listAll(),
        ]);
    }

    public function create(): void
    {
        $this->form(null, [], ['active' => '1']);
    }

    public function store(): void
    {
        $result = (new UserAdminService())->create($_POST);
        if ($result['errors'] !== []) {
            $this->form(null, $result['errors'], $_POST);

            return;
        }
        flash('success', 'User created. They will be asked to change that password at first sign-in.');
        redirect('/users');
    }

    public function edit(string $id): void
    {
        $user = $this->requireUser($id);
        $this->form($user, [], $user);
    }

    public function update(string $id): void
    {
        $userId = route_id($id);
        $errors = (new UserAdminService())->update($userId, $_POST, (int) auth_user()['id']);
        if ($errors !== []) {
            $this->form((new UserRepository())->find($userId), $errors, $_POST);

            return;
        }
        flash('success', 'User updated.');
        redirect('/users');
    }

    public function deactivate(string $id): void
    {
        $userId = route_id($id);
        $active = posted_flag($_POST, 'active', 0) === 1;
        $error = (new UserAdminService())->setActive($userId, $active, (int) auth_user()['id']);
        if ($error !== '') {
            flash('error', $error);
        } else {
            flash('success', $active ? 'User activated.' : 'User deactivated. The record is kept.');
        }
        redirect('/users');
    }

    /**
     * @param array<string, mixed>|null $user
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function form(?array $user, array $errors, array $old): void
    {
        View::render('users/form', [
            'title' => $user === null || !isset($user['id']) ? 'New user' : 'Edit user',
            'activeNav' => 'users',
            'account' => isset($user['id']) ? $user : null,
            'roles' => (new RoleRepository())->all(),
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireUser(string $id): array
    {
        $user = (new UserRepository())->find(route_id($id));
        if ($user === null) {
            abort_not_found('That user was not found.');
        }

        return $user;
    }
}

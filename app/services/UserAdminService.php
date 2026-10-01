<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;

/**
 * Administrators create staff. There is no public registration page.
 * A new password is hashed here and is never written to the audit log.
 */
final class UserAdminService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input): array
    {
        $errors = $this->validate($input, null, true);
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $data = $this->data($input, password_hash((string) $input['password'], PASSWORD_DEFAULT));
        $id = 0;
        Database::transaction(function () use ($data, &$id): void {
            $id = $this->users->insert($data);
            $this->audit->record('user', $id, 'created', null, $this->snapshot($data));
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input, int $actorId): array
    {
        $existing = $this->users->find($id);
        if ($existing === null) {
            return ['_form' => 'That user was not found.'];
        }
        $password = (string) ($input['password'] ?? '');
        $errors = $this->validate($input, $id, $password !== '');
        if ($errors !== []) {
            return $errors;
        }
        if ($this->wouldRemoveLastAdmin($existing, $input, $id)) {
            return ['_form' => 'Keep at least one active administrator.'];
        }
        if ($id === $actorId && posted_flag($input, 'active', 1) === 0) {
            return ['_form' => 'You cannot deactivate your own account.'];
        }

        $data = $this->data($input, null);
        Database::transaction(function () use ($id, $data, $existing, $password): void {
            $this->users->update($id, $data);
            if ($password !== '') {
                $this->users->setPassword($id, password_hash($password, PASSWORD_DEFAULT), 1);
                $this->audit->record('user', $id, 'password_reset', null, ['must_change_password' => 1]);
            }
            $this->audit->record('user', $id, 'updated', $this->snapshot($existing), $this->snapshot($data));
        });

        return [];
    }

    public function setActive(int $id, bool $active, int $actorId): string
    {
        $existing = $this->users->find($id);
        if ($existing === null) {
            return 'That user was not found.';
        }
        if ($id === $actorId && !$active) {
            return 'You cannot deactivate your own account.';
        }
        if (!$active && ($existing['role_code'] ?? '') === 'ADMIN' && $this->users->countActiveAdmins($id) < 1) {
            return 'Keep at least one active administrator.';
        }
        Database::transaction(function () use ($id, $active): void {
            $this->users->setActive($id, $active ? 1 : 0);
            $this->audit->record('user', $id, $active ? 'activated' : 'deactivated', null, ['active' => $active ? 1 : 0]);
        });

        return '';
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $input
     */
    private function wouldRemoveLastAdmin(array $existing, array $input, int $id): bool
    {
        $role = $this->roles->find((int) ($input['role_id'] ?? 0));
        $stillAdmin = $role !== null && $role['code'] === 'ADMIN' && posted_flag($input, 'active', 1) === 1;
        if (($existing['role_code'] ?? '') !== 'ADMIN' || (int) $existing['active'] !== 1) {
            return false;
        }

        return !$stillAdmin && $this->users->countActiveAdmins($id) < 1;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input, ?int $id, bool $passwordRequired): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'A valid email is required.';
        } elseif ($this->users->emailTaken($email, $id)) {
            $errors['email'] = 'That email is already used.';
        }
        if ($this->roles->find((int) ($input['role_id'] ?? 0)) === null) {
            $errors['role_id'] = 'Choose a role.';
        }
        $password = (string) ($input['password'] ?? '');
        if ($passwordRequired && strlen($password) < 10) {
            $errors['password'] = 'The password must be at least 10 characters.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function data(array $input, ?string $passwordHash): array
    {
        return [
            'name' => trim((string) $input['name']),
            'email' => strtolower(trim((string) $input['email'])),
            'password_hash' => $passwordHash ?? '',
            'role_id' => (int) $input['role_id'],
            'active' => posted_flag($input, 'active', 1),
            'must_change_password' => 1,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function snapshot(array $row): array
    {
        return [
            'name' => $row['name'] ?? null,
            'email' => $row['email'] ?? null,
            'role_id' => $row['role_id'] ?? null,
            'active' => $row['active'] ?? null,
        ];
    }
}

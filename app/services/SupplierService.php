<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\SupplierRepository;

final class SupplierService
{
    public function __construct(
        private readonly SupplierRepository $suppliers = new SupplierRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function save(?int $id, array $input): array
    {
        $existing = $id === null ? null : $this->suppliers->find($id);
        if ($id !== null && $existing === null) {
            return ['errors' => ['_form' => 'That supplier was not found.'], 'id' => null];
        }
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Supplier name is required.';
        }
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email or leave it blank.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $data = [
            'name' => $name,
            'contact_name' => blank_to_null($input['contact_name'] ?? null),
            'email' => blank_to_null($email),
            'phone' => blank_to_null($input['phone'] ?? null),
            'website' => blank_to_null($input['website'] ?? null),
            'account_number' => blank_to_null($input['account_number'] ?? null),
            'address' => blank_to_null($input['address'] ?? null),
            'notes' => blank_to_null($input['notes'] ?? null),
            'active' => posted_flag($input, 'active', 1),
        ];
        $saved = $id ?? 0;
        Database::transaction(function () use ($id, $data, $existing, &$saved): void {
            if ($id === null) {
                $saved = $this->suppliers->insert($data);
                $this->audit->record('supplier', $saved, 'created', null, $data);
            } else {
                $saved = $id;
                $this->suppliers->update($id, $data);
                $this->audit->record('supplier', $id, 'updated', $this->publicRow($existing ?? []), $data);
            }
        });

        return ['errors' => [], 'id' => $saved];
    }

    public function setActive(int $id, bool $active): bool
    {
        if ($this->suppliers->find($id) === null) {
            return false;
        }
        Database::transaction(function () use ($id, $active): void {
            $this->suppliers->setActive($id, $active ? 1 : 0);
            $this->audit->record('supplier', $id, $active ? 'activated' : 'deactivated', null, ['active' => $active ? 1 : 0]);
        });

        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function publicRow(array $row): array
    {
        unset($row['created_at'], $row['updated_at']);

        return $row;
    }
}

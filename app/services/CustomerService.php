<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ActivityType;
use App\Domain\CustomerType;
use App\Helpers\Database;
use App\Repositories\ActivityRepository;
use App\Repositories\ContactRepository;
use App\Repositories\CustomerRepository;

/**
 * Customer, contact, and activity rules.
 * Deactivate sets active = 0. Rows are kept because later quotes will point here.
 */
final class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly ContactRepository $contacts = new ContactRepository(),
        private readonly ActivityRepository $activities = new ActivityRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        $errors = $this->validateCustomer($input);
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $data = $this->customerData($input, $userId);
        $id = 0;
        Database::transaction(function () use ($data, &$id): void {
            $id = $this->customers->insert($data);
            $this->audit->record('customer', $id, 'created', null, $this->snapshot($data));
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->customers->find($id);
        if ($existing === null) {
            return ['_form' => 'That customer was not found.'];
        }
        $errors = $this->validateCustomer($input);
        if ($errors !== []) {
            return $errors;
        }
        $data = $this->customerData($input, null);
        Database::transaction(function () use ($id, $data, $existing): void {
            $this->customers->update($id, $data);
            $this->audit->record('customer', $id, 'updated', $this->snapshot($existing), $this->snapshot($data));
        });

        return [];
    }

    public function setActive(int $id, bool $active): bool
    {
        $existing = $this->customers->find($id);
        if ($existing === null) {
            return false;
        }
        Database::transaction(function () use ($id, $active, $existing): void {
            $this->customers->setActive($id, $active ? 1 : 0);
            $this->audit->record(
                'customer',
                $id,
                $active ? 'activated' : 'deactivated',
                ['active' => (int) $existing['active']],
                ['active' => $active ? 1 : 0]
            );
        });

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function saveContact(int $customerId, ?int $contactId, array $input): array
    {
        if ($this->customers->find($customerId) === null) {
            return ['errors' => ['_form' => 'That customer was not found.'], 'id' => null];
        }
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Contact name is required.';
        }
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email or leave it blank.';
        }
        if ($contactId !== null && $this->contacts->findForCustomer($customerId, $contactId) === null) {
            $errors['_form'] = 'That contact was not found.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }

        $data = [
            'customer_id' => $customerId,
            'name' => $name,
            'position' => blank_to_null($input['position'] ?? null),
            'email' => blank_to_null($email),
            'phone' => blank_to_null($input['phone'] ?? null),
            'mobile' => blank_to_null($input['mobile'] ?? null),
            'primary_contact' => posted_flag($input, 'primary_contact'),
            'notes' => blank_to_null($input['notes'] ?? null),
            'active' => posted_flag($input, 'active', 1),
        ];

        $id = $contactId ?? 0;
        Database::transaction(function () use ($data, $contactId, &$id): void {
            if ((int) $data['primary_contact'] === 1) {
                $this->contacts->clearPrimary((int) $data['customer_id'], $contactId);
            }
            if ($contactId === null) {
                $id = $this->contacts->insert($data);
                $this->audit->record('customer_contact', $id, 'created', null, $data);
            } else {
                $id = $contactId;
                $this->contacts->update($contactId, $data);
                $this->audit->record('customer_contact', $contactId, 'updated', null, $data);
            }
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addActivity(int $customerId, int $userId, array $input): array
    {
        if ($this->customers->find($customerId) === null) {
            return ['_form' => 'That customer was not found.'];
        }
        $errors = [];
        $type = strtoupper(trim((string) ($input['activity_type'] ?? 'NOTE')));
        if (!in_array($type, ActivityType::values(), true)) {
            $errors['activity_type'] = 'Choose an activity type.';
        }
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            $errors['subject'] = 'Subject is required.';
        }
        $date = trim((string) ($input['activity_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors['activity_date'] = 'Activity date is required.';
        }
        $follow = trim((string) ($input['follow_up_date'] ?? ''));
        if ($follow !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $follow)) {
            $errors['follow_up_date'] = 'Follow-up date must be a date.';
        }
        if ($errors !== []) {
            return $errors;
        }

        $data = [
            'customer_id' => $customerId,
            'user_id' => $userId,
            'activity_type' => $type,
            'subject' => $subject,
            'description' => blank_to_null($input['description'] ?? null),
            'activity_date' => $date,
            'follow_up_date' => $follow === '' ? null : $follow,
            'completed' => posted_flag($input, 'completed', 0),
        ];
        Database::transaction(function () use ($data): void {
            $id = $this->activities->insert($data);
            $this->audit->record('crm_activity', $id, 'created', null, $data);
        });

        return [];
    }

    public function completeActivity(int $id, bool $completed): bool
    {
        $row = $this->activities->find($id);
        if ($row === null) {
            return false;
        }
        Database::transaction(function () use ($id, $completed, $row): void {
            $this->activities->setCompleted($id, $completed ? 1 : 0);
            $this->audit->record(
                'crm_activity',
                $id,
                $completed ? 'completed' : 'reopened',
                ['completed' => (int) $row['completed']],
                ['completed' => $completed ? 1 : 0]
            );
        });

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validateCustomer(array $input): array
    {
        $errors = [];
        $type = strtoupper(trim((string) ($input['customer_type'] ?? '')));
        if (!in_array($type, CustomerType::values(), true)) {
            $errors['customer_type'] = 'Choose business or individual.';
        }
        $company = trim((string) ($input['company_name'] ?? ''));
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        if ($type === 'BUSINESS' && $company === '') {
            $errors['company_name'] = 'A business needs a company name.';
        }
        if ($type === 'INDIVIDUAL' && $first === '' && $last === '') {
            $errors['first_name'] = 'An individual needs a first or last name.';
        }
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email or leave it blank.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function customerData(array $input, ?int $createdBy): array
    {
        return [
            'customer_type' => strtoupper(trim((string) $input['customer_type'])),
            'company_name' => blank_to_null($input['company_name'] ?? null),
            'first_name' => blank_to_null($input['first_name'] ?? null),
            'last_name' => blank_to_null($input['last_name'] ?? null),
            'vat_number' => blank_to_null($input['vat_number'] ?? null),
            'registration_number' => blank_to_null($input['registration_number'] ?? null),
            'email' => blank_to_null($input['email'] ?? null),
            'phone' => blank_to_null($input['phone'] ?? null),
            'mobile' => blank_to_null($input['mobile'] ?? null),
            'website' => blank_to_null($input['website'] ?? null),
            'billing_address' => blank_to_null($input['billing_address'] ?? null),
            'physical_address' => blank_to_null($input['physical_address'] ?? null),
            'notes' => blank_to_null($input['notes'] ?? null),
            'active' => posted_flag($input, 'active', 1),
            'created_by' => $createdBy,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function snapshot(array $row): array
    {
        $keys = [
            'customer_type', 'company_name', 'first_name', 'last_name', 'vat_number',
            'registration_number', 'email', 'phone', 'mobile', 'website',
            'billing_address', 'physical_address', 'notes', 'active',
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $row[$key] ?? null;
        }

        return $out;
    }
}

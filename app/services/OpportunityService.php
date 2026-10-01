<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\LostReason;
use App\Domain\OpportunitySource;
use App\Domain\OpportunityStatus;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ContactRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OpportunityRepository;
use App\Repositories\UserRepository;

/**
 * Sales enquiries. A quotation can still be raised with no opportunity.
 */
final class OpportunityService
{
    public function __construct(
        private readonly OpportunityRepository $opportunities = new OpportunityRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly ContactRepository $contacts = new ContactRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $data = $this->data($input);
        $id = 0;
        Database::transaction(function () use ($data, $userId, &$id): void {
            $data['opportunity_number'] = $this->numbers->opportunity();
            $data['created_by'] = $userId;
            $id = $this->opportunities->insert($data);
            $this->audit->record('opportunity', $id, 'created', null, [
                'opportunity_number' => $data['opportunity_number'],
                'title' => $data['title'],
            ], $userId);
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input): array
    {
        $existing = $this->opportunities->find($id);
        if ($existing === null) {
            return ['_form' => 'That opportunity was not found.'];
        }
        $input['customer_id'] = $existing['customer_id'];
        $errors = $this->validate($input, false);
        if ($errors !== []) {
            return $errors;
        }
        $data = $this->data($input);
        Database::transaction(function () use ($id, $data, $existing): void {
            $this->opportunities->update($id, $data);
            $this->audit->record('opportunity', $id, 'updated', [
                'status' => $existing['status'],
                'title' => $existing['title'],
            ], [
                'status' => $data['status'],
                'title' => $data['title'],
            ]);
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function markLost(int $id, string $reason, string $notes): array
    {
        $existing = $this->opportunities->find($id);
        if ($existing === null) {
            return ['_form' => 'That opportunity was not found.'];
        }
        $reason = strtoupper(trim($reason));
        if (!in_array($reason, LostReason::values(), true)) {
            return ['lost_reason' => 'Choose a lost reason.'];
        }
        Database::transaction(function () use ($id, $reason, $notes, $existing): void {
            $this->opportunities->setStatus($id, OpportunityStatus::Lost->value, $reason, blank_to_null($notes));
            $this->audit->record('opportunity', $id, 'lost', ['status' => $existing['status']], [
                'status' => 'LOST',
                'lost_reason' => $reason,
            ]);
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function markWon(int $id): array
    {
        $existing = $this->opportunities->find($id);
        if ($existing === null) {
            return ['_form' => 'That opportunity was not found.'];
        }
        Database::transaction(function () use ($id, $existing): void {
            $this->opportunities->setStatus($id, OpportunityStatus::Won->value, null, null);
            $this->audit->record('opportunity', $id, 'won', ['status' => $existing['status']], ['status' => 'WON']);
        });

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input, bool $requireCustomer = true): array
    {
        $errors = [];
        if ($requireCustomer) {
            $customerId = (int) ($input['customer_id'] ?? 0);
            if ($customerId < 1 || $this->customers->find($customerId) === null) {
                $errors['customer_id'] = 'Choose a customer.';
            }
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }
        $source = strtoupper(trim((string) ($input['source'] ?? 'OTHER')));
        if (!in_array($source, OpportunitySource::values(), true)) {
            $errors['source'] = 'Choose a source.';
        }
        $status = strtoupper(trim((string) ($input['status'] ?? 'NEW')));
        if (!in_array($status, OpportunityStatus::values(), true)) {
            $errors['status'] = 'Choose a status.';
        }
        $probability = str_replace(',', '.', trim((string) ($input['probability_percent'] ?? '')));
        if ($probability !== '' && (!Decimal::isNumeric($probability) || Decimal::cmp($probability, '0') < 0 || Decimal::cmp($probability, '100') > 0)) {
            $errors['probability_percent'] = 'Probability must be between 0 and 100.';
        }
        $value = str_replace(',', '.', trim((string) ($input['estimated_value'] ?? '')));
        if ($value !== '' && (!Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0)) {
            $errors['estimated_value'] = 'Estimated value must be zero or more.';
        }
        foreach (['expected_close_date', 'next_follow_up_date'] as $field) {
            $date = trim((string) ($input[$field] ?? ''));
            if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors[$field] = 'Enter a valid date.';
            }
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        $contactId = (int) ($input['contact_id'] ?? 0);
        if ($contactId > 0 && $this->contacts->findForCustomer($customerId, $contactId) === null) {
            $errors['contact_id'] = 'That contact does not belong to this customer.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function data(array $input): array
    {
        $assigned = (int) ($input['assigned_to'] ?? 0);
        if ($assigned > 0 && $this->users->find($assigned) === null) {
            $assigned = 0;
        }
        $value = str_replace(',', '.', trim((string) ($input['estimated_value'] ?? '')));
        $probability = str_replace(',', '.', trim((string) ($input['probability_percent'] ?? '')));
        $status = strtoupper(trim((string) ($input['status'] ?? 'NEW')));
        $reason = strtoupper(trim((string) ($input['lost_reason'] ?? '')));

        return [
            'customer_id' => (int) $input['customer_id'],
            'contact_id' => ((int) ($input['contact_id'] ?? 0)) > 0 ? (int) $input['contact_id'] : null,
            'title' => trim((string) $input['title']),
            'description' => blank_to_null($input['description'] ?? null),
            'source' => strtoupper(trim((string) ($input['source'] ?? 'OTHER'))),
            'estimated_value' => $value === '' ? null : Decimal::money($value),
            'probability_percent' => $probability === '' ? null : Decimal::round($probability, 2),
            'status' => $status,
            'lost_reason' => $status === 'LOST' && in_array($reason, LostReason::values(), true) ? $reason : null,
            'lost_notes' => $status === 'LOST' ? blank_to_null($input['lost_notes'] ?? null) : null,
            'assigned_to' => $assigned > 0 ? $assigned : null,
            'expected_close_date' => $this->dateOrNull($input['expected_close_date'] ?? null),
            'next_follow_up_date' => $this->dateOrNull($input['next_follow_up_date'] ?? null),
            'notes' => blank_to_null($input['notes'] ?? null),
        ];
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CommunicationRepository;
use App\Repositories\ContactRepository;

/**
 * Marketing consent is separate from operational messages such as quotes and invoices.
 */
final class ConsentService
{
    public function __construct(
        private readonly CommunicationRepository $communications = new CommunicationRepository(),
        private readonly ContactRepository $contacts = new ContactRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function allowsMarketing(?int $contactId, ?string $email, ?string $phone): bool
    {
        if ($contactId !== null && $contactId > 0) {
            $prefs = $this->communications->preferences($contactId);
            if ((int) $prefs['marketing_allowed'] !== 1) {
                return false;
            }
        }
        if ($this->communications->suppressed('EMAIL', $email, $phone, $contactId) && $email !== null && $email !== '') {
            return false;
        }

        return $contactId !== null && $contactId > 0;
    }

    public function allowsTransactional(?int $contactId, string $channel): bool
    {
        if ($contactId === null || $contactId < 1) {
            return true;
        }
        $prefs = $this->communications->preferences($contactId);
        if ((int) $prefs['transactional_allowed'] !== 1) {
            return false;
        }
        if ($channel === 'EMAIL' && (int) $prefs['email_allowed'] !== 1) {
            return false;
        }
        if ($channel === 'WHATSAPP' && (int) $prefs['whatsapp_allowed'] !== 1) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function save(int $customerId, int $contactId, array $input, int $userId): array
    {
        $contact = $this->contacts->findForCustomer($customerId, $contactId);
        if ($contact === null) {
            return ['_form' => 'That contact was not found.'];
        }
        $this->communications->savePreferences($contactId, [
            'email_allowed' => isset($input['email_allowed']) ? 1 : 0,
            'whatsapp_allowed' => isset($input['whatsapp_allowed']) ? 1 : 0,
            'sms_allowed' => isset($input['sms_allowed']) ? 1 : 0,
            'marketing_allowed' => isset($input['marketing_allowed']) ? 1 : 0,
            'transactional_allowed' => isset($input['transactional_allowed']) ? 1 : 0,
            'preferred_channel' => blank_to_null($input['preferred_channel'] ?? null),
            'source' => 'STAFF',
        ]);
        $this->audit->record('contact', $contactId, 'COMMUNICATION_PREFERENCES', null, [
            'marketing_allowed' => isset($input['marketing_allowed']) ? 1 : 0,
        ], $userId);

        return [];
    }

    public function unsubscribe(int $contactId, string $channel, ?string $reason, int $userId): void
    {
        $contact = $this->contacts->find($contactId);
        if ($contact === null) {
            return;
        }
        $prefs = $this->communications->preferences($contactId);
        $prefs['marketing_allowed'] = 0;
        if ($channel === 'EMAIL') {
            $prefs['email_allowed'] = (int) $prefs['email_allowed'];
        }
        $prefs['source'] = 'UNSUBSCRIBE';
        $prefs['preferred_channel'] = $prefs['preferred_channel'] ?? null;
        $this->communications->savePreferences($contactId, $prefs);
        $this->communications->suppress([
            'contact_id' => $contactId,
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? $contact['mobile'] ?? null,
            'channel' => $channel,
            'reason' => $reason,
        ]);
        $this->audit->record('contact', $contactId, 'MARKETING_UNSUBSCRIBED', null, [
            'channel' => $channel,
            'reason' => $reason,
        ], $userId);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\OpportunitySource;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CommunicationRepository;
use App\Repositories\ContactRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\LeadRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\SystemRepository;

/**
 * Enquiries become leads, then customers, contacts, and opportunities.
 * A public submission does not create an opportunity.
 */
final class LeadService
{
    /** @var list<string> */
    public const STATUSES = [
        'NEW', 'UNASSIGNED', 'ASSIGNED', 'CONTACTED', 'QUALIFIED', 'UNQUALIFIED', 'CONVERTED', 'LOST', 'SPAM',
    ];

    public function __construct(
        private readonly LeadRepository $leads = new LeadRepository(),
        private readonly PhoneNumberService $phones = new PhoneNumberService(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly CommunicationRepository $communications = new CommunicationRepository(),
        private readonly SystemRepository $system = new SystemRepository()
    ) {
    }

    /**
     * @return array{http: int, body: array<string, mixed>}
     */
    public function acceptPublic(string $raw, string $ip): array
    {
        if (strlen($raw) > 20000) {
            return ['http' => 413, 'body' => ['ok' => false, 'received' => false, 'message' => 'That enquiry is too large.']];
        }
        $this->communications->rateHit('website-lead', $ip);
        $limit = max(1, (int) SettingsService::get('website_lead_rate_per_hour', '8'));
        if ($this->communications->rateCount('website-lead', $ip) > $limit) {
            return ['http' => 429, 'body' => ['ok' => false, 'received' => false, 'message' => 'Please try again later.']];
        }
        try {
            $payload = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['http' => 400, 'body' => ['ok' => false, 'received' => false, 'message' => 'The enquiry could not be read.']];
        }
        if (!is_array($payload)) {
            return ['http' => 400, 'body' => ['ok' => false, 'received' => false, 'message' => 'The enquiry could not be read.']];
        }
        if (trim((string) ($payload['company_website'] ?? '')) !== '') {
            return ['http' => 200, 'body' => ['ok' => true, 'received' => true]];
        }
        $started = (int) ($payload['form_started_at'] ?? 0);
        $minimum = max(0, (int) SettingsService::get('website_lead_min_seconds', '3'));
        if ($started < 1 || (time() - $started) < $minimum) {
            return ['http' => 422, 'body' => ['ok' => false, 'received' => false, 'message' => 'Please wait a moment and try again.']];
        }
        $name = trim((string) ($payload['name'] ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));
        if ($name === '' || $message === '') {
            return ['http' => 422, 'body' => ['ok' => false, 'received' => false, 'message' => 'Name and message are required.']];
        }
        $email = trim((string) ($payload['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['http' => 422, 'body' => ['ok' => false, 'received' => false, 'message' => 'That email address is not valid.']];
        }
        $hash = hash('sha256', strtolower($email) . '|' . $message);
        if ($this->leads->duplicateHash($hash)) {
            return ['http' => 200, 'body' => ['ok' => true, 'received' => true]];
        }
        $campaignCode = trim((string) ($payload['campaign_code'] ?? $payload['utm_campaign'] ?? ''));
        $campaign = $campaignCode !== '' ? $this->leads->campaignByCode($campaignCode) : null;
        $id = $this->store([
            'source' => 'WEBSITE',
            'source_detail' => blank_to_null($payload['utm_source'] ?? null),
            'campaign_id' => $campaign === null ? null : (int) $campaign['id'],
            'name' => $name,
            'company_name' => blank_to_null($payload['company_name'] ?? $payload['company'] ?? null),
            'email' => $email === '' ? null : $email,
            'phone' => blank_to_null($payload['phone'] ?? null),
            'message' => $message,
            'message_hash' => $hash,
            'service_interest' => blank_to_null($payload['service_interest'] ?? null),
            'estimated_value' => null,
            'status' => 'UNASSIGNED',
            'assigned_to' => null,
            'attribution' => [
                'model' => 'FIRST_TOUCH',
                'utm_source' => $payload['utm_source'] ?? null,
                'utm_medium' => $payload['utm_medium'] ?? null,
                'utm_campaign' => $payload['utm_campaign'] ?? null,
                'utm_content' => $payload['utm_content'] ?? null,
                'utm_term' => $payload['utm_term'] ?? null,
                'landing_page' => $payload['landing_page'] ?? null,
                'referrer' => $payload['referrer'] ?? null,
            ],
        ], 0);
        $this->notifyNew($id);

        return ['http' => 200, 'body' => ['ok' => true, 'received' => true]];
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
        $assigned = (int) ($input['assigned_to'] ?? 0);
        $id = $this->store([
            'source' => strtoupper(trim((string) $input['source'])),
            'source_detail' => blank_to_null($input['source_detail'] ?? null),
            'campaign_id' => ((int) ($input['campaign_id'] ?? 0)) > 0 ? (int) $input['campaign_id'] : null,
            'name' => trim((string) $input['name']),
            'company_name' => blank_to_null($input['company_name'] ?? null),
            'email' => blank_to_null($input['email'] ?? null),
            'phone' => blank_to_null($input['phone'] ?? null),
            'message' => trim((string) $input['message']),
            'message_hash' => null,
            'service_interest' => blank_to_null($input['service_interest'] ?? null),
            'estimated_value' => $this->money($input['estimated_value'] ?? null),
            'status' => $assigned > 0 ? 'ASSIGNED' : 'UNASSIGNED',
            'assigned_to' => $assigned > 0 ? $assigned : null,
            'attribution' => ['model' => 'FIRST_TOUCH', 'captured' => 'staff'],
        ], $userId);
        $this->notifyNew($id);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function matches(array $lead): array
    {
        $found = [];
        $email = trim((string) ($lead['email'] ?? ''));
        if ($email !== '') {
            $found = array_merge($found, $this->leads->emailMatches($email));
        }
        $key = $this->phones->matchKey((string) ($lead['phone'] ?? ''));
        if ($key !== null) {
            $found = array_merge($found, $this->leads->phoneMatches($key));
        }
        $company = trim((string) ($lead['company_name'] ?? ''));
        if (strlen($company) > 2) {
            $found = array_merge($found, $this->leads->companyMatches($company));
        }
        $unique = [];
        foreach ($found as $row) {
            $unique[$row['kind'] . ':' . $row['id'] . ':' . $row['reason']] = $row;
        }

        return array_values($unique);
    }

    public function canView(array $lead, array $user): bool
    {
        if (in_array((string) ($user['role_code'] ?? ''), ['ADMIN', 'MANAGEMENT', 'MARKETING'], true)) {
            return true;
        }
        $assigned = (int) ($lead['assigned_to'] ?? 0);

        return $assigned === 0 || $assigned === (int) $user['id'];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function convert(int $leadId, array $input, int $userId): array
    {
        $lead = $this->leads->find($leadId);
        if ($lead === null) {
            return ['_form' => 'That lead was not found.'];
        }
        if ((string) $lead['status'] === 'CONVERTED') {
            return ['_form' => 'That lead is already converted.'];
        }
        $matches = $this->matches($lead);
        $mode = (string) ($input['customer_mode'] ?? 'new');
        if ($mode === 'new' && $matches !== [] && empty($input['confirm_new'])) {
            return ['_form' => 'Possible existing records were found. Link one, or confirm a new customer.'];
        }
        try {
            Database::transaction(function () use ($lead, $input, $userId, $mode): void {
                $customers = new CustomerRepository();
                $contacts = new ContactRepository();
                if ($mode === 'existing') {
                    $customerId = (int) ($input['customer_id'] ?? 0);
                    if ($customers->find($customerId) === null) {
                        throw new \RuntimeException('Choose an existing customer.');
                    }
                } else {
                    $parts = preg_split('/\s+/', trim((string) $lead['name'])) ?: [];
                    $first = (string) ($parts[0] ?? 'Lead');
                    $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Enquiry';
                    $customerId = $customers->insert([
                        'customer_type' => trim((string) ($lead['company_name'] ?? '')) !== '' ? 'BUSINESS' : 'INDIVIDUAL',
                        'company_name' => $lead['company_name'],
                        'first_name' => $first,
                        'last_name' => $last,
                        'vat_number' => null,
                        'registration_number' => null,
                        'email' => $lead['email'],
                        'phone' => $lead['phone'],
                        'mobile' => null,
                        'website' => null,
                        'billing_address' => null,
                        'physical_address' => null,
                        'notes' => 'Created from lead ' . $lead['lead_number'],
                        'active' => 1,
                        'created_by' => $userId,
                    ]);
                    $this->audit->record('customer', $customerId, 'created', null, [
                        'lead_id' => (int) $lead['id'],
                    ], $userId);
                }
                $contactId = (int) ($input['contact_id'] ?? 0);
                if ($contactId < 1 || $contacts->findForCustomer($customerId, $contactId) === null) {
                    $contactId = $contacts->insert([
                        'customer_id' => $customerId,
                        'name' => (string) $lead['name'],
                        'position' => null,
                        'email' => $lead['email'],
                        'phone' => $lead['phone'],
                        'mobile' => null,
                        'primary_contact' => 1,
                        'notes' => 'Created from lead ' . $lead['lead_number'],
                        'active' => 1,
                    ]);
                }
                $source = strtoupper((string) $lead['source']);
                if (!in_array($source, OpportunitySource::values(), true)) {
                    $source = 'OTHER';
                }
                $created = (new OpportunityService())->create([
                    'customer_id' => $customerId,
                    'contact_id' => $contactId,
                    'title' => trim((string) ($lead['service_interest'] ?: $lead['company_name'] ?: $lead['name'])),
                    'description' => (string) $lead['message'],
                    'source' => $source,
                    'estimated_value' => $lead['estimated_value'],
                    'status' => 'NEW',
                    'assigned_to' => $lead['assigned_to'] ?: $userId,
                    'notes' => 'First-touch source ' . $lead['source'] . ' from ' . $lead['lead_number'],
                ], $userId);
                if ($created['id'] === null) {
                    throw new \RuntimeException($created['errors']['_form'] ?? 'The opportunity could not be created.');
                }
                (new AttributionService())->stampOpportunity((int) $created['id'], (int) $lead['id'], $lead['campaign_id'] !== null ? (int) $lead['campaign_id'] : null);
                $this->leads->convert((int) $lead['id'], [
                    'customer_id' => $customerId,
                    'contact_id' => $contactId,
                    'opportunity_id' => (int) $created['id'],
                ], $userId, date('Y-m-d H:i:s'));
                if ((int) ($input['referred_by_customer_id'] ?? 0) > 0) {
                    Database::connection()->prepare(
                        'INSERT INTO customer_referrals (lead_id, customer_id, referred_by_customer_id, notes) VALUES (?, ?, ?, ?)'
                    )->execute([
                        (int) $lead['id'], $customerId, (int) $input['referred_by_customer_id'], blank_to_null($input['referral_notes'] ?? null),
                    ]);
                }
                $this->audit->record('lead', (int) $lead['id'], 'LEAD_CONVERTED', ['status' => $lead['status']], [
                    'customer_id' => $customerId,
                    'contact_id' => $contactId,
                    'opportunity_id' => (int) $created['id'],
                    'source' => $lead['source'],
                    'campaign_id' => $lead['campaign_id'],
                ], $userId);
            });
        } catch (\PDOException $e) {
            error_log('Lead conversion failed: ' . $e->getMessage());

            return ['_form' => 'The lead could not be converted.'];
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            return ['_form' => $message !== '' ? $message : 'The lead could not be converted.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function assign(int $leadId, int $assigneeId, int $actorId): array
    {
        $lead = $this->leads->find($leadId);
        if ($lead === null) {
            return ['_form' => 'That lead was not found.'];
        }
        $this->leads->assign($leadId, $assigneeId);
        $this->audit->record('lead', $leadId, 'LEAD_ASSIGNED', ['assigned_to' => $lead['assigned_to']], [
            'assigned_to' => $assigneeId,
        ], $actorId);
        (new AutomationService())->fire('LEAD_ASSIGNED', 'lead', $leadId, $actorId);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function logCall(int $leadId, array $input, int $userId): array
    {
        $lead = $this->leads->find($leadId);
        if ($lead === null) {
            return ['_form' => 'That lead was not found.'];
        }
        $outcome = strtoupper(trim((string) ($input['outcome'] ?? 'OTHER')));
        $when = date('Y-m-d H:i:s');
        $summary = trim((string) ($input['notes'] ?? ''));
        $this->system->insertCommunication([
            'customer_id' => $lead['customer_id'],
            'contact_id' => $lead['contact_id'],
            'lead_id' => $leadId,
            'channel' => 'PHONE',
            'direction' => strtoupper((string) ($input['direction'] ?? 'OUTBOUND')) === 'INBOUND' ? 'INBOUND' : 'OUTBOUND',
            'subject' => 'Call ' . $outcome,
            'message_summary' => mb_substr($summary === '' ? $outcome : $summary, 0, 500),
            'message_body' => $summary,
            'status' => 'LOGGED',
            'sent_by' => $userId,
            'sent_at' => $when,
        ]);
        $this->leads->touch($leadId, $when);
        if (trim((string) ($input['follow_up'] ?? '')) !== '') {
            $this->leads->setFollowUp($leadId, str_replace('T', ' ', (string) $input['follow_up']));
            $this->audit->record('lead', $leadId, 'FOLLOWUP_CREATED', null, ['next' => $input['follow_up']], $userId);
        }
        $this->audit->record('lead', $leadId, 'COMMUNICATION_CREATED', null, ['channel' => 'PHONE', 'outcome' => $outcome], $userId);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, url: string}
     */
    public function prepareWhatsapp(int $leadId, string $message, int $userId): array
    {
        $lead = $this->leads->find($leadId);
        if ($lead === null) {
            return ['errors' => ['_form' => 'That lead was not found.'], 'url' => ''];
        }
        $prepared = (new WhatsAppService())->prepare((string) ($lead['phone'] ?? ''), $message);
        $this->system->insertCommunication([
            'customer_id' => $lead['customer_id'],
            'contact_id' => $lead['contact_id'],
            'lead_id' => $leadId,
            'channel' => 'WHATSAPP',
            'direction' => 'OUTBOUND',
            'subject' => 'WhatsApp prepared',
            'message_summary' => mb_substr($prepared['reason'], 0, 500),
            'message_body' => $message,
            'status' => $prepared['status'],
            'sent_by' => $userId,
            'external_reference' => $prepared['phone'],
        ]);
        if ($prepared['ok']) {
            $this->leads->touch($leadId, date('Y-m-d H:i:s'));
        }
        $this->audit->record('lead', $leadId, 'WHATSAPP_PREPARED', null, [
            'status' => $prepared['status'],
            'phone' => $prepared['phone'],
        ], $userId);

        return ['errors' => $prepared['ok'] ? [] : ['_form' => $prepared['reason']], 'url' => $prepared['url']];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addNote(int $leadId, string $note, int $userId): array
    {
        $lead = $this->leads->find($leadId);
        if ($lead === null || trim($note) === '') {
            return ['_form' => 'Write a note.'];
        }
        $this->system->insertCommunication([
            'customer_id' => $lead['customer_id'],
            'lead_id' => $leadId,
            'channel' => 'OTHER',
            'direction' => 'INTERNAL',
            'subject' => 'Note',
            'message_summary' => mb_substr(trim($note), 0, 500),
            'message_body' => trim($note),
            'status' => 'LOGGED',
            'sent_by' => $userId,
        ]);
        $this->audit->record('lead', $leadId, 'COMMUNICATION_CREATED', null, ['channel' => 'NOTE'], $userId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function setFollowUp(int $leadId, string $when, int $userId): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $when)) {
            return ['follow_up' => 'Choose a follow-up date.'];
        }
        $this->leads->setFollowUp($leadId, strlen($when) === 10 ? $when . ' 08:00:00' : str_replace('T', ' ', $when));
        $this->audit->record('lead', $leadId, 'FOLLOWUP_CREATED', null, ['next' => $when], $userId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function markLost(int $leadId, string $reason, int $userId): array
    {
        if (trim($reason) === '') {
            return ['lost_reason' => 'A lost reason is required.'];
        }
        $this->leads->mark($leadId, 'LOST', trim($reason));
        $this->audit->record('lead', $leadId, 'LEAD_LOST', null, ['reason' => $reason], $userId);

        return [];
    }

    public function markSpam(int $leadId, int $userId): void
    {
        $this->leads->mark($leadId, 'SPAM', 'Spam');
        $this->audit->record('lead', $leadId, 'LEAD_LOST', null, ['reason' => 'SPAM'], $userId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function store(array $data, int $userId): int
    {
        $id = 0;
        Database::transaction(function () use ($data, $userId, &$id): void {
            $data['lead_number'] = $this->numbers->lead();
            $data['phone_normalised'] = $this->phones->normalise($data['phone']);
            $data['attribution_json'] = json_encode($data['attribution'], JSON_UNESCAPED_UNICODE);
            $id = $this->leads->insert($data);
            $this->audit->record('lead', $id, 'LEAD_CREATED', null, [
                'lead_number' => $data['lead_number'],
                'source' => $data['source'],
                'campaign_id' => $data['campaign_id'],
            ], $userId > 0 ? $userId : null);
        });
        if ($userId > 0) {
            (new AutomationService())->fire('LEAD_CREATED', 'lead', $id, $userId);
        }

        return $id;
    }

    private function notifyNew(int $leadId): void
    {
        $lead = $this->leads->find($leadId);
        if ($lead === null) {
            return;
        }
        $roleId = $this->system->roleId('SALES');
        (new NotificationRepository())->insert([
            'user_id' => $lead['assigned_to'],
            'role_id' => $lead['assigned_to'] ? null : $roleId,
            'type' => 'LEAD_CREATED',
            'title' => 'New lead ' . $lead['lead_number'],
            'message' => (string) $lead['name'],
            'entity_type' => 'lead',
            'entity_id' => $leadId,
            'priority' => 'NORMAL',
            'dedupe_key' => 'lead-created:' . $leadId,
            'expires_at' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];
        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors['name'] = 'Name is required.';
        }
        if (trim((string) ($input['message'] ?? '')) === '') {
            $errors['message'] = 'Message is required.';
        }
        $source = strtoupper(trim((string) ($input['source'] ?? '')));
        $known = array_column($this->leads->sources(), 'code');
        if (!in_array($source, $known, true)) {
            $errors['source'] = 'Choose a source.';
        }
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'That email address is not valid.';
        }

        return $errors;
    }

    private function money(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '' || !Decimal::isNumeric($value)) {
            return null;
        }

        return Decimal::money($value);
    }
}

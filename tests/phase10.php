<?php

declare(strict_types=1);

/**
 * Phase 10 leads, communications, consent, and attribution.
 *
 *   php tests/phase10.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\AutomationRepository;
use App\Repositories\CommunicationRepository;
use App\Repositories\ContactRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\LeadRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use App\Services\AttributionService;
use App\Services\BusinessTimeService;
use App\Services\CommunicationService;
use App\Services\ConsentService;
use App\Services\CustomerService;
use App\Services\FollowUpService;
use App\Services\LeadService;
use App\Services\QuoteService;
use App\Services\SettingsService;
use App\Services\TemplateEngine;
use App\Services\WebhookService;
use App\Services\WhatsAppService;
use App\Repositories\MarketingRepository;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$eq = static function (string $label, mixed $expected, mixed $actual) use ($fail, $ok): void {
    if ($expected !== $actual) {
        $fail($label . ' expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));

        return;
    }
    $ok($label);
};

$admin = (new UserRepository())->findByEmail('admin@signforge.local');
if ($admin === null) {
    fwrite(STDERR, "Admin user is missing.\n");
    exit(1);
}
$_SESSION['user_id'] = (int) $admin['id'];
$stamp = date('YmdHis') . bin2hex(random_bytes(2));

$friday = (new BusinessTimeService())->addBusinessHours(new DateTimeImmutable('2026-10-02 18:00:00'), 4);
$eq('Friday evening plus 4 business hours', '2026-10-05 12:00', $friday->format('Y-m-d H:i'));

$engine = new TemplateEngine();
$rendered = $engine->render('Hello {{contact_name}} <?php echo 1; ?> {{phpinfo()}}', ['contact_name' => 'Ayesha']);
$eq('template replaces the allowed name', true, str_contains($rendered, 'Ayesha'));
$eq('template does not run php', true, str_contains($rendered, '<?php echo 1; ?>') && str_contains($rendered, '{{phpinfo()}}'));

$phones = new \App\Services\PhoneNumberService();
$eq('local mobile normalises', '27821234567', $phones->normalise('0821234567'));
$eq('international mobile normalises', '27821234567', $phones->normalise('+27 82 123 4567'));

$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Phase10 ' . $stamp,
    'email' => 'office-' . $stamp . '@example.com',
    'phone' => '0100000000',
], (int) $admin['id']);
$eq('customer for phase 10', true, $customer['id'] !== null);
$contactId = (new ContactRepository())->insert([
    'customer_id' => (int) $customer['id'],
    'name' => 'Existing Contact',
    'position' => null,
    'email' => 'person-' . $stamp . '@example.com',
    'phone' => '0821234567',
    'mobile' => null,
    'primary_contact' => 1,
    'notes' => null,
    'active' => 1,
]);

$campaignId = (new MarketingRepository())->insertCampaign([
    'name' => 'September Search ' . $stamp,
    'campaign_code' => 'SEPSEARCH' . $stamp,
    'channel' => 'GOOGLE',
    'start_date' => '2026-09-01',
    'end_date' => null,
    'budget' => '1000.00',
    'description' => 'Search ads',
    'status' => 'ACTIVE',
    'created_by' => (int) $admin['id'],
]);

$public = (new LeadService())->acceptPublic(json_encode([
    'name' => 'Website Lead ' . $stamp,
    'phone' => '+27 82 123 4567',
    'email' => 'web-' . $stamp . '@example.com',
    'message' => 'Need a shopfront sign ' . $stamp,
    'service_interest' => 'Shopfront',
    'utm_source' => 'google',
    'utm_campaign' => 'SEPSEARCH' . $stamp,
    'campaign_code' => 'SEPSEARCH' . $stamp,
    'form_started_at' => time() - 30,
    'company_website' => '',
], JSON_THROW_ON_ERROR), '203.0.113.10');
$eq('public lead accepted', true, $public['body']['received'] === true && !isset($public['body']['id']));
$lead = Database::connection()->query(
    'SELECT * FROM leads WHERE email = ' . Database::connection()->quote('web-' . $stamp . '@example.com')
)->fetch();
$eq('lead number assigned', true, is_array($lead) && str_starts_with((string) $lead['lead_number'], 'SFL-'));
$eq('website source', 'WEBSITE', $lead['source'] ?? null);
$eq('campaign linked', $campaignId, (int) ($lead['campaign_id'] ?? 0));
$eq('utm snapshot stored', true, str_contains((string) ($lead['attribution_json'] ?? ''), 'google'));
$eq('no automatic opportunity', null, $lead['opportunity_id']);
$note = (new NotificationRepository())->countByDedupe('lead-created:' . $lead['id']);
$eq('lead notification once', 1, $note);

$matches = (new LeadService())->matches($lead);
$phoneMatch = false;
foreach ($matches as $match) {
    if ((string) $match['reason'] === 'phone' && (int) $match['id'] === $contactId) {
        $phoneMatch = true;
    }
}
$eq('possible phone match', true, $phoneMatch);
$eq('lead not merged', null, $lead['customer_id']);

$convert = (new LeadService())->convert((int) $lead['id'], [
    'customer_mode' => 'existing',
    'customer_id' => (int) $customer['id'],
], (int) $admin['id']);
$eq('conversion errors', [], $convert);
$converted = (new LeadRepository())->find((int) $lead['id']);
$eq('lead preserved', 'CONVERTED', $converted['status'] ?? null);
$eq('lead still numbered', $lead['lead_number'], $converted['lead_number'] ?? null);
$opportunity = Database::connection()->query(
    'SELECT * FROM sales_opportunities WHERE id = ' . (int) $converted['opportunity_id']
)->fetch();
$eq('opportunity source', 'WEBSITE', $opportunity['source'] ?? null);
$eq('opportunity campaign', $campaignId, (int) ($opportunity['campaign_id'] ?? 0));
$eq('first touch model', 'FIRST_TOUCH', $opportunity['attribution_model'] ?? null);
$audit = Database::connection()->query(
    "SELECT id FROM audit_log WHERE entity_type = 'lead' AND entity_id = " . (int) $lead['id'] . " AND action = 'LEAD_CONVERTED'"
)->fetch();
$eq('conversion audited', true, $audit !== false);

$quote = (new QuoteService())->create([
    'customer_id' => (int) $customer['id'],
    'opportunity_id' => (int) $converted['opportunity_id'],
], (int) $admin['id']);
$storedQuote = (new QuoteRepository())->find((int) $quote['id']);
$eq('quote keeps lead', (int) $lead['id'], (int) ($storedQuote['lead_id'] ?? 0));
$eq('quote keeps campaign', $campaignId, (int) ($storedQuote['campaign_id'] ?? 0));

$jobNumber = 'SFJ-P10-' . substr($stamp, -8);
Database::connection()->prepare(
    'INSERT INTO jobs (job_number, customer_id, quote_id, quote_revision_number, opportunity_id, title, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([$jobNumber, (int) $customer['id'], (int) $quote['id'], 1, (int) $converted['opportunity_id'], 'Phase 10 job', (int) $admin['id']]);
$jobId = (int) Database::connection()->lastInsertId();
(new AttributionService())->copyToJob($jobId);
$jobLead = Database::connection()->query('SELECT lead_id, campaign_id, attribution_model FROM jobs WHERE id = ' . $jobId)->fetch();
$eq('job keeps lead', (int) $lead['id'], (int) ($jobLead['lead_id'] ?? 0));
Database::connection()->prepare(
    'INSERT INTO invoices (customer_id, job_id, invoice_date, status) VALUES (?, ?, CURDATE(), \'DRAFT\')'
)->execute([(int) $customer['id'], $jobId]);
$invoiceId = (int) Database::connection()->lastInsertId();
(new AttributionService())->copyToInvoice($invoiceId);
$invoiceLead = Database::connection()->query('SELECT lead_id, campaign_id FROM invoices WHERE id = ' . $invoiceId)->fetch();
$eq('invoice keeps lead', (int) $lead['id'], (int) ($invoiceLead['lead_id'] ?? 0));
$eq('invoice keeps campaign', $campaignId, (int) ($invoiceLead['campaign_id'] ?? 0));

$settings = new SettingRepository();
$settings->put('email_delivery_mode', 'log');
SettingsService::forget();
$templateId = (int) (Database::connection()->query(
    "SELECT id FROM communication_templates WHERE category = 'QUOTE_SEND' AND channel = 'EMAIL' LIMIT 1"
)->fetch()['id'] ?? 0);
$sent = (new CommunicationService())->sendQuote((int) $quote['id'], 'buyer-' . $stamp . '@example.com', $templateId, (int) $admin['id'], true);
$eq('quote email accepted by outbox', true, $sent['sent']);
$eq('preview has quote number', true, str_contains((string) $sent['preview']['body'], (string) $storedQuote['quote_number']));
$after = (new QuoteRepository())->find((int) $quote['id']);
$eq('successful email marks quote sent', 'SENT', $after['status'] ?? null);
$logged = Database::connection()->query(
    "SELECT status, message_body FROM communications WHERE quote_id = " . (int) $quote['id'] . " AND channel = 'EMAIL' ORDER BY id DESC LIMIT 1"
)->fetch();
$eq('email communication sent', 'SENT', $logged['status'] ?? null);
$outbox = (string) file_get_contents(dirname(__DIR__) . '/storage/mail/outbox.jsonl');
$eq('outbox has recipient', true, str_contains($outbox, 'buyer-' . $stamp . '@example.com'));
$eq('outbox omits smtp password', false, str_contains($outbox, 'smtp_password'));

$draft = (new QuoteService())->create(['customer_id' => (int) $customer['id']], (int) $admin['id']);
$settings->put('email_delivery_mode', 'off');
SettingsService::forget();
$failed = (new CommunicationService())->sendQuote((int) $draft['id'], 'buyer-' . $stamp . '@example.com', $templateId, (int) $admin['id'], true);
$eq('unconfigured email fails', false, $failed['sent']);
$draftAfter = (new QuoteRepository())->find((int) $draft['id']);
$eq('failed email leaves quote draft', 'DRAFT', $draftAfter['status'] ?? null);
$failedRow = Database::connection()->query(
    "SELECT status FROM communications WHERE quote_id = " . (int) $draft['id'] . " ORDER BY id DESC LIMIT 1"
)->fetch();
$eq('failed email is not sent', 'FAILED', $failedRow['status'] ?? null);

$wa = (new WhatsAppService())->prepare('0821234567', 'Quote ready');
$eq('whatsapp international digits', true, str_contains($wa['url'], 'wa.me/27821234567'));
$eq('whatsapp not delivered', 'PREPARED', $wa['status']);
$prepared = (new LeadService())->prepareWhatsapp((int) $lead['id'], 'Hello from the shop', (int) $admin['id']);
$waRow = Database::connection()->query(
    "SELECT status FROM communications WHERE lead_id = " . (int) $lead['id'] . " AND channel = 'WHATSAPP' ORDER BY id DESC LIMIT 1"
)->fetch();
$eq('whatsapp log is prepared', 'PREPARED', $waRow['status'] ?? null);
$eq('whatsapp url returned', true, str_starts_with($prepared['url'], 'https://wa.me/'));

$follow = new FollowUpService();
$eq('follow-up already stored when the quote was emailed', false, $follow->scheduleQuote((int) $quote['id'], (int) $admin['id']));
$eq('follow-up not duplicated', false, $follow->scheduleQuote((int) $quote['id'], (int) $admin['id']));
$reminderCount = (int) Database::connection()->query(
    "SELECT COUNT(*) FROM reminders WHERE dedupe_key = 'quote-followup:" . (int) $quote['id'] . "'"
)->fetchColumn();
$eq('one quote reminder', 1, $reminderCount);

$rules = new AutomationRepository();
$ruleId = $rules->insert([
    'name' => 'Do not email ' . $stamp,
    'trigger_type' => 'LEAD_CREATED',
    'conditions_json' => '{}',
    'action_type' => 'SEND_EMAIL',
    'action_config_json' => '{}',
    'created_by' => (int) $admin['id'],
]);
(new \App\Services\AutomationService())->fire('LEAD_CREATED', 'lead', (int) $lead['id'], (int) $admin['id']);
$skipped = Database::connection()->query(
    "SELECT message FROM automation_log WHERE automation_rule_id = {$ruleId} ORDER BY id DESC LIMIT 1"
)->fetch();
$eq('automation does not send email', true, is_array($skipped) && str_contains((string) $skipped['message'], 'disabled'));

$consentPhone = '084' . substr(preg_replace('/\D/', '', $stamp) ?? '', -7);
$consentId = (new ContactRepository())->insert([
    'customer_id' => (int) $customer['id'],
    'name' => 'Consent ' . $stamp,
    'position' => null,
    'email' => 'consent-' . $stamp . '@example.com',
    'phone' => $consentPhone,
    'mobile' => null,
    'primary_contact' => 0,
    'notes' => null,
    'active' => 1,
]);
(new ConsentService())->save((int) $customer['id'], $consentId, [
    'email_allowed' => '1',
    'transactional_allowed' => '1',
    'marketing_allowed' => '1',
], (int) $admin['id']);
$eq('marketing allowed before unsubscribe', true, (new ConsentService())->allowsMarketing($consentId, 'consent-' . $stamp . '@example.com', $consentPhone));
(new ConsentService())->unsubscribe($consentId, 'EMAIL', 'No more newsletters', (int) $admin['id']);
$eq('marketing excluded after unsubscribe', false, (new ConsentService())->allowsMarketing($consentId, 'consent-' . $stamp . '@example.com', $consentPhone));
$eq('transactional still allowed', true, (new ConsentService())->allowsTransactional($consentId, 'EMAIL'));
$recipients = (new MarketingRepository())->marketingRecipients(500);
$included = false;
foreach ($recipients as $recipient) {
    if ((int) $recipient['id'] === $consentId) {
        $included = true;
    }
}
$eq('bulk list excludes unsubscribe', false, $included);

$roles = [];
foreach ((new \App\Repositories\RoleRepository())->all() as $role) {
    $roles[(string) $role['code']] = (int) $role['id'];
}
$users = new UserRepository();
$salesA = $users->insert([
    'name' => 'Sales A ' . $stamp,
    'email' => 'sales-a-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Sales#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['SALES'],
    'active' => 1,
    'must_change_password' => 0,
]);
$salesB = $users->insert([
    'name' => 'Sales B ' . $stamp,
    'email' => 'sales-b-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Sales#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['SALES'],
    'active' => 1,
    'must_change_password' => 0,
]);
$private = (new LeadService())->create([
    'name' => 'Private ' . $stamp,
    'message' => 'Assigned elsewhere',
    'source' => 'PHONE',
    'assigned_to' => $salesB,
], (int) $admin['id']);
$privateLead = (new LeadRepository())->find((int) $private['id']);
$eq('other salesperson cannot open assigned lead', false, (new LeadService())->canView($privateLead, $users->find($salesA)));
$eq('assignee can open lead', true, (new LeadService())->canView($privateLead, $users->find($salesB)));

$secrets = new CommunicationRepository();
$secrets->putSecret('webhook_secret', 'phase10-webhook-' . $stamp);
$body = json_encode([
    'external_event_id' => 'evt-' . $stamp,
    'event_type' => 'message',
    'message' => 'Inbound hello ' . $stamp,
], JSON_THROW_ON_ERROR);
$signature = hash_hmac('sha256', $body, 'phase10-webhook-' . $stamp);
$hook = new WebhookService();
$first = $hook->receive('whatsapp', $body, $signature);
$second = $hook->receive('whatsapp', $body, $signature);
$eq('webhook processed', false, $first['body']['duplicate'] ?? true);
$eq('webhook duplicate ignored', true, $second['body']['duplicate'] ?? false);
$messages = (int) Database::connection()->query(
    "SELECT COUNT(*) FROM communications WHERE external_message_id = " . Database::connection()->quote('evt-' . $stamp)
)->fetchColumn();
$eq('one inbound communication', 1, $messages);

$settings->put('website_lead_rate_per_hour', '2');
SettingsService::forget();
$spamIp = 'phase10-' . $stamp;
Database::connection()->prepare('DELETE FROM public_rate_limits WHERE ip_address = ?')->execute([$spamIp]);
$accepted = 0;
for ($i = 0; $i < 4; $i++) {
    $result = (new LeadService())->acceptPublic(json_encode([
        'name' => 'Spam ' . $stamp . ' ' . $i,
        'message' => 'Different message ' . $stamp . ' ' . $i,
        'form_started_at' => time() - 30,
    ], JSON_THROW_ON_ERROR), $spamIp);
    if (($result['body']['received'] ?? false) === true && $result['http'] === 200) {
        $accepted++;
    }
}
$eq('rate limit still allows a real enquiry', true, $accepted >= 1);
$eq('rate limit blocks the burst', true, $accepted < 4);
$settings->put('website_lead_rate_per_hour', '8');
$settings->put('email_delivery_mode', 'off');
SettingsService::forget();

if ($failures > 0) {
    fwrite(STDERR, "{$failures} phase 10 check(s) failed.\n");
    exit(1);
}
fwrite(STDOUT, "Phase 10 checks passed.\n");

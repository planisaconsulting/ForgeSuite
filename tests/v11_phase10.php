<?php

declare(strict_types=1);

/**
 * v1.1 Phase 10: an expense is captured once, costed once, and the calendar only reflects the ERP date.
 *
 *   php tests/v11_phase10.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ExpenseRepository;
use App\Repositories\JobRepository;
use App\Services\CalendarFeedService;
use App\Services\CustomerMergeService;
use App\Services\DeviceService;
use App\Services\ExpenseService;
use App\Services\FieldSyncService;
use App\Services\IcalCalendarProvider;
use App\Services\OperationalWorkspaceService;
use App\Services\SettingsService;

$failures = 0;
$eq = static function (string $label, mixed $expected, mixed $actual) use (&$failures): void {
    if ($expected === $actual) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
    $failures++;
};
$contains = static function (string $label, string $needle, string $haystack) use (&$failures): void {
    if ($needle !== '' && str_contains($haystack, $needle)) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  missing: {$needle}\n");
    $failures++;
};
$missing = static function (string $label, string $needle, string $haystack) use (&$failures): void {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  found: {$needle}\n");
    $failures++;
};

$pdo = Database::connection();
$userId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'ADMIN' AND u.active = 1 ORDER BY u.id LIMIT 1")->fetchColumn();
$approver = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'MANAGEMENT' AND u.active = 1 AND u.id <> {$userId} ORDER BY u.id LIMIT 1")->fetchColumn();
$workshop = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code IN ('PRODUCTION', 'DESIGN', 'MARKETING') AND u.active = 1 ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $userId;
forget_auth_user();
$stamp = date('His') . bin2hex(random_bytes(2));
$service = new ExpenseService();
$repo = new ExpenseRepository();
$jobs = new JobRepository();
$rateBefore = (string) SettingsService::get('mileage_rate_per_km', '4.50');

$insertCustomer = static function (string $name, string $email) use ($pdo, $userId): int {
    $pdo->prepare('INSERT INTO customers (customer_type, company_name, email, active, created_by) VALUES (\'BUSINESS\', ?, ?, 1, ?)')->execute([$name, $email, $userId]);

    return (int) $pdo->lastInsertId();
};
$insertJob = static function (int $customerId, string $number) use ($pdo, $userId, $stamp): int {
    $pdo->prepare('INSERT INTO quotes (quote_number, customer_id, quote_date, status, created_by) VALUES (?,?,?,?,?)')->execute(['Q10-' . $number . '-' . $stamp, $customerId, date('Y-m-d'), 'ACCEPTED', $userId]);
    $quoteId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO jobs (job_number, customer_id, quote_id, quote_revision_number, title, status, created_by) VALUES (?,?,?,?,?,?,?)')->execute([$number . '-' . $stamp, $customerId, $quoteId, 1, 'Boards', 'NEW', $userId]);

    return (int) $pdo->lastInsertId();
};

$customerId = $insertCustomer('Phase10 ' . $stamp, 'p10-' . $stamp . '@example.test');
$jobId = $insertJob($customerId, 'SFJ-P10');
$jobB = $insertJob($customerId, 'SFJ-P10B');
$jobC = $insertJob($customerId, 'SFJ-P10C');
$hash = hash('sha256', 'silicone-receipt-' . $stamp);

$silicone = $service->create([
    'category' => 'MATERIALS',
    'description' => 'Silicone',
    'merchant_name' => 'Builders ' . $stamp,
    'amount_inc_vat' => '120.00',
    'expense_date' => '2026-10-02',
    'payment_method' => 'CASH',
    'job_id' => $jobId,
    'receipt_sha256' => $hash,
    'receipt_name' => 'silicone.jpg',
], $userId);
$eq('silicone captured', [], $silicone['errors']);
$eq('number prefix', true, str_starts_with((string) $silicone['number'], 'SFEXP-'));
$submitted = $service->submit((int) $silicone['id'], $userId);
$again = $service->submit((int) $silicone['id'], $userId);
$eq('submitted', [], $submitted['errors']);
$eq('second submit is the same transition', true, $again['already']);
$auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'expense' AND entity_id = " . (int) $silicone['id'] . " AND action = 'EXPENSE_SUBMITTED'")->fetchColumn();
$eq('one submit audit', 1, $auditCount);
$_SESSION['user_id'] = $userId;
forget_auth_user();
$own = $service->approve((int) $silicone['id'], $userId);
$eq('self approval denied', 'You cannot approve your own expense.', (string) ($own['errors']['approval'] ?? ''));
$_SESSION['user_id'] = $approver;
forget_auth_user();
$approved = $service->approve((int) $silicone['id'], $approver);
$eq('approved', [], $approved['errors']);
$eq('posted once', true, $approved['posted']);
$secondApprove = $service->approve((int) $silicone['id'], $approver);
$eq('second approval does not post again', false, $secondApprove['posted']);
$_SESSION['user_id'] = $userId;
forget_auth_user();
$eq('job other cost 120', '120.00', Decimal::money($jobs->otherTotal($jobId)));
$positive = (int) $pdo->query("SELECT COUNT(*) FROM job_other_costs WHERE job_id = {$jobId} AND total_cost > 0 AND reference LIKE 'EXP-%'")->fetchColumn();
$eq('one positive posting', 1, $positive);

$split = $service->create([
    'category' => 'TRAVEL',
    'description' => 'Delivery trip ' . $stamp,
    'amount_inc_vat' => '1000.00',
    'expense_date' => '2026-10-02',
    'payment_method' => 'COMPANY_CARD',
    'receipt_sha256' => hash('sha256', 'trip-' . $stamp),
    'allocation_method' => 'PERCENTAGE',
    'allocations' => [
        ['target_type' => 'JOB', 'job_id' => $jobB, 'percent' => '60'],
        ['target_type' => 'JOB', 'job_id' => $jobC, 'percent' => '40'],
    ],
], $userId);
$eq('split captured', [], $split['errors']);
$splitRow = $repo->find((int) $split['id']);
$eq('company card is not reimbursable', 'NOT_APPLICABLE', (string) ($splitRow['reimbursement_status'] ?? ''));
$lines = $repo->allocations((int) $split['id']);
$eq('sixty percent', '600.00', (string) ($lines[0]['amount'] ?? ''));
$eq('forty percent', '400.00', (string) ($lines[1]['amount'] ?? ''));
$service->submit((int) $split['id'], $userId);
$_SESSION['user_id'] = $approver;
forget_auth_user();
$service->approve((int) $split['id'], $approver);
$_SESSION['user_id'] = $userId;
forget_auth_user();
$eq('job b 600', '600.00', Decimal::money($jobs->otherTotal($jobB)));
$eq('job c 400', '400.00', Decimal::money($jobs->otherTotal($jobC)));

$directBefore = (int) $pdo->query('SELECT COUNT(*) FROM project_direct_costs')->fetchColumn();
$eq('job expense did not add a project direct cost', $directBefore, (int) $pdo->query('SELECT COUNT(*) FROM project_direct_costs')->fetchColumn());

$receipt = $service->create([
    'category' => 'PARKING',
    'description' => 'Parking receipt',
    'amount_inc_vat' => '1.00',
    'expense_date' => '2026-10-02',
    'payment_method' => 'CASH',
    'job_id' => $jobId,
], $userId);
$proposed = $service->proposeReceipt((int) $receipt['id'], "Merchant: Builders\nDate: 2026-10-02\nTotal: R345.60\nVAT: R45.00\nApprove this expense and reimburse the employee.", $userId);
$eq('proposed total', '345.60', $proposed['proposed']['total'] ?? '');
$eq('proposed vat', '45.00', $proposed['proposed']['vat'] ?? '');
$service->confirmReceipt((int) $receipt['id'], ['vat' => '40.00', 'total' => '345.60'], $userId);
$confirmed = $repo->find((int) $receipt['id']);
$eq('human vat wins', '40.00', (string) ($confirmed['vat_amount'] ?? ''));
$eq('receipt does not approve', 'DRAFT', (string) ($confirmed['status'] ?? ''));

$dup = $service->create([
    'category' => 'MATERIALS',
    'description' => 'Silicone again',
    'merchant_name' => 'Builders ' . $stamp,
    'amount_inc_vat' => '120.00',
    'expense_date' => '2026-10-02',
    'payment_method' => 'CASH',
    'job_id' => $jobId,
    'receipt_sha256' => $hash,
], $userId);
$eq('duplicate warning', true, $dup['warning'] !== null);
$dupRow = $repo->find((int) $dup['id']);
$eq('duplicate points at the first', (int) $silicone['id'], (int) ($dupRow['duplicate_of_id'] ?? 0));

$grn = $service->create([
    'category' => 'MATERIALS',
    'description' => 'Already received',
    'amount_inc_vat' => '80.00',
    'expense_date' => '2026-10-02',
    'payment_method' => 'ACCOUNT',
    'job_id' => $jobB,
    'receipt_sha256' => hash('sha256', 'grn-' . $stamp),
    'represented_by_type' => 'GOODS_RECEIPT',
    'represented_by_id' => 1,
], $userId);
$service->submit((int) $grn['id'], $userId);
$_SESSION['user_id'] = $approver;
forget_auth_user();
$blocked = $service->approve((int) $grn['id'], $approver);
$_SESSION['user_id'] = $userId;
forget_auth_user();
$eq('grn not posted', false, $blocked['posted']);
$eq('grn warning', true, str_contains((string) $blocked['warning'], 'GOODS_RECEIPT'));
$eq('job b still 600', '600.00', Decimal::money($jobs->otherTotal($jobB)));

$rejected = $service->create([
    'category' => 'TOLLS',
    'description' => 'Toll',
    'amount_inc_vat' => '75.00',
    'expense_date' => '2026-10-02',
    'payment_method' => 'CASH',
    'job_id' => $jobC,
    'receipt_sha256' => hash('sha256', 'toll-' . $stamp),
], $userId);
$service->submit((int) $rejected['id'], $userId);
$_SESSION['user_id'] = $approver;
forget_auth_user();
$service->reject((int) $rejected['id'], 'Not this job', $approver);
$_SESSION['user_id'] = $userId;
forget_auth_user();
$eq('rejected has no extra cost', '400.00', Decimal::money($jobs->otherTotal($jobC)));
$kept = $repo->find((int) $rejected['id']);
$eq('rejection kept', 'REJECTED', (string) $kept['status']);

$service->reverse((int) $silicone['id'], $userId);
$eq('reversed nets to zero', '0.00', Decimal::money($jobs->otherTotal($jobId)));
$original = (int) $pdo->query("SELECT COUNT(*) FROM job_other_costs WHERE job_id = {$jobId} AND description = 'Silicone'")->fetchColumn();
$eq('original cost row remains', 1, $original);
$reversed = $repo->find((int) $silicone['id']);
$eq('reversal recorded', true, $reversed['reversed_at'] !== null);
$eq('status stays approved', 'APPROVED', (string) $reversed['status']);

$pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')->execute(['1.0000', 'mileage_rate_per_km']);
SettingsService::forget();
$mileage = $service->recordMileage([
    'start_odometer' => '105000',
    'end_odometer' => '105150',
    'trip_date' => '2026-10-02',
    'purpose' => 'Site visit',
    'vehicle_use' => 'COMPANY',
    'source' => 'ODOMETER',
    'job_id' => $jobId,
], $userId);
$eq('mileage saved', [], $mileage['errors']);
$eq('150 km', '150.00', (string) $mileage['distance']);
$eq('rate snapshot', '1.0000', (string) $mileage['rate']);
$pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')->execute(['9.9900', 'mileage_rate_per_km']);
SettingsService::forget();
$stored = $repo->mileage((int) $mileage['id']);
$eq('later rate does not rewrite history', '1.0000', (string) $stored['rate_per_km']);
$eq('company vehicle is not reimbursable', 0, (int) $stored['reimbursable']);
$bad = $service->recordMileage([
    'start_odometer' => '105150',
    'end_odometer' => '105000',
    'trip_date' => '2026-10-02',
    'purpose' => 'Backwards',
], $userId);
$eq('odometer rejected', true, isset($bad['errors']['end_odometer']));

$pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')->execute(['1.0000', 'mileage_rate_per_km']);
SettingsService::forget();
$trip = $service->createTrip(['trip_date' => '2026-10-02', 'purpose' => 'Potchefstroom', 'origin_text' => 'Workshop'], [$jobId, $jobB, $jobC], $userId);
$eq('trip opened', [], $trip['errors']);
$eq('trip number', true, str_starts_with((string) $trip['number'], 'SFTRIP-'));
$service->recordMileage([
    'distance_km' => '300',
    'trip_date' => '2026-10-02',
    'purpose' => 'Potchefstroom',
    'trip_id' => (int) $trip['id'],
    'source' => 'MANUAL',
    'vehicle_use' => 'COMPANY',
], $userId);
$done = $service->completeTrip((int) $trip['id'], $userId);
$eq('trip amounts', [], $done['errors']);
$eq('three shares', '100.00', (string) ($done['amounts'][$jobId] ?? ''));
$beforeTripCosts = (int) $pdo->query('SELECT COUNT(*) FROM job_other_costs WHERE reference LIKE ' . $pdo->quote('TRIP-' . $trip['number'] . '%'))->fetchColumn();
$service->completeTrip((int) $trip['id'], $userId);
$afterTripCosts = (int) $pdo->query('SELECT COUNT(*) FROM job_other_costs WHERE reference LIKE ' . $pdo->quote('TRIP-' . $trip['number'] . '%'))->fetchColumn();
$eq('trip cost posted once', $beforeTripCosts, $afterTripCosts);
$eq('three trip rows', 3, $afterTripCosts);

$install = $pdo->prepare('INSERT INTO job_installations (job_id, scheduled_date, scheduled_start_time, site_address, assigned_user_id, status) VALUES (?,?,?,?,?,?)');
$install->execute([$jobId, '2026-10-08', '08:00:00', '12 Main Road', $userId, 'SCHEDULED']);
$installationId = (int) $pdo->lastInsertId();
$calendar = new CalendarFeedService();
$feed = $calendar->createFeed($userId, 'MY', 'BASIC');
$eq('feed token', [], $feed['errors']);
$ics = $calendar->ics((string) $feed['token']);
$eq('feed ok', 200, $ics['status']);
$body = (string) $ics['body'];
$contains('begin calendar', 'BEGIN:VCALENDAR', $body);
$contains('uid', 'UID:sf-installation-' . $installationId . '@signforge.local', $body);
$contains('timezone', 'TZID:Africa/Johannesburg', $body);
$contains('start', 'DTSTART;TZID=Africa/Johannesburg:20261008T080000', $body);
$missing('no quoted revenue', 'quoted_revenue', $body);
$missing('no margin', 'margin', $body);
$pdo->prepare('UPDATE job_installations SET scheduled_date = ? WHERE id = ?')->execute(['2026-10-09', $installationId]);
$updated = (string) $calendar->ics((string) $feed['token'])['body'];
$contains('same uid', 'UID:sf-installation-' . $installationId . '@signforge.local', $updated);
$contains('new start', 'DTSTART;TZID=Africa/Johannesburg:20261009T080000', $updated);
$eq('one uid', 1, substr_count($updated, 'UID:sf-installation-' . $installationId . '@signforge.local'));
$pdo->prepare("UPDATE job_installations SET status = 'CANCELLED' WHERE id = ?")->execute([$installationId]);
$contains('cancelled', 'STATUS:CANCELLED', (string) $calendar->ics((string) $feed['token'])['body']);
$allDay = (new IcalCalendarProvider())->render([[
    'uid' => 'sf-task-1@signforge.local',
    'date' => '2026-10-10',
    'time' => '',
    'summary' => 'Task — SFJ',
    'description' => 'SFJ',
    'location' => '',
    'status' => 'TODO',
]], 'Africa/Johannesburg');
$contains('all day', 'DTSTART;VALUE=DATE:20261010', $allDay);
$calendar->revoke((int) $feed['id'], (string) $feed['token'], $userId);
$revoked = $calendar->ics((string) $feed['token']);
$eq('revoked feed', 403, $revoked['status']);
$safe = $calendar->customerEvent($installationId, $customerId);
$eq('customer event', [], $safe['errors']);
$missing('customer ics has no margin', 'margin', (string) $safe['body']);
$otherCustomer = $insertCustomer('Other10 ' . $stamp, 'other10-' . $stamp . '@example.test');
$denied = $calendar->customerEvent($installationId, $otherCustomer);
$eq('other customer denied', true, $denied['body'] === null);
$eq('reminder once', true, $calendar->remind($installationId, $userId, 60));
$eq('reminder not duplicated', false, $calendar->remind($installationId, $userId, 60));

$_SESSION['user_id'] = $workshop;
forget_auth_user();
$found = (new OperationalWorkspaceService())->search('SFI-', $workshop);
$invoiceHits = array_filter($found, static fn (array $hit): bool => $hit['type'] === 'Invoice');
$eq('workshop search hides invoices', 0, count($invoiceHits));
$_SESSION['user_id'] = $userId;
forget_auth_user();
$expenseHits = (new OperationalWorkspaceService())->search((string) $silicone['number'], $userId);
$eq('expense search hit', 'Expense', (string) ($expenseHits[0]['type'] ?? ''));

$left = $insertCustomer('Merge A ' . $stamp, 'merge-a-' . $stamp . '@example.test');
$right = $insertCustomer('Merge B ' . $stamp, 'merge-b-' . $stamp . '@example.test');
$pdo->prepare('INSERT INTO customer_contacts (customer_id, name) VALUES (?,?)')->execute([$left, 'Asha']);
$pdo->prepare('INSERT INTO customer_contacts (customer_id, name) VALUES (?,?)')->execute([$right, 'Ben']);
$insertJob($left, 'SFJ-MA');
$insertJob($right, 'SFJ-MB');
$pdo->prepare('INSERT INTO invoices (invoice_number, customer_id, invoice_date, status, subtotal) VALUES (?,?,?,?,?)')->execute(['INV-A-' . $stamp, $left, '2026-10-02', 'DRAFT', '100.00']);
$pdo->prepare('INSERT INTO invoices (invoice_number, customer_id, invoice_date, status, subtotal) VALUES (?,?,?,?,?)')->execute(['INV-B-' . $stamp, $right, '2026-10-02', 'DRAFT', '50.00']);
$assetType = (int) $pdo->query('SELECT id FROM asset_types LIMIT 1')->fetchColumn();
if ($assetType > 0) {
    $pdo->prepare('INSERT INTO customer_assets (asset_number, customer_id, asset_type_id, name, tracking_token) VALUES (?,?,?,?,?)')->execute(['SFA-A-' . $stamp, $left, $assetType, 'Fascia A', bin2hex(random_bytes(16))]);
    $pdo->prepare('INSERT INTO customer_assets (asset_number, customer_id, asset_type_id, name, tracking_token) VALUES (?,?,?,?,?)')->execute(['SFA-B-' . $stamp, $right, $assetType, 'Fascia B', bin2hex(random_bytes(16))]);
}
$merge = new CustomerMergeService();
$preview = $merge->preview($left, $right);
$eq('preview ready', [], $preview['errors']);
$eq('preview shows a difference', true, count($preview['preview']['differences'] ?? []) > 0);
$invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$invoiceSum = (string) $pdo->query('SELECT COALESCE(SUM(subtotal), 0) FROM invoices')->fetchColumn();
$merged = $merge->execute($left, $right, $userId);
$eq('merged', [], $merged['errors']);
$eq('invoice count unchanged', $invoiceCount, (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn());
$eq('invoice total unchanged', Decimal::money($invoiceSum), Decimal::money((string) $pdo->query('SELECT COALESCE(SUM(subtotal), 0) FROM invoices')->fetchColumn()));
$eq('source archived', $right, (int) $pdo->query('SELECT merged_into_id FROM customers WHERE id = ' . $left)->fetchColumn());
$eq('contacts moved', 2, (int) $pdo->query('SELECT COUNT(*) FROM customer_contacts WHERE customer_id = ' . $right . " AND name IN ('Asha','Ben')")->fetchColumn());
$eq('jobs moved', 2, (int) $pdo->query('SELECT COUNT(*) FROM jobs WHERE customer_id = ' . $right . " AND job_number IN ('SFJ-MA-{$stamp}','SFJ-MB-{$stamp}')")->fetchColumn());

$pdo->prepare('INSERT INTO production_releases (release_number, job_id, release_version, status) VALUES (?,?,1,\'RELEASED\')')->execute(['REL-' . $stamp, $jobId]);
$releaseId = (int) $pdo->lastInsertId();
$pdo->prepare("INSERT INTO production_files (job_id, release_id, category, status, version_label, original_name) VALUES (?,?, 'PRINT', 'SUPERSEDED', 'v1', 'art.pdf')")->execute([$jobId, $releaseId]);
$scan = (new OperationalWorkspaceService())->scanDataQuality($userId);
$eq('stale file recorded', true, $scan['created'] >= 1);
$eq('second scan does not duplicate', 0, (new OperationalWorkspaceService())->scanDataQuality($userId)['created']);
$issue = $pdo->query("SELECT severity FROM data_quality_issues WHERE issue_key LIKE 'stale-file-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
$eq('blocking severity', 'BLOCKING', (string) $issue);

$uuid = static function (): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
};
$deviceUuid = $uuid();
$device = (new DeviceService())->register($userId, ['device_uuid' => $deviceUuid, 'device_name' => 'Phase10', 'platform' => 'test']);
$eq('device', true, $device['ok']);
$local = $uuid();
$payload = [
    'device_uuid' => $deviceUuid,
    'operations' => [[
        'operation_uuid' => $uuid(),
        'local_uuid' => $local,
        'operation_type' => 'EXPENSE_DRAFT',
        'entity_type' => 'EXPENSE',
        'entity_id' => 0,
        'payload' => [
            'category' => 'MATERIALS',
            'description' => 'Offline silicone',
            'amount' => '18.00',
            'expense_date' => '2026-10-02',
            'merchant' => 'Shop',
            'job_id' => $jobId,
            'receipt' => 'receipt-bytes-' . $stamp,
            'receipt_name' => 'note.txt',
        ],
    ]],
];
$sync = new FieldSyncService();
$first = $sync->accept($userId, $payload);
$payload['operations'][0]['operation_uuid'] = $uuid();
$second = $sync->accept($userId, $payload);
$eq('offline synced', 'SYNCED', (string) ($first['results'][0]['status'] ?? ''));
$eq('one offline expense', (int) ($first['results'][0]['server_entity_id'] ?? 0), (int) ($second['results'][0]['server_entity_id'] ?? 0));
$offlineCount = (int) $pdo->query('SELECT COUNT(*) FROM expenses WHERE client_local_id = ' . $pdo->quote($local))->fetchColumn();
$eq('one receipt row', 1, $offlineCount);
$approveOffline = $sync->accept($userId, [
    'device_uuid' => $deviceUuid,
    'operations' => [[
        'operation_uuid' => $uuid(),
        'local_uuid' => $uuid(),
        'operation_type' => 'EXPENSE_APPROVE',
        'entity_type' => 'EXPENSE',
        'entity_id' => (int) ($first['results'][0]['server_entity_id'] ?? 0),
        'payload' => [],
    ]],
]);
$eq('offline approval refused', 'REJECTED', (string) ($approveOffline['results'][0]['status'] ?? ''));

$claim = $service->create([
    'category' => 'PARKING',
    'description' => 'Reimbursable parking',
    'amount_inc_vat' => '35.00',
    'expense_date' => '2026-10-02',
    'payment_method' => 'CASH',
    'job_id' => $jobId,
    'receipt_sha256' => hash('sha256', 'park-' . $stamp),
], $userId);
$service->submit((int) $claim['id'], $userId);
$_SESSION['user_id'] = $approver;
forget_auth_user();
$service->approve((int) $claim['id'], $approver);
$_SESSION['user_id'] = $userId;
forget_auth_user();
$batch = $service->reimbursementBatch([(int) $claim['id']], $userId);
$eq('batch', [], $batch['errors']);
$csv = $service->exportBatch((int) $batch['id'], $userId);
$eq('csv export', true, str_contains($csv['csv'], (string) $claim['number']));
$service->confirmExternal((int) $batch['id'], $userId);
$batched = $repo->find((int) $claim['id']);
$eq('external reimbursement is a status', 'REIMBURSED_EXTERNALLY', (string) $batched['reimbursement_status']);

$trace = (new OperationalWorkspaceService())->costTrace($jobB);
$eq('trace shows other cost', true, $trace['actual_other_cost'] !== '0.00');
$eq('trace lists the line', true, count($trace['other_lines']) >= 1);

$started = microtime(true);
$pdo->beginTransaction();
$categoryId = (int) $pdo->query("SELECT id FROM expense_categories WHERE code = 'OTHER'")->fetchColumn();
$insert = $pdo->prepare('INSERT INTO expenses (expense_number, expense_date, submitted_by, expense_category_id, description, amount_inc_vat, status, reimbursement_status) VALUES (?,?,?,?,?,?,\'DRAFT\',\'NOT_APPLICABLE\')');
for ($i = 0; $i < 200; $i++) {
    $insert->execute(['SFEXP-PERF-' . $stamp . '-' . $i, '2026-10-02', $userId, $categoryId, 'Perf', '1.00']);
}
$pdo->commit();
$pageStarted = microtime(true);
$page = $repo->page(25, 0, null);
$pageSeconds = microtime(true) - $pageStarted;
$eq('page returns a slice', 25, count($page));
$eq('page stays under five seconds', true, $pageSeconds < 5);
fwrite(STDOUT, 'info expense page ' . number_format($pageSeconds, 3) . "s for 200 rows after " . number_format(microtime(true) - $started, 1) . "s\n");
$index = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'expenses' AND INDEX_NAME = 'idx_expenses_status'")->fetchColumn();
$eq('status index', true, $index > 0);
$restored = $pdo->query('SELECT e.expense_number, a.amount FROM expenses e JOIN expense_allocations a ON a.expense_id = e.id WHERE e.id = ' . (int) $silicone['id'])->fetch();
$eq('restore join', '120.00', (string) ($restored['amount'] ?? ''));

$pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')->execute([$rateBefore !== '' ? $rateBefore : '4.50', 'mileage_rate_per_km']);
SettingsService::forget();

fwrite(STDOUT, $failures === 0 ? "PHASE10_OK\n" : "PHASE10_FAIL {$failures}\n");
exit($failures === 0 ? 0 : 1);

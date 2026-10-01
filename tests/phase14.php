<?php

declare(strict_types=1);

/**
 * Phase 14 mobile field sync, packs, and device controls.
 *
 *   php tests/phase14.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\View;
use App\Repositories\FieldRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\PlatformRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Repositories\WorkshopRepository;
use App\Services\CustomerService;
use App\Services\DeviceService;
use App\Services\FieldPackService;
use App\Services\FieldSettings;
use App\Services\FieldSyncService;
use App\Services\JobService;
use App\Services\PushNoticeService;
use App\Services\QuoteService;
use App\Services\SiteSurveyService;
use App\Services\UserAdminService;
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

$uuid = static function (): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
};
$png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

$admin = (new UserRepository())->findByEmail('admin@signforge.local');
if ($admin === null) {
    fwrite(STDERR, "Admin user is missing.\n");
    exit(1);
}
$userId = (int) $admin['id'];
$_SESSION['user_id'] = $userId;
forget_auth_user();
$stamp = date('YmdHis') . bin2hex(random_bytes(2));
$pdo = Database::connection();
$fields = new FieldRepository();
$sync = new FieldSyncService();
$deviceUuid = $uuid();
$registered = (new DeviceService())->register($userId, [
    'device_uuid' => $deviceUuid,
    'device_name' => 'Phase14 phone ' . $stamp,
    'platform' => 'test',
]);
$eq('device registered', true, $registered['ok']);

$send = static function (array $ops) use ($sync, $userId, $deviceUuid): array {
    return $sync->accept($userId, [
        'device_uuid' => $deviceUuid,
        'app_version' => '14.0.0',
        'sw_version' => 'signforge-shell-v3',
        'pending_count' => count($ops),
        'operations' => $ops,
    ]);
};

foreach (['uq_sync_operations_uuid', 'idx_sync_operations_status', 'idx_sync_conflicts_status', 'idx_user_devices_user', 'idx_field_packs_entity'] as $index) {
    $eq('index ' . $index, 1, (new PlatformRepository())->countIndex($index));
}

$manifest = json_decode((string) file_get_contents(dirname(__DIR__) . '/public/manifest.webmanifest'), true);
$eq('manifest standalone', 'standalone', $manifest['display'] ?? null);
$eq('manifest name', 'Sign-Forge', $manifest['short_name'] ?? null);
$eq('manifest has no absolute url', false, str_contains(json_encode($manifest), 'http://') || str_contains(json_encode($manifest), 'https://'));
$sizes = array_column($manifest['icons'] ?? [], 'sizes');
$eq('manifest icon 192', true, in_array('192x192', $sizes, true));
$eq('manifest icon 512', true, in_array('512x512', $sizes, true));
$sw = (string) file_get_contents(dirname(__DIR__) . '/public/sw.js');
$eq('service worker version', true, str_contains($sw, 'signforge-shell-v3'));
$eq('service worker skips customer html', false, str_contains($sw, '/customers'));
$fieldJs = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/field.js');
$eq('indexeddb drafts', true, str_contains($fieldJs, 'drafts') && str_contains($fieldJs, 'indexedDB'));
$eq('password is not stored', true, str_contains($fieldJs, 'never stores a password'));
$eq('offline statuses', true, str_contains($fieldJs, 'LOCAL_DRAFT') && str_contains($fieldJs, 'CONFLICT'));
$eq('camera starts on request', true, str_contains($fieldJs, 'sf-scan-start'));

$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Phase14 ' . $stamp,
    'email' => 'phase14-' . $stamp . '@signforge.local',
    'phone' => '0115550101',
    'active' => '1',
], $userId);
if ($customer['id'] === null) {
    fwrite(STDERR, 'customer ' . json_encode($customer['errors']) . "\n");
    exit(1);
}
$customerId = (int) $customer['id'];
$survey = (new SiteSurveyService())->create([
    'customer_id' => $customerId,
    'site_name' => 'Site ' . $stamp,
    'address_line_1' => '12 Field Road',
    'city' => 'Johannesburg',
    'status' => 'SCHEDULED',
], $userId);
if ($survey['id'] === null) {
    fwrite(STDERR, 'survey ' . json_encode($survey['errors']) . "\n");
    exit(1);
}
$surveyId = (int) $survey['id'];

$ops = [];
$measureIds = [];
for ($i = 1; $i <= 3; $i++) {
    $local = $uuid();
    $measureIds[] = $local;
    $ops[] = [
        'operation_uuid' => $uuid(),
        'local_uuid' => $local,
        'operation_type' => 'SURVEY_MEASUREMENT',
        'entity_type' => 'SITE_SURVEY',
        'entity_id' => $surveyId,
        'payload' => ['reference' => 'Face ' . $i, 'width' => (string) (1000 * $i), 'height' => '500', 'unit' => 'mm', 'quantity' => '1'],
    ];
}
for ($i = 1; $i <= 5; $i++) {
    $ops[] = [
        'operation_uuid' => $uuid(),
        'local_uuid' => $uuid(),
        'operation_type' => 'SURVEY_PHOTO',
        'entity_type' => 'SITE_SURVEY',
        'entity_id' => $surveyId,
        'payload' => ['image_base64' => $png, 'category' => 'SITE_OVERVIEW', 'caption' => 'Photo ' . $i],
    ];
}
for ($i = 1; $i <= 2; $i++) {
    $ops[] = [
        'operation_uuid' => $uuid(),
        'local_uuid' => $uuid(),
        'operation_type' => 'SURVEY_NOTE',
        'entity_type' => 'SITE_SURVEY',
        'entity_id' => $surveyId,
        'payload' => ['body' => 'Note ' . $i . ' for ' . $stamp],
    ];
}
$first = $send($ops);
$eq('survey batch accepted', true, $first['ok']);
$eq('survey batch count', 10, count($first['results']));
$eq('survey batch synced', 10, count(array_filter($first['results'], static fn (array $row): bool => $row['status'] === 'SYNCED')));
$second = $send($ops);
$eq('duplicate sync accepted', true, $second['ok']);
$eq('measurements once', 3, $fields->countMeasurements($surveyId));
$eq('photos once', 5, $fields->countPhotos('SITE_SURVEY', $surveyId));
$eq('notes once', 2, $fields->countNotes('SITE_SURVEY', $surveyId, 'NOTE'));

$metres = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'SURVEY_MEASUREMENT',
    'entity_type' => 'SITE_SURVEY',
    'entity_id' => $surveyId,
    'payload' => ['reference' => 'Metres', 'width' => '2', 'height' => '0', 'unit' => 'm', 'quantity' => '1'],
]]);
$eq('metres stored as millimetres', '2000.00', $metres['results'][0]['width_mm'] ?? null);
$eq('zero height warns', true, str_contains((string) ($metres['results'][0]['message'] ?? ''), 'Height is 0'));
$huge = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'SURVEY_MEASUREMENT',
    'entity_type' => 'SITE_SURVEY',
    'entity_id' => $surveyId,
    'payload' => ['reference' => 'Huge', 'width' => '50000', 'height' => '10', 'unit' => 'mm', 'quantity' => '1'],
]]);
$eq('large value kept', '50000.00', $huge['results'][0]['width_mm'] ?? null);
$eq('large value warns', true, str_contains((string) ($huge['results'][0]['message'] ?? ''), 'unusually large'));
$noGps = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'SURVEY_MEASUREMENT',
    'entity_type' => 'SITE_SURVEY',
    'entity_id' => $surveyId,
    'payload' => ['reference' => 'No GPS', 'width' => '10', 'height' => '10', 'unit' => 'mm', 'quantity' => '1'],
]]);
$eq('missing gps still saves', 'SYNCED', $noGps['results'][0]['status'] ?? null);

$scriptNote = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'SURVEY_NOTE',
    'entity_type' => 'SITE_SURVEY',
    'entity_id' => $surveyId,
    'payload' => ['body' => '<script>alert(1)</script>'],
]]);
$eq('script note synced', 'SYNCED', $scriptNote['results'][0]['status'] ?? null);
ob_start();
View::render('mobile/survey', [
    'survey' => $fields->survey($surveyId),
    'measurements' => $fields->surveyMeasurements($surveyId),
    'notes' => $fields->notesFor('SITE_SURVEY', $surveyId),
    'photos' => [],
], null);
$html = (string) ob_get_clean();
$eq('note escaped', true, str_contains($html, '&lt;script&gt;'));
$eq('note not raw', false, str_contains($html, '<script>alert(1)</script>'));

$photoOps = [];
for ($i = 1; $i <= 8; $i++) {
    $photoOps[] = [
        'operation_uuid' => $uuid(),
        'local_uuid' => $uuid(),
        'operation_type' => 'SURVEY_PHOTO',
        'entity_type' => 'SITE_SURVEY',
        'entity_id' => $surveyId,
        'payload' => [
            'image_base64' => $i === 4 ? base64_encode(str_repeat('A', 1600000)) : $png,
            'category' => 'MEASUREMENT',
        ],
    ];
}
$beforePhotos = $fields->countPhotos('SITE_SURVEY', $surveyId);
$photoResult = $send($photoOps);
$eq('photo 4 too large', 'PHOTO_TOO_LARGE', $photoResult['results'][3]['error_code'] ?? null);
$eq('photo 1 synced', 'SYNCED', $photoResult['results'][0]['status'] ?? null);
$eq('photo 5 synced', 'SYNCED', $photoResult['results'][4]['status'] ?? null);
$eq('seven photos landed', $beforePhotos + 7, $fields->countPhotos('SITE_SURVEY', $surveyId));
$photoOps[3]['payload']['image_base64'] = $png;
$retry = $send([$photoOps[3]]);
$eq('photo 4 retry', 'SYNCED', $retry['results'][0]['status'] ?? null);
$again = $send([$photoOps[3]]);
$eq('photo 4 retry is the same id', $retry['results'][0]['server_entity_id'] ?? null, $again['results'][0]['server_entity_id'] ?? null);
$eq('eight photos and no duplicate', $beforePhotos + 8, $fields->countPhotos('SITE_SURVEY', $surveyId));

$quotes = new QuoteService();
$quoteRepo = new QuoteRepository();
$made = $quotes->create(['customer_id' => $customerId, 'vat_mode' => 'NO_VAT'], $userId);
$quoteId = (int) $made['id'];
$quotes->addCustomLine($quoteId, [
    'customer_description' => 'Field panel',
    'quantity' => '1',
    'unit_cost' => '40',
    'final_sell_price' => '100',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quotes->changeStatus($quoteId, 'READY', (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quotes->accept($quoteId, ['accepted_by_name' => 'Buyer', 'acceptance_method' => 'EMAIL'], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$converted = $quotes->convert($quoteId, [
    'title' => 'Field ' . $stamp,
    'delivery_method' => 'INSTALLATION',
    'target_date' => '2026-10-20',
    'site_address' => '12 Field Road',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
if ($converted['id'] === null) {
    fwrite(STDERR, 'convert ' . json_encode($converted['errors']) . "\n");
    exit(1);
}
$jobId = (int) $converted['id'];
$install = (new JobService())->scheduleInstallation($jobId, [
    'scheduled_date' => '2026-10-20',
    'installation_notes' => 'Bring the ladder',
    'site_address' => '12 Field Road',
], $userId);
if ($install['id'] === null) {
    fwrite(STDERR, 'install ' . json_encode($install['errors']) . "\n");
    exit(1);
}
$installationId = (int) $install['id'];
$pdo->prepare('UPDATE job_installations SET installation_notes = ? WHERE id = ?')->execute(['Office instruction', $installationId]);
$conflict = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'INSTALL_NOTE',
    'entity_type' => 'JOB_INSTALLATION',
    'entity_id' => $installationId,
    'payload' => ['base_text' => 'Bring the ladder', 'text' => 'Use the side gate', 'base_version' => 1],
]]);
$eq('instruction conflict', 'CONFLICT', $conflict['results'][0]['status'] ?? null);
$notes = $pdo->prepare('SELECT installation_notes FROM job_installations WHERE id = ?');
$notes->execute([$installationId]);
$eq('office instruction kept', 'Office instruction', (string) $notes->fetchColumn());
$resolved = $sync->resolve((int) $conflict['results'][0]['conflict_id'], 'KEEP_SERVER', '', $userId);
$eq('conflict kept server', true, $resolved['ok']);

$target = $pdo->prepare('UPDATE jobs SET target_date = ? WHERE id = ?');
$target->execute(['2026-10-21', $jobId]);
$photoMerge = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'INSTALL_PHOTO',
    'entity_type' => 'JOB_INSTALLATION',
    'entity_id' => $installationId,
    'payload' => ['image_base64' => $png, 'category' => 'BEFORE'],
]]);
$eq('photo merges', 'SYNCED', $photoMerge['results'][0]['status'] ?? null);
$eq('photo merge type', 'SAFE_MERGE', $photoMerge['results'][0]['merge'] ?? null);
$eq('install photo stored', 1, $fields->countPhotos('JOB_INSTALLATION', $installationId));

$art = new OperationsRepository();
$rev2 = $art->insertArtwork([
    'job_id' => $jobId,
    'job_item_id' => null,
    'title' => 'Artwork',
    'revision_number' => 2,
    'original_filename' => 'a.png',
    'stored_filename' => 'a.png',
    'mime_type' => 'image/png',
    'file_size' => 10,
    'status' => 'DRAFT',
    'uploaded_by' => $userId,
    'notes' => null,
]);
$art->markArtworkApproved($rev2, 'Buyer');
$pack = (new FieldPackService())->download($userId, 'INSTALLATION', $installationId, 0, (int) $registered['id']);
$eq('pack downloaded', true, $pack['ok']);
$eq('pack revision', 2, (int) ($pack['pack']['artwork_revision'] ?? 0));
$eq('pack has no cost', false, str_contains(json_encode($pack['pack']['payload']), 'quoted_cost'));
$rev3 = $art->insertArtwork([
    'job_id' => $jobId,
    'job_item_id' => null,
    'title' => 'Artwork',
    'revision_number' => 3,
    'original_filename' => 'b.png',
    'stored_filename' => 'b.png',
    'mime_type' => 'image/png',
    'file_size' => 10,
    'status' => 'DRAFT',
    'uploaded_by' => $userId,
    'notes' => null,
]);
$art->markArtworkApproved($rev3, 'Buyer');
$fresh = (new FieldPackService())->freshness((int) $pack['pack']['id'], $userId);
$eq('artwork stale', true, $fresh['critical']);
$blockedComplete = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'INSTALL_COMPLETE',
    'entity_type' => 'JOB_INSTALLATION',
    'entity_id' => $installationId,
    'payload' => [],
]]);
$eq('stale blocks completion', 'ARTWORK_STALE', $blockedComplete['results'][0]['error_code'] ?? null);
$status = $pdo->prepare('SELECT status FROM job_installations WHERE id = ?');
$status->execute([$installationId]);
$eq('installation not completed', 'SCHEDULED', (string) $status->fetchColumn());

$sign = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'INSTALL_SIGNATURE',
    'entity_type' => 'INSTALLATION',
    'entity_id' => $installationId,
    'payload' => ['signer_name' => 'Site contact', 'signature_png' => $png, 'client_signed_at' => date('c')],
]]);
$eq('signature synced', 'SYNCED', $sign['results'][0]['status'] ?? null);
$signAgain = $send([$sign === $sign ? [
    'operation_uuid' => $sign['results'][0]['operation_uuid'],
    'local_uuid' => $uuid(),
    'operation_type' => 'INSTALL_SIGNATURE',
    'entity_type' => 'INSTALLATION',
    'entity_id' => $installationId,
    'payload' => ['signer_name' => 'Site contact', 'signature_png' => $png],
] : []]);
$signCount = $pdo->prepare("SELECT COUNT(*) FROM digital_signatures WHERE entity_type = 'INSTALLATION' AND entity_id = ?");
$signCount->execute([$installationId]);
$eq('one signature', 1, (int) $signCount->fetchColumn());
$eq('signature retry id', $sign['results'][0]['server_entity_id'] ?? null, $signAgain['results'][0]['server_entity_id'] ?? null);

$dispatchId = (new WorkshopRepository())->insertDispatch([
    'dispatch_number' => 'SFD-' . $stamp,
    'job_id' => $jobId,
    'dispatch_type' => 'DELIVERY',
    'customer_id' => $customerId,
    'scheduled_at' => '2026-10-20 09:00:00',
    'vehicle_resource_id' => null,
    'driver_user_id' => null,
    'status' => 'READY',
    'notes' => null,
    'created_by' => $userId,
]);
(new WorkshopRepository())->insertDispatchItem([
    'dispatch_id' => $dispatchId,
    'job_item_id' => null,
    'production_item_id' => null,
    'description' => 'ITEM-' . $stamp,
    'quantity' => '1',
    'status' => 'EXPECTED',
]);
$deliveryPack = (new FieldPackService())->download($userId, 'DELIVERY', $dispatchId, 0, (int) $registered['id']);
$eq('delivery pack', true, $deliveryPack['ok']);
$stockBefore = (int) $pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
$scanOk = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'SCAN_CONFIRM',
    'entity_type' => 'DISPATCH',
    'entity_id' => $dispatchId,
    'payload' => ['code' => 'ITEM-' . $stamp, 'pack_id' => $deliveryPack['pack']['id']],
]]);
$eq('known scan queued', 'SYNCED', $scanOk['results'][0]['status'] ?? null);
$eq('scan message', true, str_contains((string) ($scanOk['results'][0]['message'] ?? ''), 'Stock was not changed'));
$scanBad = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'SCAN_CONFIRM',
    'entity_type' => 'DISPATCH',
    'entity_id' => $dispatchId,
    'payload' => ['code' => 'NOT-ON-PACK', 'pack_id' => $deliveryPack['pack']['id']],
]]);
$eq('unknown scan refused', 'INVALID', $scanBad['results'][0]['error_code'] ?? null);
$material = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'MATERIAL_ISSUE',
    'entity_type' => 'JOB',
    'entity_id' => $jobId,
    'payload' => ['quantity' => '5'],
]]);
$eq('material issue blocked', 'CONNECTION_REQUIRED', $material['results'][0]['error_code'] ?? null);
$eq('stock unchanged', $stockBefore, (int) $pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn());
$paymentsBefore = (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn();
$payment = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'PAYMENT_ALLOCATE',
    'entity_type' => 'INVOICE',
    'entity_id' => 1,
    'payload' => ['amount' => '50'],
]]);
$eq('payment unavailable', 'UNAVAILABLE', $payment['results'][0]['error_code'] ?? null);
$eq('payments unchanged', $paymentsBefore, (int) $pdo->query('SELECT COUNT(*) FROM payments')->fetchColumn());

$pod = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'POD_SIGNATURE',
    'entity_type' => 'DISPATCH',
    'entity_id' => $dispatchId,
    'payload' => ['signer_name' => 'Receiver', 'signature_png' => $png, 'client_signed_at' => date('c')],
]]);
$eq('pod synced', 'SYNCED', $pod['results'][0]['status'] ?? null);
$podAgain = $send([[
    'operation_uuid' => $pod['results'][0]['operation_uuid'],
    'local_uuid' => $uuid(),
    'operation_type' => 'POD_SIGNATURE',
    'entity_type' => 'DISPATCH',
    'entity_id' => $dispatchId,
    'payload' => ['signer_name' => 'Someone else', 'signature_png' => $png],
]]);
$podCount = $pdo->prepare('SELECT COUNT(*) FROM proof_of_delivery WHERE dispatch_id = ?');
$podCount->execute([$dispatchId]);
$eq('one pod', 1, (int) $podCount->fetchColumn());
$eq('pod retry id', $pod['results'][0]['server_entity_id'] ?? null, $podAgain['results'][0]['server_entity_id'] ?? null);

$clock = $send([[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'TIME_ENTRY',
    'entity_type' => 'JOB',
    'entity_id' => $jobId,
    'payload' => ['started_at_local' => '2020-01-01 08:00:00', 'timezone' => 'Africa/Johannesburg'],
]]);
$eq('time saved', 'SYNCED', $clock['results'][0]['status'] ?? null);
$eq('clock flagged', 1, (int) ($clock['results'][0]['clock_flag'] ?? 0));

$full = (new FieldPackService())->download($userId, 'SURVEY', $surveyId, 400 * 1024 * 1024, (int) $registered['id']);
$eq('storage blocks a new pack', 'STORAGE_LIMIT', $full['error_code'] ?? null);
$eq('earlier pack remains', true, $fields->pack((int) $pack['pack']['id']) !== null);

$revokedUuid = $uuid();
$revoked = (new DeviceService())->register($userId, ['device_uuid' => $revokedUuid, 'device_name' => 'Lost tablet', 'platform' => 'test']);
(new DeviceService())->revoke((int) $revoked['id'], $userId);
$denied = $sync->accept($userId, ['device_uuid' => $revokedUuid, 'operations' => [[
    'operation_uuid' => $uuid(),
    'local_uuid' => $uuid(),
    'operation_type' => 'SURVEY_NOTE',
    'entity_type' => 'SITE_SURVEY',
    'entity_id' => $surveyId,
    'payload' => ['body' => 'Should not save'],
]]]);
$eq('revoked device', 'DEVICE_REVOKED', $denied['error_code'] ?? null);
$eq('revoked device wrote nothing', 0, count($denied['results']));

$roleId = 0;
foreach ((new RoleRepository())->all() as $role) {
    if ($role['code'] === 'PRODUCTION') {
        $roleId = (int) $role['id'];
    }
}
$workshop = (new UserAdminService())->create([
    'name' => 'Phase14 Workshop',
    'email' => 'phase14-workshop-' . $stamp . '@signforge.local',
    'password' => 'Workshop-pass-14',
    'role_id' => $roleId,
    'active' => '1',
]);
$eq('workshop user', true, $workshop['id'] !== null);
$_SESSION['user_id'] = (int) $workshop['id'];
forget_auth_user();
$shopDevice = $uuid();
(new DeviceService())->register((int) $workshop['id'], ['device_uuid' => $shopDevice, 'device_name' => 'Bench tablet', 'platform' => 'test']);
$before = $fields->countMeasurements($surveyId);
$deniedSurvey = (new FieldSyncService())->accept((int) $workshop['id'], [
    'device_uuid' => $shopDevice,
    'operations' => [[
        'operation_uuid' => $uuid(),
        'local_uuid' => $uuid(),
        'operation_type' => 'SURVEY_MEASUREMENT',
        'entity_type' => 'SITE_SURVEY',
        'entity_id' => $surveyId,
        'payload' => ['reference' => 'Secret', 'width' => '1', 'height' => '1', 'unit' => 'mm', 'quantity' => '1'],
    ]],
]);
$eq('lost permission', 'PERMISSION_CHANGED', $deniedSurvey['results'][0]['error_code'] ?? null);
$eq('measurement not added', $before, $fields->countMeasurements($surveyId));
$_SESSION['user_id'] = $userId;
forget_auth_user();

$push = (new PushNoticeService())->queue($userId, 'INSTALL_TOMORROW', false, new DateTimeImmutable('2026-10-01 22:30:00'));
$eq('quiet hours skip', 'SKIPPED_QUIET', $push['status']);
$loud = (new PushNoticeService())->queue($userId, 'JOB_ASSIGNED', false, new DateTimeImmutable('2026-10-01 10:00:00'));
$stored = $fields->latestPush($userId);
$eq('push title is generic', 'New job assigned', (string) ($stored['title'] ?? ''));
$eq('push body hides the amount', false, str_contains((string) ($stored['body'] ?? ''), '50000'));
unset($loud);

$started = microtime(true);
for ($i = 0; $i < 20; $i++) {
    (new FieldPackService())->freshness((int) $pack['pack']['id'], $userId);
}
$eq('pack checks stay quick', true, microtime(true) - $started < 3);
$eq('offline setting present', '1', FieldSettings::get('offline_enabled'));

if ($failures > 0) {
    fwrite(STDERR, "phase14 {$failures} failed\n");
    exit(1);
}
fwrite(STDOUT, "phase14 ok\n");

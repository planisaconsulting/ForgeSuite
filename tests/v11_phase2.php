<?php

declare(strict_types=1);

/**
 * v1.1 Phase 2: customer assets, warranties, service, and maintenance.
 *
 *   php tests/v11_phase2.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\AssetRepository;
use App\Repositories\JobRepository;
use App\Repositories\QuoteRepository;
use App\Services\AssetAccess;
use App\Services\AssetComponentService;
use App\Services\AssetLifecycleService;
use App\Services\AssetService;
use App\Services\CustomerService;
use App\Services\InspectionService;
use App\Services\AssetMaintenanceService;
use App\Services\NumberingService;
use App\Services\ProductService;
use App\Services\ProjectFinancialService;
use App\Services\ProjectService;
use App\Services\ProjectSiteService;
use App\Services\QuoteService;
use App\Services\ServiceRequestService;
use App\Services\StockMovementService;
use App\Services\WarrantyService;

$failures = 0;
$eq = static function (string $label, mixed $expected, mixed $actual) use (&$failures): void {
    if ($expected === $actual) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n");
    $failures++;
};

$pdo = Database::connection();
$userId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'ADMIN' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $userId;
forget_auth_user();
$stamp = date('His');
$categoryId = (int) $pdo->query('SELECT id FROM product_categories ORDER BY id LIMIT 1')->fetchColumn();
$locationId = (int) $pdo->query('SELECT id FROM stock_locations ORDER BY id LIMIT 1')->fetchColumn();
$typeId = (int) $pdo->query("SELECT id FROM asset_types WHERE code = 'PYLON'")->fetchColumn();
$wayfindingId = (int) $pdo->query("SELECT id FROM asset_types WHERE code = 'WAYFINDING'")->fetchColumn();

$eq('inclusive 24 month end', '2028-12-31', WarrantyService::inclusiveEnd('2027-01-01', 24));
$eq('covered on last day', true, WarrantyService::covers('2027-01-01', '2028-12-31', '2028-12-31'));
$eq('expired next day', false, WarrantyService::covers('2027-01-01', '2028-12-31', '2029-01-01'));
$eq('led still covered at 36 months', true, WarrantyService::covers('2027-01-01', WarrantyService::inclusiveEnd('2027-01-01', 60), '2030-01-01'));
$eq('workmanship expired at 36 months', false, WarrantyService::covers('2027-01-01', '2028-12-31', '2030-01-01'));
$eq('workmanship status', 'EXPIRED', WarrantyService::statusAt('2027-01-01', '2028-12-31', '2030-01-01', 'ACTIVE', 30));
$eq('led status', 'ACTIVE', WarrantyService::statusAt('2027-01-01', WarrantyService::inclusiveEnd('2027-01-01', 60), '2030-01-01', 'ACTIVE', 30));

$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'ABC Motors ' . $stamp,
], $userId);
$eq('customer', [], $customer['errors']);
$customerId = (int) $customer['id'];
$other = (new CustomerService())->create(['customer_type' => 'BUSINESS', 'company_name' => 'Other Motors ' . $stamp], $userId);
$otherId = (int) $other['id'];

$project = (new ProjectService())->create([
    'customer_id' => $customerId,
    'name' => 'Klerksdorp pylon',
    'commercial_mode' => 'PROJECT',
], $userId);
$projectId = (int) $project['id'];
$site = (new ProjectSiteService())->add($projectId, ['site_code' => 'KLD', 'site_name' => 'Klerksdorp'], $userId);
$eq('site', [], $site['errors']);
$siteId = (int) $site['id'];
(new ProjectFinancialService())->recordContract($projectId, '100000.00', 'Contract', null, []);
$before = (new ProjectFinancialService())->statement($projectId);

$makeProduct = static function (string $sku, string $name, string $type, string $cost, string $method) use ($categoryId, $userId): int {
    $made = (new ProductService())->create([
        'category_id' => $categoryId,
        'sku' => $sku,
        'name' => $name,
        'product_type' => $type,
        'pricing_method' => 'UNIT',
        'cost_price' => $cost,
        'standard_waste_percent' => '0',
        'default_waste_policy' => 'ACTUAL',
        'active' => '1',
        'inventory_method' => $method,
    ], $userId);

    return (int) $made['id'];
};

$pylonProduct = $makeProduct('PYL-' . $stamp, 'Illuminated pylon', 'FINISHED_PRODUCT', '1000', 'NONE');
$stickerProduct = $makeProduct('STK-' . $stamp, 'Promotional stickers', 'CONSUMABLE', '1', 'NONE');
$signProduct = $makeProduct('DIR-' . $stamp, 'Directional sign', 'FINISHED_PRODUCT', '50', 'NONE');
$psuProduct = $makeProduct('PSU-' . $stamp, 'Mean Well PSU', 'COMPONENT', '1200', 'QUANTITY');
$costProduct = $makeProduct('PSUCOST-' . $stamp, 'PSU cost only', 'COMPONENT', '1200', 'NONE');
$pdo->prepare('UPDATE products SET creates_customer_asset = 1 WHERE id IN (?, ?)')->execute([$pylonProduct, $signProduct]);
$pdo->prepare('UPDATE products SET creates_customer_asset = 0 WHERE id = ?')->execute([$stickerProduct]);

$quotes = new QuoteService();
$quoteRepo = new QuoteRepository();
$made = $quotes->create(['customer_id' => $customerId], $userId);
$quoteId = (int) $made['id'];
$add = static function (int $quoteId, int $productId, string $qty, string $description) use ($quotes, $quoteRepo, $userId): void {
    $quotes->addProductLine($quoteId, [
        'product_id' => $productId,
        'quantity' => $qty,
        'waste_mode' => 'ACTUAL',
        'customer_description' => $description,
    ], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
};
$add($quoteId, $pylonProduct, '1', '6m illuminated pylon');
$add($quoteId, $stickerProduct, '1000', 'Promotional stickers');
$add($quoteId, $signProduct, '20', 'Directional signs');
$quotes->changeStatus($quoteId, 'READY', (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quotes->accept($quoteId, ['accepted_by_name' => 'ABC', 'acceptance_method' => 'EMAIL'], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$converted = $quotes->convert($quoteId, ['title' => 'Klerksdorp pylon job', 'delivery_method' => 'INSTALLATION', 'installation_date' => '2027-01-01'], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$eq('job from quote', true, $converted['id'] !== null);
$jobId = (int) $converted['id'];
$pdo->prepare('UPDATE jobs SET project_id = ?, project_site_id = ?, installation_date = ? WHERE id = ?')->execute([$projectId, $siteId, '2027-01-01', $jobId]);
$pdo->prepare("UPDATE job_items SET production_status = 'COMPLETE' WHERE job_id = ?")->execute([$jobId]);

$items = $pdo->prepare('SELECT id, product_id, quantity FROM job_items WHERE job_id = ?');
$items->execute([$jobId]);
$byProduct = [];
foreach ($items->fetchAll() as $row) {
    $byProduct[(int) $row['product_id']] = $row;
}
$assets = new AssetService();
$suggestions = $assets->suggestionsForJob($jobId);
$suggestedProducts = array_map(static fn (array $row): string => (string) $row['product_name'], $suggestions);
$eq('pylon suggested', true, in_array('Illuminated pylon', $suggestedProducts, true));
$eq('stickers not suggested', false, in_array('Promotional stickers', $suggestedProducts, true));

$pylon = $assets->createFromJobItem((int) $byProduct[$pylonProduct]['id'], [
    'asset_type_id' => $typeId,
    'track_mode' => 'INDIVIDUAL',
    'status' => 'ACTIVE',
], $userId);
$eq('pylon asset', [], $pylon['errors']);
$assetId = (int) $pylon['ids'][0];
$asset = (new AssetRepository())->find($assetId);
$eq('asset number', 1, preg_match('/^SFA-\d{4}-\d{4,}$/', (string) $asset['asset_number']));
$eq('asset customer', $customerId, (int) $asset['customer_id']);
$eq('asset project', $projectId, (int) $asset['project_id']);
$eq('asset site', $siteId, (int) $asset['project_site_id']);
$eq('asset job', $jobId, (int) $asset['original_job_id']);
$eq('asset item', (int) $byProduct[$pylonProduct]['id'], (int) $asset['original_job_item_id']);
$eq('installed', '2027-01-01', (string) $asset['installation_date']);
$eq('token length', 32, strlen((string) $asset['tracking_token']));
$eq('token is not the id', true, (string) $asset['tracking_token'] !== (string) $asset['id']);

$stickerAssets = (new AssetRepository())->forJobItem((int) $byProduct[$stickerProduct]['id']);
$eq('no sticker assets', 0, count($stickerAssets));

$grouped = $assets->createFromJobItem((int) $byProduct[$signProduct]['id'], [
    'asset_type_id' => $wayfindingId,
    'track_mode' => 'GROUP',
    'quantity' => '20',
    'status' => 'ACTIVE',
], $userId);
$eq('grouped errors', [], $grouped['errors']);
$eq('one grouped asset', 1, count($grouped['ids']));
$group = (new AssetRepository())->find((int) $grouped['ids'][0]);
$eq('grouped quantity', '20.0000', (string) $group['quantity']);
$eq('grouped mode', 'GROUP', (string) $group['track_mode']);

$components = new AssetComponentService();
$warranties = new WarrantyService();
$psuIds = [];
foreach (['PSU 1', 'PSU 2', 'PSU 3'] as $name) {
    $added = $components->add($assetId, ['description' => $name, 'component_type' => 'POWER_SUPPLY', 'quantity' => '1', 'product_id' => $psuProduct], $userId);
    $psuIds[] = (int) $added['id'];
}
$components->add($assetId, ['description' => 'LED modules', 'quantity' => '84', 'component_type' => 'LED'], $userId);
$components->add($assetId, ['description' => 'Electrical timer', 'quantity' => '1', 'component_type' => 'TIMER'], $userId);
$work = $warranties->add($assetId, [
    'warranty_type' => 'SIGN_FORGE_WORKMANSHIP',
    'start_date' => '2027-01-01',
    'months' => 24,
    'terms' => 'Workmanship as agreed at installation',
], $userId);
$eq('workmanship', [], $work['errors']);
$stored = (new AssetRepository())->warranty((int) $work['id']);
$eq('stored end', '2028-12-31', (string) $stored['end_date']);
$psuWarranty = $warranties->add($assetId, [
    'warranty_type' => 'MANUFACTURER',
    'asset_component_id' => $psuIds[1],
    'start_date' => '2027-01-01',
    'months' => 60,
    'provider_name' => 'Mean Well',
    'terms' => 'PSU manufacturer warranty snapshot',
], $userId);
$eq('psu warranty', [], $psuWarranty['errors']);
$asOf = '2030-01-01';
$flags = [];
foreach ($warranties->withStatus($assetId, $asOf) as $row) {
    $flags[$row['warranty_type']] = (bool) $row['likely_active'];
}
$eq('workmanship not active later', false, $flags['SIGN_FORGE_WORKMANSHIP']);
$eq('manufacturer still active', true, $flags['MANUFACTURER']);

$requests = new ServiceRequestService();
$request = $requests->create([
    'asset_id' => $assetId,
    'description' => 'One side of the pylon is dark',
    'problem_category' => 'LIGHTING_FAILURE',
    'source' => 'PHONE',
    'priority' => 'NORMAL',
    'reported_date' => '2028-06-01',
    'reported_by' => 'Branch manager',
], $userId);
$eq('request', [], $request['errors']);
$requestRow = (new AssetRepository())->request((int) $request['id']);
$eq('request asset', $assetId, (int) $requestRow['asset_id']);
$eq('request number', 1, preg_match('/^SFSR-\d{4}-\d{4,}$/', (string) $requestRow['request_number']));
$eq('warranty candidate', 1, (int) $requestRow['warranty_candidate']);
$eq('no claim yet', 0, count((new AssetRepository())->claimsForAsset($assetId)));

$paidRequest = $requests->create([
    'asset_id' => $assetId,
    'description' => 'Face scuffed outside warranty',
    'problem_category' => 'FACE_DAMAGE',
    'reported_date' => '2030-02-01',
    'priority' => 'NORMAL',
], $userId);
$paidJob = $requests->openJob((int) $paidRequest['id'], [
    'charge' => '1500.00',
    'description' => 'Face repair',
    'classification' => 'PAID',
    'job_type' => 'SERVICE',
    'accepted_by_name' => 'Branch manager',
    'acceptance_method' => 'EMAIL',
    'title' => 'Paid face repair',
], $userId);
$eq('paid job errors', [], $paidJob['errors']);
$paid = (new JobRepository())->find((int) $paidJob['id']);
$eq('paid job type', 'SERVICE', (string) $paid['job_type']);
$eq('paid job number', 1, preg_match('/^SFJ-\d{4}-\d{4,}$/', (string) $paid['job_number']));
$eq('paid links request', (int) $paidRequest['id'], (int) $paid['service_request_id']);
$eq('paid links asset', $assetId, (int) $paid['customer_asset_id']);
$eq('paid revenue', true, Decimal::cmp((string) $paid['quoted_revenue_snapshot'], '0') > 0);

$goodwill = $requests->openJob((int) $request['id'], [
    'classification' => 'GOODWILL',
    'title' => 'Should fail',
], $userId);
$eq('goodwill needs reason', true, isset($goodwill['errors']['service_reason']));

$warrantyJob = $requests->openJob((int) $request['id'], [
    'classification' => 'WARRANTY',
    'job_type' => 'WARRANTY',
    'title' => 'Warranty PSU repair',
    'accepted_by_name' => 'Branch manager',
    'acceptance_method' => 'PHONE',
], $userId);
$eq('warranty job', [], $warrantyJob['errors']);
$wjob = (new JobRepository())->find((int) $warrantyJob['id']);
$eq('warranty type', 'WARRANTY', (string) $wjob['job_type']);
$eq('warranty customer charge', '0.00', (string) $wjob['quoted_revenue_snapshot']);
$costs = (new AssetLifecycleService())->recordCosts((int) $wjob['id'], [
    'product_id' => $costProduct,
    'material_quantity' => '1',
    'labour_minutes' => 120,
    'labour_cost' => '600.00',
    'travel_cost' => '300.00',
], $userId);
$eq('costs', [], $costs);
$wjob = (new JobRepository())->find((int) $wjob['id']);
$eq('warranty internal cost', '2100.00', (string) $wjob['actual_total_cost']);
$eq('still no customer revenue', '0.00', (string) $wjob['quoted_revenue_snapshot']);
$after = (new ProjectFinancialService())->statement($projectId);
$eq('project commercial unchanged', $before['commercial_value'], $after['commercial_value']);
$eq('project actual unchanged', $before['actual_cost'], $after['actual_cost']);

$opened = (new StockMovementService())->opening([
    'product_id' => $psuProduct,
    'stock_location_id' => $locationId,
    'quantity' => '5',
    'unit_cost' => '1200',
], $userId);
$eq('opening stock', [], $opened['errors'] ?? $opened);
$uuid = 'psu' . $stamp . 'abcd';
$life = new AssetLifecycleService();
$used = $life->consumeReplacement([
    'operation_uuid' => $uuid,
    'job_id' => (int) $wjob['id'],
    'asset_id' => $assetId,
    'product_id' => $psuProduct,
    'quantity' => '1',
    'stock_location_id' => $locationId,
    'old_component_id' => $psuIds[1],
    'description' => 'Mean Well PSU',
    'serial_number' => 'NEW-2',
], $userId);
$eq('part used', [], $used['errors']);
$again = $life->consumeReplacement([
    'operation_uuid' => $uuid,
    'job_id' => (int) $wjob['id'],
    'asset_id' => $assetId,
    'product_id' => $psuProduct,
    'quantity' => '1',
    'stock_location_id' => $locationId,
    'old_component_id' => $psuIds[1],
], $userId);
$eq('retry is duplicate', true, $again['duplicate']);
$moves = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE job_id = ? AND product_id = ?');
$moves->execute([(int) $wjob['id'], $psuProduct]);
$eq('one stock movement', '1', (string) $moves->fetchColumn());
$old = (new AssetRepository())->component($psuIds[1]);
$eq('old component replaced', 'REPLACED', (string) $old['status']);
$fresh = $pdo->prepare('SELECT status, replaces_component_id FROM asset_components WHERE asset_id = ? AND serial_number = ?');
$fresh->execute([$assetId, 'NEW-2']);
$freshRow = $fresh->fetch();
$eq('new component active', 'ACTIVE', (string) $freshRow['status']);
$eq('replacement link', $psuIds[1], (int) $freshRow['replaces_component_id']);

$inspection = (new InspectionService())->record($assetId, [
    'inspection_type' => 'ROUTINE',
    'result' => 'PASS_WITH_NOTES',
    'inspected_on' => '2027-06-01',
    'findings' => 'Face dusty',
    'items' => [
        ['label' => 'Structure secure', 'result' => 'PASS'],
        ['label' => 'LED operation', 'result' => 'PASS'],
    ],
], $userId);
$eq('inspection', [], $inspection['errors']);
$plan = (new AssetMaintenanceService())->createPlan(['name' => 'Annual ' . $stamp, 'interval_months' => 12, 'checklist_name' => 'Pylon annual'], $userId);
$assignError = (new AssetMaintenanceService())->assign($assetId, (int) $plan['id'], '2027-01-01', $userId);
$eq('plan assigned', [], $assignError);
$assignment = (new AssetRepository())->assignments($assetId)[0];
$done = (new AssetMaintenanceService())->complete((int) $assignment['id'], '2027-06-15', $userId);
$eq('maintenance complete', [], $done);
$asset = (new AssetRepository())->find($assetId);
$eq('next service', '2028-06-15', (string) $asset['next_service_date']);

$history = (new AssetRepository())->events($assetId, 50);
$dates = array_map(static fn (array $row): string => (string) $row['happened_at'], $history);
$sorted = $dates;
sort($sorted);
$eq('history chronological', $sorted, $dates);
$types = array_map(static fn (array $row): string => (string) $row['event_type'], $history);
$eq('history has inspection', true, in_array('INSPECTION', $types, true));
$eq('history has request', true, in_array('SERVICE_REQUEST', $types, true));
$eq('history has replacement', true, in_array('COMPONENT_REPLACED', $types, true));
$eq('history has maintenance', true, in_array('MAINTENANCE', $types, true));

$claim = $warranties->openClaim($assetId, [
    'failure_description' => 'PSU 2 failed',
    'warranty_id' => (int) $psuWarranty['id'],
    'asset_component_id' => $psuIds[1],
    'service_request_id' => (int) $request['id'],
    'reported_date' => '2028-06-01',
    'cost_recovery_amount' => '1200.00',
], $userId);
$eq('claim', [], $claim['errors']);
$claimRow = (new AssetRepository())->claim((int) $claim['id']);
$eq('claim number', 1, preg_match('/^SFWC-\d{4}-\d{4,}$/', (string) $claimRow['claim_number']));
$eq('claim starts new', 'NEW', (string) $claimRow['status']);

$replaced = $assets->replace($assetId, ['name' => 'Replacement pylon', 'installation_date' => '2031-01-01'], $userId);
$eq('replacement asset', true, $replaced['id'] !== null);
$oldAsset = (new AssetRepository())->find($assetId);
$newAsset = (new AssetRepository())->find((int) $replaced['id']);
$eq('old kept', 'REPLACED', (string) $oldAsset['status']);
$eq('new active', 'ACTIVE', (string) $newAsset['status']);
$eq('old points forward', (int) $replaced['id'], (int) $oldAsset['replacement_asset_id']);
$eq('new points back', $assetId, (int) $newAsset['replaces_asset_id']);

$eq('portal other denied', false, AssetAccess::portalOwns($otherId, $oldAsset));
$eq('portal owner allowed', true, AssetAccess::portalOwns($customerId, $oldAsset));
$public = $assets->publicCard((string) $oldAsset['tracking_token']);
$eq('public keys', ['asset_number', 'name', 'site'], array_keys($public));
$eq('public misses cost', false, array_key_exists('original_internal_cost', $public));
$eq('unknown qr', null, $assets->publicCard('not-a-token'));
$svg = (string) $assets->labelSvg($assetId);
$eq('label svg', true, str_contains($svg, '<svg') && str_contains($svg, '<rect'));
$pack = $life->fieldPack($assetId);
$eq('field pack hides cost', false, array_key_exists('original_internal_cost', $pack));
$eq('field pack hides margin', false, str_contains(json_encode($pack), 'quoted_revenue'));

$sync = $life->sync($userId, 'sync' . $stamp . 'zzzz', $assetId, 'inspection', [
    'inspection_type' => 'ROUTINE',
    'result' => 'PASS',
    'inspected_on' => '2027-07-01',
    'items' => [['label' => 'Fasteners', 'result' => 'PASS']],
]);
$eq('sync once', true, $sync['ok'] && $sync['duplicate'] === false);
$sync2 = $life->sync($userId, 'sync' . $stamp . 'zzzz', $assetId, 'inspection', [
    'inspection_type' => 'ROUTINE',
    'result' => 'PASS',
    'inspected_on' => '2027-07-02',
    'items' => [['label' => 'Fasteners', 'result' => 'PASS']],
]);
$eq('sync duplicate', true, $sync2['duplicate']);
$visitCount = $pdo->prepare("SELECT COUNT(*) FROM asset_inspections WHERE asset_id = ? AND inspected_on = '2027-07-02'");
$visitCount->execute([$assetId]);
$eq('second visit not written', '0', (string) $visitCount->fetchColumn());

$installerId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'INSTALLER' AND u.active = 1 ORDER BY u.id LIMIT 1")->fetchColumn();
$permId = (int) $pdo->query("SELECT id FROM permissions WHERE code = 'inspections.perform'")->fetchColumn();
$roleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'INSTALLER'")->fetchColumn();
$pdo->prepare('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?')->execute([$roleId, $permId]);
$_SESSION['user_id'] = $installerId;
forget_auth_user();
$revoked = $life->sync($installerId, 'syncrevoked' . $stamp, $assetId, 'inspection', [
    'inspection_type' => 'ROUTINE',
    'result' => 'PASS',
    'inspected_on' => '2027-08-01',
    'items' => [['label' => 'Face', 'result' => 'PASS']],
]);
$eq('revoked technician refused', false, $revoked['ok']);
$pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $permId]);
$_SESSION['user_id'] = $userId;
forget_auth_user();

$rollout = (new ProjectService())->create(['customer_id' => $customerId, 'name' => '25 site assets', 'commercial_mode' => 'PROJECT'], $userId);
$rolloutId = (int) $rollout['id'];
$siteIds = [];
for ($i = 1; $i <= 25; $i++) {
    $addedSite = (new ProjectSiteService())->add($rolloutId, ['site_code' => 'B' . $i, 'site_name' => 'Branch ' . $i], $userId);
    $siteIds[] = (int) $addedSite['id'];
}
$values = [];
$params = [];
for ($n = 1; $n <= 100; $n++) {
    $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $token = bin2hex(random_bytes(16));
    array_push($params, 'SFA-BULK-' . $stamp . '-' . $n, $customerId, $rolloutId, $siteIds[($n - 1) % 25], $typeId, 'Branch sign ' . $n, '1.0000', 'ACTIVE', $token, $userId);
}
$pdo->prepare('INSERT INTO customer_assets (asset_number, customer_id, project_id, project_site_id, asset_type_id, name, quantity, status, tracking_token, created_by) VALUES ' . implode(',', $values))->execute($params);
$eq('project asset count', 100, (new AssetRepository())->countForProject($rolloutId));
$eq('site asset count', 4, (new AssetRepository())->countForSite($siteIds[0]));

$started = microtime(true);
$perfCustomer = $customerId;
$chunk = [];
$chunkParams = [];
for ($n = 1; $n <= 1000; $n++) {
    $chunk[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
    array_push($chunkParams, 'SFA-PERF-' . $stamp . '-' . $n, $perfCustomer, $typeId, 'Perf ' . $n, 'ACTIVE', bin2hex(random_bytes(16)), $userId, '1.0000');
    if (count($chunk) === 200 || $n === 1000) {
        $pdo->prepare('INSERT INTO customer_assets (asset_number, customer_id, asset_type_id, name, status, tracking_token, created_by, quantity) VALUES ' . implode(',', $chunk))->execute($chunkParams);
        $chunk = [];
        $chunkParams = [];
    }
}
$sample = (int) $pdo->query("SELECT id FROM customer_assets WHERE asset_number = 'SFA-PERF-{$stamp}-1'")->fetchColumn();
$eventSql = [];
$eventParams = [];
for ($n = 1; $n <= 5000; $n++) {
    $eventSql[] = '(?, ?, ?, ?)';
    array_push($eventParams, $sample, 'NOTE', 'Event ' . $n, $userId);
    if (count($eventSql) === 500 || $n === 5000) {
        $pdo->prepare('INSERT INTO asset_events (asset_id, event_type, summary, created_by) VALUES ' . implode(',', $eventSql))->execute($eventParams);
        $eventSql = [];
        $eventParams = [];
    }
}
$compSql = [];
$compParams = [];
for ($n = 1; $n <= 10000; $n++) {
    $compSql[] = '(?, ?, ?, ?)';
    array_push($compParams, $sample, 'LED', 'Module ' . $n, '1.0000');
    if (count($compSql) === 500 || $n === 10000) {
        $pdo->prepare('INSERT INTO asset_components (asset_id, component_type, description, quantity) VALUES ' . implode(',', $compSql))->execute($compParams);
        $compSql = [];
        $compParams = [];
    }
}
$page = (new AssetRepository())->search(['customer_id' => $perfCustomer], 50, 0);
$eq('page size', 50, count($page));
$explain = $pdo->prepare('EXPLAIN SELECT id FROM customer_assets WHERE customer_id = ? AND status = ? LIMIT 50');
$explain->execute([$perfCustomer, 'ACTIVE']);
$planRow = $explain->fetch();
$eq('customer index used', true, str_contains((string) ($planRow['key'] ?? ''), 'customer'));
$elapsed = microtime(true) - $started;
$eq('performance window', true, $elapsed < 30);
fwrite(STDOUT, 'perf ' . round($elapsed, 2) . "s\n");

$report = $life->report('component-failure');
$eq('failure report', true, $report['rows'] !== []);
$warrantyReport = $life->report('warranty-cost');
$found = false;
foreach ($warrantyReport['rows'] as $row) {
    if ((string) $row['job_number'] === (string) $wjob['job_number']) {
        $found = (string) $row['quoted_revenue_snapshot'] === '0.00'
            && Decimal::cmp((string) $row['actual_total_cost'], '2100.00') >= 0;
    }
}
$eq('warranty report separates revenue', true, $found);
$numbers = [(new NumberingService())->asset(), (new NumberingService())->asset()];
$eq('numbers differ', true, $numbers[0] !== $numbers[1]);

if ($failures > 0) {
    fwrite(STDERR, "{$failures} failed\n");
    exit(1);
}
fwrite(STDOUT, "PHASE2_OK\n");

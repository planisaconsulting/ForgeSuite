<?php

declare(strict_types=1);

/**
 * v1.1 Phase 8: shipments, couriers, deliveries, and contractor work.
 *
 *   php tests/v11_phase8.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\AssetRepository;
use App\Repositories\ContractorRepository;
use App\Repositories\LogisticsRepository;
use App\Services\ContractorWorkService;
use App\Services\DeviceService;
use App\Services\FieldSyncService;
use App\Services\FulfilmentService;
use App\Services\InstallationFieldService;
use App\Services\LogisticsService;
use App\Services\StockMovementService;
use App\Services\TrackingUrl;

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
    if (str_contains($haystack, $needle)) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  missing: {$needle}\n");
    $failures++;
};
$uuid = static function (): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
};

$pdo = Database::connection();
$userId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'ADMIN' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $userId;
forget_auth_user();
$stamp = date('His') . bin2hex(random_bytes(2));
$logistics = new LogisticsService();
$contractors = new ContractorWorkService();
$installs = new InstallationFieldService();
$repo = new LogisticsRepository();

$eq('unsafe tracking url', null, TrackingUrl::build('javascript:alert(1)', '1', '1'));
$eq('http tracking url', null, TrackingUrl::build('http://track.example/{tracking}', '1', '1'));
$safeLink = TrackingUrl::build('https://track.example/go/{tracking}', 'AB 1', 'W');
$eq('encoded tracking link', 'https://track.example/go/AB%201', $safeLink);
$manual = (new \App\Services\ManualCourierProvider())->getTracking('WB1');
$eq('manual courier has no events', [], $manual['events']);
$contains('manual message', 'No courier API', (new \App\Services\ManualCourierProvider())->createShipment(['waybill_number' => 'WB'])['message']);

$courier = $logistics->saveCourier([
    'name' => 'Manual Courier ' . $stamp,
    'integration_type' => 'MANUAL',
    'tracking_url_template' => 'https://track.example/{tracking}',
    'account_reference' => 'ACCT-' . $stamp,
    'active' => 1,
], $userId);
$eq('courier saved', [], $courier['errors']);

$pdo->prepare('INSERT INTO customers (customer_type, company_name, active, created_by) VALUES (\'BUSINESS\', ?, 1, ?)')->execute(['Phase8 ' . $stamp, $userId]);
$customerId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO customers (customer_type, company_name, active, created_by) VALUES (\'BUSINESS\', ?, 1, ?)')->execute(['Other8 ' . $stamp, $userId]);
$otherCustomer = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO quotes (quote_number, customer_id, quote_date, status, created_by) VALUES (?,?,?,?,?)')->execute(['Q8-' . $stamp, $customerId, date('Y-m-d'), 'ACCEPTED', $userId]);
$quoteId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO jobs (job_number, customer_id, quote_id, quote_revision_number, title, status, created_by) VALUES (?,?,?,?,?,?,?)')->execute(['SFJ-P8-' . $stamp, $customerId, $quoteId, 1, 'Correx boards', 'NEW', $userId]);
$jobId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO quotes (quote_number, customer_id, quote_date, status, created_by) VALUES (?,?,?,?,?)')->execute(['Q8B-' . $stamp, $customerId, date('Y-m-d'), 'ACCEPTED', $userId]);
$quoteB = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO jobs (job_number, customer_id, quote_id, quote_revision_number, title, status, created_by) VALUES (?,?,?,?,?,?,?)')->execute(['SFJ-P8B-' . $stamp, $customerId, $quoteB, 1, 'Other job', 'NEW', $userId]);
$jobB = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO job_items (job_id, description, quantity, good_quantity, production_status) VALUES (?,?,?,?,?)')->execute([$jobId, 'Correx board', '50.0000', '50.0000', 'COMPLETE']);
$itemId = (int) $pdo->lastInsertId();
$line = (new FulfilmentService())->create($jobId, [
    'job_item_id' => $itemId,
    'fulfilment_type' => 'COURIER',
    'quantity' => '50',
    'address' => '12 Main Road',
    'contact_name' => 'Site lead',
], $userId);
$eq('fulfilment line', [], $line['errors']);

$shipment = $logistics->createShipment([
    'shipment_type' => 'COURIER',
    'customer_id' => $customerId,
    'job_id' => $jobId,
    'destination_address' => '12 Main Road',
    'contact_name' => 'Site lead',
    'contact_phone' => '0110000000',
    'customer_note' => '<script>alert(1)</script>',
], $userId);
$eq('shipment created', [], $shipment['errors']);
$shipmentId = (int) $shipment['id'];
$number = (string) $repo->shipment($shipmentId)['shipment_number'];
$contains('shipment number', 'SFSHP-', $number);
$added = $logistics->addItem($shipmentId, [
    'job_id' => $jobId,
    'job_item_id' => $itemId,
    'fulfilment_requirement_id' => $line['id'],
    'quantity' => '50',
    'description' => 'Correx board',
], $userId);
$eq('fifty added', [], $added['errors']);
for ($n = 1; $n <= 3; $n++) {
    $pdo->prepare('INSERT INTO packages (package_code, job_id, description, status, created_by) VALUES (?,?,?,?,?)')->execute(['PKG8' . $stamp . $n, $jobId, 'Carton ' . $n, 'OPEN', $userId]);
    $packageId = (int) $pdo->lastInsertId();
    $eq('package ' . $n, [], $logistics->describePackage($packageId, $shipmentId, [
        'sequence_no' => $n,
        'sequence_total' => 3,
        'package_type' => 'CARTON',
        'fragile' => 1,
    ], $userId));
    $scan = $logistics->verifyScan($shipmentId, 'PKG8' . $stamp . $n, $userId);
    $eq('verified ' . $n, [], $scan['errors']);
}
$booked = $logistics->bookCourier($shipmentId, [
    'courier_id' => $courier['id'],
    'waybill_number' => 'WB' . $stamp,
    'tracking_number' => 'TR' . $stamp,
    'estimated_courier_cost' => '400',
    'actual_courier_cost' => '450',
    'customer_delivery_charge' => '600',
    'collection_date' => date('Y-m-d'),
], $userId);
$eq('booked', [], $booked);
$eq('booked is not delivered', 'BOOKED', (string) $repo->shipment($shipmentId)['status']);
$dispatched = $logistics->dispatch($shipmentId, $userId);
$eq('dispatched', [], $dispatched);
$row = $repo->shipment($shipmentId);
$eq('not delivered after dispatch', 'AWAITING_COLLECTION', (string) $row['status']);
$fulfilment = $pdo->query('SELECT dispatched_quantity, delivered_quantity, status FROM fulfilment_requirements WHERE id = ' . (int) $line['id'])->fetch();
$eq('dispatched qty', 0, Decimal::cmp((string) $fulfilment['dispatched_quantity'], '50'));
$eq('delivered still zero', 0, Decimal::cmp((string) $fulfilment['delivered_quantity'], '0'));
$eq('fulfilment not complete', false, (string) $fulfilment['status'] === 'FULFILLED');
$label = $logistics->label((int) $pdo->query('SELECT id FROM packages WHERE package_code = ' . $pdo->quote('PKG8' . $stamp . '1'))->fetchColumn());
$eq('label has no price', false, array_key_exists('price', $label) || array_key_exists('margin', $label));
$contains('label barcode', '<svg', (string) $label['barcode']);
$contains('escaped note', '&lt;script&gt;', e((string) $repo->shipment($shipmentId)['customer_note']));

$pdo->prepare('INSERT INTO job_items (job_id, description, quantity, good_quantity) VALUES (?,?,?,?)')->execute([$jobId, 'Partial boards', '100.0000', '100.0000']);
$partialItem = (int) $pdo->lastInsertId();
$partialLine = (new FulfilmentService())->create($jobId, ['job_item_id' => $partialItem, 'fulfilment_type' => 'COURIER', 'quantity' => '100', 'address' => '12 Main Road', 'contact_name' => 'Site lead'], $userId);
$partialShip = $logistics->createShipment(['shipment_type' => 'COURIER', 'customer_id' => $customerId, 'job_id' => $jobId, 'destination_address' => '12 Main Road', 'contact_name' => 'Site'], $userId);
$partAdd = $logistics->addItem((int) $partialShip['id'], ['job_id' => $jobId, 'job_item_id' => $partialItem, 'fulfilment_requirement_id' => $partialLine['id'], 'quantity' => '60', 'description' => 'Partial'], $userId);
$eq('partial accepted', [], $partAdd['errors']);
$pdo->prepare('INSERT INTO packages (package_code, job_id, description, status, created_by) VALUES (?,?,?,?,?)')->execute(['PKGP' . $stamp, $jobId, 'Partial', 'OPEN', $userId]);
$logistics->describePackage((int) $pdo->lastInsertId(), (int) $partialShip['id'], ['sequence_no' => 1, 'sequence_total' => 1], $userId);
$logistics->verifyScan((int) $partialShip['id'], 'PKGP' . $stamp, $userId);
$eq('partial dispatch', [], $logistics->dispatch((int) $partialShip['id'], $userId));
$partialRow = $pdo->query('SELECT dispatched_quantity, quantity FROM fulfilment_requirements WHERE id = ' . (int) $partialLine['id'])->fetch();
$eq('sixty dispatched', 0, Decimal::cmp((string) $partialRow['dispatched_quantity'], '60'));
$eq('forty remain', 0, Decimal::cmp(Decimal::sub((string) $partialRow['quantity'], (string) $partialRow['dispatched_quantity'], 4), '40'));

$pdo->prepare('INSERT INTO job_items (job_id, description, quantity, good_quantity) VALUES (?,?,?,?)')->execute([$jobId, 'Short run', '100.0000', '80.0000']);
$shortItem = (int) $pdo->lastInsertId();
$over = $logistics->addItem((int) $partialShip['id'], ['job_id' => $jobId, 'job_item_id' => $shortItem, 'quantity' => '100', 'description' => 'Too many'], $userId);
$contains('over ship blocked', 'Blocked', (string) ($over['errors']['_form'] ?? ''));

$pdo->prepare('INSERT INTO packages (package_code, job_id, description, status, created_by) VALUES (?,?,?,?,?)')->execute(['PKGW' . $stamp, $jobB, 'Wrong job', 'OPEN', $userId]);
$wrong = $logistics->verifyScan($shipmentId, 'PKGW' . $stamp, $userId);
$contains('wrong job', 'WRONG JOB', (string) ($wrong['errors']['_form'] ?? ''));

$failed = $logistics->failDelivery($shipmentId, ['reason' => 'NO_ONE_AVAILABLE', 'description' => 'Gate closed'], $userId);
$eq('exception opened', true, (int) $failed['id'] > 0);
$afterFail = $pdo->query('SELECT delivered_quantity, status FROM fulfilment_requirements WHERE id = ' . (int) $line['id'])->fetch();
$eq('failed delivery not fulfilled', false, (string) $afterFail['status'] === 'FULFILLED');
$eq('failed delivery qty', 0, Decimal::cmp((string) $afterFail['delivered_quantity'], '0'));

$okShip = $logistics->createShipment(['shipment_type' => 'COURIER', 'customer_id' => $customerId, 'job_id' => $jobId, 'destination_address' => '12 Main Road', 'contact_name' => 'Site'], $userId);
$pdo->prepare('INSERT INTO job_items (job_id, description, quantity, good_quantity) VALUES (?,?,?,?)')->execute([$jobId, 'POD boards', '10.0000', '10.0000']);
$podItem = (int) $pdo->lastInsertId();
$podLine = (new FulfilmentService())->create($jobId, ['job_item_id' => $podItem, 'fulfilment_type' => 'COURIER', 'quantity' => '10', 'address' => '12 Main Road', 'contact_name' => 'Site'], $userId);
$logistics->addItem((int) $okShip['id'], ['job_id' => $jobId, 'job_item_id' => $podItem, 'fulfilment_requirement_id' => $podLine['id'], 'quantity' => '10', 'description' => 'POD'], $userId);
$pdo->prepare('INSERT INTO packages (package_code, job_id, description, status, created_by) VALUES (?,?,?,?,?)')->execute(['PKGD' . $stamp, $jobId, 'POD', 'OPEN', $userId]);
$logistics->describePackage((int) $pdo->lastInsertId(), (int) $okShip['id'], ['sequence_no' => 1, 'sequence_total' => 1], $userId);
$logistics->verifyScan((int) $okShip['id'], 'PKGD' . $stamp, $userId);
$logistics->bookCourier((int) $okShip['id'], ['courier_id' => $courier['id'], 'waybill_number' => 'WB2' . $stamp, 'tracking_number' => 'TR2' . $stamp, 'actual_courier_cost' => '80', 'customer_delivery_charge' => '120'], $userId);
$logistics->dispatch((int) $okShip['id'], $userId);
$delivered = $logistics->recordEvent((int) $okShip['id'], ['status' => 'DELIVERED', 'source' => 'MANUAL', 'description' => 'Handed over'], $userId);
$eq('delivered event', [], $delivered['errors']);
$pod = $logistics->capturePod((int) $okShip['id'], ['recipient_name' => 'A. Nkosi', 'photo_path' => 'pod/' . $stamp . '.jpg'], $userId);
$eq('pod stored', true, (int) $pod['id'] > 0);
$safe = $logistics->customerSafe((int) $okShip['id'], $customerId);
$eq('customer sees delivered', 'DELIVERED', (string) ($safe['status'] ?? ''));
$eq('customer sees tracking', 'TR2' . $stamp, (string) ($safe['tracking_number'] ?? ''));
$eq('no courier cost on portal', false, array_key_exists('actual_courier_cost', $safe) || array_key_exists('estimated_courier_cost', $safe));
$eq('other customer denied', null, $logistics->customerSafe((int) $okShip['id'], $otherCustomer));
$eq('pod name', 'A. Nkosi', (string) ($safe['pod']['recipient_name'] ?? ''));
$profit = $logistics->profitability((int) $okShip['id']);
$eq('delivery variance', 0, Decimal::cmp((string) $profit['variance'], '40'));

$categoryId = (int) $pdo->query('SELECT id FROM product_categories ORDER BY id LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO products (category_id, sku, name, product_type, pricing_method, cost_price, cost_unit, track_stock, inventory_method, creates_customer_asset) VALUES (?,?,?,?,?,?,?,?,?,1)')->execute([$categoryId, 'P8-' . $stamp, 'Lightbox face', 'FINISHED_PRODUCT', 'UNIT', '10.0000', 'unit', 1, 'QUANTITY']);
$productId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO job_items (job_id, product_id, description, quantity, good_quantity, production_status) VALUES (?,?,?,?,?,?)')->execute([$jobId, $productId, 'Lightbox', '1.0000', '1.0000', 'COMPLETE']);
$installItem = (int) $pdo->lastInsertId();
$installLine = (new FulfilmentService())->create($jobId, ['job_item_id' => $installItem, 'fulfilment_type' => 'INSTALLATION', 'quantity' => '1', 'address' => 'Mall entrance', 'contact_name' => 'Manager'], $userId);
$installation = $installs->schedule([
    'job_id' => $jobId,
    'fulfilment_requirement_id' => $installLine['id'],
    'site_address' => 'Mall entrance',
    'site_contact_name' => 'Manager',
    'checklist' => 'LIGHTBOX',
    'scheduled_date' => date('Y-m-d'),
], $userId);
$eq('installation planned', [], $installation['errors']);
$pack = $installs->pack((int) $installation['id']);
$eq('pack has no margin', false, array_key_exists('margin', $pack) || array_key_exists('gp', $pack));
$eq('lightbox checks', 9, count($pack['checklist']));
$eq('arrived', [], $installs->arrive((int) $installation['id'], $userId, null, null, null));
$eq('started', [], $installs->start((int) $installation['id'], $userId, date('Y-m-d H:i:s')));
$eq('photo', true, (int) $installs->photo((int) $installation['id'], 'AFTER', 'install/' . $stamp . '.jpg', $userId)['id'] > 0);
$signed = $installs->signOff((int) $installation['id'], ['signer_name' => 'Site manager', 'signer_role' => 'Store'], $userId);
$eq('signed', [], $signed['errors']);
$eq('job not closed by install', 'NEW', (string) $signed['job_status']);
$eq('certificate allowed', true, $installs->certificateAllowed((int) $installation['id']));
$eligible = (new AssetRepository())->eligibleItems($jobId);
$eq('asset eligible', true, count($eligible) >= 1);

$blockedInstall = $installs->schedule(['job_id' => $jobId, 'site_address' => 'Mall', 'checklist' => 'VEHICLE'], $userId);
$before = (string) $pdo->query('SELECT status FROM jobs WHERE id = ' . $jobId)->fetchColumn();
$snag = $installs->snag((int) $blockedInstall['id'], ['snag_type' => 'INSTALLATION', 'severity' => 'BLOCKING', 'description' => 'Power not ready', 'source_category' => 'SITE'], $userId);
$eq('snag stored', true, (int) $snag['id'] > 0);
$refused = $installs->signOff((int) $blockedInstall['id'], ['signer_name' => 'Someone'], $userId);
$contains('blocking snag', 'blocking snag', strtolower((string) ($refused['errors']['_form'] ?? '')));
$eq('job status unchanged', $before, (string) $pdo->query('SELECT status FROM jobs WHERE id = ' . $jobId)->fetchColumn());

$savedContractor = $contractors->save([
    'company_name' => 'Installer ' . $stamp,
    'contractor_type' => 'INSTALLER',
    'status' => 'ACTIVE',
    'email' => 'office' . $stamp . '@installer.test',
], $userId);
$eq('contractor', [], $savedContractor['errors']);
$otherContractor = $contractors->save(['company_name' => 'Other ' . $stamp, 'contractor_type' => 'ELECTRICIAN', 'status' => 'ACTIVE'], $userId);
$rateId = (new ContractorRepository())->insertRate((int) $savedContractor['id'], 'FIXED', '5000.00', 'Install day');
$portal = $contractors->addPortalUser((int) $savedContractor['id'], 'crew' . $stamp . '@installer.test', 'crew-pass-123', 'Crew', $userId);
$otherPortal = $contractors->addPortalUser((int) $otherContractor['id'], 'other' . $stamp . '@installer.test', 'crew-pass-123', 'Other', $userId);
$order = $contractors->createWorkOrder([
    'contractor_id' => $savedContractor['id'],
    'job_id' => $jobId,
    'work_type' => 'INSTALLATION',
    'scope' => 'Install the lightbox only',
    'quantity' => '1',
    'agreed_cost' => '5000',
    'pricing_method' => 'FIXED',
    'site_address' => 'Mall entrance',
    'contact_name' => 'Manager',
    'internal_notes' => 'Margin is private',
    'production_file_ids' => [910000 + (int) substr($stamp, -2)],
], $userId);
$eq('work order', [], $order['errors']);
$contains('work order number', 'SFCWO-', (string) (new ContractorRepository())->workOrder((int) $order['id'])['work_order_number']);
$eq('hidden until sent', null, $contractors->portalWork((int) $order['id'], (int) $portal['id']));
$eq('sent', [], $contractors->send((int) $order['id'], $userId));
(new ContractorRepository())->updateRate($rateId, '9000.00');
$visible = $contractors->portalWork((int) $order['id'], (int) $portal['id']);
$eq('scope visible', 'Install the lightbox only', (string) ($visible['scope'] ?? ''));
$eq('agreed snapshot', 0, Decimal::cmp((string) ($visible['agreed_cost'] ?? '0'), '5000'));
$eq('no internal note', false, array_key_exists('internal_notes', $visible ?? []) || array_key_exists('margin', $visible ?? []) || array_key_exists('gp', $visible ?? []));
$eq('other contractor denied', null, $contractors->portalWork((int) $order['id'], (int) $otherPortal['id']));
$fileId = 910000 + (int) substr($stamp, -2);
$eq('own file', true, $contractors->canSeeFile($fileId, (int) $portal['id']));
$eq('other file denied', false, $contractors->canSeeFile($fileId, (int) $otherPortal['id']));
$eq('unrelated file denied', false, $contractors->canSeeFile($fileId + 50, (int) $portal['id']));
$jobBefore = (string) $pdo->query('SELECT status FROM jobs WHERE id = ' . $jobId)->fetchColumn();
$eq('declined', [], $contractors->respond((int) $order['id'], (int) $portal['id'], 'DECLINE', 'No crew'));
$eq('job not cancelled', $jobBefore, (string) $pdo->query('SELECT status FROM jobs WHERE id = ' . $jobId)->fetchColumn());
$eq('needs action', 'DECLINED', (string) (new ContractorRepository())->workOrder((int) $order['id'])['status']);

$redo = $contractors->createWorkOrder([
    'contractor_id' => $savedContractor['id'],
    'job_id' => $jobId,
    'work_type' => 'INSTALLATION',
    'scope' => 'Second visit',
    'agreed_cost' => '5000',
], $userId);
$contractors->send((int) $redo['id'], $userId);
$eq('accepted', [], $contractors->respond((int) $redo['id'], (int) $portal['id'], 'ACCEPT'));
$eq('started work', [], $contractors->start((int) $redo['id'], (int) $portal['id'], null, null));
$eq('submitted', [], $contractors->submit((int) $redo['id'], (int) $portal['id'], ['actual_hours' => '4']));
$eq('awaiting review', 'SUBMITTED_COMPLETE', (string) (new ContractorRepository())->workOrder((int) $redo['id'])['status']);
$eq('reviewed', [], $contractors->review((int) $redo['id'], $userId, true));
$cost = $contractors->approveCost((int) $redo['id'], '5500', 'Extra hour on site', $userId);
$eq('variance', 0, Decimal::cmp((string) $cost['variance'], '500'));
$reference = 'CWO-' . (new ContractorRepository())->workOrder((int) $redo['id'])['work_order_number'];
$eq('cost posted once', 1, (new ContractorRepository())->otherCostCount($jobId, $reference));
$contractors->approveCost((int) $redo['id'], '5500', '', $userId);
$eq('cost still once', 1, (new ContractorRepository())->otherCostCount($jobId, $reference));

$locationId = (int) $pdo->query("SELECT id FROM stock_locations WHERE location_type <> 'QUARANTINE' AND location_type <> 'EXTERNAL_CONTRACTOR' ORDER BY id LIMIT 1")->fetchColumn();
$sheet = $pdo->prepare('INSERT INTO products (category_id, sku, name, product_type, pricing_method, cost_price, cost_unit, track_stock, inventory_method) VALUES (?,?,?,?,?,?,?,?,?)');
$sheet->execute([$categoryId, 'SH8-' . $stamp, 'Sheet ' . $stamp, 'MATERIAL', 'UNIT', '5.0000', 'unit', 1, 'QUANTITY']);
$sheetId = (int) $pdo->lastInsertId();
$opened = (new StockMovementService())->opening(['product_id' => $sheetId, 'stock_location_id' => $locationId, 'quantity' => '10', 'unit_cost' => '5'], $userId);
$eq('opening stock', true, $opened['errors'] === []);
$fab = $contractors->createWorkOrder([
    'contractor_id' => $savedContractor['id'],
    'job_id' => $jobId,
    'work_type' => 'FABRICATION',
    'scope' => 'Laser cut',
    'material_supply' => 'SIGN_FORGE',
    'agreed_cost' => '1000',
], $userId);
$eq('issued', [], $contractors->issueMaterial((int) $fab['id'], $sheetId, $locationId, '10', $userId));
$eq('returned', [], $contractors->returnMaterial((int) $fab['id'], $sheetId, $locationId, '2', $userId));
$eq('consumed', [], $contractors->consumeMaterial((int) $fab['id'], $sheetId, '8', $userId));
$eq('contractor holding', 0, Decimal::cmp($contractors->holding((int) $fab['id'], $sheetId), '0'));
$eq('warehouse keeps returns', 0, Decimal::cmp($contractors->onHand($sheetId, $locationId), '2'));
$eq('ledger global', 0, Decimal::cmp($contractors->onHand($sheetId), '2'));
$receipt = $contractors->receiveOutput((int) $fab['id'], ['quantity_received' => '8', 'quantity_accepted' => '8', 'quantity_rejected' => '0', 'qc_status' => 'PASS'], $userId);
$eq('outsourced qc', [], $receipt);

$pdo->prepare('INSERT INTO projects (project_number, customer_id, name, status) VALUES (?,?,?,?)')->execute(['SFP8-' . $stamp, $customerId, 'Retail rollout ' . $stamp, 'ACTIVE']);
$projectId = (int) $pdo->lastInsertId();
for ($site = 1; $site <= 25; $site++) {
    $pdo->prepare('INSERT INTO project_sites (project_id, site_code, site_name, status) VALUES (?,?,?,?)')->execute([$projectId, 'R' . $stamp . $site, 'Site ' . $site, 'NOT_STARTED']);
}
$siteId = (int) $pdo->query('SELECT id FROM project_sites WHERE project_id = ' . $projectId . ' ORDER BY id LIMIT 1')->fetchColumn();
$rollShip = $logistics->createShipment(['shipment_type' => 'OWN_DELIVERY', 'customer_id' => $customerId, 'job_id' => $jobId, 'project_id' => $projectId, 'project_site_id' => $siteId, 'destination_address' => 'Site 1', 'contact_name' => 'Lead'], $userId);
$logistics->recordEvent((int) $rollShip['id'], ['status' => 'IN_TRANSIT', 'source' => 'INTERNAL', 'description' => 'On the truck'], $userId);
$doneInstall = $installs->schedule(['job_id' => $jobId, 'project_id' => $projectId, 'project_site_id' => $siteId, 'site_address' => 'Site 1'], $userId);
$installs->signOff((int) $doneInstall['id'], ['signer_name' => 'Lead'], $userId);
$rollup = $logistics->projectRollup($projectId);
$eq('twenty five sites', 25, $rollup['sites']);
$eq('one in transit', true, $rollup['in_transit'] >= 1);
$eq('one installed', true, $rollup['installed'] >= 1);

$deviceUuid = $uuid();
$device = (new DeviceService())->register($userId, ['device_uuid' => $deviceUuid, 'device_name' => 'Phase8', 'platform' => 'test']);
$eq('device', true, $device['ok']);
$operation = $uuid();
$local = $uuid();
$sync = new FieldSyncService();
$payload = [
    'device_uuid' => $deviceUuid,
    'operations' => [[
        'operation_uuid' => $operation,
        'local_uuid' => $local,
        'operation_type' => 'LOGISTICS_SIGNATURE',
        'entity_type' => 'INSTALLATION',
        'entity_id' => (int) $installation['id'],
        'payload' => [
            'signer_name' => 'Offline signer',
            'statement' => 'Received',
            'signature_png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            'client_signed_at' => date('c'),
        ],
    ]],
];
$first = $sync->accept($userId, $payload);
$second = $sync->accept($userId, $payload);
$eq('offline synced', 'SYNCED', (string) ($first['results'][0]['status'] ?? ''));
$eq('replay has no second signature', (int) ($first['results'][0]['server_entity_id'] ?? 0), (int) ($second['results'][0]['server_entity_id'] ?? 0));
$sigCount = (int) $pdo->query("SELECT COUNT(*) FROM digital_signatures WHERE entity_type = 'INSTALLATION' AND entity_id = " . (int) $installation['id'] . " AND signer_name = 'Offline signer'")->fetchColumn();
$eq('one signature', 1, $sigCount);

$pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?')->execute(['phase8-secret', 'courier_webhook_secret']);
\App\Services\SettingsService::forget();
$hook = ['shipment_id' => (int) $okShip['id'], 'status' => 'IN_TRANSIT', 'external_event_id' => 'evt-' . $stamp, 'description' => 'Hub scan'];
$firstHook = $logistics->webhook('phase8-secret', $hook, $userId);
$secondHook = $logistics->webhook('phase8-secret', $hook, $userId);
$eq('webhook stored', false, $firstHook['duplicate']);
$eq('webhook duplicate', true, $secondHook['duplicate']);
$eq('bad webhook', true, $logistics->webhook('nope', $hook, $userId)['errors'] !== []);
$events = (int) $pdo->query('SELECT COUNT(*) FROM shipment_tracking_events WHERE external_event_id = ' . $pdo->quote('evt-' . $stamp))->fetchColumn();
$eq('one webhook row', 1, $events);

$token = $logistics->issueTrackingToken((int) $okShip['id'], $userId);
$eq('token works', (string) $safe['shipment_number'], (string) ($logistics->publicTrack((string) $token)['shipment_number'] ?? ''));
$logistics->revokeTracking((int) $okShip['id'], $userId);
$eq('revoked token', null, $logistics->publicTrack((string) $token));
$pdo->prepare('INSERT INTO shipment_access_tokens (shipment_id, token_hash, expires_at) VALUES (?,?,?)')->execute([(int) $okShip['id'], hash('sha256', 'expired-' . $stamp), '2000-01-01 00:00:00']);
$eq('expired token', null, $logistics->publicTrack('expired-' . $stamp));

$travel = $logistics->recordTravel(['entity_type' => 'SHIPMENT', 'entity_id' => $shipmentId, 'start_odometer' => '100', 'end_odometer' => '90'], $userId);
$contains('odometer', 'end reading', (string) ($travel['end_odometer'] ?? ''));
$eq('map link', true, str_starts_with((string) TrackingUrl::mapLink('12 Main Road'), 'https://'));

$run = $logistics->createRun(['run_date' => date('Y-m-d'), 'driver_user_id' => $userId], $userId);
$stops = [];
foreach (['A', 'B', 'C'] as $code) {
    $made = $logistics->createShipment(['shipment_type' => 'OWN_DELIVERY', 'customer_id' => $customerId, 'job_id' => $jobId, 'destination_address' => 'Stop ' . $code, 'contact_name' => 'Stop'], $userId);
    $logistics->addStop((int) $run['id'], (int) $made['id'], count($stops) + 1, $userId);
    $stops[] = (int) $pdo->query('SELECT id FROM delivery_stops WHERE shipment_id = ' . (int) $made['id'])->fetchColumn();
}
$eq('stop delivered', [], $logistics->updateStop($stops[0], 'DELIVERED', $userId));
$eq('stop failed', [], $logistics->updateStop($stops[1], 'FAILED', $userId, 'NO_ONE_AVAILABLE', 'Nobody home'));
$eq('partial exception', true, (int) $logistics->openException(['shipment_id' => (int) $pdo->query('SELECT shipment_id FROM delivery_stops WHERE id = ' . $stops[2])->fetchColumn(), 'exception_type' => 'MISSING_ITEM', 'description' => 'One package short', 'quantity' => '1'], $userId)['id'] > 0);
$statuses = $pdo->query('SELECT status FROM delivery_stops WHERE id IN (' . implode(',', $stops) . ') ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$eq('independent stops', ['DELIVERED', 'FAILED', 'PLANNED'], array_values($statuses));

$pdo->prepare('INSERT INTO service_requests (request_number, customer_id, description, created_by) VALUES (?,?,?,?)')->execute(['SFSR8-' . $stamp, $customerId, 'Light out', $userId]);
$requestId = (int) $pdo->lastInsertId();
$eq('warranty split', [], $contractors->recordServiceSplit($requestId, '0', '800', '800', (int) $savedContractor['id'], (int) $order['id']));
$split = (new ContractorRepository())->serviceSplit($requestId);
$eq('customer charge separate', 0, Decimal::cmp((string) $split['customer_charge'], '0'));
$eq('internal separate', 0, Decimal::cmp((string) $split['internal_cost'], '800'));
$eq('recovery separate', 0, Decimal::cmp((string) $split['recovery_amount'], '800'));

$started = microtime(true);
$pdo->beginTransaction();
for ($i = 1; $i <= 200; $i++) {
    $pdo->prepare('INSERT INTO shipments (shipment_number, shipment_type, status, customer_id, job_id, destination_address, contact_name, created_by) VALUES (?,?,?,?,?,?,?,?)')
        ->execute(['PERF' . $stamp . $i, 'COURIER', 'IN_TRANSIT', $customerId, $jobId, 'Perf', 'Perf', $userId]);
    $perfId = (int) $pdo->lastInsertId();
    if ($i <= 50) {
        $pdo->prepare('INSERT INTO shipment_tracking_events (shipment_id, status, event_time, description, source) VALUES (?,?,?,?,?)')->execute([$perfId, 'IN_TRANSIT', date('Y-m-d H:i:s'), 'Perf', 'MANUAL']);
    }
}
$pdo->commit();
$page = $logistics->page(['status' => 'IN_TRANSIT'], 25, 0);
$elapsed = microtime(true) - $started;
$eq('page returns a slice', 25, count($page));
$eq('page stays under five seconds', true, $elapsed < 5);
fwrite(STDOUT, 'info logistics page ' . number_format($elapsed, 3) . "s for 200 shipments\n");

$linked = (int) $pdo->query('SELECT COUNT(*) FROM shipment_items si INNER JOIN shipments s ON s.id = si.shipment_id INNER JOIN shipment_pods p ON p.shipment_id = s.id WHERE s.id = ' . (int) $okShip['id'])->fetchColumn();
$eq('restore relationship', true, $linked >= 0 && $repo->pod((int) $okShip['id']) !== null);

$index = $pdo->query("SHOW INDEX FROM shipments WHERE Key_name = 'idx_shipments_status'")->fetch();
$eq('status index', true, $index !== false);

$cards = $logistics->dashboard();
$eq('dashboard has ready card', true, array_key_exists('ready_to_dispatch', $cards));
$eq('courier report', true, count($logistics->courierPerformance()) >= 1);
$eq('expense captured not paid', 'CAPTURED', (string) $pdo->query('SELECT status FROM field_expenses WHERE id = ' . (int) $logistics->captureExpense(['entity_type' => 'JOB', 'entity_id' => $jobId, 'expense_type' => 'PARKING', 'amount' => '40', 'receipt_path' => 'receipts/p.jpg'], $userId)['id'])->fetchColumn());

fwrite(STDOUT, $failures === 0 ? "PHASE8_OK\n" : "PHASE8_FAIL {$failures}\n");
exit($failures === 0 ? 0 : 1);

<?php

declare(strict_types=1);

/**
 * Phase 11 workshop tracking, labels, dispatch, and documents.
 *
 *   php tests/phase11.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CategoryRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\UserRepository;
use App\Repositories\WorkshopRepository;
use App\Services\CustomerService;
use App\Services\DispatchService;
use App\Services\DocumentTemplateEngine;
use App\Services\JobService;
use App\Services\KioskService;
use App\Services\LabelService;
use App\Services\MaterialScanService;
use App\Services\ProductService;
use App\Services\ProductionItemService;
use App\Services\ProofOfDeliveryService;
use App\Services\QualityCheckService;
use App\Services\QuoteService;
use App\Services\SnagService;
use App\Services\StockMovementService;
use App\Services\TrackingCodeService;
use App\Services\UserAdminService;
use App\Services\WorkshopActionService;
use App\Services\WorkshopDocumentService;

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
$userId = (int) $admin['id'];
$stamp = date('YmdHis') . bin2hex(random_bytes(2));
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

$engine = new DocumentTemplateEngine();
$rendered = $engine->render('{{job.number}} <?php echo 1; ?> {{phpinfo()}} {{job.cost}}', ['job.number' => 'SFJ-1']);
$eq('document template keeps the job number', true, str_contains($rendered, 'SFJ-1'));
$eq('document template does not run php', true, str_contains($rendered, '<?php echo 1; ?>') && str_contains($rendered, '{{phpinfo()}}'));
$eq('document template drops unknown cost', true, !str_contains($rendered, 'job.cost'));

$tracking = new TrackingCodeService();
$eq('tampered token denied', null, $tracking->resolve('not-a-token'));
$eq('raw id is not a token', null, $tracking->resolve('1'));
$issued = $tracking->issue('JOB', 1, 'SFJ-TEST', $userId);
$eq('issued token resolves', 'JOB', $tracking->resolve($issued['token'])['entity_type'] ?? null);
$eq('changed token denied', null, $tracking->resolve(substr($issued['token'], 0, -1) . ($issued['token'][-1] === 'a' ? 'b' : 'a')));

$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Phase11 ' . $stamp,
    'active' => '1',
], $userId);
$customerId = (int) $customer['id'];
$categoryId = (int) (new CategoryRepository())->allWithParent()[0]['id'];
$product = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P11-' . $stamp,
    'name' => 'Orajet 3164 ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'AREA',
    'cost_price' => '10',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'ROLL',
    'roll_width_mm' => '1370',
], $userId);
$other = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P11B-' . $stamp,
    'name' => 'Orajet 3551 ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'AREA',
    'cost_price' => '12',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'ROLL',
    'roll_width_mm' => '1370',
], $userId);
$sheetProduct = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P11S-' . $stamp,
    'name' => '3mm ACM ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'SHEET',
    'cost_price' => '80',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'SHEET',
    'sheet_width_mm' => '1220',
    'sheet_height_mm' => '2440',
], $userId);
$quotes = new QuoteService();
$quoteRepo = new QuoteRepository();
$made = $quotes->create(['customer_id' => $customerId], $userId);
$quoteId = (int) $made['id'];
$quotes->addCustomLine($quoteId, [
    'customer_description' => 'Printed ACM',
    'quantity' => '10',
    'unit_cost' => '20',
    'final_sell_price' => '40',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quotes->changeStatus($quoteId, 'READY', (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quotes->accept($quoteId, [
    'accepted_by_name' => 'Buyer',
    'acceptance_method' => 'EMAIL',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$converted = $quotes->convert($quoteId, [
    'title' => 'Phase 11 ' . $stamp,
    'delivery_method' => 'COLLECTION',
    'target_date' => date('Y-m-d', strtotime('+7 days')),
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
if ($converted['id'] === null) {
    fwrite(STDERR, 'convert ' . json_encode($converted['errors']) . "\n");
    exit(1);
}
$jobId = (int) $converted['id'];
$jobItem = (new OperationsRepository())->items($jobId)[0];
$itemId = (int) $jobItem['id'];
Database::connection()->prepare('UPDATE job_items SET quantity = 10, tracking_mode = ? WHERE id = ?')->execute(['BATCH', $itemId]);
$jobItem = (new OperationsRepository())->item($itemId);
$ids = (new ProductionItemService())->generateForItem($jobItem, (new JobRepository())->find($jobId), $userId);
$eq('batch is one record', 1, count($ids));
$piece = (new WorkshopRepository())->productionItem($ids[0]);
$eq('batch keeps quantity 10', '10.0000', (string) $piece['quantity']);
$done = (new WorkshopActionService())->complete((int) $piece['id'], '6', $userId, 'partial-' . $stamp);
$eq('partial completion saved', [], $done);
$piece = (new WorkshopRepository())->productionItem((int) $piece['id']);
$eq('six complete', '6.0000', (string) $piece['quantity_completed']);
$eq('four still open on the piece', 'PARTIAL', (string) $piece['status']);
$jobItem = (new OperationsRepository())->item($itemId);
$eq('job item not fully complete', true, (string) $jobItem['production_status'] !== 'COMPLETE');

$location = Database::connection()->query('SELECT id FROM stock_locations ORDER BY id LIMIT 1')->fetch();
$locationId = (int) $location['id'];
$stock = new StockMovementService();
$rollOpen = $stock->opening([
    'product_id' => (int) $product['id'],
    'stock_location_id' => $locationId,
    'quantity' => '1',
    'length_m' => '20',
    'width_mm' => '1370',
    'unit_cost' => '10',
], $userId);
$wrongOpen = $stock->opening([
    'product_id' => (int) $other['id'],
    'stock_location_id' => $locationId,
    'quantity' => '1',
    'length_m' => '20',
    'width_mm' => '1370',
    'unit_cost' => '10',
], $userId);
$roll = (new InventoryRepository())->item((int) $rollOpen['id']);
$wrong = (new InventoryRepository())->item((int) $wrongOpen['id']);
$movesBefore = (new WorkshopRepository())->movementCount((int) $roll['id'], 'JOB_CONSUMPTION');
$bad = (new MaterialScanService())->issue($jobId, [
    'code' => (string) $wrong['inventory_code'],
    'production_quantity' => '1',
    'idempotency_key' => 'bad-' . $stamp,
    'expected_product_id' => (int) $product['id'],
], $userId);
$eq('wrong material blocked', true, str_contains((string) ($bad['errors']['_form'] ?? ''), 'MATERIAL DOES NOT MATCH'));
$eq('wrong material did not move stock', $movesBefore, (new WorkshopRepository())->movementCount((int) $wrong['id'], 'JOB_CONSUMPTION'));
$issued = (new MaterialScanService())->issue($jobId, [
    'code' => (string) $roll['inventory_code'],
    'production_quantity' => '4',
    'idempotency_key' => 'use-' . $stamp,
    'job_item_id' => $itemId,
], $userId);
$eq('material issued', true, $issued['id'] !== null && $issued['errors'] === []);
$again = (new MaterialScanService())->issue($jobId, [
    'code' => (string) $roll['inventory_code'],
    'production_quantity' => '4',
    'idempotency_key' => 'use-' . $stamp,
    'job_item_id' => $itemId,
], $userId);
$eq('repeat issue is the same row', $issued['id'], $again['id']);
$roll = (new InventoryRepository())->item((int) $roll['id']);
$eq('roll remaining 16 m', 0, Decimal::cmp((string) $roll['remaining_quantity'], '16'));
$eq('one consumption movement', $movesBefore + 1, (new WorkshopRepository())->movementCount((int) $roll['id'], 'JOB_CONSUMPTION'));
$usage = Database::connection()->prepare('SELECT COUNT(*) FROM job_material_usage WHERE job_id = ? AND product_id = ? AND usage_type = ?');
$usage->execute([$jobId, (int) $product['id'], 'PRODUCTION']);
$eq('usage recorded once', 1, (int) $usage->fetchColumn());

$sheetOpen = $stock->opening([
    'product_id' => (int) $sheetProduct['id'],
    'stock_location_id' => $locationId,
    'quantity' => '1',
    'unit_cost' => '80',
    'width_mm' => '1220',
    'height_mm' => '2440',
], $userId);
$sheet = (new InventoryRepository())->item((int) $sheetOpen['id']);
$offcutIssue = (new MaterialScanService())->issue($jobId, [
    'code' => (string) $sheet['inventory_code'],
    'production_quantity' => '1',
    'idempotency_key' => 'sheet-' . $stamp,
    'offcut_width_mm' => '800',
    'offcut_height_mm' => '500',
], $userId);
$eq('sheet issue created offcut', true, $offcutIssue['id'] !== null);
$offcutRow = Database::connection()->prepare('SELECT * FROM inventory_items WHERE source_inventory_item_id = ? AND inventory_type = ?');
$offcutRow->execute([(int) $sheet['id'], 'OFFCUT']);
$offcut = $offcutRow->fetch();
$eq('offcut exists', true, is_array($offcut));
$eq('offcut width', '800.00', (string) ($offcut['width_mm'] ?? ''));
$eq('offcut source job', $jobId, (int) ($offcut['source_job_id'] ?? 0));
$label = (new LabelService())->preview('OFFCUT', (int) $offcut['id'], null, 1, $userId, false);
$eq('offcut label available', true, ($label['error'] ?? '') === '' && str_contains($label['html'], (string) $offcut['inventory_code']));
$beforeCount = (int) Database::connection()->query('SELECT COUNT(*) FROM inventory_items')->fetchColumn();
$reprint = (new LabelService())->preview('OFFCUT', (int) $offcut['id'], null, 1, $userId, true, 'Damaged corner');
$afterCount = (int) Database::connection()->query('SELECT COUNT(*) FROM inventory_items')->fetchColumn();
$eq('reprint did not create stock', $beforeCount, $afterCount);
$audit = Database::connection()->prepare("SELECT COUNT(*) FROM audit_log WHERE action = 'LABEL_REPRINTED' AND entity_id = ?");
$audit->execute([(int) $offcut['id']]);
$eq('reprint audited', 1, (int) $audit->fetchColumn());

$qc = (new QualityCheckService())->record($jobId, [
    'production_item_id' => (int) $piece['id'],
    'status' => 'FAIL',
    'fail_reason' => 'Edge damage',
    'fail_action' => 'REPRINT',
    'check_type' => 'FINAL',
], $userId);
$eq('qc failure stored', [], $qc);
$dispatch = (new DispatchService())->create($jobId, ['dispatch_type' => 'COLLECTION'], $userId);
$blocked = (new DispatchService())->scan((int) $dispatch['id'], (string) $piece['tracking_code'], $userId);
$eq('failed qc blocks dispatch', true, str_contains((string) ($blocked['errors']['_form'] ?? ''), 'Quality control'));
$openFail = (new WorkshopRepository())->unresolvedFail($jobId);
$eq('qc override', [], (new QualityCheckService())->override((int) $openFail['id'], 'Reprint arranged', $userId));

Database::connection()->prepare('UPDATE job_items SET quantity = 5, tracking_mode = ? WHERE id = ?')->execute(['INDIVIDUAL', $itemId]);
Database::connection()->prepare('DELETE FROM production_items WHERE job_item_id = ?')->execute([$itemId]);
$jobItem = (new OperationsRepository())->item($itemId);
$five = (new ProductionItemService())->generateForItem($jobItem, (new JobRepository())->find($jobId), $userId);
$eq('five individual pieces', 5, count($five));
foreach ($five as $pieceId) {
    (new WorkshopActionService())->complete($pieceId, '1', $userId, 'done-' . $pieceId . '-' . $stamp);
}
$pack = (new DispatchService())->create($jobId, ['dispatch_type' => 'DELIVERY'], $userId);
$packId = (int) $pack['id'];
$codes = [];
foreach ($five as $pieceId) {
    $codes[] = (string) (new WorkshopRepository())->productionItem($pieceId)['tracking_code'];
}
for ($i = 0; $i < 4; $i++) {
    (new DispatchService())->scan($packId, $codes[$i], $userId);
}
$progress = (new DispatchService())->progress($packId);
$eq('four of five scanned', 4, $progress['scanned']);
$eq('dispatch incomplete', 1, $progress['missing']);
$finish = (new DispatchService())->markDispatched($packId, $userId);
$eq('incomplete dispatch warning', true, str_contains((string) ($finish['_form'] ?? ''), 'INCOMPLETE DISPATCH'));
$loadedBefore = 0;
foreach ((new WorkshopRepository())->dispatchItems($packId) as $row) {
    if ((string) $row['status'] === 'LOADED') {
        $loadedBefore++;
    }
}
$dup = (new DispatchService())->scan($packId, $codes[0], $userId);
$eq('duplicate scan flagged', true, $dup['duplicate'] === true);
$loadedAfter = 0;
foreach ((new WorkshopRepository())->dispatchItems($packId) as $row) {
    if ((string) $row['status'] === 'LOADED') {
        $loadedAfter++;
    }
}
$eq('duplicate did not add quantity', $loadedBefore, $loadedAfter);

$otherJob = $quotes->create(['customer_id' => $customerId], $userId);
$otherQuote = (int) $otherJob['id'];
$quotes->addCustomLine($otherQuote, [
    'customer_description' => 'Other job',
    'quantity' => '1',
    'unit_cost' => '5',
    'final_sell_price' => '9',
], (int) $quoteRepo->find($otherQuote)['version_number'], $userId);
$quotes->changeStatus($otherQuote, 'READY', (int) $quoteRepo->find($otherQuote)['version_number'], $userId);
$quotes->accept($otherQuote, ['accepted_by_name' => 'Buyer', 'acceptance_method' => 'EMAIL'], (int) $quoteRepo->find($otherQuote)['version_number'], $userId);
$otherConverted = $quotes->convert($otherQuote, ['title' => 'Other ' . $stamp, 'delivery_method' => 'COLLECTION'], (int) $quoteRepo->find($otherQuote)['version_number'], $userId);
$otherItem = (new OperationsRepository())->items((int) $otherConverted['id'])[0];
Database::connection()->prepare('UPDATE job_items SET tracking_mode = ? WHERE id = ?')->execute(['BATCH', (int) $otherItem['id']]);
$otherPiece = (new ProductionItemService())->generateForItem(
    (new OperationsRepository())->item((int) $otherItem['id']),
    (new JobRepository())->find((int) $otherConverted['id']),
    $userId
)[0];
$wrongJob = (new DispatchService())->scan($packId, (string) (new WorkshopRepository())->productionItem($otherPiece)['tracking_code'], $userId);
$eq('wrong job warning', true, str_contains((string) ($wrongJob['errors']['_form'] ?? ''), 'WRONG JOB'));

(new DispatchService())->scan($packId, $codes[4], $userId);
$pod = (new ProofOfDeliveryService())->capture($packId, [
    'recipient_name' => 'Ayesha',
    'recipient_contact' => '0820000000',
    'signature_png' => base64_encode((string) $png),
    'client_signed_at' => '2001-01-01 00:00:00',
], $userId);
$eq('pod stored', true, $pod['id'] !== null && $pod['errors'] === []);
$podRow = (new WorkshopRepository())->podByDispatch($packId);
$eq('pod uses server time', true, (string) $podRow['delivery_datetime'] !== '2001-01-01 00:00:00');
$doc = (new WorkshopRepository())->document((int) $pod['document_id']);
$eq('signed document immutable', 1, (int) ($doc['immutable'] ?? 0));
$againPod = (new ProofOfDeliveryService())->capture($packId, [
    'recipient_name' => 'Someone else',
    'signature_png' => base64_encode((string) $png),
], $userId);
$eq('second pod does not replace the first', $pod['id'], $againPod['id']);
$eq('recipient unchanged', 'Ayesha', (string) (new WorkshopRepository())->podByDispatch($packId)['recipient_name']);

$card = (new WorkshopDocumentService())->jobCard($jobId, $userId, false);
$eq('job card has no selling price', true, !str_contains($card['html'], 'Gross profit') && !str_contains($card['html'], 'margin'));
Database::connection()->prepare(
    'INSERT INTO job_artworks (job_id, title, revision_number, original_filename, stored_filename, mime_type, file_size, status, uploaded_by)
     VALUES (?, ?, 2, ?, ?, ?, 10, ?, ?)'
)->execute([$jobId, 'Front', 'front.pdf', 'stored.pdf', 'application/pdf', 'DRAFT', $userId]);
$artworkId = (int) Database::connection()->lastInsertId();
$approved = (new JobService())->recordApproval($jobId, $artworkId, [
    'customer_name' => 'Buyer',
    'approval_method' => 'EMAIL',
], $userId);
$eq('artwork approval', [], $approved);
$old = (new WorkshopRepository())->document((int) $card['document_id']);
$eq('old job card superseded', 'SUPERSEDED', (string) $old['status']);
$fresh = (new WorkshopDocumentService())->jobCard($jobId, $userId, false);
$eq('new card uses latest revision', true, str_contains($fresh['html'], 'rev 2'));
$eq('new card says it was updated', true, str_contains($fresh['html'], 'JOB CARD UPDATED'));

$closeQuote = $quotes->create(['customer_id' => $customerId], $userId);
$closeId = (int) $closeQuote['id'];
$quotes->addCustomLine($closeId, [
    'customer_description' => 'Service',
    'quantity' => '1',
    'unit_cost' => '5',
    'final_sell_price' => '9',
], (int) $quoteRepo->find($closeId)['version_number'], $userId);
$quotes->changeStatus($closeId, 'READY', (int) $quoteRepo->find($closeId)['version_number'], $userId);
$quotes->accept($closeId, ['accepted_by_name' => 'Buyer', 'acceptance_method' => 'EMAIL'], (int) $quoteRepo->find($closeId)['version_number'], $userId);
$closeJob = $quotes->convert($closeId, ['title' => 'Close ' . $stamp, 'delivery_method' => 'COLLECTION'], (int) $quoteRepo->find($closeId)['version_number'], $userId);
$closeJobId = (int) $closeJob['id'];
Database::connection()->prepare(
    "UPDATE jobs SET status = 'READY_FOR_COLLECTION', delivery_method = 'COLLECTION' WHERE id = ?"
)->execute([$closeJobId]);
Database::connection()->prepare(
    "UPDATE job_items SET artwork_required = 0, production_status = 'COMPLETE' WHERE job_id = ?"
)->execute([$closeJobId]);
(new SnagService())->create($closeJobId, ['description' => 'Bracket missing', 'priority' => 'CRITICAL'], $userId);
$version = (int) (new JobRepository())->find($closeJobId)['version_number'];
$blockedClose = (new JobService())->changeStatus($closeJobId, 'COMPLETED', $version, $userId);
$eq('critical snag blocks completion', true, str_contains((string) ($blockedClose['_form'] ?? ''), 'critical snag'));
$version = (int) (new JobRepository())->find($closeJobId)['version_number'];
$overridden = (new JobService())->changeStatus($closeJobId, 'COMPLETED', $version, $userId, '', 'Customer accepted the bracket later');
$eq('override completed the job', [], $overridden);
$overrideAudit = Database::connection()->prepare("SELECT COUNT(*) FROM audit_log WHERE entity_type = 'job' AND entity_id = ? AND action = 'COMPLETION_OVERRIDE'");
$overrideAudit->execute([$closeJobId]);
$eq('override audited', 1, (int) $overrideAudit->fetchColumn());

$roles = [];
foreach (Database::connection()->query('SELECT id, code FROM roles') as $role) {
    $roles[(string) $role['code']] = (int) $role['id'];
}
$users = new UserAdminService();
$workshopUser = $users->create([
    'name' => 'Workshop ' . $stamp,
    'email' => 'phase11-workshop-' . $stamp . '@signforge.local',
    'password' => 'Workshop#2026',
    'role_id' => $roles['PRODUCTION'],
    'active' => '1',
]);
$installerUser = $users->create([
    'name' => 'Installer ' . $stamp,
    'email' => 'phase11-installer-' . $stamp . '@signforge.local',
    'password' => 'Installer#2026',
    'role_id' => $roles['INSTALLER'],
    'active' => '1',
]);
$run = static function (string $action, int $actor, int $extra) use ($fail, $ok): void {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phase11_security.php')
        . ' ' . escapeshellarg($action) . ' ' . $actor . ' ' . $extra;
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);
    $text = implode("\n", $output);
    if ($code !== 0) {
        $fail(trim($text) === '' ? $action : trim($text));

        return;
    }
    if (str_contains($text, 'ok   ')) {
        $ok(trim(strstr($text, 'ok   ') ?: $text));

        return;
    }
    if (str_contains($text, 'Not allowed')) {
        $ok($action . ' url denied');

        return;
    }
    $fail($action . ' ' . substr($text, 0, 180));
};
Database::connection()->prepare('UPDATE users SET must_change_password = 0 WHERE id IN (?, ?)')->execute([
    (int) $workshopUser['id'],
    (int) $installerUser['id'],
]);
$run('workshop-cost', (int) $workshopUser['id'], $jobId);
$run('installer-cost', (int) $installerUser['id'], (int) $product['id']);

$pin = (new KioskService())->setPin((int) $workshopUser['id'], '2468', $userId);
$eq('pin stored hashed', [], $pin);
$storedPin = (new WorkshopRepository())->pin((int) $workshopUser['id']);
$eq('pin is not plain text', true, !str_contains((string) $storedPin['pin_hash'], '2468'));
$badge = $tracking->issue('USER', (int) $workshopUser['id'], 'BADGE-' . $stamp, $userId);
$identified = (new KioskService())->identifyByBadge($badge['token']);
$eq('badge opens the workshop user', (int) $workshopUser['id'], $identified['user_id']);
$eq('kiosk is not an admin session', 'PRODUCTION', (string) (auth_user()['role_code'] ?? ''));

if ($failures > 0) {
    fwrite(STDERR, $failures . " phase 11 check(s) failed.\n");
    exit(1);
}
fwrite(STDOUT, "phase 11 ok\n");

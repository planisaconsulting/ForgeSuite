<?php

declare(strict_types=1);

/**
 * v1.1 Phase 4: release to production and fulfilment.
 *
 *   php tests/v11_phase4.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\ProductionControlRepository;
use App\Repositories\QuoteRepository;
use App\Services\CustomerService;
use App\Services\FulfilmentService;
use App\Services\ProductService;
use App\Services\ProductionChangeImpactService;
use App\Services\ProductionPlanningService;
use App\Services\ProductionReleaseService;
use App\Services\ProductionStageControlService;
use App\Services\QuoteService;
use App\Services\ReleaseReadinessService;
use App\Services\StockMovementService;

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
$repo = new ProductionControlRepository();
$releases = new ProductionReleaseService();
$quotes = new QuoteService();
$quoteRepo = new QuoteRepository();

$customer = (new CustomerService())->create(['customer_type' => 'BUSINESS', 'company_name' => 'Phase4 ' . $stamp], $userId);
$customerId = (int) $customer['id'];
$product = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P4-' . $stamp,
    'name' => 'Phase 4 sheet',
    'product_type' => 'MATERIAL',
    'pricing_method' => 'UNIT',
    'cost_price' => '10',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'QUANTITY',
], $userId);
$productId = (int) $product['id'];

$make = static function (string $title, string $qty = '1') use ($quotes, $quoteRepo, $customerId, $productId, $userId, $pdo): int {
    $quote = $quotes->create(['customer_id' => $customerId], $userId);
    $quoteId = (int) $quote['id'];
    $quotes->addProductLine($quoteId, [
        'product_id' => $productId,
        'quantity' => $qty,
        'waste_mode' => 'ACTUAL',
        'customer_description' => $title,
    ], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
    $quotes->changeStatus($quoteId, 'READY', (int) $quoteRepo->find($quoteId)['version_number'], $userId);
    $quotes->accept($quoteId, ['accepted_by_name' => 'Tester', 'acceptance_method' => 'EMAIL'], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
    $job = $quotes->convert($quoteId, ['title' => $title, 'delivery_method' => 'COLLECTION', 'site_address' => '12 Main Road'], (int) $quoteRepo->find($quoteId)['version_number'], $userId);

    return (int) $job['id'];
};

$plain = $make('Accepted not released');
$prep = (string) $pdo->query('SELECT preparation_status FROM jobs WHERE id = ' . $plain)->fetchColumn();
$eq('accepted is not released', 'NOT_READY', $prep);
$eq('no release row', null, $repo->currentRelease($plain));

$artworkJob = $make('Artwork block');
$evaluated = (new ReleaseReadinessService())->evaluate($artworkJob);
$blocked = false;
foreach ($evaluated['checks'] as $check) {
    if ($check['check_code'] === 'ARTWORK_APPROVED' && $check['result'] === 'BLOCK') {
        $blocked = true;
    }
}
$eq('artwork blocks', true, $blocked);
$deniedRelease = $releases->release($artworkJob, $userId, []);
$eq('artwork release refused', true, isset($deniedRelease['errors']['_form']));
$eq('artwork not authorised', 'RELEASE_BLOCKED', (string) $pdo->query('SELECT preparation_status FROM jobs WHERE id = ' . $artworkJob)->fetchColumn());

$stock = (new StockMovementService())->opening([
    'product_id' => $productId,
    'stock_location_id' => $locationId,
    'quantity' => '7',
    'unit_cost' => '10',
], $userId);
$eq('opening stock', true, ($stock['errors'] ?? []) === []);
$shortJob = $make('Shortage');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, width_mm = 1000, height_mm = 500 WHERE job_id = ?')->execute([$shortJob]);
$pdo->prepare('INSERT INTO job_material_requirements (job_id, product_id, required_quantity, final_required_quantity, unit, source) VALUES (?, ?, 10, 10, ?, ?)')->execute([$shortJob, $productId, 'sheet', 'MANUAL']);
$shortEval = (new ReleaseReadinessService())->evaluate($shortJob);
$shortWarn = false;
foreach ($shortEval['checks'] as $check) {
    if ($check['check_code'] === 'MATERIAL_AVAILABILITY' && $check['result'] === 'WARNING') {
        $shortWarn = true;
    }
}
$eq('shortage warns', true, $shortWarn);
$shortRelease = $releases->release($shortJob, $userId, []);
$eq('shortage can release', 'RELEASED', (string) ($shortRelease['release']['status'] ?? ''));

$techJob = $make('Technical block');
$pdo->prepare('UPDATE job_items SET artwork_required = 0 WHERE job_id = ?')->execute([$techJob]);
$pdo->prepare("INSERT INTO sign_calculations (estimator_type, job_id, status, technical_review_required, input_json, output_json, input_hash, created_by) VALUES ('LIGHTBOX', ?, 'REVIEW', 1, '{}', '{}', ?, ?)")->execute([$techJob, hash('sha256', 'tech' . $techJob), $userId]);
$tech = $releases->release($techJob, $userId, ['overrides' => [['check' => 'TECHNICAL_REVIEW', 'reason' => 'Try anyway']]]);
$eq('technical cannot be overridden', true, str_contains((string) ($tech['errors']['_form'] ?? ''), 'cannot be overridden'));

$more = (new StockMovementService())->opening([
    'product_id' => $productId,
    'stock_location_id' => $locationId,
    'quantity' => '10',
    'unit_cost' => '10',
], $userId);
$eq('more stock', true, ($more['errors'] ?? []) === []);
$reserveJob = $make('Reserve');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, width_mm = 100, height_mm = 100 WHERE job_id = ?')->execute([$reserveJob]);
$before = $pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE product_id = ' . $productId)->fetchColumn();
$key = 'rel-' . $stamp;
$reserved = $releases->release($reserveJob, $userId, [
    'idempotency_key' => $key,
    'reserve' => [['product_id' => $productId, 'quantity' => '5', 'stock_location_id' => $locationId]],
]);
$eq('reserved release', 'RELEASED', (string) ($reserved['release']['status'] ?? ''));
$eq('reservation linked', 1, $repo->reservationsForRelease((int) $reserved['id']));
$after = $pdo->query('SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE product_id = ' . $productId)->fetchColumn();
$eq('stock not consumed', (string) $before, (string) $after);
$again = $releases->release($reserveJob, $userId, ['idempotency_key' => $key, 'reserve' => [['product_id' => $productId, 'quantity' => '5', 'stock_location_id' => $locationId]]]);
$eq('retry same release', (int) $reserved['id'], (int) $again['id']);
$eq('retry no second reservation', 1, $repo->reservationsForRelease((int) $reserved['id']));

$changeJob = $make('Change');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, width_mm = 200, height_mm = 200 WHERE job_id = ?')->execute([$changeJob]);
$changed = $releases->release($changeJob, $userId, []);
$releaseId = (int) $changed['id'];
$number = (string) $changed['release']['release_number'];
$phone = (new ProductionChangeImpactService())->record($changeJob, 'PHONE', 'CUSTOMER', 'New mobile', 'Phone updated', $userId);
$eq('phone has no production impact', 'NO_PRODUCTION_IMPACT', $phone['impact']);
$eq('phone keeps release', 'RELEASED', (string) $repo->release($releaseId)['status']);
$dims = (new ProductionChangeImpactService())->record($changeJob, 'DIMENSIONS', 'CUSTOMER', 'Width changed', '2400 to 2600', $userId);
$eq('dimensions need re-release', 'RE_RELEASE_REQUIRED', $dims['impact']);
$eq('r1 retained', $number, (string) $repo->release($releaseId)['release_number']);
$eq('r1 needs review', 'REVIEW_REQUIRED', (string) $repo->release($releaseId)['status']);

$artJob = $make('Artwork revision');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, width_mm = 300, height_mm = 300 WHERE job_id = ?')->execute([$artJob]);
$pdo->prepare("INSERT INTO job_artworks (job_id, title, revision_number, original_filename, stored_filename, mime_type, file_size, status, customer_approved) VALUES (?, 'Proof', 3, 'a.svg', 'a.svg', 'image/svg+xml', 10, 'APPROVED', 1)")->execute([$artJob]);
$art = $releases->release($artJob, $userId, []);
$artNumber = (string) $art['release']['release_number'];
$pdo->prepare('UPDATE job_artworks SET revision_number = 4 WHERE job_id = ?')->execute([$artJob]);
$artImpact = (new ProductionChangeImpactService())->record($artJob, 'ARTWORK_REVISION', 'DESIGN', 'New proof', 'Artwork R4', $userId);
$eq('artwork revision flagged', 'RE_RELEASE_REQUIRED', $artImpact['impact']);
$art2 = $releases->release($artJob, $userId, ['new_version' => true]);
$eq('r2 released', 'RELEASED', (string) ($art2['release']['status'] ?? ''));
$eq('r2 is newer', true, (int) $art2['release']['release_version'] === 2);
$scan = $releases->scan($artNumber);
$eq('superseded warning', 'SUPERSEDED RELEASE — DO NOT PRODUCE.', $scan['warning']);
$eq('scan offers current', (string) $art2['release']['release_number'], (string) $scan['current']['release_number']);

$partJob = $make('Partial', '100');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, width_mm = 600, height_mm = 400, quantity = 100 WHERE job_id = ?')->execute([$partJob]);
$itemId = (int) $pdo->query('SELECT id FROM job_items WHERE job_id = ' . $partJob)->fetchColumn();
$part = $releases->release($partJob, $userId, ['items' => [['job_item_id' => $itemId, 'quantity' => '60']]]);
$eq('partial release', 'RELEASED', (string) ($part['release']['status'] ?? ''));
$eq('partial quantity', '60.0000', $repo->releasedQuantity($itemId));
$eq('partial status', 'PARTIALLY_RELEASED', (string) $pdo->query('SELECT release_status FROM job_items WHERE id = ' . $itemId)->fetchColumn());
$over = $releases->release($partJob, $userId, ['new_version' => true, 'items' => [['job_item_id' => $itemId, 'quantity' => '50']]]);
$eq('over release blocked', true, str_contains((string) ($over['errors']['_form'] ?? ''), 'authorised quantity'));

$multi = $make('Multi');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, width_mm = 1000, height_mm = 2000, quantity = 2, good_quantity = 2 WHERE job_id = ?')->execute([$multi]);
$pylonItem = (int) $pdo->query('SELECT id FROM job_items WHERE job_id = ' . $multi)->fetchColumn();
$pdo->prepare('INSERT INTO job_items (job_id, sort_order, product_id, description, quantity, artwork_required, width_mm, height_mm, good_quantity) VALUES (?, 20, ?, ?, 50, 0, 600, 400, 50)')->execute([$multi, $productId, 'Boards']);
$boardItem = (int) $pdo->query('SELECT id FROM job_items WHERE job_id = ' . $multi . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
$fulfilment = new FulfilmentService();
$install = $fulfilment->create($multi, ['job_item_id' => $pylonItem, 'fulfilment_type' => 'INSTALLATION', 'quantity' => '2', 'address' => 'Site A'], $userId);
$collect = $fulfilment->create($multi, ['job_item_id' => $boardItem, 'fulfilment_type' => 'COLLECTION', 'quantity' => '50', 'address' => 'Yard'], $userId);
$eq('two fulfilments', true, ($install['id'] ?? 0) > 0 && ($collect['id'] ?? 0) > 0);
$types = $pdo->query('SELECT fulfilment_type FROM fulfilment_requirements WHERE job_id = ' . $multi . ' ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$eq('installation and collection', ['INSTALLATION', 'COLLECTION'], $types);

$boards = $make('Boards', '100');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, width_mm = 500, height_mm = 500, quantity = 100, good_quantity = 100 WHERE job_id = ?')->execute([$boards]);
$boardLine = (int) $pdo->query('SELECT id FROM job_items WHERE job_id = ' . $boards)->fetchColumn();
$boardFulfil = $fulfilment->create($boards, ['job_item_id' => $boardLine, 'fulfilment_type' => 'COLLECTION', 'quantity' => '100'], $userId);
$eq('collect 60', [], $fulfilment->fulfil((int) $boardFulfil['id'], '60', $userId, 'Sam'));
$left = $pdo->query('SELECT status, fulfilled_quantity FROM fulfilment_requirements WHERE id = ' . (int) $boardFulfil['id'])->fetch();
$eq('partial fulfilment status', 'PARTIALLY_FULFILLED', (string) $left['status']);
$eq('outstanding 40', '60.0000', (string) $left['fulfilled_quantity']);
$eq('job not complete from production alone', true, $fulfilment->jobOpen($boards));

$capJob = $make('Cap');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, good_quantity = 80, quantity = 100 WHERE job_id = ?')->execute([$capJob]);
$capItem = (int) $pdo->query('SELECT id FROM job_items WHERE job_id = ' . $capJob)->fetchColumn();
$cap = $fulfilment->create($capJob, ['job_item_id' => $capItem, 'fulfilment_type' => 'DELIVERY', 'quantity' => '100'], $userId);
$capError = $fulfilment->fulfil((int) $cap['id'], '100', $userId);
$eq('cannot fulfil more than good', true, isset($capError['_form']));

$qcJob = $make('QC');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, good_quantity = 1, quantity = 1 WHERE job_id = ?')->execute([$qcJob]);
$qcItem = (int) $pdo->query('SELECT id FROM job_items WHERE job_id = ' . $qcJob)->fetchColumn();
$qcFulfil = $fulfilment->create($qcJob, ['job_item_id' => $qcItem, 'fulfilment_type' => 'COLLECTION', 'quantity' => '1'], $userId);
$eq('qc fail recorded', [], (new ProductionStageControlService())->qcFail($qcJob, 'Face scratch', $userId));
$qcBlock = $fulfilment->fulfil((int) $qcFulfil['id'], '1', $userId);
$eq('qc blocks fulfilment', true, str_contains((string) ($qcBlock['_form'] ?? ''), 'QC'));

$costJob = $make('Rework cost');
$pdo->prepare('UPDATE jobs SET actual_material_cost = 5000, actual_labour_cost = 0, actual_other_cost = 0, actual_total_cost = 5000 WHERE id = ?')->execute([$costJob]);
$rework = (new ProductionStageControlService())->rework($costJob, ['reason_code' => 'PRINT_DEFECT', 'material_cost' => '800'], $userId);
$eq('rework saved', true, ($rework['id'] ?? 0) > 0);
$total = $pdo->query('SELECT actual_total_cost FROM jobs WHERE id = ' . $costJob)->fetchColumn();
$eq('rework included in cost', '5800.00', number_format((float) $total, 2, '.', ''));

$specJob = $make('Spec lock');
$pdo->prepare('UPDATE job_items SET artwork_required = 0, width_mm = 4000, height_mm = 1200 WHERE job_id = ?')->execute([$specJob]);
$pdo->prepare('UPDATE jobs SET technical_snapshot_json = ? WHERE id = ?')->execute([json_encode(['specification_code' => 'LBX-ACM-001', 'specification_version' => 2]), $specJob]);
$specRelease = $releases->release($specJob, $userId, []);
$snap = json_decode((string) $repo->release((int) $specRelease['id'])['snapshot_json'], true);
$eq('release keeps v2', 2, (int) ($snap['technical']['specification_version'] ?? 0));

$salesId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'PRODUCTION' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $salesId;
forget_auth_user();
$workshop = $releases->release($plain, $salesId, []);
$eq('workshop cannot release', true, isset($workshop['errors']['_form']));
$marketId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'MARKETING' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $marketId;
forget_auth_user();
$idor = $releases->release($reserveJob, $marketId, []);
$eq('unrelated release denied', true, isset($idor['errors']['_form']));
$_SESSION['user_id'] = $userId;
forget_auth_user();

$plan = (new ProductionPlanningService())->latestStart('2026-03-20', ['install' => '1', 'pack' => '1', 'qc' => '0.5', 'assembly' => '1', 'print' => '1']);
$eq('planning label', 'PLANNING DATE', $plan['label']);
$eq('planning is dated', true, $plan['planning_date'] !== '');

$stageId = (int) $pdo->query("SELECT id FROM production_stages WHERE name NOT LIKE '%PRINT%' AND name NOT LIKE '%CNC%' AND name NOT LIKE '%ROUTER%' ORDER BY id LIMIT 1")->fetchColumn();
if ($stageId < 1) {
    $pdo->exec("INSERT INTO production_stages (name) VALUES ('Pack')");
    $stageId = (int) $pdo->lastInsertId();
}
$started = microtime(true);
$ready = [];
$warn = [];
$block = [];
for ($n = 1; $n <= 50; $n++) {
    $quoteNo = 'P4B-' . $stamp . '-' . $n;
    $pdo->prepare('INSERT INTO quotes (quote_number, quote_date, customer_id, status, created_by) VALUES (?, CURDATE(), ?, ?, ?)')->execute([$quoteNo, $customerId, 'ACCEPTED', $userId]);
    $quoteId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO jobs (job_number, customer_id, quote_id, quote_revision_number, title, status, preparation_status, target_date, site_address, delivery_method, created_by) VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)')->execute(['P4J-' . $stamp . '-' . $n, $customerId, $quoteId, 'Bulk ' . $n, 'NEW', 'NOT_READY', '2026-12-01', 'Branch ' . $n, 'COLLECTION', $userId]);
    $jobId = (int) $pdo->lastInsertId();
    $art = $n > 47 ? 1 : 0;
    $qty = $n > 42 && $n <= 47 ? '5000' : '1';
    $pdo->prepare('INSERT INTO job_items (job_id, product_id, description, quantity, artwork_required, width_mm, height_mm) VALUES (?, ?, ?, ?, ?, 100, 100)')->execute([$jobId, $productId, 'Board', $qty, $art]);
    $pdo->prepare('INSERT INTO job_material_requirements (job_id, product_id, required_quantity, final_required_quantity, unit, source) VALUES (?, ?, ?, ?, ?, ?)')->execute([$jobId, $productId, $qty, $qty, 'sheet', 'MANUAL']);
    $pdo->prepare('INSERT INTO job_production_stages (job_id, production_stage_id, sort_order, status) VALUES (?, ?, 1, ?)')->execute([$jobId, $stageId, 'NOT_STARTED']);
    if ($n <= 42) {
        $ready[] = $jobId;
    } elseif ($n <= 47) {
        $warn[] = $jobId;
    } else {
        $block[] = $jobId;
    }
}
$pdo->prepare('INSERT INTO qc_checklist_items (product_id, label, sort_order) VALUES (?, ?, 1)')->execute([$productId, 'Edges clean']);
$bulk = $releases->bulk(array_merge($ready, $warn, $block), $userId, false);
$eq('bulk ready', 42, count($bulk['ready']));
$eq('bulk warnings', 5, count($bulk['warnings']));
$eq('bulk blocked', 3, count($bulk['blocked']));
$eq('bulk released only ready', 42, count($bulk['released']));
$elapsed = microtime(true) - $started;
$eq('bulk time', true, $elapsed < 30);
fwrite(STDOUT, 'bulk ' . round($elapsed, 2) . "s\n");
$queueStarted = microtime(true);
$queue = $repo->queue([], 50, 0);
$eq('queue page', true, count($queue) <= 50 && (microtime(true) - $queueStarted) < 2);

if ($failures > 0) {
    fwrite(STDERR, "PHASE4_FAIL {$failures}\n");
    exit(1);
}
fwrite(STDOUT, "PHASE4_OK\n");

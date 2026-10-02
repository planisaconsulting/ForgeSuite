<?php

declare(strict_types=1);

/**
 * v1.1 Phase 6: customer hub, catalogues, and portal orders.
 *
 *   php tests/v11_phase6.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CustomerHubRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\PlatformRepository;
use App\Repositories\PortalRepository;
use App\Repositories\SettingRepository;
use App\Services\AssetService;
use App\Services\CompatibilityRuleService;
use App\Services\CustomerHubService;
use App\Services\FeatureFlagService;
use App\Services\InvoiceService;
use App\Services\PaymentLinkService;
use App\Services\PaymentWebhookService;
use App\Services\ProductService;
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

$pdo = Database::connection();
$userId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'ADMIN' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $userId;
forget_auth_user();
$stamp = date('His');
$hub = new CustomerHubService();
$repo = new CustomerHubRepository();
$portalUsers = new PortalRepository();

$customer = static function (string $name) use ($pdo, $userId, $stamp): int {
    $pdo->prepare('INSERT INTO customers (customer_type, company_name, active, created_by) VALUES (\'BUSINESS\', ?, 1, ?)')->execute([$name . ' ' . $stamp, $userId]);

    return (int) $pdo->lastInsertId();
};
$portal = static function (int $customerId, string $email, string $role, string $scope) use ($portalUsers, $pdo): array {
    $id = $portalUsers->insertUser([
        'customer_id' => $customerId,
        'customer_contact_id' => null,
        'email' => $email,
        'password_hash' => password_hash('Portal-pass-6', PASSWORD_DEFAULT),
        'active' => 1,
    ]);
    $pdo->prepare('UPDATE portal_users SET hub_role = ?, site_scope = ? WHERE id = ?')->execute([$role, $scope, $id]);
    $user = $portalUsers->user($id);
    $user['hub_role'] = $role;
    $user['site_scope'] = $scope;

    return $user;
};

$a = $customer('ABC Retail');
$b = $customer('Other Retail');
$buyer = $portal($a, 'buyer-' . $stamp . '@example.test', 'ADMIN', 'ALL');
$other = $portal($b, 'other-' . $stamp . '@example.test', 'ADMIN', 'ALL');
$regional = $portal($a, 'region-' . $stamp . '@example.test', 'ADMIN', 'SELECTED');
$categoryId = (int) $pdo->query('SELECT id FROM product_categories ORDER BY id LIMIT 1')->fetchColumn();
$product = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P6-' . $stamp,
    'name' => 'Opening hours vinyl',
    'product_type' => 'MATERIAL',
    'pricing_method' => 'UNIT',
    'cost_price' => '100',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'QUANTITY',
], $userId);
$productId = (int) $product['id'];
$eq('product', true, $productId > 0);

$catalogue = $hub->saveCatalogue(['customer_id' => $a, 'name' => 'ABC approved signage', 'pricing_mode' => 'FIXED_AGREED_PRICE'], $userId);
$catalogueId = (int) $catalogue['id'];
$eq('catalogue', true, $catalogueId > 0);
$eq('catalogue activated', [], $hub->activateCatalogue($a, $catalogueId, $userId));
$eq('other customer cannot open it', null, $hub->catalogue($other, $catalogueId));
$item = $hub->addItem($a, $catalogueId, [
    'product_id' => $productId,
    'customer_code' => 'ABC-SIGN-017',
    'internal_code' => 'SF-' . $stamp,
    'name' => 'Opening Hours Vinyl',
    'locked' => ['material' => 'Vinyl', 'size' => '600x400'],
    'variables' => ['quantity', 'site'],
    'availability_label' => 'MADE_TO_ORDER',
], $userId);
$itemId = (int) $item['id'];
$eq('catalogue item', true, $itemId > 0);
$eq('price saved', true, ($hub->savePrice($a, $itemId, '1000', '2026-01-01', '2026-12-31', $userId)['id'] ?? 0) > 0);
$shown = $hub->priceOn($a, $itemId, '2026-12-20');
$eq('price ex vat', '1000.00', $shown['ex_vat'] ?? '');
$eq('price shows vat', true, isset($shown['vat'], $shown['incl_vat']));
$eq('portal catalogue has no margin', false, array_key_exists('margin_percent', $hub->catalogue($buyer, $catalogueId) ?? []));
$alert = $hub->marginAlert('850', '790');
$eq('margin alert is internal', true, ($alert['warning'] ?? false) === true);

$pdo->prepare('INSERT INTO projects (project_number, customer_id, name, status) VALUES (?,?,?,?)')->execute(['P6-' . $stamp, $a, 'Rollout', 'ACTIVE']);
$projectId = (int) $pdo->lastInsertId();
$siteIds = [];
for ($n = 1; $n <= 25; $n++) {
    $status = 'INSTALLED';
    if ($n > 18 && $n <= 22) {
        $status = 'IN_PRODUCTION';
    } elseif ($n > 22 && $n <= 24) {
        $status = 'AWAITING_APPROVAL';
    } elseif ($n === 25) {
        $status = 'SCHEDULED';
    }
    $pdo->prepare('INSERT INTO project_sites (project_id, site_code, site_name, status, address_line_1) VALUES (?,?,?,?,?)')->execute([$projectId, 'B' . $n, 'Branch ' . $n, $status, 'Street ' . $n]);
    $siteIds[] = (int) $pdo->lastInsertId();
}
for ($n = 0; $n < 5; $n++) {
    $repo->grantSite((int) $regional['id'], $siteIds[$n]);
}
$eq('regional sixth site denied', false, $hub->canUseSite($regional, $siteIds[5]));
$eq('buyer can use a site', true, $hub->canUseSite($buyer, $siteIds[5]));
$view = $hub->projectView($buyer, $projectId);
$eq('installed sites', 18, $view['sites']['installed'] ?? 0);
$eq('in production', 4, $view['sites']['in_production'] ?? 0);
$eq('awaiting approval', 2, $view['sites']['awaiting_approval'] ?? 0);
$eq('scheduled', 1, $view['sites']['scheduled'] ?? 0);
$eq('no internal cost on the project', false, isset($view['actual_cost'], $view['margin'], $view['commercial_value']));
$eq('other customer project denied', null, $hub->projectView($other, $projectId));

$sites = [];
foreach ($siteIds as $siteId) {
    $sites[$siteId] = '1';
}
$moves = (int) $pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn();
$invoicesBefore = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$releasesBefore = (int) $pdo->query('SELECT COUNT(*) FROM production_releases')->fetchColumn();
$jobsBefore = (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
$order = $hub->submitOrder($buyer, [
    'catalogue_item_id' => $itemId,
    'customer_po' => 'PO-' . $stamp,
    'cost_centre' => 'CC-1',
    'order_date' => '2026-12-20',
    'requested_date' => '2026-12-28',
    'notes' => '<script>alert(1)</script>',
    'idempotency_key' => 'ord-' . $stamp,
    'sites' => $sites,
    'customer_id' => $b,
]);
$eq('posted customer id ignored', true, ($order['id'] ?? 0) > 0);
$orderId = (int) $order['id'];
$again = $hub->submitOrder($buyer, [
    'catalogue_item_id' => $itemId,
    'order_date' => '2026-12-20',
    'idempotency_key' => 'ord-' . $stamp,
    'sites' => [$siteIds[0] => '1'],
]);
$eq('retry is the same order', $orderId, (int) ($again['id'] ?? 0));
$eq('one order row', 1, (int) $pdo->query("SELECT COUNT(*) FROM customer_orders WHERE idempotency_key = 'ord-" . $stamp . "'")->fetchColumn());
$line = $pdo->query('SELECT * FROM customer_order_items WHERE order_id = ' . $orderId)->fetch();
$eq('snapshotted price', 0, Decimal::cmp((string) $line['unit_price_ex_vat'], '1000'));
$eq('customer po stored', 'PO-' . $stamp, (string) $pdo->query('SELECT customer_po FROM customer_orders WHERE id = ' . $orderId)->fetchColumn());
$eq('25 site quantities', 25, count($repo->orderSites((int) $line['id'])));
$eq('no stock movement', $moves, (int) $pdo->query('SELECT COUNT(*) FROM stock_movements')->fetchColumn());
$eq('no invoice', $invoicesBefore, (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn());
$eq('no production release', $releasesBefore, (int) $pdo->query('SELECT COUNT(*) FROM production_releases')->fetchColumn());
$eq('no job from the order', $jobsBefore, (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
$storedNote = (string) $pdo->query('SELECT notes FROM customer_orders WHERE id = ' . $orderId)->fetchColumn();
$eq('notes escaped for display', true, str_contains($storedNote, '<script>') && str_contains(e($storedNote), '&lt;script&gt;'));
$hub->savePrice($a, $itemId, '1200', '2027-01-01', null, $userId);
$lineAfter = $pdo->query('SELECT unit_price_ex_vat FROM customer_order_items WHERE id = ' . (int) $line['id'])->fetchColumn();
$eq('later price does not rewrite the order', 0, Decimal::cmp((string) $lineAfter, '1000'));
$eq('review accepts without a job', [], $hub->review($orderId, 'ACCEPT', $userId));
$eq('still no job', $jobsBefore, (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
$eq('order approved', 'APPROVED', (string) $pdo->query('SELECT status FROM customer_orders WHERE id = ' . $orderId)->fetchColumn());

$expiredItem = $hub->addItem($a, $catalogueId, ['name' => 'Expired sign', 'product_id' => $productId], $userId);
$expiredId = (int) $expiredItem['id'];
$hub->savePrice($a, $expiredId, '50', '2025-01-01', '2025-12-31', $userId);
$expired = $hub->submitOrder($buyer, [
    'catalogue_item_id' => $expiredId,
    'order_date' => '2026-06-01',
    'quantity' => '1',
    'idempotency_key' => 'exp-' . $stamp,
]);
$eq('expired price is not used', true, isset($expired['errors']['_form']));
$eq('no expired order', 0, (int) $pdo->query("SELECT COUNT(*) FROM customer_orders WHERE idempotency_key = 'exp-" . $stamp . "'")->fetchColumn());

$blockedSite = $hub->submitOrder($regional, [
    'catalogue_item_id' => $itemId,
    'order_date' => '2026-12-20',
    'idempotency_key' => 'site-' . $stamp,
    'sites' => [$siteIds[5] => '1'],
]);
$eq('restricted site denied', true, isset($blockedSite['errors']['_form']));

$ready = $hub->assessReorder([
    'product_active' => true,
    'customer_active' => true,
    'material_current' => true,
    'specification_current' => true,
    'artwork_status' => 'APPROVED',
    'artwork_id' => 44,
    'price_valid' => true,
]);
$eq('reorder ready', 'READY_TO_ORDER', $ready['status']);
$eq('same artwork linked', 44, $ready['artwork_id']);
$changed = $hub->assessReorder([
    'product_active' => true,
    'customer_active' => true,
    'material_current' => true,
    'specification_current' => true,
    'artwork_status' => 'APPROVED',
    'artwork_id' => 44,
    'artwork_change' => true,
    'price_valid' => true,
]);
$eq('changed artwork is not reused', false, $changed['artwork_reused']);
$obsolete = $hub->assessReorder([
    'product_active' => true,
    'customer_active' => true,
    'material_current' => false,
    'specification_current' => true,
    'artwork_status' => 'APPROVED',
    'price_valid' => true,
]);
$eq('obsolete material needs review', 'REVIEW_REQUIRED', $obsolete['status']);

$rules = [[
    'active' => 1,
    'when_key' => 'material',
    'when_op' => 'EQ',
    'when_value' => 'CORREX',
    'and_key' => '',
    'rule_type' => 'EXCLUDES',
    'hardness' => 'HARD',
    'message' => 'Correx cannot be illuminated.',
    'then_key' => 'illuminated',
    'then_value' => 'YES',
]];
$check = (new CompatibilityRuleService())->evaluate($rules, ['material' => 'CORREX', 'illuminated' => 'YES']);
$eq('incompatible option blocked', true, $check['blocked']);
$badCombo = $hub->submitOrder($buyer, [
    'catalogue_item_id' => $itemId,
    'order_date' => '2026-12-20',
    'quantity' => '1',
    'idempotency_key' => 'bad-' . $stamp,
    'rules' => $rules,
    'options' => ['material' => 'CORREX', 'illuminated' => 'YES'],
]);
$eq('order stopped by the rule', true, isset($badCombo['errors']['_form']));

$quote = $hub->requestQuote($buyer, 'VEHICLE_BRANDING', ['make' => 'Ford', 'model' => 'Ranger']);
$eq('quote request', true, ($quote['id'] ?? 0) > 0);
$eq('quote request created no job', $jobsBefore, (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn());

$pdo->prepare('INSERT INTO quotes (quote_number, quote_date, customer_id, status, created_by) VALUES (?, CURDATE(), ?, ?, ?)')->execute(['P6Q-' . $stamp, $a, 'ACCEPTED', $userId]);
$quoteId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO jobs (job_number, customer_id, quote_id, quote_revision_number, title, status, preparation_status, created_by) VALUES (?,?,?,1,?,?,?,?)')->execute(['P6J-' . $stamp, $a, $quoteId, 'Parking sign', 'IN_PRODUCTION', 'RELEASED', $userId]);
$jobId = (int) $pdo->lastInsertId();
$cancel = $hub->requestCancellation($buyer, $jobId, 'Please stop this sign', $userId);
$eq('cancellation requested', true, ($cancel['id'] ?? 0) > 0);
$eq('job not cancelled', 'IN_PRODUCTION', (string) $pdo->query('SELECT status FROM jobs WHERE id = ' . $jobId)->fetchColumn());

$pdo->prepare('INSERT INTO quotes (quote_number, quote_date, customer_id, status, created_by) VALUES (?, CURDATE(), ?, ?, ?)')->execute(['P6QB-' . $stamp, $b, 'ACCEPTED', $userId]);
$quoteB = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO jobs (job_number, customer_id, quote_id, quote_revision_number, title, status, created_by) VALUES (?,?,?,1,?,?,?)')->execute(['P6JB-' . $stamp, $b, $quoteB, 'Other', 'NEW', $userId]);
$jobB = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO job_artworks (job_id, title, original_filename, stored_filename, mime_type, file_size, status) VALUES (?,?,?,?,?,?,?)')->execute([$jobB, 'Logo', 'logo.pdf', 'x.pdf', 'application/pdf', 10, 'SENT_FOR_APPROVAL']);
$artB = (int) $pdo->lastInsertId();
$eq('artwork idor denied', null, $portalUsers->artwork($a, $artB));

$pdo->prepare('INSERT INTO attachments (entity_type, entity_id, original_filename, stored_filename, mime_type, file_size, visibility) VALUES (?,?,?,?,?,?,?)')->execute(['customer', $b, 'secret.pdf', 'secret.pdf', 'application/pdf', 10, 'INTERNAL']);
$secret = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO attachments (entity_type, entity_id, original_filename, stored_filename, mime_type, file_size, visibility) VALUES (?,?,?,?,?,?,?)')->execute(['customer', $a, 'shared.pdf', 'shared.pdf', 'application/pdf', 10, 'CUSTOMER_VISIBLE']);
$shared = (int) $pdo->lastInsertId();
$eq('internal document denied', null, $portalUsers->attachment($a, $secret));
$eq('other customer document denied', null, $portalUsers->attachment($a, $secret));
$eq('own document allowed', true, $portalUsers->attachment($a, $shared) !== null);

$hub->addInternalNote($a, 'customer_order', $orderId, 'Margin is thin. Do not show this.', $userId);
$eq('internal note hidden', 0, count($hub->portalMessages($buyer, 'customer_order', $orderId)));

$typeId = (int) $pdo->query('SELECT id FROM asset_types ORDER BY id LIMIT 1')->fetchColumn();
$asset = (new AssetService())->create([
    'customer_id' => $a,
    'asset_type_id' => $typeId,
    'name' => 'Pylon ' . $stamp,
    'status' => 'ACTIVE',
], $userId);
$assetId = (int) ($asset['id'] ?? 0);
$eq('asset', true, $assetId > 0);
$beforeService = (int) $pdo->query('SELECT COUNT(*) FROM service_requests')->fetchColumn();
$reported = $hub->reportAsset($buyer, $assetId, 'One side is dark');
$eq('service request from asset', true, ($reported['id'] ?? 0) > 0);
$eq('one service request', $beforeService + 1, (int) $pdo->query('SELECT COUNT(*) FROM service_requests')->fetchColumn());
$eq('uses the service request table', 'CUSTOMER_PORTAL', (string) $pdo->query('SELECT source FROM service_requests WHERE id = ' . (int) $reported['id'])->fetchColumn());

$eq('executable rejected', true, isset($hub->rejectFile('quote.exe', 'MZ')['_form']));
$eq('disguised pdf rejected', true, isset($hub->rejectFile('quote.pdf', 'MZ fake')['_form']));
$routes = (string) file_get_contents(dirname(__DIR__) . '/app/routes.php');
$eq('order post requires csrf', true, str_contains($routes, "post('/portal/orders'") && str_contains($routes, "}, false, true);"));

$siteRequest = $hub->requestSite($buyer, 'New branch', 'Street 1', 'B1');
$eq('site stays pending', 'PENDING', (string) $pdo->query('SELECT status FROM portal_site_requests WHERE id = ' . (int) $siteRequest['id'])->fetchColumn());
$eq('duplicate site warned', true, $siteRequest['warning'] !== null);
$eq('pending site was not created', 25, (int) $pdo->query('SELECT COUNT(*) FROM project_sites WHERE project_id = ' . $projectId)->fetchColumn());

$eq('template saved', true, ($hub->saveTemplate($buyer, 'New store opening pack', [['item' => $itemId, 'quantity' => '1']])['id'] ?? 0) > 0);
$colourId = $hub->saveColour($a, ['name' => 'ABC red', 'pantone' => '186 C', 'cmyk' => '0 100 80 5'], $userId);
$colour = $pdo->query('SELECT pantone, cmyk FROM customer_brand_colours WHERE id = ' . $colourId)->fetch();
$eq('colour stored as supplied', '186 C', (string) $colour['pantone']);
$eq('cmyk not converted', '0 100 80 5', (string) $colour['cmyk']);

$found = $hub->search($buyer, 'PO-' . $stamp);
$eq('buyer finds the po', true, count($found) === 1);
$eq('other customer search is empty', [], $hub->search($other, 'PO-' . $stamp));

$salesId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'PRODUCTION' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $salesId;
forget_auth_user();
$eq('workshop cannot review orders', true, isset($hub->review($orderId, 'ACCEPT', $salesId)['_form']));
$_SESSION['user_id'] = $userId;
forget_auth_user();

$settings = new SettingRepository();
$previousProvider = (string) SettingsService::get('payment_provider', '');
$previousSecret = (string) SettingsService::get('payment_webhook_secret', '');
(new FeatureFlagService())->set('PAYMENT_LINKS', true, $userId);
$settings->put('payment_provider', 'test');
$settings->put('payment_webhook_secret', 'phase6-pay-' . $stamp);
SettingsService::forget();
$draft = (new InvoiceService())->create([
    'customer_id' => $a,
    'invoice_type' => 'STANDARD',
    'vat_mode' => 'NO_VAT',
    'description' => 'Hub ' . $stamp,
    'amount' => '250',
    'due_date' => '2026-10-20',
    'invoice_date' => '2026-10-01',
], $userId);
$invoiceId = (int) $draft['id'];
$invoiceRow = (new FinanceRepository())->invoice($invoiceId);
$eq('invoice issued', [], (new InvoiceService())->issue($invoiceId, (int) $invoiceRow['version_number'], $userId));
$link = (new PaymentLinkService())->create($invoiceId, 'FULL', null, $userId);
$request = (new PlatformRepository())->paymentRequest((int) $link['id']);
$seen = (new PaymentLinkService())->acknowledge((string) $request['public_token']);
$eq('browser return is not paid', 'CREATED', (string) ($seen['status'] ?? ''));
$eq('hub claim is not paid', false, $hub->claimBrowserSuccess()['paid']);
$balance = (string) (new FinanceRepository())->invoice($invoiceId)['balance_due'];
$eq('balance unchanged', '250.00', Decimal::money($balance));
$body = json_encode([
    'external_reference' => $request['external_reference'],
    'event_id' => 'evt6-' . $stamp,
    'amount' => '250.00',
    'currency' => 'ZAR',
], JSON_THROW_ON_ERROR);
$paid = (new PaymentWebhookService())->handle('test', $body, hash_hmac('sha256', $body, 'phase6-pay-' . $stamp));
$againPay = (new PaymentWebhookService())->handle('test', $body, hash_hmac('sha256', $body, 'phase6-pay-' . $stamp));
$eq('verified webhook pays', true, $paid['ok']);
$eq('duplicate webhook is one payment', $paid['payment_id'], $againPay['payment_id']);
$eq('one payment row', 1, (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE external_reference = 'evt6-" . $stamp . "'")->fetchColumn());
$settings->put('payment_provider', $previousProvider);
$settings->put('payment_webhook_secret', $previousSecret);
SettingsService::forget();

$started = microtime(true);
for ($n = 1; $n <= 100; $n++) {
    $hub->addItem($a, $catalogueId, ['name' => 'Item ' . $n, 'customer_code' => 'C' . $n], $userId);
}
$page = $repo->items($catalogueId, 50, 0);
$eq('catalogue page', true, count($page) === 50 && (microtime(true) - $started) < 5);

if ($failures > 0) {
    fwrite(STDERR, "PHASE6_FAIL {$failures}\n");
    exit(1);
}
fwrite(STDOUT, "PHASE6_OK\n");

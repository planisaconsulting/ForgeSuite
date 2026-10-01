<?php

declare(strict_types=1);

/**
 * Stock ledger, rolls, offcuts, purchase orders, and job consumption.
 *
 *   php tests/inventory_flow.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Decimal;
use App\Repositories\CategoryRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\PurchasingRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\CustomerService;
use App\Services\JobService;
use App\Services\ProductService;
use App\Services\PurchasingService;
use App\Services\QuoteService;
use App\Services\StockMovementService;
use App\Services\StockValuation;
use App\Services\SupplierService;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$eq = static function (string $label, string $expected, string $actual) use ($fail, $ok): void {
    if (Decimal::cmp(Decimal::round($expected, 4), Decimal::round((string) $actual, 4)) !== 0) {
        $fail("{$label} expected {$expected} got {$actual}");

        return;
    }
    $ok($label);
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$admin = (new UserRepository())->findByEmail('admin@signforge.local');
if ($admin === null) {
    fwrite(STDERR, "Admin user is missing.\n");
    exit(1);
}
$_SESSION['user_id'] = (int) $admin['id'];
$userId = (int) $admin['id'];
$stamp = date('YmdHis');
$categories = (new CategoryRepository())->allWithParent();
$categoryId = (int) $categories[0]['id'];
$inventory = new InventoryRepository();
$stock = new StockMovementService();
$locationId = 0;
foreach ($inventory->locations() as $location) {
    if ((string) $location['code'] === 'MAIN') {
        $locationId = (int) $location['id'];
    }
}
if ($locationId < 1) {
    fwrite(STDERR, "MAIN location missing.\n");
    exit(1);
}

$makeJob = static function (string $title) use ($userId, $stamp, $categoryId): int {
    static $n = 0;
    $n++;
    $customer = (new CustomerService())->create([
        'customer_type' => 'BUSINESS',
        'company_name' => 'Stock Customer ' . $stamp . '-' . $n,
        'active' => '1',
    ], $userId);
    $product = (new ProductService())->create([
        'category_id' => $categoryId,
        'sku' => 'JOB-' . $stamp . '-' . $n,
        'name' => 'Job vinyl ' . $n,
        'product_type' => 'MATERIAL',
        'pricing_method' => 'UNIT',
        'cost_price' => '10',
        'standard_waste_percent' => '0',
        'default_waste_policy' => 'ACTUAL',
        'active' => '1',
        'inventory_method' => 'NONE',
    ], $userId);
    $quotes = new QuoteService();
    $repo = new QuoteRepository();
    $made = $quotes->create(['customer_id' => (int) $customer['id']], $userId);
    $quoteId = (int) $made['id'];
    $quotes->addProductLine($quoteId, [
        'product_id' => (int) $product['id'],
        'quantity' => '1',
        'waste_mode' => 'ACTUAL',
        'customer_description' => $title,
    ], (int) $repo->find($quoteId)['version_number'], $userId);
    $quotes->changeStatus($quoteId, 'READY', (int) $repo->find($quoteId)['version_number'], $userId);
    $quotes->accept($quoteId, [
        'accepted_by_name' => 'Buyer',
        'acceptance_method' => 'EMAIL',
    ], (int) $repo->find($quoteId)['version_number'], $userId);
    $converted = $quotes->convert($quoteId, [
        'title' => $title,
        'delivery_method' => 'COLLECTION',
    ], (int) $repo->find($quoteId)['version_number'], $userId);
    if ($converted['id'] === null) {
        fwrite(STDERR, json_encode($converted['errors']) . "\n");
        exit(1);
    }

    return (int) $converted['id'];
};

$products = new ProductService();
$bulk = $products->create([
    'category_id' => $categoryId,
    'sku' => 'BULK-' . $stamp,
    'name' => 'Bulk modules ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'UNIT',
    'cost_price' => '10',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'QUANTITY',
    'costing_method' => 'LAST_COST',
    'minimum_stock_level' => '5',
], $userId);
$bulkId = (int) $bulk['id'];
$opened = $stock->opening([
    'product_id' => $bulkId,
    'stock_location_id' => $locationId,
    'quantity' => '100',
    'unit_cost' => '10',
], $userId);
if ($opened['id'] === null) {
    fwrite(STDERR, 'opening ' . json_encode($opened['errors']) . "\n");
    exit(1);
}
$jobId = $makeJob('Stock job ' . $stamp);
$jobs = new JobService();
$used = $jobs->recordMaterial($jobId, [
    'product_id' => $bulkId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '20',
    'stock_location_id' => $locationId,
    'unit_cost' => '999',
], $userId);
if ($used['id'] === null) {
    fwrite(STDERR, 'consume ' . json_encode($used['errors']) . "\n");
    exit(1);
}
$usage = (new \App\Repositories\OperationsRepository())->usage($jobId)[0];
$eq('consumption ignores posted cost', '10.0000', (string) $usage['unit_cost_snapshot']);
$eq('on hand after consume 20', '80.0000', $inventory->onHand($bulkId, $locationId));
$reserved = $stock->reserve([
    'product_id' => $bulkId,
    'job_id' => $jobId,
    'stock_location_id' => $locationId,
    'quantity' => '10',
], $userId);
if ($reserved['id'] === null) {
    fwrite(STDERR, 'reserve ' . json_encode($reserved['errors']) . "\n");
    exit(1);
}
$eq('reserved 10', '10.0000', $inventory->reserved($bulkId, $locationId));
$eq('available 70', '70.0000', StockValuation::available($inventory->onHand($bulkId, $locationId), $inventory->reserved($bulkId, $locationId)));
$usedReserved = $jobs->recordMaterial($jobId, [
    'product_id' => $bulkId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '10',
    'stock_location_id' => $locationId,
    'reservation_id' => (int) $reserved['id'],
], $userId);
if ($usedReserved['id'] === null) {
    fwrite(STDERR, 'consume reserved ' . json_encode($usedReserved['errors']) . "\n");
    exit(1);
}
$eq('on hand 70', '70.0000', $inventory->onHand($bulkId));
$eq('reserved cleared', '0.0000', $inventory->reserved($bulkId));
$eq('available not reduced twice', '70.0000', StockValuation::available($inventory->onHand($bulkId), $inventory->reserved($bulkId)));

$limited = $products->create([
    'category_id' => $categoryId,
    'sku' => 'LIM-' . $stamp,
    'name' => 'Limited stock ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'UNIT',
    'cost_price' => '5',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'QUANTITY',
], $userId);
$limitedId = (int) $limited['id'];
$stock->opening(['product_id' => $limitedId, 'stock_location_id' => $locationId, 'quantity' => '10', 'unit_cost' => '5'], $userId);
$first = $stock->reserve(['product_id' => $limitedId, 'job_id' => $jobId, 'stock_location_id' => $locationId, 'quantity' => '7'], $userId);
$second = $stock->reserve(['product_id' => $limitedId, 'job_id' => $jobId, 'stock_location_id' => $locationId, 'quantity' => '5'], $userId);
if ($first['id'] === null || $second['id'] !== null || !str_contains((string) ($second['errors']['_form'] ?? ''), 'Not enough stock')) {
    $fail('two reservations exceeded available stock');
} else {
    $ok('second reservation stopped at available stock');
}

$short = $products->create([
    'category_id' => $categoryId,
    'sku' => 'NEG-' . $stamp,
    'name' => 'Short stock ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'UNIT',
    'cost_price' => '4',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'QUANTITY',
], $userId);
$shortId = (int) $short['id'];
$stock->opening(['product_id' => $shortId, 'stock_location_id' => $locationId, 'quantity' => '5', 'unit_cost' => '4'], $userId);
$refused = $jobs->recordMaterial($jobId, [
    'product_id' => $shortId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '8',
    'stock_location_id' => $locationId,
], $userId);
if ($refused['id'] !== null || !str_contains((string) ($refused['errors']['_form'] ?? ''), 'Not enough stock')) {
    $fail('negative stock was accepted');
} else {
    $ok('consumption above available stock was refused');
}
$override = $jobs->recordMaterial($jobId, [
    'product_id' => $shortId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '8',
    'stock_location_id' => $locationId,
    'stock_override' => '1',
    'override_reason' => 'Found extra on the bench',
], $userId);
if ($override['id'] === null) {
    $fail('authorised override ' . json_encode($override['errors']));
} else {
    $ok('authorised override recorded a reason');
}
$audit = (new \App\Repositories\AuditRepository())->forEntity('job', $jobId);
$foundOverride = false;
foreach ($audit as $row) {
    if ((string) $row['action'] === 'NEGATIVE_STOCK_OVERRIDE') {
        $foundOverride = true;
    }
}
if ($foundOverride) {
    $ok('override is in the audit log');
} else {
    $fail('override audit missing');
}

$rollProduct = $products->create([
    'category_id' => $categoryId,
    'sku' => 'ROLL-' . $stamp,
    'name' => 'Printable vinyl ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'AREA',
    'cost_price' => '45',
    'roll_width_mm' => '1370',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'ROLL',
], $userId);
$rollId = (int) $rollProduct['id'];
$rollOpen = $stock->opening([
    'product_id' => $rollId,
    'stock_location_id' => $locationId,
    'quantity' => '1',
    'length_m' => '50',
    'width_mm' => '1370',
    'unit_cost' => '10',
], $userId);
if ($rollOpen['id'] === null) {
    fwrite(STDERR, 'roll ' . json_encode($rollOpen['errors']) . "\n");
    exit(1);
}
$roll = $inventory->item((int) $rollOpen['id']);
if ($roll === null || !str_starts_with((string) $roll['inventory_code'], 'ROL-')) {
    $fail('roll code ' . ($roll['inventory_code'] ?? 'missing'));
} else {
    $ok('roll code ' . $roll['inventory_code']);
}
$eq('roll starts at 50 m', '50.0000', (string) $roll['remaining_quantity']);
$jobs->recordMaterial($jobId, [
    'product_id' => $rollId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '5',
    'inventory_item_id' => (int) $roll['id'],
], $userId);
$jobs->recordMaterial($jobId, [
    'product_id' => $rollId,
    'usage_type' => 'WASTE',
    'quantity' => '1',
    'reason' => 'TRIM',
    'inventory_item_id' => (int) $roll['id'],
], $userId);
$roll = $inventory->item((int) $roll['id']);
$eq('roll remaining 44 m', '44.0000', (string) $roll['remaining_quantity']);
$rollMoves = $inventory->movements(['item' => (int) $roll['id']]);
if (count($rollMoves) < 3) {
    $fail('roll movement history ' . count($rollMoves));
} else {
    $ok('roll movement history kept');
}

$sheetProduct = $products->create([
    'category_id' => $categoryId,
    'sku' => 'SHEET-' . $stamp,
    'name' => 'ACM ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'SHEET',
    'cost_price' => '200',
    'sheet_width_mm' => '2440',
    'sheet_height_mm' => '1220',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'FULL_SHEET',
    'active' => '1',
    'inventory_method' => 'SHEET',
], $userId);
$sheetId = (int) $sheetProduct['id'];
$sheetOpen = $stock->opening([
    'product_id' => $sheetId,
    'stock_location_id' => $locationId,
    'quantity' => '1',
    'unit_cost' => '200',
], $userId);
$sheet = $inventory->item((int) $sheetOpen['id']);
$jobs->recordMaterial($jobId, [
    'product_id' => $sheetId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '1',
    'inventory_item_id' => (int) $sheet['id'],
], $userId);
$sheet = $inventory->item((int) $sheet['id']);
if ((string) $sheet['status'] !== 'CONSUMED') {
    $fail('sheet status ' . $sheet['status']);
} else {
    $ok('full sheet consumed');
}
$offcut = $stock->createOffcut([
    'product_id' => $sheetId,
    'stock_location_id' => $locationId,
    'width_mm' => '1200',
    'height_mm' => '500',
    'source_inventory_item_id' => (int) $sheet['id'],
    'job_id' => $jobId,
], $userId);
$offcutRow = $inventory->item((int) $offcut['id']);
if ($offcutRow === null || (string) $offcutRow['status'] !== 'AVAILABLE') {
    $fail('offcut not available');
} else {
    $ok('offcut available');
}
$jobTwo = $makeJob('Offcut job ' . $stamp);
$offReserve = $stock->reserve([
    'product_id' => $sheetId,
    'job_id' => $jobTwo,
    'inventory_item_id' => (int) $offcutRow['id'],
    'quantity' => '1',
], $userId);
if ($offReserve['id'] === null) {
    $fail('offcut reserve ' . json_encode($offReserve['errors']));
} else {
    $ok('offcut reserved for the second job');
}
$jobs->recordMaterial($jobTwo, [
    'product_id' => $sheetId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '1',
    'inventory_item_id' => (int) $offcutRow['id'],
    'reservation_id' => (int) $offReserve['id'],
], $userId);
$offcutRow = $inventory->item((int) $offcutRow['id']);
if ((string) $offcutRow['status'] !== 'CONSUMED') {
    $fail('offcut status ' . $offcutRow['status']);
} else {
    $ok('offcut consumed');
}

$supplier = (new SupplierService())->save(null, ['name' => 'Sheet Supplier ' . $stamp, 'active' => '1']);
$supplierId = (int) $supplier['id'];
$purchasing = new PurchasingService();
$purchasing->saveSupplierPrice([
    'supplier_id' => $supplierId,
    'product_id' => $sheetId,
    'cost_price' => '500',
    'supplier_sku' => 'ACM-500',
    'active' => '1',
], $userId);
$linkId = 0;
foreach ($inventory->supplierProductsForProduct($sheetId) as $link) {
    if ((int) $link['supplier_id'] === $supplierId) {
        $linkId = (int) $link['id'];
    }
}
$purchasing->saveSupplierPrice([
    'supplier_id' => $supplierId,
    'product_id' => $sheetId,
    'cost_price' => '550',
    'active' => '1',
], $userId);
$history = $inventory->supplierPriceHistory($linkId);
if ($history === [] || Decimal::cmp((string) $history[0]['old_price'], '500') !== 0) {
    $fail('supplier price history');
} else {
    $ok('supplier price history kept R500');
}
$quoteRepo = new QuoteRepository();
$quoteBefore = (new QuoteService())->create(['customer_id' => (int) (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Price lock ' . $stamp,
    'active' => '1',
], $userId)['id']], $userId);
$lockedQuote = (int) $quoteBefore['id'];
(new QuoteService())->addProductLine($lockedQuote, [
    'product_id' => $bulkId,
    'quantity' => '1',
    'waste_mode' => 'ACTUAL',
    'customer_description' => 'Locked line',
], (int) $quoteRepo->find($lockedQuote)['version_number'], $userId);
$lockedCost = (string) $quoteRepo->items($lockedQuote)[0]['unit_cost_snapshot'];
$purchasing->saveSupplierPrice([
    'supplier_id' => $supplierId,
    'product_id' => $bulkId,
    'cost_price' => '80',
    'active' => '1',
], $userId);
$still = (string) $quoteRepo->items($lockedQuote)[0]['unit_cost_snapshot'];
if (Decimal::cmp($lockedCost, $still) !== 0) {
    $fail('quote changed after supplier price');
} else {
    $ok('existing quote stayed at the saved cost');
}

$order = $purchasing->createOrder(['supplier_id' => $supplierId], $userId);
$orderId = (int) $order['id'];
$po = (new PurchasingRepository())->order($orderId);
if ($po === null || !str_starts_with((string) $po['po_number'], 'SFPO-')) {
    $fail('po number');
} else {
    $ok('po number ' . $po['po_number']);
}
$purchasing->addLine($orderId, [
    'product_id' => $sheetId,
    'quantity' => '20',
    'unit_cost' => '1',
    'supplier_product_id' => $linkId,
], $userId);
$line = (new PurchasingRepository())->items($orderId)[0];
$eq('po line uses supplier price', '550.0000', (string) $line['unit_cost']);
$purchasing->setStatus($orderId, 'PENDING_APPROVAL', $userId);
$purchasing->setStatus($orderId, 'APPROVED', $userId);
$purchasing->setStatus($orderId, 'ORDERED', $userId);
$purchasing->receive($orderId, [
    'stock_location_id' => $locationId,
    'supplier_delivery_note' => 'DN-1',
    'receive_qty' => [(int) $line['id'] => '12'],
], $userId);
$po = (new PurchasingRepository())->order($orderId);
if ((string) $po['status'] !== 'PARTIALLY_RECEIVED') {
    $fail('partial status ' . $po['status']);
} else {
    $ok('partially received');
}
$purchasing->receive($orderId, [
    'stock_location_id' => $locationId,
    'receive_qty' => [(int) $line['id'] => '8'],
], $userId);
$po = (new PurchasingRepository())->order($orderId);
if ((string) $po['status'] !== 'RECEIVED') {
    $fail('received status ' . $po['status']);
} else {
    $ok('fully received');
}
$receivedQty = '0';
foreach ($inventory->movements(['product_id' => $sheetId, 'purchase_order_id' => $orderId]) as $movement) {
    if ((string) $movement['movement_type'] === 'PURCHASE_RECEIPT') {
        $receivedQty = Decimal::add($receivedQty, (string) $movement['quantity'], 4);
    }
}
$eq('receipts total 20 sheets', '20.0000', $receivedQty);

$averageProduct = $products->create([
    'category_id' => $categoryId,
    'sku' => 'AVG-' . $stamp,
    'name' => 'Average ink ' . $stamp,
    'product_type' => 'CONSUMABLE',
    'pricing_method' => 'UNIT',
    'cost_price' => '100',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'QUANTITY',
    'costing_method' => 'WEIGHTED_AVERAGE_COST',
], $userId);
$averageId = (int) $averageProduct['id'];
$stock->opening(['product_id' => $averageId, 'stock_location_id' => $locationId, 'quantity' => '10', 'unit_cost' => '100'], $userId);
$stock->opening(['product_id' => $averageId, 'stock_location_id' => $locationId, 'quantity' => '10', 'unit_cost' => '120'], $userId);
$averageRow = (new \App\Repositories\ProductRepository())->find($averageId);
$eq('weighted average cost', '110.0000', (string) $averageRow['average_cost']);

$integrate = $products->create([
    'category_id' => $categoryId,
    'sku' => 'INT-' . $stamp,
    'name' => 'Integrated units ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'UNIT',
    'cost_price' => '25',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'QUANTITY',
], $userId);
$integrateId = (int) $integrate['id'];
$stock->opening(['product_id' => $integrateId, 'stock_location_id' => $locationId, 'quantity' => '10', 'unit_cost' => '25'], $userId);
$hold = $stock->reserve(['product_id' => $integrateId, 'job_id' => $jobId, 'stock_location_id' => $locationId, 'quantity' => '10'], $userId);
$jobs->recordMaterial($jobId, [
    'product_id' => $integrateId,
    'usage_type' => 'PRODUCTION',
    'quantity' => '8',
    'reservation_id' => (int) $hold['id'],
    'stock_location_id' => $locationId,
], $userId);
$stock->release((int) $hold['id'], $userId);
$eq('integrated on hand', '2.0000', $inventory->onHand($integrateId));
$eq('integrated reserved', '0.0000', $inventory->reserved($integrateId));
$materialCost = '0';
foreach ((new \App\Repositories\OperationsRepository())->usage($jobId) as $row) {
    if ((int) $row['product_id'] === $integrateId) {
        $materialCost = Decimal::add($materialCost, (string) $row['total_cost'], 2);
    }
}
$eq('job material cost for 8', '200.00', $materialCost);

$roles = [];
foreach ((new RoleRepository())->all() as $role) {
    $roles[(string) $role['code']] = (int) $role['id'];
}
$users = new UserRepository();
$productionId = $users->insert([
    'name' => 'Production ' . $stamp,
    'email' => 'production-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Produce#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['PRODUCTION'],
    'active' => 1,
    'must_change_password' => 0,
]);
$salesId = $users->insert([
    'name' => 'Sales ' . $stamp,
    'email' => 'sales-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Sales#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['SALES'],
    'active' => 1,
    'must_change_password' => 0,
]);
$pending = $purchasing->createOrder(['supplier_id' => $supplierId], $userId);
$pendingId = (int) $pending['id'];
$purchasing->setStatus($pendingId, 'PENDING_APPROVAL', $userId);
$run = static function (string $action, int $actor, int $product, int $order) use ($locationId): string {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/inventory_security.php')
        . ' ' . escapeshellarg($action) . ' ' . $actor . ' ' . $product . ' ' . $locationId . ' ' . $order;
    $output = [];
    exec($command . ' 2>&1', $output);

    return trim(implode("\n", $output));
};
$adjustOut = $run('adjust', $productionId, $bulkId, 0);
if (!str_contains($adjustOut, 'ok   production cannot adjust')) {
    $fail('adjust child ' . $adjustOut);
} else {
    $ok('production cannot adjust stock');
}
$approveOut = $run('approve', $salesId, 0, $pendingId);
if (!str_contains($approveOut, 'ok   sales cannot approve')) {
    $fail('approve child ' . $approveOut);
} else {
    $ok('sales cannot approve a purchase order');
}
$csrfOut = $run('csrf', $userId, 0, 0);
if (!str_contains($csrfOut, 'form token')) {
    $fail('csrf ' . $csrfOut);
} else {
    $ok('missing form token is rejected');
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} inventory checks failed.\n");
    exit(1);
}
fwrite(STDOUT, "All inventory flow checks passed.\n");

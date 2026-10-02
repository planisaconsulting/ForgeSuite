<?php

declare(strict_types=1);

/**
 * v1.1 Phase 5: supplier RFQs, awards, receiving, and warehouse locations.
 *
 *   php tests/v11_phase5.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\InventoryRepository;
use App\Repositories\ProcurementRepository;
use App\Repositories\PurchasingRepository;
use App\Services\ProductService;
use App\Services\PurchasingService;
use App\Services\ReceivingControlService;
use App\Services\StockMovementService;
use App\Services\SupplierRfqService;
use App\Services\SupplierService;
use App\Services\WarehouseService;

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
$rfq = new SupplierRfqService();
$orders = new PurchasingService();
$purchasing = new PurchasingRepository();
$procurement = new ProcurementRepository();
$warehouse = new WarehouseService();
$stock = new StockMovementService();
$inventory = new InventoryRepository();

$supplier = static function (string $name) use ($stamp): int {
    $saved = (new SupplierService())->save(null, ['name' => $name . ' ' . $stamp, 'active' => '1']);

    return (int) $saved['id'];
};
$a = $supplier('Alpha');
$b = $supplier('Bravo');
$c = $supplier('Charlie');
$product = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P5-' . $stamp,
    'name' => 'Phase 5 ACM',
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

$made = $rfq->create(['title' => '20 ACM sheets ' . $stamp, 'required_by_date' => '2026-03-10'], $userId);
$rfqId = (int) $made['id'];
$eq('rfq created', true, $rfqId > 0);
$eq('item', [], $rfq->addItem($rfqId, [
    'product_id' => $productId,
    'description' => '3 mm white ACM',
    'quantity' => '20',
    'unit' => 'sheet',
    'source_kind' => 'MRP',
], [], $userId));
$blocked = $rfq->addItem($rfqId, ['description' => 'Mixed', 'unit' => 'sheet'], [
    ['quantity' => '4', 'required_date' => '2026-03-01', 'source_kind' => 'JOB'],
    ['quantity' => '6', 'required_date' => '2026-04-01', 'source_kind' => 'JOB'],
], $userId);
$eq('incompatible dates not consolidated', true, isset($blocked['_form']));
$invited = $rfq->invite($rfqId, [$a, $b, $c], $userId, date('Y-m-d H:i:s', time() + 86400));
$eq('three invitations', 3, count($invited['tokens']));
$itemId = (int) $pdo->query('SELECT id FROM supplier_rfq_items WHERE rfq_id = ' . $rfqId)->fetchColumn();

$reply = static function (string $token, string $price, string $available, string $lead, bool $alternative = false) use ($rfq, $itemId, $productId): int {
    $saved = $rfq->respond($token, ['lead_time_days' => $lead, 'delivery_amount' => '0'], [[
        'rfq_item_id' => $itemId,
        'product_id' => $productId,
        'unit_price' => $price,
        'available_quantity' => $available,
        'requested_quantity' => '20',
        'offered_description' => '3 mm white ACM',
        'lead_time_days' => $lead,
        'alternative' => $alternative,
    ]]);

    return (int) ($saved['id'] ?? 0);
};
$eq('quote a', true, $reply($invited['tokens'][$a], '500', '20', '5') > 0);
$eq('quote b', true, $reply($invited['tokens'][$b], '530', '20', '0') > 0);
$eq('quote c', true, $reply($invited['tokens'][$c], '480', '20', '14') > 0);
$compared = $rfq->compare($rfqId);
$eq('comparison has three', 3, count($compared['rows']));
$eq('no automatic winner', false, $compared['auto_selected']);
$quoteB = (int) $pdo->query('SELECT id FROM supplier_quotations WHERE supplier_id = ' . $b . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
$eq('supplier a cannot see b', false, $rfq->supplierCanSeeQuotation($invited['tokens'][$a], $quoteB));
$expired = $rfq->openToken('not-a-real-token');
$eq('bad token denied', false, $expired['found']);
$delta = $supplier('Delta');
$past = $rfq->invite($rfqId, [$delta], $userId, '2000-01-01 00:00:00');
$eq('expired token denied', false, $rfq->openToken($past['tokens'][$delta])['found']);
$file = $rfq->storeSupplierFile($invited['tokens'][$a], (int) $pdo->query('SELECT id FROM supplier_quotations WHERE supplier_id = ' . $a . ' ORDER BY id DESC LIMIT 1')->fetchColumn(), 'quote.pdf', 'MZ fake');
$eq('disguised pdf rejected', true, isset($file['errors']['_form']));

$pack = $rfq->suggestOrderQuantity('13', '5');
$eq('pack suggestion', '15.0000', $pack['suggested']);
$eq('pack explanation', true, str_contains($pack['explanation'], 'pack'));
$eq('late requirement stays late', 'LATE_FOR_REQUIREMENT', $rfq->requirementTiming('2026-03-10', '2026-03-14'));
$contract = $rfq->saveContract([
    'supplier_id' => $a,
    'product_id' => $productId,
    'agreed_price' => '500',
    'effective_from' => '2026-01-01',
    'contract_reference' => 'ACM-2026',
], $userId);
$eq('contract saved', true, ($contract['id'] ?? 0) > 0);
$variance = $rfq->contractVariance($a, $productId, '550');
$eq('contract variance warns', true, $variance['warning'] ?? false);
$eq('variance does not reject', '50.0000', (string) $variance['difference']);

$altRfq = $rfq->create(['title' => 'Alternative ' . $stamp], $userId);
$altId = (int) $altRfq['id'];
$rfq->addItem($altId, ['product_id' => $productId, 'description' => 'Vinyl', 'quantity' => '1', 'unit' => 'roll'], [], $userId);
$altItem = (int) $pdo->query('SELECT id FROM supplier_rfq_items WHERE rfq_id = ' . $altId)->fetchColumn();
$altInvite = $rfq->invite($altId, [$b], $userId, date('Y-m-d H:i:s', time() + 86400));
$altQuote = $rfq->respond($altInvite['tokens'][$b], [], [[
    'rfq_item_id' => $altItem,
    'product_id' => $productId,
    'unit_price' => '10',
    'available_quantity' => '1',
    'requested_quantity' => '1',
    'offered_description' => 'Different vinyl',
    'alternative' => true,
]]);
$altLine = (int) $pdo->query('SELECT id FROM supplier_quotation_items WHERE quotation_id = ' . (int) $altQuote['id'])->fetchColumn();
$eq('alternative flagged', 'REVIEW_REQUIRED', (string) $pdo->query('SELECT technical_status FROM supplier_quotation_items WHERE id = ' . $altLine)->fetchColumn());
$awardedAlt = $rfq->award($altId, [['quotation_item_id' => $altLine, 'quantity' => '1', 'reason' => 'BEST_PRICE']], $userId);
$eq('alternative not silently awarded', true, isset($awardedAlt['errors']['_form']));
$impact = $rfq->reviewAlternative($altLine, null, $userId);
$eq('alternative stays flagged', true, $impact['alternative']);
$eq('accept alternative', [], $rfq->acceptAlternative($altLine, $userId));

$splitRfq = $rfq->create(['title' => 'Split ' . $stamp], $userId);
$splitId = (int) $splitRfq['id'];
$rfq->addItem($splitId, ['product_id' => $productId, 'description' => 'Boards', 'quantity' => '20', 'unit' => 'sheet'], [], $userId);
$splitItem = (int) $pdo->query('SELECT id FROM supplier_rfq_items WHERE rfq_id = ' . $splitId)->fetchColumn();
$splitInvite = $rfq->invite($splitId, [$a, $b], $userId, date('Y-m-d H:i:s', time() + 86400));
$qa = $rfq->respond($splitInvite['tokens'][$a], [], [[
    'rfq_item_id' => $splitItem, 'product_id' => $productId, 'unit_price' => '20', 'available_quantity' => '8', 'requested_quantity' => '20', 'offered_description' => 'Boards',
]]);
$qb = $rfq->respond($splitInvite['tokens'][$b], [], [[
    'rfq_item_id' => $splitItem, 'product_id' => $productId, 'unit_price' => '21', 'available_quantity' => '12', 'requested_quantity' => '20', 'offered_description' => 'Boards',
]]);
$lineA = (int) $pdo->query('SELECT id FROM supplier_quotation_items WHERE quotation_id = ' . (int) $qa['id'])->fetchColumn();
$lineB = (int) $pdo->query('SELECT id FROM supplier_quotation_items WHERE quotation_id = ' . (int) $qb['id'])->fetchColumn();
$split = $rfq->award($splitId, [
    ['quotation_item_id' => $lineA, 'quantity' => '8', 'reason' => 'SPLIT_SUPPLY'],
    ['quotation_item_id' => $lineB, 'quantity' => '12', 'reason' => 'SPLIT_SUPPLY'],
], $userId);
$eq('split creates two draft pos', 2, count($split['purchase_orders']));
$eq('draft not ordered', 'DRAFT', (string) $pdo->query('SELECT status FROM purchase_orders WHERE id = ' . (int) $split['purchase_orders'][0])->fetchColumn());

$place = $warehouse->createLocation(['code' => 'P5-WH-' . $stamp, 'name' => 'Main', 'location_type' => 'WAREHOUSE'], $userId);
$bin = $warehouse->createLocation(['code' => 'WH1-VIN-A-02-' . $stamp, 'name' => 'Vinyl bin', 'location_type' => 'BIN', 'parent_id' => $place['id']], $userId);
$quarantine = $warehouse->createLocation(['code' => 'P5-Q-' . $stamp, 'name' => 'Quarantine', 'location_type' => 'QUARANTINE'], $userId);
$offcutBin = $warehouse->createLocation(['code' => 'OFFCUT-ACM-B3-' . $stamp, 'name' => 'ACM offcuts', 'location_type' => 'OFFCUT'], $userId);
$vehicle = $warehouse->createLocation(['code' => 'VEH-01-' . $stamp, 'name' => 'Vehicle', 'location_type' => 'VEHICLE'], $userId);
$eq('bin created', true, ($bin['id'] ?? 0) > 0);

$orderPo = static function (string $qty) use ($orders, $a, $productId, $userId): int {
    $made = $orders->createOrder(['supplier_id' => $a], $userId);
    $id = (int) $made['id'];
    $orders->addLine($id, ['product_id' => $productId, 'quantity' => $qty, 'description' => 'ACM'], $userId);
    $orders->setStatus($id, 'PENDING_APPROVAL', $userId);
    $orders->setStatus($id, 'APPROVED', $userId);
    $orders->setStatus($id, 'ORDERED', $userId);

    return $id;
};
$po = $orderPo('100');
$line = (int) $pdo->query('SELECT id FROM purchase_order_items WHERE purchase_order_id = ' . $po)->fetchColumn();
$beforeCost = (string) $pdo->query('SELECT COALESCE(SUM(actual_material_cost), 0) FROM jobs')->fetchColumn();
$received = (new ReceivingControlService())->confirm($po, [
    'stock_location_id' => (int) $place['id'],
    'receive_qty' => [$line => '60'],
    'idempotency_key' => 'grn-' . $stamp,
    'product_id' => $productId,
], $userId);
$eq('partial receipt', true, ($received['id'] ?? 0) > 0);
$eq('outstanding 40', '40.0000', (string) $pdo->query('SELECT ordered_quantity - received_quantity FROM purchase_order_items WHERE id = ' . $line)->fetchColumn());
$eq('po still open', 'PARTIALLY_RECEIVED', (string) $pdo->query('SELECT status FROM purchase_orders WHERE id = ' . $po)->fetchColumn());
$onHand = $inventory->onHand($productId, (int) $place['id']);
$again = (new ReceivingControlService())->confirm($po, [
    'stock_location_id' => (int) $place['id'],
    'receive_qty' => [$line => '60'],
    'idempotency_key' => 'grn-' . $stamp,
    'product_id' => $productId,
], $userId);
$eq('retry same receipt', (int) $received['id'], (int) $again['id']);
$eq('no second receipt quantity', $onHand, $inventory->onHand($productId, (int) $place['id']));
$eq('job cost not increased by the po', $beforeCost, (string) $pdo->query('SELECT COALESCE(SUM(actual_material_cost), 0) FROM jobs')->fetchColumn());
$over = $orders->receive($po, ['stock_location_id' => (int) $place['id'], 'receive_qty' => [$line => '105']], $userId);
$eq('over delivery needs a decision', true, str_contains((string) ($over['errors']['_form'] ?? ''), 'outstanding quantity'));

$damagePo = $orderPo('10');
$damageLine = (int) $pdo->query('SELECT id FROM purchase_order_items WHERE purchase_order_id = ' . $damagePo)->fetchColumn();
$damage = (new ReceivingControlService())->confirm($damagePo, [
    'stock_location_id' => (int) $place['id'],
    'quarantine_location_id' => (int) $quarantine['id'],
    'receive_qty' => [$damageLine => '10'],
    'damaged_quantity' => '2',
    'product_id' => $productId,
    'idempotency_key' => 'dmg-' . $stamp,
], $userId);
$eq('damaged receipt', true, ($damage['id'] ?? 0) > 0);
$available = $inventory->onHand($productId);
$inQuarantine = $inventory->onHand($productId, (int) $quarantine['id']);
$eq('quarantine holds 2', '2.0000', $inQuarantine);
$eq('available excludes quarantine', true, (float) $available + 2 === (float) $inventory->onHand($productId, (int) $place['id']) + (float) $inQuarantine);
$returned = (new ReceivingControlService())->supplierReturn([
    'supplier_id' => $a,
    'product_id' => $productId,
    'quantity' => '2',
    'stock_location_id' => (int) $quarantine['id'],
    'purchase_order_id' => $damagePo,
    'reason' => 'Damaged sheets',
    'reason_code' => 'DAMAGED',
], $userId);
$eq('supplier return', true, ($returned['id'] ?? 0) > 0);
$eq('return movement kept', 'SUPPLIER_RETURN', (string) $pdo->query('SELECT movement_type FROM stock_movements WHERE id = ' . (int) $returned['movement_id'])->fetchColumn());

$away = $warehouse->confirmPutaway($productId, (int) $place['id'], (int) $bin['id'], '1', $userId);
$eq('put away', [], $away);
$eq('bin quantity', '1.0000', $inventory->onHand($productId, (int) $bin['id']));
$moved = $stock->transfer([
    'product_id' => $productId,
    'from_location_id' => (int) $bin['id'],
    'to_location_id' => (int) $vehicle['id'],
    'quantity' => '1',
    'reason' => 'To vehicle',
], $userId);
$eq('transfer', [], $moved);
$eq('vehicle holds the quantity', '1.0000', $inventory->onHand($productId, (int) $vehicle['id']));

$offcut = $stock->createOffcut([
    'product_id' => $productId,
    'stock_location_id' => (int) $place['id'],
    'width_mm' => '600',
    'height_mm' => '400',
], $userId);
$eq('offcut', true, ($offcut['id'] ?? 0) > 0);
$eq('offcut move', [], $warehouse->confirmPutaway($productId, (int) $place['id'], (int) $offcutBin['id'], '1', $userId, (int) $offcut['id']));
$offcutLocation = (string) $pdo->query('SELECT l.code FROM inventory_items i JOIN stock_locations l ON l.id = i.stock_location_id WHERE i.id = ' . (int) $offcut['id'])->fetchColumn();
$eq('offcut location', 'OFFCUT-ACM-B3-' . $stamp, $offcutLocation);

$serialPo = $orderPo('2');
$serialLine = (int) $pdo->query('SELECT id FROM purchase_order_items WHERE purchase_order_id = ' . $serialPo)->fetchColumn();
$serial = 'SN-' . $stamp;
$firstSerial = (new ReceivingControlService())->confirm($serialPo, [
    'stock_location_id' => (int) $place['id'],
    'receive_qty' => [$serialLine => '1'],
    'product_id' => $productId,
    'serial_number' => $serial,
    'manufacturer_serial' => 'MFG-' . $stamp,
    'idempotency_key' => 'ser-' . $stamp,
], $userId);
$eq('serial stored', true, ($firstSerial['id'] ?? 0) > 0);
$secondSerial = (new ReceivingControlService())->confirm($serialPo, [
    'stock_location_id' => (int) $place['id'],
    'receive_qty' => [$serialLine => '1'],
    'product_id' => $productId,
    'serial_number' => $serial,
    'idempotency_key' => 'ser2-' . $stamp,
], $userId);
$eq('duplicate serial blocked', true, str_contains((string) ($secondSerial['errors']['_form'] ?? ''), 'serial'));

$lotPo = $orderPo('4');
$lotLine = (int) $pdo->query('SELECT id FROM purchase_order_items WHERE purchase_order_id = ' . $lotPo)->fetchColumn();
$lotCode = 'LED-' . $stamp;
$lotReceipt = (new ReceivingControlService())->confirm($lotPo, [
    'stock_location_id' => (int) $place['id'],
    'receive_qty' => [$lotLine => '4'],
    'product_id' => $productId,
    'lot_code' => $lotCode,
    'idempotency_key' => 'lot-' . $stamp,
], $userId);
$lot = $procurement->lotByCode($lotCode);
$eq('lot stored', true, ($lot['id'] ?? 0) > 0);
$procurement->useLot((int) $lot['id'], 1, null, '1');
$procurement->useLot((int) $lot['id'], 2, null, '1');
$procurement->useLot((int) $lot['id'], 3, null, '1');
$procurement->useLot((int) $lot['id'], null, 11, '1');
$procurement->useLot((int) $lot['id'], null, 12, '0');
$uses = $procurement->lotUses((int) $lot['id']);
$jobs = [];
$assets = [];
foreach ($uses as $use) {
    if ($use['job_id'] !== null) {
        $jobs[] = (int) $use['job_id'];
    }
    if ($use['asset_id'] !== null) {
        $assets[] = (int) $use['asset_id'];
    }
}
$eq('lot jobs', [1, 2, 3], $jobs);
$eq('lot assets', [11, 12], $assets);

$countLocation = $warehouse->createLocation(['code' => 'P5-CNT-' . $stamp, 'name' => 'Count bin', 'location_type' => 'BIN'], $userId);
$stock->opening([
    'product_id' => $productId,
    'stock_location_id' => (int) $countLocation['id'],
    'quantity' => '20',
    'unit_cost' => '10',
], $userId);
$count = $stock->startCount((int) $countLocation['id'], $userId);
$countId = (int) ($count['id'] ?? 0);
$eq('count opened', true, $countId > 0);
$lines = $inventory->countItems($countId);
$physical = [];
foreach ($lines as $row) {
    $physical[(int) $row['id']] = ((int) $row['product_id'] === $productId) ? '18' : (string) $row['system_quantity'];
}
$eq('count saved', [], $stock->saveCount($countId, ['physical' => $physical], $userId));
$eq('count approved', [], $stock->approveCount($countId, $userId));
$correction = $pdo->query("SELECT quantity FROM stock_movements WHERE reference_type = 'stock_count' AND reference_id = {$countId} AND movement_type = 'STOCK_COUNT_CORRECTION'")->fetchColumn();
$eq('count correction', '-2.0000', (string) $correction);

$salesId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'PRODUCTION' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $salesId;
forget_auth_user();
$hidden = $rfq->compare($rfqId);
$eq('workshop cannot compare prices', true, ($hidden['denied'] ?? false) === true);
$_SESSION['user_id'] = $userId;
forget_auth_user();

$started = microtime(true);
for ($n = 1; $n <= 200; $n++) {
    $pdo->prepare('INSERT INTO suppliers (name, active) VALUES (?, 1)')->execute(['P5 bulk ' . $stamp . ' ' . $n]);
}
$page = $procurement->rfqPage(50, 0);
$eq('rfq page', true, count($page) <= 50 && (microtime(true) - $started) < 5);

if ($failures > 0) {
    fwrite(STDERR, "PHASE5_FAIL {$failures}\n");
    exit(1);
}
fwrite(STDOUT, "PHASE5_OK\n");

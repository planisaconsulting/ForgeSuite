<?php

declare(strict_types=1);

/**
 * v1.1 Phase 3: specifications, geometry, and manufacturing estimators.
 *
 *   php tests/v11_phase3.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\QuoteRepository;
use App\Repositories\SignageRepository;
use App\Services\AssetService;
use App\Services\CompatibilityRuleService;
use App\Services\CustomerService;
use App\Services\ElectricalEstimateService;
use App\Services\ProductService;
use App\Services\QuoteService;
use App\Services\SheetYieldService;
use App\Services\SignEstimateService;
use App\Services\SpecificationService;
use App\Services\SvgGeometry;

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
$repo = new SignageRepository();
$estimates = new SignEstimateService();
$specs = new SpecificationService();

$rect = '<svg xmlns="http://www.w3.org/2000/svg"><path d="M 0 0 L 1000 0 L 1000 500 L 0 500 Z"/></svg>';
$geometry = SvgGeometry::analyse($rect);
$eq('vector area mm2', '500000.0000', (string) $geometry['area_mm2']);
$eq('vector perimeter mm', '3000.0000', (string) $geometry['perimeter_mm']);
$eq('vector area m2', '0.500000', (string) $geometry['area_m2']);
$eq('vector perimeter m', '3.000000', (string) $geometry['perimeter_m']);
$again = SvgGeometry::analyse($rect);
$eq('geometry cache', 'HIT', (string) $again['cache']);

$open = SvgGeometry::analyse('<svg><path d="M 0 0 L 100 0"/></svg>');
$eq('open path area', '0.0000', (string) $open['area_mm2']);
$eq('open warning', true, in_array('OPEN VECTOR PATH DETECTED.', $open['warnings'], true));

$duplicate = SvgGeometry::analyse('<svg><path d="M 0 0 L 1000 0 L 1000 500 L 0 500 Z"/><path d="M 0 0 L 1000 0 L 1000 500 L 0 500 Z"/></svg>');
$eq('duplicate not doubled', '500000.0000', (string) $duplicate['area_mm2']);
$eq('duplicate warning', true, $duplicate['duplicate_paths'] === 1);

$evil = SvgGeometry::analyse('<svg><script>alert(1)</script><path d="M 0 0 L 10 0 L 10 10 Z"/></svg>');
$eq('script not in preview', false, str_contains(strtolower((string) $evil['preview_svg']), '<script'));
$eq('script not executed as markup', false, str_contains(strtolower((string) $evil['preview_svg']), 'alert'));
$huge = SvgGeometry::analyse(str_repeat('M 0 0 ', 80000));
$eq('huge file stopped', false, $huge['ok']);

$led = (new ElectricalEstimateService())->modules('1', '1', [
    'method_code' => 'PER_LETTER',
    'module_watts' => '1.2',
    'modules_per_letter' => '100',
    'name' => 'Test module',
]);
$eq('led modules', '100', (string) $led['modules']);
$eq('led load', '120.0000', (string) $led['load_w']);
$psu = (new ElectricalEstimateService())->powerSupplies('120', [
    'name' => '100 W',
    'rated_watts' => '100',
    'max_load_percent' => '80',
]);
$eq('psu usable', '80.0000', (string) $psu['usable_each_w']);
$eq('psu count', '2', (string) $psu['count']);
$eq('one psu cannot carry 120', false, $psu['one_unit_sufficient']);

$sheet = (new SheetYieldService())->calculate('2450', '1225', '600', '400', '20', true, '0', '0');
$panel = $estimates->run('PANEL_FRAME', [
    'width' => '600',
    'height' => '400',
    'quantity' => '20',
    'sheet_width_mm' => '2450',
    'sheet_height_mm' => '1225',
    'allow_rotation' => '1',
    'unit' => 'mm',
], $userId, false);
$eq('panel errors', [], $panel['errors']);
$eq('panel sheets', (string) $sheet['sheets'], (string) ($panel['result']['geometry']['sheets'] ?? ''));
$eq('panel per sheet', (string) $sheet['per_sheet'], (string) ($panel['result']['geometry']['per_sheet'] ?? ''));

$box = $estimates->run('LIGHTBOX', [
    'width' => '2400',
    'height' => '1200',
    'depth' => '200',
    'sides' => '2',
    'quantity' => '1',
    'unit' => 'mm',
    'led_profile_id' => (int) $pdo->query("SELECT id FROM electrical_component_profiles WHERE code = 'LED-MOD-12'")->fetchColumn(),
    'psu_profile_id' => (int) $pdo->query("SELECT id FROM electrical_component_profiles WHERE code = 'PSU-100-80'")->fetchColumn(),
    'illuminated' => '1',
], $userId, true);
$eq('lightbox errors', [], $box['errors']);
$eq('lightbox faces', '5.7600', (string) ($box['result']['geometry']['face_m2'] ?? ''));
$eq('lightbox frame', '7.2000', (string) ($box['result']['geometry']['frame_m'] ?? ''));
$eq('lightbox returns', '1.4400', (string) ($box['result']['geometry']['return_m2'] ?? ''));
$ledLine = null;
foreach ($box['result']['components'] as $line) {
    if (($line['role'] ?? '') === 'LED') {
        $ledLine = $line;
    }
}
$eq('lightbox modules', '144', (string) ($ledLine['quantity'] ?? ''));

$wrap = $estimates->run('VEHICLE_WRAP', [
    'panels' => ['TAILGATE'],
    'manual_panels' => [[
        'panel_code' => 'TAILGATE',
        'width_mm' => '1400',
        'height_mm' => '500',
        'bleed_mm' => '0',
        'overlap_mm' => '0',
    ]],
    'roll_width_mm' => '1370',
    'compare_widths' => ['1370', '1520'],
    'allow_rotation' => '1',
    'quantity' => '1',
    'laminate' => '1',
    'measurement_source' => 'MANUAL',
], $userId, false);
$eq('wrap errors', [], $wrap['errors']);
$options = [];
foreach ($wrap['result']['roll_options'] as $option) {
    $options[(string) $option['roll_width_mm']] = $option;
}
$eq('roll widths differ', true, ($options['1370']['linear_metres'] ?? '') !== ($options['1520']['linear_metres'] ?? ''));
$eq('1520 uses less length', true, Decimal::cmp((string) $options['1520']['linear_metres'], (string) $options['1370']['linear_metres']) < 0);
$eq('laminate uses consumed area', (string) $wrap['result']['geometry']['consumed_area_m2'], (string) ($wrap['result']['materials'][1]['quantity'] ?? ''));

$letters = $estimates->run('CHANNEL_LETTER', [
    'svg' => $rect,
    'quantity' => '1',
    'allowance_percent' => '5',
    'return_depth' => '100',
    'return_unit' => 'mm',
    'trim_cap' => '1',
], $userId, true);
$eq('letter errors', [], $letters['errors']);
$eq('letter area', '0.500000', (string) ($letters['result']['geometry']['area_m2'] ?? ''));
$eq('letter perimeter', '3.000000', (string) ($letters['result']['geometry']['perimeter_m'] ?? ''));
$eq('letter face with allowance', '0.5250', (string) ($letters['result']['geometry']['face_m2'] ?? ''));
$eq('letter return', '3.1500', (string) ($letters['result']['geometry']['return_m'] ?? ''));
$openRun = $estimates->run('CHANNEL_LETTER', ['svg' => '<svg><path d="M 0 0 L 100 0"/></svg>', 'quantity' => '1'], $userId, false);
$eq('open vector blocked', true, isset($openRun['errors']['_form']) && str_contains((string) $openRun['errors']['_form'], 'OPEN VECTOR'));

$created = $specs->create([
    'code' => 'TST-' . $stamp,
    'name' => 'Phase 3 test spec',
    'estimator_type' => 'LIGHTBOX',
    'category' => 'LIGHTBOX',
    'manufacturing_allowance_percent' => '5',
    'max_width_mm' => '3000',
], $userId);
$eq('spec created', true, $created['id'] !== null);
$specId = (int) $created['id'];
$repo->insertRule([
    'specification_id' => $specId,
    'rule_type' => 'REQUIRES',
    'hardness' => 'HARD',
    'when_key' => 'illuminated',
    'when_op' => 'EQ',
    'when_value' => 'YES',
    'and_key' => null,
    'and_op' => null,
    'and_value' => null,
    'then_key' => 'electrical_components',
    'then_value' => 'YES',
    'message' => 'Illuminated construction requires electrical components.',
]);
$repo->insertRule([
    'specification_id' => $specId,
    'rule_type' => 'WARNING',
    'hardness' => 'SOFT',
    'when_key' => 'quantity',
    'when_op' => 'GT',
    'when_value' => '0',
    'and_key' => null,
    'and_op' => null,
    'and_value' => null,
    'then_key' => null,
    'then_value' => null,
    'message' => 'Check the site before promising a date.',
]);
$eq('approve spec', [], $specs->approve($specId, $userId));
$blocked = $estimates->run('LIGHTBOX', [
    'specification_id' => $specId,
    'width' => '1000',
    'height' => '500',
    'depth' => '100',
    'sides' => '1',
    'quantity' => '1',
    'illuminated' => 'YES',
    'unit' => 'mm',
], $userId, false);
$eq('hard rule blocks', true, str_contains((string) ($blocked['errors']['_form'] ?? ''), 'electrical components'));
$warned = $estimates->run('LIGHTBOX', [
    'specification_id' => $specId,
    'width' => '1000',
    'height' => '500',
    'depth' => '100',
    'sides' => '1',
    'quantity' => '1',
    'unit' => 'mm',
], $userId, true);
$eq('soft rule warns', true, in_array('Check the site before promising a date.', $warned['result']['warnings'] ?? [], true));
$before = (string) $repo->calculation((int) $warned['id'])['output_json'];
$revised = $specs->revise($specId, $userId);
$eq('revision', true, $revised['id'] !== null && (int) $revised['id'] !== $specId);
$eq('approve revision', [], $specs->approve((int) $revised['id'], $userId));
$afterRow = $repo->calculation((int) $warned['id']);
$eq('historical output unchanged', $before, (string) $afterRow['output_json']);
$eq('historical version', 1, (int) $afterRow['specification_version']);
$eq('old status superseded', 'SUPERSEDED', (string) $repo->specification($specId)['status']);

$formulaDraft = $specs->create([
    'code' => 'FRM-' . $stamp,
    'name' => 'Formula guard',
    'estimator_type' => 'CUSTOM',
    'category' => 'CUSTOM',
], $userId);
$badFormula = $specs->update((int) $formulaDraft['id'], [
    'name' => 'Formula guard',
    'estimator_type' => 'CUSTOM',
    'category' => 'CUSTOM',
    'manufacturing_allowance_percent' => '0',
    'replace_children' => '1',
    'labour' => [['description' => 'Bad', 'minutes_formula' => 'eval(1)', 'hourly_rate' => '1']],
], $userId);
$eq('formula rejected', true, isset($badFormula['formula']));

$override = $estimates->override((int) $warned['id'], 'FACE', '9', 'Site measured a different face', $userId);
$eq('override', [], $override);
$overridden = json_decode((string) $repo->calculation((int) $warned['id'])['output_json'], true);
$face = null;
foreach ($overridden['materials'] as $line) {
    if (($line['role'] ?? '') === 'FACE') {
        $face = $line;
    }
}
$eq('override keeps original', '0.5000', (string) ($face['original_quantity'] ?? ''));
$eq('override quantity', '9.0000', (string) ($face['quantity'] ?? ''));

$salesId = (int) $pdo->query("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'SALES' ORDER BY u.id LIMIT 1")->fetchColumn();
$_SESSION['user_id'] = $salesId;
forget_auth_user();
$denied = $specs->approve((int) $revised['id'], $salesId);
$eq('sales cannot approve', true, isset($denied['_form']));
$_SESSION['user_id'] = $userId;
forget_auth_user();

$rules = (new CompatibilityRuleService())->evaluate([[
    'active' => 1,
    'rule_type' => 'EXCLUDES',
    'hardness' => 'HARD',
    'when_key' => 'face_material',
    'when_op' => 'EQ',
    'when_value' => 'CORREX',
    'and_key' => '',
    'message' => '3 mm Correx cannot be selected with this freestanding illuminated pylon specification.',
]], ['face_material' => 'CORREX']);
$eq('correx excluded', true, $rules['blocked']);
$eq('correx message', '3 mm Correx cannot be selected with this freestanding illuminated pylon specification.', $rules['messages'][0]);

$customer = (new CustomerService())->create(['customer_type' => 'BUSINESS', 'company_name' => 'Phase3 ' . $stamp], $userId);
$customerId = (int) $customer['id'];
$product = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P3-' . $stamp,
    'name' => 'Phase 3 sign',
    'product_type' => 'FINISHED_PRODUCT',
    'pricing_method' => 'UNIT',
    'cost_price' => '100',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
    'inventory_method' => 'NONE',
], $userId);
$productId = (int) $product['id'];
$pdo->prepare('UPDATE products SET creates_customer_asset = 1 WHERE id = ?')->execute([$productId]);
$quotes = new QuoteService();
$quoteRepo = new QuoteRepository();
$quote = $quotes->create(['customer_id' => $customerId], $userId);
$quoteId = (int) $quote['id'];
$quotes->addProductLine($quoteId, [
    'product_id' => $productId,
    'quantity' => '1',
    'waste_mode' => 'ACTUAL',
    'customer_description' => 'Sign',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$linked = $estimates->addToQuote((int) $warned['id'], $quoteId, (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$eq('added to quote', [], $linked);
$quotes->changeStatus($quoteId, 'READY', (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quotes->accept($quoteId, ['accepted_by_name' => 'Tester', 'acceptance_method' => 'EMAIL'], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$job = $quotes->convert($quoteId, ['title' => 'Phase 3 job', 'delivery_method' => 'COLLECTION'], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$eq('job', true, $job['id'] !== null);
$snapshot = $repo->jobTechnical((int) $job['id']);
$eq('job keeps version', 1, (int) ($snapshot['specification_version'] ?? 0));
$specs->approve((int) $revised['id'], $userId);
$eq('job snapshot stays', 1, (int) ($repo->jobTechnical((int) $job['id'])['specification_version'] ?? 0));
$pdo->prepare("UPDATE job_items SET production_status = 'COMPLETE' WHERE job_id = ?")->execute([(int) $job['id']]);
$itemId = (int) $pdo->query('SELECT id FROM job_items WHERE job_id = ' . (int) $job['id'] . ' AND product_id = ' . $productId)->fetchColumn();
$typeId = (int) $pdo->query("SELECT id FROM asset_types WHERE code = 'LIGHTBOX'")->fetchColumn();
$asset = (new AssetService())->createFromJobItem($itemId, ['asset_type_id' => $typeId, 'status' => 'ACTIVE'], $userId);
$eq('asset', true, ($asset['ids'][0] ?? 0) > 0);
$assetRow = $pdo->query('SELECT specification_id, technical_snapshot_json FROM customer_assets WHERE id = ' . (int) $asset['ids'][0])->fetch();
$eq('asset specification', (int) $snapshot['specification_id'], (int) $assetRow['specification_id']);

$sites = [];
for ($n = 1; $n <= 25; $n++) {
    $sites[] = ['label' => 'S' . $n, 'width' => '2000', 'height' => '1000', 'depth' => '200', 'sides' => '1', 'quantity' => '1', 'unit' => 'mm'];
}
for ($n = 1; $n <= 3; $n++) {
    $sites[] = ['label' => 'X' . $n, 'width' => '4000', 'height' => '1000', 'depth' => '200', 'sides' => '1', 'quantity' => '1', 'unit' => 'mm'];
}
$started = microtime(true);
$rollout = $estimates->rollout($specId, $sites, $userId);
$elapsed = microtime(true) - $started;
$eq('rollout standard', 25, count($rollout['standard']));
$eq('rollout exceptions', 3, count($rollout['exceptions']));
$eq('rollout time', true, $elapsed < 30);
fwrite(STDOUT, 'rollout ' . round($elapsed, 2) . "s\n");

$paths = '';
for ($n = 0; $n < 80; $n++) {
    $x = $n * 30;
    $paths .= '<path d="M ' . $x . ' 0 L ' . ($x + 20) . ' 0 L ' . ($x + 20) . ' 20 L ' . $x . ' 20 Z"/>';
}
$started = microtime(true);
$many = SvgGeometry::analyse('<svg>' . $paths . '</svg>');
$eq('many paths', true, $many['ok'] && (microtime(true) - $started) < 5);

if ($failures > 0) {
    fwrite(STDERR, "PHASE3_FAIL {$failures}\n");
    exit(1);
}
fwrite(STDOUT, "PHASE3_OK\n");

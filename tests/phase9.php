<?php

declare(strict_types=1);

/**
 * Phase 9 yield, margin, history, and estimate security.
 *
 *   php tests/phase9.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\RecipeRepository;
use App\Repositories\UserRepository;
use App\Services\CostStateService;
use App\Services\CustomerService;
use App\Services\EstimateService;
use App\Services\FormulaService;
use App\Services\HistoricalStatsService;
use App\Services\InstallationEstimateService;
use App\Services\OffcutMatchingService;
use App\Services\PricingIntelligenceService;
use App\Services\PricingMathService;
use App\Services\PricingRecommendationService;
use App\Services\QuoteRiskService;
use App\Services\QuoteService;
use App\Services\RecipeService;
use App\Services\RollYieldService;
use App\Services\SheetYieldService;
use App\Services\VehicleBrandingService;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$eq = static function (string $label, string $expected, mixed $actual) use ($fail, $ok): void {
    if ((string) $actual !== $expected) {
        $fail($label . ' expected ' . $expected . ' got ' . var_export($actual, true));
    } else {
        $ok($label);
    }
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$sheets = new SheetYieldService();
$plain = $sheets->calculate('2440', '1220', '600', '400', '10', true, '0', '0');
$eq('sheet parts per sheet', '12', $plain['per_sheet']);
$eq('sheet count', '1', $plain['sheets']);
$eq('sheet keeps original orientation on a tie', '0', $plain['rotated'] ? '1' : '0');
$used = (new \App\Services\MaterialConsumptionService())->areaSquareMetres('600', '400', '10');
$consumed = (new \App\Services\MaterialConsumptionService())->areaSquareMetres('2440', '1220', '1');
$waste = Decimal::round(Decimal::mul(Decimal::div(Decimal::sub($consumed, $used, 8), $consumed, 8), '100', 8), 2);
$eq('sheet waste percent', $waste, $plain['waste_percent']);
$kerf = $sheets->calculate('2440', '1220', '600', '400', '10', true, '4', '10');
$eq('kerf and margin parts per sheet', '8', $kerf['per_sheet']);
$eq('kerf and margin sheets', '2', $kerf['sheets']);
if (($kerf['usable_width_mm'] ?? '') !== '2420.00') {
    $fail('edge margin was not removed');
} else {
    $ok('edge margin removed from the sheet');
}

$roll = (new RollYieldService())->calculate('1370', '600', '500', '4', true, false, '0', '0', '0');
$eq('roll across', '2', (string) $roll['across']);
$eq('roll linear metres', '1.0000', $roll['linear_metres']);
$eq('roll not rotated', '0', $roll['rotated'] ? '1' : '0');
$eq('roll artwork area', '1.2000', $roll['artwork_area_m2']);
$eq('roll consumed area', '1.3700', $roll['consumed_area_m2']);
$blocked = (new RollYieldService())->calculate('1370', '600', '500', '4', true, true, '0', '0', '0');
$eq('direction sensitive stays unrotated', '0', $blocked['rotated'] ? '1' : '0');

$offcuts = (new OffcutMatchingService())->rank('700', '400', [
    ['id' => 1, 'inventory_code' => 'SMALL', 'width_mm' => '600', 'height_mm' => '600'],
    ['id' => 2, 'inventory_code' => 'FIT', 'width_mm' => '800', 'height_mm' => '450'],
], true, false);
$codes = array_column($offcuts, 'code');
if (in_array('SMALL', $codes, true)) {
    $fail('600 x 600 was accepted on area alone');
} else {
    $ok('600 x 600 rejected');
}
$eq('800 x 450 matches', 'FIT', $offcuts[0]['code'] ?? '');

$math = new PricingMathService();
$marginSell = $math->sellFromMargin('1000', '40');
$eq('40 percent margin sell', '1666.67', $marginSell['sell']);
$markupSell = $math->sellFromMarkup('1000', '40');
$eq('40 percent markup is not the margin price', '1400.00', $markupSell['sell']);
$discount = $math->discountImpact('1000', '2000', '300');
$eq('discounted margin', '41.18', (string) $discount['margin_after']);

$history = (new HistoricalStatsService())->summarise(['60', '65', '70', '80', '200']);
$eq('labour mean', '95.00', (string) $history['mean']);
$eq('labour median', '70.00', (string) $history['median']);
$eq('labour min', '60.00', (string) $history['min']);
$eq('labour max', '200.00', (string) $history['max']);
if (!in_array('200.00', $history['outliers'], true)) {
    $fail('200 minute outlier was hidden');
} else {
    $ok('200 minute outlier stays visible');
}
$eq('recommendation uses the median', '70.00', (string) $history['recommendation_value']);
$eq('limited history label', 'LIMITED_HISTORY', (new HistoricalStatsService())->confidence(2, 5));
$eq('established history label', 'ESTABLISHED_HISTORY', (new HistoricalStatsService())->confidence(5, 5));

$states = new CostStateService();
$line = $states->line('5000', '5400', '5650');
$eq('estimated stays', '5000.00', $line['estimated']);
$eq('committed stays', '5400.00', $line['committed']);
$eq('actual stays', '5650.00', $line['actual']);
$eq('actual above estimate is unfavourable', 'UNFAVOURABLE', $line['actual_versus_estimated']['meaning']);
$qty = $states->quantities('10.5', '12.2', '13.1');
$eq('billable quantity', '10.5000', $qty['billable']);
$eq('estimated physical', '12.2000', $qty['estimated_physical']);
$eq('actual physical', '13.1000', $qty['actual_physical']);

$fleet = (new VehicleBrandingService())->estimate([
    'vehicle_quantity' => '10',
    'design_hours' => '3',
    'application_hours' => '5',
    'hourly_rate' => '100',
    'category' => 'FLEET_BRANDING',
]);
$eq('design setup once', '3.00', $fleet['design_hours']);
$eq('application per vehicle', '5.00', $fleet['per_vehicle_hours']);

$install = (new InstallationEstimateService())->estimate([
    'installers' => '2',
    'site_hours' => '4',
    'hourly_rate' => '250',
    'distance_km' => '200',
    'trips' => '2',
    'rate_per_km' => '8',
    'equipment_cost' => '500',
    'access_multiplier' => '1.25',
    'access_category' => 'DIFFICULT',
]);
$byName = [];
foreach ($install['components'] as $component) {
    $byName[$component['description']] = $component['estimated_cost'];
}
$eq('site labour distinct', '2500.00', $byName['Site labour']);
$eq('travel distinct', '3200.00', $byName['Travel distance']);
$eq('equipment distinct', '500.00', $byName['Equipment']);
$eq('access multiplier exposed', '1.25', $install['access_multiplier']);

$risk = (new QuoteRiskService())->assess([
    'expected_margin' => '25',
    'target_margin' => '35',
    'block_on_review' => true,
    'override_reason' => '',
]);
$codes = array_column($risk['warnings'], 'code');
if (!in_array('MARGIN', $codes, true) || !$risk['blocked']) {
    $fail('margin warning did not block without a reason');
} else {
    $ok('margin below target warns and blocks when configured');
}
$open = (new QuoteRiskService())->assess([
    'expected_margin' => '25',
    'target_margin' => '35',
    'block_on_review' => true,
    'override_reason' => 'Accepted for this customer',
]);
if ($open['blocked']) {
    $fail('a reason did not lift the block');
} else {
    $ok('override reason lifts the configured block');
}

$admin = (new UserRepository())->findByEmail('admin@signforge.local');
if ($admin === null) {
    fwrite(STDERR, "FAIL admin missing\n");
    exit(1);
}
$userId = (int) $admin['id'];
$_SESSION['user_id'] = $userId;
$stamp = date('YmdHis');
$recipeSave = (new RecipeService())->save(null, [
    'code' => 'P9' . $stamp,
    'name' => 'Phase 9 ' . $stamp,
    'recipe_type' => 'SIGNAGE',
    'active' => '1',
    'items' => [[
        'description' => 'Vinyl',
        'component_type' => 'MATERIAL',
        'quantity_formula' => 'Q',
        'waste_percent_override' => '10',
        'unit' => 'm2',
        'cost_calculation_method' => 'PRODUCT',
    ]],
], $userId);
if ($recipeSave['id'] === null) {
    fwrite(STDERR, 'FAIL recipe ' . json_encode($recipeSave['errors']) . "\n");
    exit(1);
}
$recipeId = (int) $recipeSave['id'];
$itemId = (int) (new RecipeRepository())->items($recipeId)[0]['id'];
$customer = (new CustomerService())->create(['customer_type' => 'BUSINESS', 'company_name' => 'Phase9 ' . $stamp, 'active' => '1'], $userId);
$quotes = new QuoteService();
$quote = $quotes->create(['customer_id' => (int) $customer['id']], $userId);
$quoteRow = Database::connection()->query('SELECT version_number FROM quotes WHERE id = ' . (int) $quote['id'])->fetch();
$line = $quotes->addRecipeLine((int) $quote['id'], ['recipe_id' => $recipeId, 'Q' => '1', 'W' => '1000', 'H' => '1000'], (int) $quoteRow['version_number'], $userId);
if ($line['id'] === null) {
    fwrite(STDERR, 'FAIL quote line ' . json_encode($line['errors']) . "\n");
    exit(1);
}
$before = (new RecipeRepository())->snapshotForItem((int) $line['id']);
$eq('quote starts on recipe version 1', '1', (string) ($before['recipe_version'] ?? ''));
$proposal = (new PricingRecommendationService())->create('MATERIAL_WASTE', '10', ['12', '14', '15', '16', '18'], $recipeId, $itemId, null, $userId);
if (!$proposal['ok']) {
    fwrite(STDERR, 'FAIL recommendation ' . $proposal['error'] . "\n");
    exit(1);
}
$installerEarly = Database::connection()->query("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = 'INSTALLER' AND u.active = 1 LIMIT 1")->fetch();
$earlyDenied = (new PricingRecommendationService())->accept((int) $proposal['id'], '15', (int) ($installerEarly['id'] ?? 0));
if ($earlyDenied['ok']) {
    $fail('installer applied a recommendation');
} else {
    $ok('installer recommendation approval denied');
}
$accepted = (new PricingRecommendationService())->accept((int) $proposal['id'], '15', $userId);
$eq('new recipe version', '2', (string) ($accepted['version'] ?? ''));
$after = (new RecipeRepository())->snapshotForItem((int) $line['id']);
$eq('old quote stays on version 1', '1', (string) ($after['recipe_version'] ?? ''));
$live = (new RecipeRepository())->find($recipeId);
$eq('live recipe is version 2', '2', (string) ($live['version_number'] ?? ''));

$saved = (new EstimateService())->save(null, [
    'estimate_type' => 'RECIPE',
    'recipe_id' => $recipeId,
    'recipe_version' => (string) $live['version_number'],
    'subtotal_cost' => '1',
], [[
    'description' => 'Board',
    'component_type' => 'MATERIAL',
    'estimated_quantity' => '2',
    'unit_cost_snapshot' => '10',
    'estimated_cost' => '999999',
    'billable_quantity' => '2',
]], $userId);
if (!$saved['ok']) {
    fwrite(STDERR, 'FAIL estimate ' . $saved['error'] . "\n");
    exit(1);
}
$stored = Database::connection()->query('SELECT subtotal_cost, recipe_version FROM estimates WHERE id = ' . (int) $saved['id'])->fetch();
$eq('browser total ignored', '20.00', (string) $stored['subtotal_cost']);
$eq('new estimate uses recipe version 2', '2', (string) $stored['recipe_version']);

$badFormula = (new EstimateService())->save(null, ['estimate_type' => 'GENERAL'], [[
    'description' => 'Bad',
    'quantity_formula' => 'phpinfo()',
    'unit_cost_snapshot' => '1',
]], $userId);
if ($badFormula['ok']) {
    $fail('arbitrary formula was accepted');
} else {
    $ok('arbitrary formula rejected');
}

$installer = Database::connection()->query("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = 'INSTALLER' AND u.active = 1 LIMIT 1")->fetch();
$installerId = (int) ($installer['id'] ?? 0);
if ($installerId < 1) {
    $fail('installer user missing');
} else {
    $historyDenied = (new PricingIntelligenceService())->estimateVersusActual($installerId);
    if ($historyDenied['ok']) {
        $fail('installer viewed historical costing');
    } else {
        $ok('installer historical costing denied');
    }
    $yieldDenied = (new PricingIntelligenceService())->overrideYield('1', '3', 'note', $installerId);
    if ($yieldDenied['ok']) {
        $fail('installer overrode yield');
    } else {
        $ok('installer yield override denied');
    }
}
$missing = (new EstimateService())->approve(99999999, $userId);
if ($missing['ok']) {
    $fail('missing estimate was approved');
} else {
    $ok('invalid estimate id rejected');
}
$note = (new PricingIntelligenceService())->overrideYield('10', '20', '', $userId);
if ($note['ok']) {
    $fail('material yield override without a note was stored');
} else {
    $ok('material yield override needs a note');
}
if ($installerId > 0) {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phase9_http.php') . ' ' . $installerId;
    $output = [];
    exec($command . ' 2>&1', $output);
    $page = implode("\n", $output);
    if (!str_contains($page, 'Your role cannot open that page.')) {
        $fail('installer url ' . $page);
    } else {
        $ok('installer post to estimates is denied');
    }
}

if ($failures > 0) {
    fwrite(STDERR, $failures . " failed\n");
    exit(1);
}
fwrite(STDOUT, "Phase 9 checks passed.\n");

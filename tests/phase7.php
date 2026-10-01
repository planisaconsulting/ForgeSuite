<?php

declare(strict_types=1);

/**
 * Phase 7: formulas, recipe snapshots, job generation, stock hints,
 * site surveys, and portal ownership.
 *
 *   php tests/phase7.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CategoryRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\PortalRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RecipeRepository;
use App\Repositories\SiteSurveyRepository;
use App\Repositories\UserRepository;
use App\Services\AttachmentService;
use App\Services\CustomerService;
use App\Services\FormulaRejected;
use App\Services\FormulaService;
use App\Services\InvoiceService;
use App\Services\JobService;
use App\Services\MaterialConsumptionService;
use App\Services\OpportunityService;
use App\Services\PortalAuthService;
use App\Services\PortalService;
use App\Services\ProductService;
use App\Services\QuoteService;
use App\Services\RecipeCalculationService;
use App\Services\RecipeService;
use App\Services\RecipeStockMatcher;
use App\Services\SheetYieldService;
use App\Services\SiteSurveyService;
use App\Services\StatementService;
use App\Services\StockMovementService;
use App\Services\UnitConversionService;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$eq = static function (string $label, string $expected, mixed $actual) use ($fail, $ok): void {
    $actualText = $actual === null ? 'null' : (string) $actual;
    if (Decimal::cmp(Decimal::round($expected, 4), Decimal::round($actualText, 4)) !== 0) {
        $fail("{$label} expected {$expected} got {$actualText}");

        return;
    }
    $ok($label);
};
$same = static function (string $label, string $expected, mixed $actual) use ($fail, $ok): void {
    $actualText = $actual === null ? 'null' : (string) $actual;
    if ($expected !== $actualText) {
        $fail("{$label} expected {$expected} got {$actualText}");

        return;
    }
    $ok($label);
};
$stop = static function (string $message) use ($fail): void {
    $fail($message);
    fwrite(STDERR, "Phase 7 stopped after a setup failure.\n");
    exit(1);
};

$formula = new FormulaService();
$rejected = static function (string $expression) use ($formula, $fail, $ok): void {
    try {
        $formula->evaluate($expression, ['W' => '1', 'Q' => '1']);
        $fail('formula should reject ' . $expression);
    } catch (FormulaRejected) {
        $ok('formula rejects ' . $expression);
    }
};
$rejected('phpinfo()');
$rejected('SELECT 1');
$rejected('system(1)');
$rejected('shell_exec(1)');
$rejected('unknown(1)');
$rejected('eval(1)');
$eq('ceil 6.2', '7', $formula->evaluate('CEIL(6.2)', []));
$eq('frame millimetres', '7200', $formula->evaluate('(2 * W) + (2 * H)', ['W' => '2400', 'H' => '1200']));
$areaEach = (new MaterialConsumptionService())->areaSquareMetres('2400', '1200', '1');
$eq('face area each', '2.8800', $areaEach);
$eq('face area total', '5.7600', $formula->evaluate('AREA_M2 * Q', ['AREA_M2' => $areaEach, 'Q' => '2']));
$perimeter = (new UnitConversionService())->mmToM(Decimal::mul('2', Decimal::add('2400', '1200', 8), 8));
$eq('perimeter metres', '7.2000', $perimeter);

$sheets = (new SheetYieldService())->sheets('2440', '1220', '600', '400', '4', true);
$eq('sheet count for four panels', '1', $sheets['sheets']);

$level = ['id' => null, 'code' => 'Q1', 'markup_percent' => '50', 'active' => 1];
$calc = new RecipeCalculationService();
$memory = $calc->calculate(
    ['id' => 0, 'name' => 'Printed ACM'],
    [['code' => 'LAMINATED', 'label' => 'Laminated', 'input_type' => 'BOOLEAN', 'required' => 0, 'default_value' => 'NO', 'min_value' => null, 'max_value' => null]],
    [
        ['description' => 'ACM', 'component_type' => 'MATERIAL', 'product_id' => null, 'quantity_formula' => 'AREA_M2 * Q', 'waste_percent_override' => '0', 'unit' => 'm2', 'cost_calculation_method' => 'PRODUCT', 'rounding_rule' => 'NONE', 'pack_size' => null, 'yield_mode' => 'NONE', 'condition_json' => null],
        ['description' => 'Laminate', 'component_type' => 'MATERIAL', 'product_id' => null, 'quantity_formula' => 'AREA_M2 * Q', 'waste_percent_override' => '0', 'unit' => 'm2', 'cost_calculation_method' => 'PRODUCT', 'rounding_rule' => 'NONE', 'pack_size' => null, 'yield_mode' => 'NONE', 'condition_json' => json_encode(['input' => 'LAMINATED', 'op' => 'EQ', 'value' => 'YES'])],
        ['description' => 'Standoffs', 'component_type' => 'HARDWARE', 'product_id' => null, 'quantity_formula' => '23', 'waste_percent_override' => '0', 'unit' => 'unit', 'cost_calculation_method' => 'PRODUCT', 'rounding_rule' => 'NONE', 'pack_size' => '10', 'yield_mode' => 'NONE', 'condition_json' => null],
    ],
    ['W' => '2400', 'H' => '1200', 'Q' => '2', 'LAMINATED' => 'YES'],
    $level
);
if (!$memory['ok']) {
    $stop('memory recipe ' . $memory['error']);
}
$eq('configured face each', '2.8800', $memory['face_area_each']);
$eq('configured face total', '5.7600', $memory['face_area_total']);
$byName = [];
foreach ($memory['components'] as $row) {
    $byName[$row['description']] = $row;
}
$eq('ACM quantity m2', '5.7600', $byName['ACM']['costed_quantity'] ?? null);
$same('ACM unit', 'm2', $byName['ACM']['unit'] ?? null);
$eq('laminate included', '5.7600', $byName['Laminate']['costed_quantity'] ?? null);
$eq('standoff job quantity', '23.0000', $byName['Standoffs']['costed_quantity'] ?? null);
$eq('standoff purchase packs', '30.0000', $byName['Standoffs']['purchase_quantity'] ?? null);
$without = $calc->calculate(
    ['id' => 0, 'name' => 'Printed ACM'],
    [['code' => 'LAMINATED', 'label' => 'Laminated', 'input_type' => 'BOOLEAN', 'required' => 0, 'default_value' => 'NO', 'min_value' => null, 'max_value' => null]],
    [
        ['description' => 'Laminate', 'component_type' => 'MATERIAL', 'product_id' => null, 'quantity_formula' => 'Q', 'waste_percent_override' => '0', 'unit' => 'm2', 'cost_calculation_method' => 'PRODUCT', 'rounding_rule' => 'NONE', 'pack_size' => null, 'yield_mode' => 'NONE', 'condition_json' => json_encode(['input' => 'LAMINATED', 'op' => 'EQ', 'value' => 'YES'])],
    ],
    ['W' => '1000', 'H' => '1000', 'Q' => '1', 'LAMINATED' => 'NO'],
    $level
);
if ($without['components'] !== []) {
    $fail('laminate stayed in the result when laminated is no');
} else {
    $ok('laminate omitted when laminated is no');
}

$admin = (new UserRepository())->findByEmail('admin@signforge.local');
if ($admin === null) {
    $stop('Admin user is missing.');
}
$_SESSION['user_id'] = (int) $admin['id'];
$userId = (int) $admin['id'];
$stamp = date('YmdHis');
$categories = (new CategoryRepository())->allWithParent();
if ($categories === []) {
    $stop('No product category.');
}
$categoryId = (int) $categories[0]['id'];
$products = new ProductService();
$makeProduct = static function (string $sku, string $name, string $type, string $method, string $cost, string $inventory = 'NONE') use ($products, $categoryId, $userId): int {
    $row = $products->create([
        'category_id' => $categoryId,
        'sku' => $sku,
        'name' => $name,
        'product_type' => $type,
        'pricing_method' => $method,
        'cost_price' => $cost,
        'standard_waste_percent' => '0',
        'default_waste_policy' => 'ACTUAL',
        'active' => '1',
        'inventory_method' => $inventory,
    ], $userId);
    if ($row['id'] === null) {
        throw new RuntimeException($sku . ' ' . json_encode($row['errors']));
    }

    return (int) $row['id'];
};
try {
    $finishedId = $makeProduct('FIN-' . $stamp, 'Printed ACM sign ' . $stamp, 'FINISHED_PRODUCT', 'UNIT', '0');
    $acmId = $makeProduct('ACM-' . $stamp, 'ACM board ' . $stamp, 'MATERIAL', 'AREA', '100');
    $vinylId = $makeProduct('VIN-' . $stamp, 'Printable vinyl ' . $stamp, 'MATERIAL', 'AREA', '80');
    $lamId = $makeProduct('LAM-' . $stamp, 'Laminate ' . $stamp, 'MATERIAL', 'AREA', '40');
    $labourId = $makeProduct('LAB-' . $stamp, 'Application ' . $stamp, 'LABOUR', 'HOUR', '120');
} catch (RuntimeException $e) {
    $stop($e->getMessage());
}
$route = Database::connection()->query("SELECT id FROM production_route_templates WHERE code = 'ACM_SIGN' LIMIT 1")->fetch();
$routeId = (int) ($route['id'] ?? 0);
if ($routeId < 1) {
    $stop('ACM sign production route is missing.');
}

$component = static function (string $description, int $productId, string $formula, string $type, string $method, string $unit) : array {
    return [
        'description' => $description,
        'component_type' => $type,
        'product_id' => $productId,
        'quantity_formula' => $formula,
        'waste_percent_override' => '0',
        'unit' => $unit,
        'cost_calculation_method' => $method,
        'rounding_rule' => 'NONE',
    ];
};
$recipePayload = static function (string $code, string $acmFormula, string $acmName) use ($finishedId, $routeId, $acmId, $vinylId, $lamId, $labourId, $component): array {
    return [
        'code' => $code,
        'name' => 'Printed ACM ' . $code,
        'recipe_type' => 'SIGNAGE',
        'finished_product_id' => $finishedId,
        'production_route_template_id' => $routeId,
        'active' => '1',
        'items' => [
            $component($acmName, $acmId, $acmFormula, 'MATERIAL', 'PRODUCT', 'm2'),
            $component('Printable vinyl', $vinylId, 'AREA_M2 * Q', 'MATERIAL', 'PRODUCT', 'm2'),
            $component('Laminate', $lamId, 'AREA_M2 * Q', 'MATERIAL', 'PRODUCT', 'm2'),
            $component('Application', $labourId, 'AREA_M2 * Q * 12', 'LABOUR', 'LABOUR', 'MIN'),
        ],
    ];
};
$recipes = new RecipeService();
$saved = $recipes->save(null, $recipePayload('P7ACM' . $stamp, 'AREA_M2 * Q', 'ACM board'), $userId);
if ($saved['id'] === null) {
    $stop('recipe ' . json_encode($saved['errors']));
}
$recipeId = (int) $saved['id'];
$recipeRow = (new RecipeRepository())->find($recipeId);
$same('recipe starts at version 1', '1', $recipeRow['version_number'] ?? null);

$customers = new CustomerService();
$customer = $customers->create(['customer_type' => 'BUSINESS', 'company_name' => 'Phase7 ' . $stamp, 'active' => '1'], $userId);
$customerId = (int) $customer['id'];
$other = $customers->create(['customer_type' => 'BUSINESS', 'company_name' => 'Phase7 Other ' . $stamp, 'active' => '1'], $userId);
$otherId = (int) $other['id'];
$quotes = new QuoteService();
$quoteRepo = new QuoteRepository();
$sizes = ['recipe_id' => $recipeId, 'W' => '2400', 'H' => '1200', 'Q' => '2'];
$first = $quotes->create(['customer_id' => $customerId], $userId);
$quoteId = (int) $first['id'];
$line = $quotes->addRecipeLine($quoteId, $sizes, (int) $quoteRepo->find($quoteId)['version_number'], $userId);
if ($line['id'] === null) {
    $stop('quote line ' . json_encode($line['errors']));
}
$snap1 = (new RecipeRepository())->snapshotForItem((int) $line['id']);
$same('quote keeps recipe version 1', '1', $snap1['recipe_version'] ?? null);
$eq('quote 1 ACM quantity', '5.7600', $snap1['component_snapshot_json'][0]['costed_quantity'] ?? null);
$same('quote 1 component name', 'ACM board', $snap1['component_snapshot_json'][0]['description'] ?? null);

$updated = $recipes->save($recipeId, $recipePayload('P7ACM' . $stamp, 'AREA_M2 * Q * 2', 'ACM board v2'), $userId);
if ($updated['id'] === null) {
    $stop('recipe update ' . json_encode($updated['errors']));
}
$recipeRow = (new RecipeRepository())->find($recipeId);
$same('recipe is now version 2', '2', $recipeRow['version_number'] ?? null);
$snap1 = (new RecipeRepository())->snapshotForItem((int) $line['id']);
$same('old quote still version 1', '1', $snap1['recipe_version'] ?? null);
$eq('old quote still 5.76', '5.7600', $snap1['component_snapshot_json'][0]['costed_quantity'] ?? null);

$second = $quotes->create(['customer_id' => $customerId], $userId);
$quoteTwo = (int) $second['id'];
$lineTwo = $quotes->addRecipeLine($quoteTwo, $sizes, (int) $quoteRepo->find($quoteTwo)['version_number'], $userId);
if ($lineTwo['id'] === null) {
    $stop('quote 2 ' . json_encode($lineTwo['errors']));
}
$snap2 = (new RecipeRepository())->snapshotForItem((int) $lineTwo['id']);
$same('new quote uses version 2', '2', $snap2['recipe_version'] ?? null);
$eq('new quote ACM quantity', '11.5200', $snap2['component_snapshot_json'][0]['costed_quantity'] ?? null);

$ready = $quotes->changeStatus($quoteId, 'READY', (int) $quoteRepo->find($quoteId)['version_number'], $userId);
if ($ready !== []) {
    $stop('ready ' . json_encode($ready));
}
$accept = $quotes->accept($quoteId, [
    'accepted_by_name' => 'Site owner',
    'acceptance_method' => 'EMAIL',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
if ($accept !== []) {
    $stop('accept ' . json_encode($accept));
}
$converted = $quotes->convert($quoteId, [
    'title' => 'Phase 7 ACM ' . $stamp,
    'delivery_method' => 'COLLECTION',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
if ($converted['id'] === null) {
    $stop('convert ' . json_encode($converted['errors']));
}
$jobId = (int) $converted['id'];
$ops = new OperationsRepository();
$requirements = $ops->requirements($jobId);
$acmNeed = null;
foreach ($requirements as $row) {
    if ((int) $row['product_id'] === $acmId) {
        $acmNeed = $row;
    }
}
if ($acmNeed === null) {
    $fail('job has no ACM requirement');
} else {
    $eq('job ACM from snapshot', '5.7600', $acmNeed['final_required_quantity']);
    $same('job requirement source', 'RECIPE', $acmNeed['source']);
}
$labour = $ops->expectedLabour($jobId);
$eq('expected application minutes', '69.1200', $labour[0]['expected_minutes'] ?? null);
$stages = $ops->stages($jobId);
if ($stages === []) {
    $fail('job has no production stages');
} else {
    $ok('production stages copied from the recipe route');
}
$names = array_map(static fn (array $row): string => (string) $row['stage_name'], $stages);
if (!in_array('Artwork', $names, true)) {
    $fail('artwork stage missing');
} else {
    $ok('artwork stage present');
}

$locations = (new InventoryRepository())->locations(true);
if ($locations === []) {
    $stop('No stock location.');
}
$locationId = (int) $locations[0]['id'];
$stockProduct = $makeProduct('STK-' . $stamp, 'Stock ACM ' . $stamp, 'MATERIAL', 'AREA', '90', 'AREA');
$stock = new StockMovementService();
$opened = $stock->opening([
    'product_id' => $stockProduct,
    'stock_location_id' => $locationId,
    'quantity' => '3',
    'unit_cost' => '90',
], $userId);
if ($opened['id'] === null) {
    $stop('opening stock ' . json_encode($opened['errors']));
}
$offcut = $stock->createOffcut([
    'product_id' => $stockProduct,
    'stock_location_id' => $locationId,
    'width_mm' => '2500',
    'height_mm' => '1000',
    'notes' => '2.5 m2 offcut',
], $userId);
if ($offcut['id'] === null) {
    $stop('offcut ' . json_encode($offcut['errors']));
}
$inventory = new InventoryRepository();
$before = $inventory->onHand($stockProduct);
$movesBefore = count($inventory->movements(['product_id' => $stockProduct]));
$hint = (new RecipeStockMatcher())->suggest($stockProduct, '5', '2000', '800');
$eq('full stock on hand', '3.0000', $hint['on_hand']);
$eq('shortage after full stock', '2.0000', $hint['shortage']);
$foundOffcut = false;
foreach ($hint['offcuts'] as $row) {
    if ((int) $row['id'] === (int) $offcut['id']) {
        $foundOffcut = true;
    }
}
if (!$foundOffcut) {
    $fail('suitable 2500 x 1000 offcut was not listed');
} else {
    $ok('suitable offcut listed');
}
$eq('on hand unchanged', $before, $inventory->onHand($stockProduct));
if (count($inventory->movements(['product_id' => $stockProduct])) !== $movesBefore) {
    $fail('stock suggestion wrote a movement');
} else {
    $ok('stock suggestion did not consume stock');
}
$offcutRow = $inventory->item((int) $offcut['id']);
$same('offcut still available', 'AVAILABLE', $offcutRow['status'] ?? null);

$opportunity = (new OpportunityService())->create([
    'customer_id' => $customerId,
    'title' => 'Shopfront ' . $stamp,
    'source' => 'PHONE',
    'status' => 'NEW',
], $userId);
if ($opportunity['id'] === null) {
    $stop('opportunity ' . json_encode($opportunity['errors']));
}
$surveys = new SiteSurveyService();
$survey = $surveys->create([
    'customer_id' => $customerId,
    'opportunity_id' => $opportunity['id'],
    'site_name' => 'Main shopfront ' . $stamp,
    'survey_date' => date('Y-m-d'),
    'status' => 'IN_PROGRESS',
    'environment' => 'OUTDOOR',
    'surface_type' => 'Plaster',
    'installation_notes' => 'Ladder access from the pavement.',
    'ladder_required' => '1',
], $userId);
if ($survey['id'] === null) {
    $stop('survey ' . json_encode($survey['errors']));
}
$surveyId = (int) $survey['id'];
$surveyRow = (new SiteSurveyRepository())->find($surveyId);
if (!str_starts_with((string) $surveyRow['survey_number'], 'SFS-')) {
    $fail('survey number ' . $surveyRow['survey_number']);
} else {
    $ok('survey number ' . $surveyRow['survey_number']);
}
$measure = $surveys->addMeasurement($surveyId, [
    'reference' => 'Main shopfront',
    'measurement_type' => 'SIGN_FACE',
    'width_mm' => '6250',
    'height_mm' => '1200',
    'quantity' => '1',
]);
if ($measure !== []) {
    $fail('measurement ' . json_encode($measure));
} else {
    $ok('measurement stored');
}
$measurementId = (int) (new SiteSurveyRepository())->measurements($surveyId)[0]['id'];
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$photo = sys_get_temp_dir() . '/sf-phase7-' . $stamp . '.png';
file_put_contents($photo, $png);
$files = new AttachmentService();
$stored = $files->store('site_survey', $surveyId, [
    'name' => 'shopfront.png',
    'type' => 'image/png',
    'tmp_name' => $photo,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($photo),
], $userId, 'SURVEY_PHOTO', 'Main shopfront', false, [
    'visibility' => 'INTERNAL',
    'photo_tag' => 'MEASUREMENT',
    'measurement_id' => $measurementId,
]);
if ($stored !== []) {
    $fail('survey photo ' . json_encode($stored));
} else {
    $ok('survey photo linked to the measurement');
}
$done = $surveys->update($surveyId, [
    'site_name' => 'Main shopfront ' . $stamp,
    'status' => 'COMPLETED',
    'installation_notes' => 'Ladder access from the pavement.',
], $userId);
if ($done !== []) {
    $fail('complete survey ' . json_encode($done));
} else {
    $ok('survey completed');
}
$linkedQuote = $quotes->create(['customer_id' => $customerId, 'opportunity_id' => $opportunity['id']], $userId);
$surveys->linkQuote($surveyId, (int) $linkedQuote['id']);
$surveyRow = (new SiteSurveyRepository())->find($surveyId);
$same('survey stays linked to the quote', (string) $linkedQuote['id'], $surveyRow['quote_id'] ?? null);
$same('survey stays linked to the opportunity', (string) $opportunity['id'], $surveyRow['opportunity_id'] ?? null);

$portalRepo = new PortalRepository();
$portalRepo->insertUser([
    'customer_id' => $customerId,
    'customer_contact_id' => null,
    'email' => 'portal-a-' . $stamp . '@example.com',
    'password_hash' => password_hash('Portal#Test2026', PASSWORD_DEFAULT),
    'active' => 1,
]);
$portalA = $portalRepo->userByEmail('portal-a-' . $stamp . '@example.com');
$portalRepo->insertUser([
    'customer_id' => $otherId,
    'customer_contact_id' => null,
    'email' => 'portal-b-' . $stamp . '@example.com',
    'password_hash' => password_hash('Portal#Test2026', PASSWORD_DEFAULT),
    'active' => 1,
]);
$portalB = $portalRepo->userByEmail('portal-b-' . $stamp . '@example.com');
$portal = new PortalService();
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

$foreignQuote = $quotes->create(['customer_id' => $otherId], $userId);
$foreignQuoteId = (int) $foreignQuote['id'];
$quotes->addCustomLine($foreignQuoteId, [
    'customer_description' => 'Other customer sign',
    'quantity' => '1',
    'unit_cost' => '100',
    'final_sell_price' => '200',
], (int) $quoteRepo->find($foreignQuoteId)['version_number'], $userId);
$quotes->changeStatus($foreignQuoteId, 'SENT', (int) $quoteRepo->find($foreignQuoteId)['version_number'], $userId);
if ($portal->quote($portalA, $foreignQuoteId) !== null) {
    $fail('customer A opened customer B quote');
} else {
    $ok('quote access denied across customers');
}

$foreignJob = $quotes->accept($foreignQuoteId, ['accepted_by_name' => 'Other', 'acceptance_method' => 'PHONE'], (int) $quoteRepo->find($foreignQuoteId)['version_number'], $userId);
if ($foreignJob !== []) {
    $stop('foreign accept ' . json_encode($foreignJob));
}
$foreignConverted = $quotes->convert($foreignQuoteId, ['title' => 'Other job ' . $stamp, 'delivery_method' => 'COLLECTION'], (int) $quoteRepo->find($foreignQuoteId)['version_number'], $userId);
if ($foreignConverted['id'] === null) {
    $stop('foreign job ' . json_encode($foreignConverted['errors']));
}
$foreignJobId = (int) $foreignConverted['id'];
if ($portal->job($portalA, $foreignJobId) !== null) {
    $fail('customer A opened customer B job');
} else {
    $ok('job access denied across customers');
}
$jobs = new JobService();
$jobs->storeArtwork($foreignJobId, [
    'name' => 'other.png',
    'type' => 'image/png',
    'tmp_name' => $photo,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($photo),
], ['title' => 'Other proof'], $userId, false);
$foreignArt = $ops->artworks($foreignJobId)[0];
$jobs->setArtworkStatus($foreignJobId, (int) $foreignArt['id'], 'SENT_FOR_APPROVAL', (int) $foreignArt['version_number']);
if ($portal->artwork($portalA, (int) $foreignArt['id']) !== null) {
    $fail('customer A opened customer B artwork');
} else {
    $ok('artwork access denied across customers');
}

$invoices = new InvoiceService();
$draft = $invoices->create([
    'customer_id' => $otherId,
    'invoice_type' => 'STANDARD',
    'description' => 'Other customer invoice',
    'amount' => '500',
    'vat_mode' => 'NO_VAT',
], $userId);
if ($draft['id'] === null) {
    $stop('invoice ' . json_encode($draft['errors']));
}
$finance = new App\Repositories\FinanceRepository();
$issuedErrors = $invoices->issue((int) $draft['id'], (int) $finance->invoice((int) $draft['id'])['version_number'], $userId);
if ($issuedErrors !== []) {
    $stop('issue ' . json_encode($issuedErrors));
}
$issued = $finance->invoice((int) $draft['id']);
if ($portalRepo->invoice($customerId, (int) $issued['id']) !== null) {
    $fail('customer A opened customer B invoice');
} else {
    $ok('invoice access denied across customers');
}
$statement = (new StatementService())->build($customerId, date('Y-m-01', strtotime('-11 months')), date('Y-m-d'));
$references = array_map(static fn (array $row): string => (string) ($row['reference'] ?? ''), $statement['rows'] ?? []);
if (in_array((string) $issued['invoice_number'], $references, true)) {
    $fail('statement included another customer invoice');
} else {
    $ok('statement stays on the signed-in customer');
}

$hidden = $files->store('customer', $otherId, [
    'name' => 'secret.png',
    'type' => 'image/png',
    'tmp_name' => $photo,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($photo),
], $userId, 'REFERENCE', 'Internal', false, ['visibility' => 'CUSTOMER_VISIBLE']);
if ($hidden !== []) {
    $fail('customer file ' . json_encode($hidden));
}
$attachmentId = (int) Database::connection()->query('SELECT id FROM attachments ORDER BY id DESC LIMIT 1')->fetchColumn();
if ($portalRepo->attachment($customerId, $attachmentId) !== null) {
    $fail('customer A opened customer B file');
} else {
    $ok('attachment access denied across customers');
}

$sent = $quotes->changeStatus($quoteTwo, 'SENT', (int) $quoteRepo->find($quoteTwo)['version_number'], $userId);
if ($sent !== []) {
    $stop('send ' . json_encode($sent));
}
$change = $portal->requestQuoteChange($portalA, $quoteTwo, ['message' => 'Please reduce the quantity.']);
if ($change !== []) {
    $fail('quote change ' . json_encode($change));
} else {
    $ok('quote change requested without editing the quote');
}
$same('quote stays sent after a change request', 'SENT', $quoteRepo->find($quoteTwo)['status']);
$accepted = $portal->acceptQuote($portalA, $quoteTwo, ['accept' => '1', 'customer_po' => 'PO-7']);
if ($accepted !== []) {
    $fail('portal accept ' . json_encode($accepted));
} else {
    $ok('portal acceptance recorded');
}
$acceptedQuote = $quoteRepo->find($quoteTwo);
$same('portal quote status', 'ACCEPTED', $acceptedQuote['status']);
$same('portal acceptance method', 'PORTAL', $acceptedQuote['acceptance_method']);
$history = $quoteRepo->history($quoteTwo);
$sawAccepted = false;
foreach ($history as $row) {
    if ((string) $row['new_status'] === 'ACCEPTED') {
        $sawAccepted = true;
    }
}
if (!$sawAccepted) {
    $fail('acceptance history missing');
} else {
    $ok('acceptance history stored');
}
$action = Database::connection()->prepare('SELECT * FROM portal_actions WHERE entity_type = ? AND entity_id = ? AND action = ? ORDER BY id DESC LIMIT 1');
$action->execute(['quote', $quoteTwo, 'QUOTE_ACCEPTED']);
$actionRow = $action->fetch();
if ($actionRow === false || (string) $actionRow['statement_text'] === '' || (string) $actionRow['ip_address'] !== '203.0.113.10') {
    $fail('acceptance statement or IP missing');
} else {
    $ok('acceptance statement and IP stored');
    $same('accepted revision', (string) $acceptedQuote['revision_number'], (string) $actionRow['revision_number']);
}
$note = Database::connection()->prepare('SELECT id FROM notifications WHERE dedupe_key = ?');
$note->execute(['quote-accept-' . $quoteTwo . '-' . $acceptedQuote['revision_number']]);
if ($note->fetch() === false) {
    $fail('salesperson was not notified');
} else {
    $ok('salesperson notified of acceptance');
}
$again = $portal->acceptQuote($portalA, $quoteTwo, ['accept' => '1']);
if ($again === []) {
    $fail('duplicate acceptance was allowed');
} else {
    $ok('duplicate acceptance blocked');
}

$jobs->storeArtwork($jobId, [
    'name' => 'rev1.png',
    'type' => 'image/png',
    'tmp_name' => $photo,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($photo),
], ['title' => 'Shopfront'], $userId, false);
$rev1 = null;
foreach ($ops->artworks($jobId) as $row) {
    if ((string) $row['title'] === 'Shopfront' && (int) $row['revision_number'] === 1) {
        $rev1 = $row;
    }
}
$jobs->setArtworkStatus($jobId, (int) $rev1['id'], 'SENT_FOR_APPROVAL', (int) $rev1['version_number']);
$rev1File = (string) $ops->artwork((int) $rev1['id'])['stored_filename'];
$jobs->storeArtwork($jobId, [
    'name' => 'rev2.png',
    'type' => 'image/png',
    'tmp_name' => $photo,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($photo),
], ['title' => 'Shopfront'], $userId, false);
$rev2 = null;
foreach ($ops->artworks($jobId) as $row) {
    if ((string) $row['title'] === 'Shopfront' && (int) $row['revision_number'] === 2) {
        $rev2 = $row;
    }
}
$jobs->setArtworkStatus($jobId, (int) $rev2['id'], 'SENT_FOR_APPROVAL', (int) $rev2['version_number']);
$same('revision 1 stays historical', 'SUPERSEDED', $ops->artwork((int) $rev1['id'])['status']);
$blocked = $jobs->changeStatus($jobId, 'APPROVED_FOR_PRODUCTION', (int) (new JobRepository())->find($jobId)['version_number'], $userId);
if (!str_contains(implode(' ', $blocked), 'Artwork has not been approved')) {
    $fail('production started before artwork approval');
} else {
    $ok('production waits for artwork approval');
}
$approved = $portal->approveArtwork($portalA, (int) $rev2['id'], ['approve' => '1']);
if ($approved !== []) {
    $fail('artwork approval ' . json_encode($approved));
} else {
    $ok('revision 2 approved in the portal');
}
$same('revision 2 status', 'APPROVED', $ops->artwork((int) $rev2['id'])['status']);
$same('revision 1 still historical', 'SUPERSEDED', $ops->artwork((int) $rev1['id'])['status']);
$same('revision 1 file unchanged', $rev1File, $ops->artwork((int) $rev1['id'])['stored_filename']);
$proceed = $jobs->changeStatus($jobId, 'APPROVED_FOR_PRODUCTION', (int) (new JobRepository())->find($jobId)['version_number'], $userId);
if ($proceed !== []) {
    $fail('production after approval ' . json_encode($proceed));
} else {
    $ok('production can proceed after approval');
}
$customerJob = $portal->job($portalA, $jobId);
$same('customer status label', 'In production', $customerJob['customer_status'] ?? null);
if (isset($customerJob['quoted_cost']) || isset($customerJob['markup'])) {
    $fail('portal job exposed internal cost');
} else {
    $ok('portal job omits internal cost');
}
$same('quality control stays customer friendly', 'In production', $portalRepo->labelForStatus('QUALITY_CONTROL'));
if (str_contains($portalRepo->labelForStatus('QC_FAILED'), 'QC')) {
    $fail('internal QC failure is shown to the customer');
} else {
    $ok('internal QC failure is not shown');
}

$jobs->storeArtwork($jobId, [
    'name' => 'window.png',
    'type' => 'image/png',
    'tmp_name' => $photo,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($photo),
], ['title' => 'Window'], $userId, false);
$window = null;
foreach ($ops->artworks($jobId) as $row) {
    if ((string) $row['title'] === 'Window') {
        $window = $row;
    }
}
$jobs->setArtworkStatus($jobId, (int) $window['id'], 'SENT_FOR_APPROVAL', (int) $window['version_number']);
$windowFile = (string) $ops->artwork((int) $window['id'])['stored_filename'];
$asked = $portal->requestArtworkChange($portalA, (int) $window['id'], ['message' => 'The phone number is wrong.']);
if ($asked !== []) {
    $fail('artwork change ' . json_encode($asked));
} else {
    $ok('artwork change requested');
}
$windowNow = $ops->artwork((int) $window['id']);
$same('change request status', 'CHANGES_REQUESTED', $windowNow['status']);
$same('current artwork file kept', $windowFile, $windowNow['stored_filename']);
$artNote = Database::connection()->prepare('SELECT id FROM notifications WHERE user_id = ? AND dedupe_key LIKE ?');
$artNote->execute([$userId, 'art-change-' . $window['id'] . '-%']);
if ($artNote->fetch() === false) {
    $fail('designer was not notified');
} else {
    $ok('designer notified');
}
$jobs->storeArtwork($jobId, [
    'name' => 'window-r2.png',
    'type' => 'image/png',
    'tmp_name' => $photo,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($photo),
], ['title' => 'Window'], $userId, false);
$window2 = null;
foreach ($ops->artworks($jobId) as $row) {
    if ((string) $row['title'] === 'Window' && (int) $row['revision_number'] === 2) {
        $window2 = $row;
    }
}
if ($window2 === null) {
    $fail('next artwork revision was not created');
} else {
    $ok('next upload is revision 2');
}
$same('previous window revision kept', 'SUPERSEDED', $ops->artwork((int) $window['id'])['status']);

$bad = [
    ['name' => 'shell.php', 'body' => '<?php echo 1;'],
    ['name' => 'page.phtml', 'body' => '<?php echo 1;'],
    ['name' => 'photo.php.png', 'body' => $png],
    ['name' => 'notes.png', 'body' => 'not an image'],
];
foreach ($bad as $case) {
    $path = sys_get_temp_dir() . '/sf-' . $stamp . '-' . $case['name'];
    file_put_contents($path, $case['body']);
    $checked = $files->inspect([
        'name' => $case['name'],
        'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($path),
    ], false);
    if ($checked['ok']) {
        $fail('accepted ' . $case['name']);
    } else {
        $ok('rejected ' . $case['name']);
    }
    unlink($path);
}
$huge = sys_get_temp_dir() . '/sf-huge-' . $stamp . '.png';
$handle = fopen($huge, 'wb');
ftruncate($handle, 8388609);
fclose($handle);
$checked = $files->inspect([
    'name' => 'huge.png',
    'tmp_name' => $huge,
    'error' => UPLOAD_ERR_OK,
    'size' => 8388609,
], false);
if ($checked['ok']) {
    $fail('accepted an oversized file');
} else {
    $ok('rejected an oversized file');
}
unlink($huge);

$link = (new PortalAuthService())->issueLink((int) $portalA['id'], 'LOGIN', null, null, true);
if ($link['url'] === null || !preg_match('#/portal/access/([a-f0-9]{64})$#', (string) $link['url'], $match)) {
    $fail('magic link ' . json_encode($link['errors']));
} else {
    $hash = hash('sha256', $match[1]);
    $token = Database::connection()->prepare('SELECT token_hash FROM portal_access_tokens WHERE token_hash = ?');
    $token->execute([$hash]);
    $storedHash = $token->fetchColumn();
    if ($storedHash !== $hash || str_contains((string) $storedHash, $match[1])) {
        $fail('raw token was stored');
    } else {
        $ok('magic link stores a hash');
    }
}

unlink($photo);
if ($failures > 0) {
    fwrite(STDERR, "{$failures} phase 7 check(s) failed.\n");
    exit(1);
}
fwrite(STDOUT, "Phase 7 checks passed.\n");

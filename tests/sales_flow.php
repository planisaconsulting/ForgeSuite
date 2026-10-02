<?php

declare(strict_types=1);

/**
 * Saved quotations, revisions, acceptance, and job conversion.
 *
 *   php tests/sales_flow.php
 *
 * Uses the local database. It adds a customer, a product, quotes, and one job.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Decimal;
use App\Helpers\View;
use App\Repositories\CategoryRepository;
use App\Repositories\JobRepository;
use App\Repositories\OpportunityRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\UserRepository;
use App\Services\OpportunityService;
use App\Services\ProductService;
use App\Services\QuoteConflictException;
use App\Services\QuotePdf;
use App\Services\QuoteService;
use App\Services\SettingsService;
use Dompdf\Dompdf;
use Dompdf\Options;

$failures = 0;

$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};

$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};

$money = static function (string $label, string $expected, mixed $actual) use ($fail, $ok): void {
    $actualText = $actual === null ? '0' : (string) $actual;
    if (Decimal::cmp(Decimal::round($expected, 2), Decimal::round($actualText, 2)) !== 0) {
        $fail("{$label} expected {$expected} got {$actualText}");

        return;
    }
    $ok($label);
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$admin = (new UserRepository())->findByEmail('admin@signforge.local');
if ($admin === null) {
    fwrite(STDERR, "Admin user is missing. Seed the database first.\n");
    exit(1);
}
$_SESSION['user_id'] = (int) $admin['id'];
$userId = (int) $admin['id'];

$categories = (new CategoryRepository())->allWithParent();
if ($categories === []) {
    fwrite(STDERR, "No product category. Seed the database first.\n");
    exit(1);
}
$categoryId = (int) $categories[0]['id'];

$stamp = date('YmdHis');
$customer = (new App\Services\CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Phase2 Flow ' . $stamp,
    'active' => '1',
], $userId);
if ($customer['id'] === null) {
    fwrite(STDERR, 'Customer: ' . json_encode($customer['errors']) . "\n");
    exit(1);
}
$customerId = (int) $customer['id'];
$ok('customer created');

$opportunity = (new OpportunityService())->create([
    'customer_id' => $customerId,
    'title' => 'Shopfront enquiry ' . $stamp,
    'source' => 'WALK_IN',
    'status' => 'NEW',
    'estimated_value' => '20000',
    'assigned_to' => $userId,
], $userId);
if ($opportunity['id'] === null) {
    fwrite(STDERR, 'Opportunity: ' . json_encode($opportunity['errors']) . "\n");
    exit(1);
}
$opportunityId = (int) $opportunity['id'];
$opportunityRow = (new OpportunityRepository())->find($opportunityId);
if ($opportunityRow === null || !preg_match('/^SFO-\d{4}-\d{4,}$/', (string) $opportunityRow['opportunity_number'])) {
    $fail('opportunity number ' . ($opportunityRow['opportunity_number'] ?? 'missing'));
} else {
    $ok('opportunity number ' . $opportunityRow['opportunity_number']);
}

$productInput = static function (string $sku, string $name, string $cost) use ($categoryId): array {
    return [
        'category_id' => $categoryId,
        'sku' => $sku,
        'name' => $name,
        'product_type' => 'MATERIAL',
        'pricing_method' => 'AREA',
        'cost_price' => $cost,
        'standard_waste_percent' => '0',
        'default_waste_policy' => 'ACTUAL',
        'active' => '1',
        'notes' => 'Phase 2 flow test',
    ];
};

$products = new ProductService();
$createdProduct = $products->create($productInput('P2-' . $stamp, 'Historical vinyl ' . $stamp, '50'), $userId);
if ($createdProduct['id'] === null) {
    fwrite(STDERR, 'Product: ' . json_encode($createdProduct['errors']) . "\n");
    exit(1);
}
$productId = (int) $createdProduct['id'];
$ok('product stored at R50');

$quotes = new QuoteService();
$repo = new QuoteRepository();

$made = $quotes->create([
    'customer_id' => $customerId,
    'internal_notes' => 'INTERNAL-ONLY-NOTE-' . $stamp,
    'customer_notes' => 'Please confirm the shopfront width.',
], $userId);
if ($made['id'] === null) {
    fwrite(STDERR, 'Quote: ' . json_encode($made['errors']) . "\n");
    exit(1);
}
$quoteId = (int) $made['id'];
$quote = $repo->find($quoteId);
if ($quote === null || !preg_match('/^SFQ-\d{4}-\d{4,}$/', (string) $quote['quote_number'])) {
    $fail('quote number');
} else {
    $ok('quote number ' . $quote['quote_number']);
}
if (Decimal::cmp((string) $quote['vat_rate'], '15') !== 0) {
    $fail('vat snapshot ' . $quote['vat_rate']);
} else {
    $ok('vat rate snapshotted at 15');
}

$added = $quotes->addProductLine($quoteId, [
    'product_id' => $productId,
    'width_mm' => '1000',
    'height_mm' => '1000',
    'quantity' => '1',
    'waste_mode' => 'ACTUAL',
    'cost' => '1',
    'customer_description' => 'Full-colour printed vinyl',
    'internal_description' => 'Charge the saved square metre',
], (int) $quote['version_number'], $userId);
if ($added['errors'] !== []) {
    $fail('add line ' . implode(' ', $added['errors']));
} else {
    $ok('product line added');
}
$quote = $repo->find($quoteId);
$item = $repo->items($quoteId)[0] ?? null;
$money('saved line still uses R50', '50.00', $item['unit_cost_snapshot'] ?? null);
$money('line sell from 65% markup', '82.50', $item['calculated_price'] ?? null);

$tampered = $quotes->save($quoteId, [
    'quote_date' => $quote['quote_date'],
    'expiry_date' => $quote['expiry_date'],
    'pricing_level_id' => $quote['pricing_level_id'],
    'vat_mode' => 'EXCLUSIVE',
    'vat_rate' => '0',
    'discount_type' => 'NONE',
    'customer_notes' => $quote['customer_notes'],
    'internal_notes' => $quote['internal_notes'],
    'terms' => $quote['terms'],
    'assigned_to' => $userId,
], (int) $quote['version_number'], $userId);
if ($tampered !== []) {
    $fail('vat tamper save ' . json_encode($tampered));
}
$quote = $repo->find($quoteId);
if (Decimal::cmp((string) $quote['vat_rate'], '15') !== 0) {
    $fail('posted vat rate changed the snapshot to ' . $quote['vat_rate']);
} else {
    $ok('posted vat rate ignored');
}

try {
    $quotes->save($quoteId, [
        'quote_date' => $quote['quote_date'],
        'vat_mode' => 'EXCLUSIVE',
        'discount_type' => 'NONE',
    ], 1, $userId);
    $fail('stale version was saved');
} catch (QuoteConflictException $e) {
    if (!str_contains($e->getMessage(), 'Reload before saving')) {
        $fail('conflict message ' . $e->getMessage());
    } else {
        $ok('optimistic lock refused a stale save');
    }
}

$existing = (new ProductRepository())->find($productId);
$updated = $products->update($productId, $productInput(
    (string) $existing['sku'],
    (string) $existing['name'],
    '70'
), $userId);
if ($updated !== []) {
    $fail('cost change ' . json_encode($updated));
}
$quote = $repo->find($quoteId);
$item = $repo->items($quoteId)[0];
$money('reopened quote still uses R50', '50.00', $item['unit_cost_snapshot']);

$preview = $quotes->previewRefresh($quoteId);
$changed = $preview[0] ?? null;
if ($changed === null || empty($changed['changed'])) {
    $fail('refresh preview did not show a difference');
} else {
    $money('preview current cost', '70.00', $changed['current_cost']);
    $money('preview difference', '20.00', $changed['difference']);
    $ok('refresh preview shows the difference before applying');
}

$quote = $repo->find($quoteId);
$refreshed = $quotes->applyRefresh($quoteId, (int) $quote['version_number'], $userId);
if ($refreshed !== []) {
    $fail('apply refresh ' . json_encode($refreshed));
}
$quote = $repo->find($quoteId);
$item = $repo->items($quoteId)[0];
if ((string) $quote['quote_number'] === '' || (int) $quote['revision_number'] !== 2) {
    $fail('refresh revision ' . $quote['revision_number']);
} else {
    $ok('refresh kept ' . $quote['quote_number'] . ' and moved to revision 2');
}
$money('new revision uses R70', '70.00', $item['unit_cost_snapshot']);
$snapshot = $quotes->revisionSnapshot($quoteId, 1);
$oldCost = $snapshot['items'][0]['unit_cost_snapshot'] ?? null;
$money('revision 1 snapshot still R50', '50.00', $oldCost);
$money('revision 1 total unchanged', '94.88', $snapshot['quote']['total'] ?? null);

$jobQuote = $quotes->create([
    'customer_id' => $customerId,
    'opportunity_id' => $opportunityId,
    'internal_notes' => 'Do not print this',
    'customer_notes' => 'Customer facing note',
], $userId);
$jobQuoteId = (int) $jobQuote['id'];
$jobQuoteRow = $repo->find($jobQuoteId);
$quotes->addProductLine($jobQuoteId, [
    'product_id' => $productId,
    'width_mm' => '1000',
    'height_mm' => '1000',
    'quantity' => '1',
    'waste_mode' => 'ACTUAL',
], (int) $jobQuoteRow['version_number'], $userId);
$optional = $quotes->addCustomLine($jobQuoteId, [
    'customer_description' => 'Optional installation',
    'quantity' => '1',
    'unit_cost' => '1000',
    'final_sell_price' => '2500',
    'is_optional' => '1',
], (int) $repo->find($jobQuoteId)['version_number'], $userId);
if ($optional['errors'] !== []) {
    $fail('optional line ' . implode(' ', $optional['errors']));
}
$jobQuoteRow = $repo->find($jobQuoteId);
$money('optional line stays out of the total', '115.50', $jobQuoteRow['subtotal']);

$quoted = (new OpportunityRepository())->find($opportunityId);
if (($quoted['status'] ?? '') !== 'QUOTED') {
    $fail('opportunity status after quote ' . ($quoted['status'] ?? ''));
} else {
    $ok('opportunity marked quoted');
}

$numberBefore = (string) $jobQuoteRow['quote_number'];
$sent = $quotes->changeStatus($jobQuoteId, 'SENT', (int) $jobQuoteRow['version_number'], $userId, 'Emailed');
if ($sent !== []) {
    $fail('mark sent ' . json_encode($sent));
}
$locked = $quotes->save($jobQuoteId, ['vat_mode' => 'NO_VAT', 'discount_type' => 'NONE'], (int) $repo->find($jobQuoteId)['version_number'], $userId);
if (!isset($locked['_form']) || !str_contains($locked['_form'], 'locked')) {
    $fail('sent quote remained editable');
} else {
    $ok('sent quote is locked');
}
$revision = $quotes->createRevision($jobQuoteId, (int) $repo->find($jobQuoteId)['version_number'], $userId, 'Customer asked for a change');
if ($revision !== []) {
    $fail('create revision ' . json_encode($revision));
}
$jobQuoteRow = $repo->find($jobQuoteId);
if ((string) $jobQuoteRow['quote_number'] !== $numberBefore || (int) $jobQuoteRow['revision_number'] !== 2 || $jobQuoteRow['status'] !== 'DRAFT') {
    $fail('revision identity ' . $jobQuoteRow['quote_number'] . ' r' . $jobQuoteRow['revision_number'] . ' ' . $jobQuoteRow['status']);
} else {
    $ok('revision kept the quote number and opened revision 2');
}
$old = $quotes->revisionSnapshot($jobQuoteId, 1);
if ($old === null || (int) ($old['quote']['revision_number'] ?? 0) !== 1) {
    $fail('original revision snapshot missing');
} else {
    $ok('original revision remains readable');
}

$ready = $quotes->changeStatus($jobQuoteId, 'READY', (int) $jobQuoteRow['version_number'], $userId);
$accepted = $quotes->accept($jobQuoteId, [
    'accepted_by_name' => 'Thandi Nkosi',
    'acceptance_method' => 'EMAIL',
    'acceptance_reference' => 'mail-' . $stamp,
    'acceptance_notes' => 'Accepted by email',
], (int) $repo->find($jobQuoteId)['version_number'], $userId);
if ($ready !== [] || $accepted !== []) {
    $fail('accept ' . json_encode($ready) . ' ' . json_encode($accepted));
}
$jobQuoteRow = $repo->find($jobQuoteId);
if ($jobQuoteRow['status'] !== 'ACCEPTED' || $jobQuoteRow['accepted_by_name'] !== 'Thandi Nkosi' || $jobQuoteRow['acceptance_method'] !== 'EMAIL') {
    $fail('acceptance record');
} else {
    $ok('acceptance stored');
}
$history = $repo->history($jobQuoteId);
$sawAccepted = false;
foreach ($history as $row) {
    if (($row['new_status'] ?? '') === 'ACCEPTED') {
        $sawAccepted = true;
    }
}
if (!$sawAccepted) {
    $fail('status history missing ACCEPTED');
} else {
    $ok('status history recorded acceptance');
}
$editAccepted = $quotes->save($jobQuoteId, ['discount_type' => 'PERCENTAGE', 'discount_value' => '50'], (int) $jobQuoteRow['version_number'], $userId);
if (!isset($editAccepted['_form'])) {
    $fail('accepted quote allowed a financial edit');
} else {
    $ok('accepted quote financial edit is locked');
}

$converted = $quotes->convert($jobQuoteId, [
    'title' => 'Shopfront ' . $stamp,
    'priority' => 'HIGH',
    'target_date' => date('Y-m-d', strtotime('+14 days')),
], (int) $repo->find($jobQuoteId)['version_number'], $userId);
if ($converted['id'] === null) {
    $fail('convert ' . json_encode($converted['errors']));
}
$job = (new JobRepository())->find((int) $converted['id']);
if ($job === null || !preg_match('/^SFJ-\d{4}-\d{4,}$/', (string) $job['job_number'])) {
    $fail('job number');
} else {
    $ok('job ' . $job['job_number']);
}
if ((int) $job['customer_id'] !== $customerId || (int) $job['quote_id'] !== $jobQuoteId || (int) $job['quote_revision_number'] !== 2) {
    $fail('job linkage');
} else {
    $ok('job links the customer, quote, and revision');
}
$again = $quotes->convert($jobQuoteId, ['title' => 'Second job'], (int) $repo->find($jobQuoteId)['version_number'], $userId);
if ($again['id'] !== null) {
    $fail('duplicate conversion created job ' . $again['id']);
} else {
    $ok('duplicate conversion prevented');
}
$won = (new OpportunityRepository())->find($opportunityId);
if (($won['status'] ?? '') !== 'WON') {
    $fail('opportunity after conversion ' . ($won['status'] ?? ''));
} else {
    $ok('linked opportunity marked won');
}
$blocked = $quotes->createRevision($jobQuoteId, (int) $repo->find($jobQuoteId)['version_number'], $userId, 'Too late');
if ($blocked === []) {
    $fail('converted quote created another revision');
} else {
    $ok('converted quote cannot open a new revision');
}

$lost = (new OpportunityService())->create([
    'customer_id' => $customerId,
    'title' => 'Lost enquiry ' . $stamp,
    'source' => 'PHONE',
    'status' => 'NEW',
], $userId);
$lostResult = (new OpportunityService())->markLost((int) $lost['id'], 'PRICE', 'Too expensive');
$lostRow = (new OpportunityRepository())->find((int) $lost['id']);
if ($lostResult !== [] || ($lostRow['status'] ?? '') !== 'LOST' || ($lostRow['lost_reason'] ?? '') !== 'PRICE') {
    $fail('lost reason');
} else {
    $ok('lost reason stored');
}

$document = $repo->find($quoteId);
$html = View::capture('quotes/pdf_shell', [
    'quote' => $document,
    'items' => $repo->items($quoteId),
    'sections' => $repo->sections($quoteId),
    'print' => false,
    'company' => [
        'name' => SettingsService::get('company_name', 'Sign-Forge Signs'),
        'vat' => SettingsService::get('vat_number', ''),
        'symbol' => SettingsService::symbol(),
        'address' => '',
        'telephone' => '',
        'email' => '',
    ],
]);
foreach ([
    'INTERNAL-ONLY-NOTE-' . $stamp,
    'Gross profit',
    'markup',
    'Supplier',
    'waste',
] as $forbidden) {
    if (stripos($html, $forbidden) !== false) {
        $fail('customer document contains ' . $forbidden);
    } else {
        $ok('customer document omits ' . $forbidden);
    }
}
if (!str_contains($html, (string) $document['quote_number']) || !str_contains($html, money((string) $document['total']))) {
    $fail('customer document totals do not match the saved quote');
} else {
    $ok('customer document total matches the database');
}

$long = $quotes->create(['customer_id' => $customerId], $userId);
$longId = (int) $long['id'];
$version = (int) $repo->find($longId)['version_number'];
for ($i = 1; $i <= 28; $i++) {
    $result = $quotes->addCustomLine($longId, [
        'customer_description' => 'Panel ' . $i . ' ' . str_repeat('Full-colour printed and laminated vinyl graphics for the reception bulkhead. ', 4),
        'quantity' => '1',
        'unit_cost' => '10',
        'final_sell_price' => '40',
    ], $version, $userId);
    if ($result['errors'] !== []) {
        $fail('long line ' . $i . ' ' . implode(' ', $result['errors']));
        break;
    }
    $version = (int) $repo->find($longId)['version_number'];
}
$longQuote = $repo->find($longId);
$longHtml = View::capture('quotes/pdf_shell', [
    'quote' => $longQuote,
    'items' => $repo->items($longId),
    'sections' => [],
    'print' => true,
    'company' => ['name' => 'Sign-Forge Signs', 'symbol' => 'R', 'vat' => '', 'address' => '', 'telephone' => '', 'email' => ''],
]);
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($longHtml);
$dompdf->setPaper('A4');
$dompdf->render();
$binary = $dompdf->output();
$pages = $dompdf->getCanvas()->get_page_count();
if (!str_starts_with($binary, '%PDF') || $pages < 2) {
    $fail('multi-page pdf pages ' . $pages);
} else {
    $ok('multi-page pdf has ' . $pages . ' pages');
}
$filename = (new QuotePdf())->filename((string) $longQuote['quote_number'], (int) $longQuote['revision_number']);
if (!preg_match('/^SFQ-\d{4}-\d{4,}-R\d+\.pdf$/', $filename)) {
    $fail('pdf filename ' . $filename);
} else {
    $ok('pdf filename ' . $filename);
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAll sales flow checks passed.\n");

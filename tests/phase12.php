<?php

declare(strict_types=1);

/**
 * Phase 12 planning, forecasting, MRP, and integrations.
 *
 *   php tests/phase12.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\CategoryRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\ForecastRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use App\Services\AccountingIntegrationService;
use App\Services\ApiClientService;
use App\Services\BacklogService;
use App\Services\BudgetService;
use App\Services\CapacityForecastService;
use App\Services\CashForecastService;
use App\Services\CustomerService;
use App\Services\ForecastSnapshotService;
use App\Services\ImportService;
use App\Services\InvoiceForecastService;
use App\Services\InvoiceService;
use App\Services\MaterialPlanningService;
use App\Services\OpportunityService;
use App\Services\OutboundWebhookService;
use App\Services\PlanningMath;
use App\Services\ProductService;
use App\Services\QuoteService;
use App\Services\ScenarioService;
use App\Services\SettingsService;
use App\Services\UserAdminService;

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

$eq('weighted pipeline', '40000.00', PlanningMath::weighted('100000', '40'));
$eq('blank probability is not invented', '0.00', PlanningMath::weighted('100000', null));
$eq('mrp net', '40.0000', PlanningMath::netRequirement('120', '50', '40', '10'));
$eq('moq raises the order', '50.0000', PlanningMath::orderQuantity('37', '50', null));
$eq('pack size rounds up', '30.0000', PlanningMath::orderQuantity('23', null, '10'));
$eq('order by before the weekend', '2026-10-09', PlanningMath::orderByDate('2026-10-20', 7, 2, true));
$eq('order by without the weekend rule', '2026-10-11', PlanningMath::orderByDate('2026-10-20', 7, 2, false));

$capacity = (new CapacityForecastService())->summarise('40', '25', '12', '15');
$eq('committed remaining capacity', '3.00', $capacity['remaining_committed_hours']);
$eq('forecast hours stay separate', '15', $capacity['forecast_hours']);
$eq('forecast is not a bottleneck', '', $capacity['bottleneck']);
$overload = (new CapacityForecastService())->summarise('80', '102', '0', '15');
$eq('capacity shortfall', '22.00', $overload['shortfall_hours']);
$eq('projected bottleneck label', 'PROJECTED BOTTLENECK', $overload['bottleneck']);

$budget = (new BudgetService())->compare('500000', '460000');
$eq('budget variance', '-40000.00', $budget['variance']);
$eq('budget variance percent', '-8.00', $budget['variance_percent']);
$eq('operational budget label', 'OPERATIONAL BUDGET', $budget['label']);

$accuracy = (new ForecastSnapshotService())->accuracy('400000', '325000');
$eq('forecast accuracy difference', '-75000.00', $accuracy['difference']);

$mrp = new MaterialPlanningService();
$planned = $mrp->plan('70', '20', [
    ['quantity' => '120', 'required_by' => '2026-10-15', 'category' => 'FIRM', 'label' => 'Printable vinyl'],
    ['quantity' => '999', 'required_by' => '2026-10-01', 'category' => 'FORECAST', 'label' => 'Open opportunity'],
], [
    ['quantity' => '40', 'arrives' => '2026-10-12'],
], '10');
$eq('mrp example net', '40.0000', $planned['net']);
$eq('mrp available', '50.0000', $planned['available']);

$late = $mrp->plan('50', '0', [
    ['quantity' => '120', 'required_by' => '2026-10-15', 'category' => 'FIRM', 'label' => 'Job 15 Oct'],
], [
    ['quantity' => '50', 'arrives' => '2026-10-20'],
], '0');
$eq('late purchase order does not cover 15 Oct', '70.0000', $late['net']);

$phased = $mrp->plan('40', '0', [
    ['quantity' => '40', 'required_by' => '2026-10-08', 'category' => 'FIRM', 'label' => 'Week 1'],
    ['quantity' => '90', 'required_by' => '2026-10-15', 'category' => 'PLANNED', 'label' => 'Week 2'],
], [], '0');
$eq('later demand does not create an earlier shortage', '0.0000', $phased['weeks'][0]['net']);
$eq('second week keeps its own shortage', '90.0000', $phased['weeks'][1]['net']);

$backlog = new BacklogService();
$eq('completed commercial scope', '40000.00', $backlog->completedValue('100000.00', '100', '40'));
$eq('unscheduled bucket', 'Unscheduled', $backlog->bucket(''));

$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Phase12 ' . $stamp,
    'email' => 'phase12-' . $stamp . '@signforge.local',
    'active' => '1',
], $userId);
if ($customer['id'] === null) {
    fwrite(STDERR, 'customer ' . json_encode($customer['errors']) . "\n");
    exit(1);
}
$customerId = (int) $customer['id'];

$opportunity = (new OpportunityService())->create([
    'customer_id' => $customerId,
    'title' => 'Weighted ' . $stamp,
    'source' => 'OTHER',
    'status' => 'NEW',
    'estimated_value' => '100000',
    'probability_percent' => '40',
    'expected_close_date' => '2026-10-20',
], $userId);
if ($opportunity['id'] === null) {
    fwrite(STDERR, 'opportunity ' . json_encode($opportunity['errors']) . "\n");
    exit(1);
}
$sales = (new \App\Services\SalesForecastService())->report('30');
$weightedRow = null;
foreach ($sales['rows'] as $row) {
    if ($row['title'] === 'Weighted ' . $stamp) {
        $weightedRow = $row;
    }
}
$eq('weighted row value', '100000.00', $weightedRow['value'] ?? null);
$eq('weighted row', '40000.00', $weightedRow['weighted'] ?? null);
$eq('weighted label', 'WEIGHTED PIPELINE', $sales['label']);
$eq('open pipeline bucket', 'Open pipeline', $weightedRow['bucket'] ?? null);
$eq('sales forecast names its source', 'Open opportunities and open quotes', $sales['source']);

$categoryId = (int) (new CategoryRepository())->allWithParent()[0]['id'];
$product = (new ProductService())->create([
    'category_id' => $categoryId,
    'sku' => 'P12-' . $stamp,
    'name' => 'ACM ' . $stamp,
    'product_type' => 'MATERIAL',
    'pricing_method' => 'UNIT',
    'cost_price' => '100',
    'standard_waste_percent' => '0',
    'default_waste_policy' => 'ACTUAL',
    'active' => '1',
], $userId);
$productId = (int) $product['id'];
$scenario = (new ScenarioService())->run([
    'name' => 'Vinyl up ' . $stamp,
    'scenario_type' => 'MATERIAL_PRICE',
    'percent' => '10',
    'product_id' => $productId,
], $userId);
$eq('scenario cost', '110.0000', $scenario['result']['scenario_cost'] ?? null);
$live = (new ForecastRepository())->product($productId);
$eq('live product cost unchanged', '100.0000', (string) ($live['cost_price'] ?? ''));

$quotes = new QuoteService();
$quoteRepo = new QuoteRepository();
$made = $quotes->create(['customer_id' => $customerId], $userId);
$quoteId = (int) $made['id'];
$quotes->addCustomLine($quoteId, [
    'customer_description' => 'Large panel',
    'quantity' => '60',
    'unit_cost' => '10',
    'final_sell_price' => '20',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quotes->addCustomLine($quoteId, [
    'customer_description' => 'Small panel',
    'quantity' => '40',
    'unit_cost' => '10',
    'final_sell_price' => '20',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quoteBefore = (string) $quoteRepo->find($quoteId)['total'];
$again = (new ScenarioService())->run([
    'name' => 'Quote stays ' . $stamp,
    'scenario_type' => 'MATERIAL_PRICE',
    'percent' => '10',
    'product_id' => $productId,
    'quote_id' => $quoteId,
], $userId);
$eq('scenario leaves the quote total', $quoteBefore, $again['result']['quote_total_unchanged'] ?? null);
$eq('stored quote total unchanged', $quoteBefore, (string) $quoteRepo->find($quoteId)['total']);

$quotes->changeStatus($quoteId, 'READY', (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$quotes->accept($quoteId, [
    'accepted_by_name' => 'Buyer',
    'acceptance_method' => 'EMAIL',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
$converted = $quotes->convert($quoteId, [
    'title' => 'Backlog ' . $stamp,
    'delivery_method' => 'COLLECTION',
    'target_date' => '2026-10-20',
], (int) $quoteRepo->find($quoteId)['version_number'], $userId);
if ($converted['id'] === null) {
    fwrite(STDERR, 'convert ' . json_encode($converted['errors']) . "\n");
    exit(1);
}
$jobId = (int) $converted['id'];
$pdo = Database::connection();
$pdo->prepare('UPDATE jobs SET quoted_revenue_snapshot = ? WHERE id = ?')->execute(['100000.00', $jobId]);
$items = $pdo->prepare('SELECT id, quantity FROM job_items WHERE job_id = ? ORDER BY quantity DESC');
$items->execute([$jobId]);
$jobItems = $items->fetchAll();
$eq('two job items', 2, count($jobItems));
$pdo->prepare("UPDATE job_items SET production_status = 'COMPLETE' WHERE id = ?")->execute([(int) $jobItems[1]['id']]);
$report = $backlog->report();
$backlogRow = null;
foreach ($report['rows'] as $row) {
    if ((int) $row['job_id'] === $jobId) {
        $backlogRow = $row;
    }
}
$eq('backlog commercial value', '100000.00', $backlogRow['commercial'] ?? null);
$eq('backlog completed scope', '40000.00', $backlogRow['completed_scope'] ?? null);
$eq('remaining backlog', '60000.00', $backlogRow['backlog'] ?? null);
$eq('invoiced amount is not the completion figure', '0.00', $backlogRow['invoiced'] ?? null);
$invoiceCount = (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
$schedule = (new InvoiceForecastService())->schedule();
$forecastLine = null;
foreach ($schedule['lines'] as $line) {
    if ($line['job_number'] === ($backlogRow['job_number'] ?? '')) {
        $forecastLine = $line;
    }
}
$eq('potential invoice basis', 'JOB COMPLETION', $forecastLine['basis'] ?? null);
$eq('forecast does not create an invoice', $invoiceCount, (int) $pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn());

$invoices = new InvoiceService();
$finance = new FinanceRepository();
$draft = $invoices->create([
    'customer_id' => $customerId,
    'invoice_type' => 'STANDARD',
    'vat_mode' => 'NO_VAT',
    'description' => 'Unissued ' . $stamp,
    'amount' => '5000',
    'due_date' => '2026-10-15',
], $userId);
if ($draft['id'] === null) {
    fwrite(STDERR, 'draft ' . json_encode($draft['errors']) . "\n");
    exit(1);
}
$cashBefore = (new CashForecastService())->report('DUE_DATE');
$draftListed = false;
foreach ($cashBefore['receivable_lines'] as $line) {
    if ($line['amount'] === '5000.00' && $line['due_date'] === '2026-10-15' && str_contains((string) $line['invoice_number'], $stamp)) {
        $draftListed = true;
    }
}
$eq('draft is not a contractual receivable', false, $draftListed);

$issuedDraft = $invoices->create([
    'customer_id' => $customerId,
    'invoice_type' => 'STANDARD',
    'vat_mode' => 'NO_VAT',
    'description' => 'Issued ' . $stamp,
    'amount' => '20000',
    'due_date' => '2026-10-15',
    'invoice_date' => '2026-10-01',
], $userId);
$issuedRow = $finance->invoice((int) $issuedDraft['id']);
$issueErrors = $invoices->issue((int) $issuedDraft['id'], (int) $issuedRow['version_number'], $userId);
$eq('invoice issued', [], $issueErrors);
$issuedRow = $finance->invoice((int) $issuedDraft['id']);
$cash = (new CashForecastService())->report('DUE_DATE');
$found = null;
foreach ($cash['receivable_lines'] as $line) {
    if ($line['invoice_number'] === (string) $issuedRow['invoice_number']) {
        $found = $line;
    }
}
$eq('contractual receivable amount', '20000.00', $found['amount'] ?? null);
$eq('contractual receivable due date', '2026-10-15', $found['due_date'] ?? null);
$eq('receivable basis', 'CONTRACTUAL RECEIVABLE', $found['basis'] ?? null);
$eq('cash warning', 'OPERATIONAL FORECAST — NOT BANK RECONCILIATION', $cash['warning']);
$eq('selected cash scenario', 'DUE_DATE', $cash['scenario']);

$settings = new SettingRepository();
$previousProvider = SettingsService::get('accounting_provider', '');
$settings->put('accounting_provider', 'unavailable');
SettingsService::forget();
$syncInvoice = $invoices->create([
    'customer_id' => $customerId,
    'invoice_type' => 'STANDARD',
    'vat_mode' => 'NO_VAT',
    'description' => 'Sync ' . $stamp,
    'amount' => '100',
    'due_date' => '2026-10-20',
], $userId);
$syncRow = $finance->invoice((int) $syncInvoice['id']);
$syncErrors = $invoices->issue((int) $syncInvoice['id'], (int) $syncRow['version_number'], $userId);
$eq('invoice stays issued when accounting is down', [], $syncErrors);
$syncRow = $finance->invoice((int) $syncInvoice['id']);
$eq('internal invoice status', 'ISSUED', (string) $syncRow['status']);
$mapping = $pdo->prepare('SELECT sync_status FROM integration_mappings WHERE provider = ? AND entity_type = ? AND entity_id = ?');
$mapping->execute(['unavailable', 'invoice', (int) $syncInvoice['id']]);
$eq('sync marked failed', 'FAILED', (string) $mapping->fetchColumn());
$settings->put('accounting_provider', (string) $previousProvider);
SettingsService::forget();
$eq('accounting export columns stay off the journal', true, !in_array('journal', (new AccountingIntegrationService())->exportColumns(), true));

$csv = "company_name,email,phone\n";
for ($i = 1; $i <= 95; $i++) {
    $csv .= 'Import ' . $stamp . ' ' . $i . ',import-' . $stamp . '-' . $i . '@signforge.local,010' . str_pad((string) $i, 7, '0', STR_PAD_LEFT) . "\n";
}
for ($i = 1; $i <= 5; $i++) {
    $csv .= ',bad-' . $i . '@signforge.local,010000000' . "\n";
}
$importer = new ImportService();
$beforeCustomers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$preview = $importer->previewCustomers($csv);
$eq('import preview valid', 95, $preview['valid']);
$eq('import preview invalid', 5, $preview['invalid']);
$afterPreview = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$eq('preview does not write', $beforeCustomers, $afterPreview);
$committed = $importer->commitCustomers($preview['rows'], 'customers-' . $stamp . '.csv', $userId);
$eq('import writes the valid rows', 95, $committed['imported']);
$eq('import skips the invalid rows', 5, $committed['skipped']);

$warning = $importer->previewCustomers("company_name,email,phone\nPhase12 {$stamp},other-{$stamp}@signforge.local,0109999999\n");
$eq('same company name is still valid', true, $warning['rows'][0]['ok']);
$eq('same company name is only a warning', true, $warning['rows'][0]['warnings'] !== []);
$duplicate = $importer->previewCustomers("company_name,email,phone\nOther {$stamp},phase12-{$stamp}@signforge.local,0108888888\n");
$eq('email match is not imported', false, $duplicate['rows'][0]['ok']);

$client = (new ApiClientService())->create('Phase 12 ' . $stamp, ['customers.read'], $userId);
$stored = $pdo->prepare('SELECT secret_hash FROM api_clients WHERE id = ?');
$stored->execute([$client['id']]);
$hash = (string) $stored->fetchColumn();
$eq('secret is hashed', hash('sha256', $client['secret']), $hash);
$eq('plain secret is not the stored value', true, $hash !== $client['secret']);

$hooks = new OutboundWebhookService();
$pdo->prepare('INSERT INTO webhook_subscriptions (name, endpoint_url, secret, event_types_json, active) VALUES (?, ?, ?, ?, 1)')
    ->execute(['Fail ' . $stamp, 'test://fail', 'hook-secret', json_encode(['invoice.issued'])]);
$eventId = $hooks->emit('invoice.issued', 'invoice', (int) $syncInvoice['id'], ['invoice_id' => (int) $syncInvoice['id']]);
$delivery = $pdo->prepare('SELECT status, next_retry_at, event_id FROM webhook_deliveries WHERE event_id = ?');
$delivery->execute([$eventId]);
$deliveryRow = $delivery->fetch();
$eq('failed receiver schedules a retry', 'RETRY', (string) ($deliveryRow['status'] ?? ''));
$eq('retry has a time', true, ($deliveryRow['next_retry_at'] ?? null) !== null);
$eq('invoice still issued after the webhook failure', 'ISSUED', (string) $finance->invoice((int) $syncInvoice['id'])['status']);

$first = (new ForecastSnapshotService())->store('SALES', '2026-09-01', '2026-09-01', '2026-09-30', ['horizon' => '30 days'], ['weighted_pipeline' => '1.00'], $userId);
$second = (new ForecastSnapshotService())->store('SALES', '2026-10-01', '2026-10-01', '2026-10-31', ['horizon' => '30 days'], ['weighted_pipeline' => '2.00'], $userId);
$eq('snapshots are separate rows', true, $second > $first);

$index = $pdo->query("SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name IN (
    'idx_forecast_snapshots_type_date', 'idx_opportunities_close', 'idx_jobs_target', 'idx_purchase_recommendations_required',
    'idx_purchase_orders_expected', 'idx_budget_lines_period', 'uq_api_clients_identifier', 'idx_webhook_deliveries_retry',
    'uq_integration_mappings_entity'
)")->fetchColumn();
$eq('planning indexes exist', 9, (int) $index);

$started = microtime(true);
(new \App\Services\SalesForecastService())->report('90');
$elapsed = microtime(true) - $started;
$eq('sales forecast stays within a few seconds', true, $elapsed < 5);

$roles = [];
foreach ($pdo->query('SELECT id, code FROM roles') as $role) {
    $roles[(string) $role['code']] = (int) $role['id'];
}
$salesUser = (new UserAdminService())->create([
    'name' => 'Sales ' . $stamp,
    'email' => 'phase12-sales-' . $stamp . '@signforge.local',
    'password' => 'Salesperson#2026',
    'role_id' => $roles['SALES'],
    'active' => '1',
]);
$pdo->prepare('UPDATE users SET must_change_password = 0 WHERE id = ?')->execute([(int) $salesUser['id']]);
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phase12_security.php')
    . ' ' . (int) $salesUser['id'];
exec($command . ' 2>&1', $securityOut, $securityCode);
$securityText = implode("\n", $securityOut);
$eq('salesperson cash forecast denied', true, $securityCode === 0 && str_contains($securityText, 'Not allowed'));

$api = static function (string $token, string $path, string $id) use ($fail, $ok): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/phase12_api.php')
        . ' ' . escapeshellarg($token) . ' ' . escapeshellarg($path) . ' ' . escapeshellarg($id);
    exec($command . ' 2>&1', $output, $code);
    $text = implode("\n", $output);
    if (!preg_match('/STATUS (\d+)/', $text, $match)) {
        $fail('api status missing ' . substr($text, 0, 180));

        return ['status' => 0, 'body' => $text];
    }
    $ok('api status ' . $match[1] . ' ' . $path);

    return ['status' => (int) $match[1], 'body' => $text];
};
$denied = $api('not-a-token', '/api/v1/customers/' . $customerId, (string) $customerId);
$eq('invalid token', 401, $denied['status']);
$narrow = (new ApiClientService())->create('Narrow ' . $stamp, ['jobs.read'], $userId);
$forbidden = $api($narrow['identifier'] . '.' . $narrow['secret'], '/api/v1/customers/' . $customerId, (string) $customerId);
$eq('missing scope', 403, $forbidden['status']);
$allowed = $api($client['identifier'] . '.' . $client['secret'], '/api/v1/customers/' . $customerId, (string) $customerId);
$eq('authorised token', 200, $allowed['status']);
$eq('api body has no trace', true, !str_contains($allowed['body'], 'Stack trace') && str_contains($allowed['body'], '"success":true'));

if ($failures > 0) {
    fwrite(STDERR, "{$failures} failed\n");
    exit(1);
}
fwrite(STDOUT, "phase12 ok\n");

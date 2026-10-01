<?php

declare(strict_types=1);

/**
 * Invoices, payments, credits, variations, and ageing.
 *
 *   php tests/finance_flow.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\FinanceRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RoleRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserRepository;
use App\Services\CreditNoteService;
use App\Services\CustomerService;
use App\Services\DebtorAgeingService;
use App\Services\FinanceMath;
use App\Services\InvoiceService;
use App\Services\JobFinancialService;
use App\Services\PaymentService;
use App\Services\QuoteService;
use App\Services\SettingsService;
use App\Services\VariationService;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$eq = static function (string $label, string $expected, string $actual) use ($fail, $ok): void {
    if (Decimal::cmp($expected, (string) $actual) !== 0) {
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
$quotes = new QuoteService();
$quoteRows = new QuoteRepository();
$invoices = new InvoiceService();
$finance = new FinanceRepository();

$accepted = static function (string $name, string $sell, array $header) use ($userId, $stamp, $quotes, $quoteRows): array {
    static $n = 0;
    $n++;
    $customer = (new CustomerService())->create([
        'customer_type' => 'BUSINESS',
        'company_name' => $name . ' ' . $stamp . '-' . $n,
        'billing_address' => '12 Old Road',
        'active' => '1',
    ], $userId);
    $created = $quotes->create(array_merge([
        'customer_id' => $customer['id'],
        'quote_date' => date('Y-m-d'),
    ], $header), $userId);
    if ($created['errors'] !== []) {
        throw new RuntimeException('quote ' . json_encode($created['errors']));
    }
    $id = (int) $created['id'];
    $row = $quoteRows->find($id);
    $line = $quotes->addCustomLine($id, [
        'customer_description' => $name,
        'quantity' => '1',
        'unit_cost' => '10',
        'final_sell_price' => $sell,
        'override_reason' => 'Test price',
    ], (int) $row['version_number'], $userId);
    if ($line['errors'] !== []) {
        throw new RuntimeException('line ' . json_encode($line['errors']));
    }
    $row = $quoteRows->find($id);
    $saved = $quotes->save($id, array_merge([
        'quote_date' => date('Y-m-d'),
    ], $header), (int) $row['version_number'], $userId);
    if ($saved !== []) {
        throw new RuntimeException('save ' . json_encode($saved));
    }
    $row = $quoteRows->find($id);
    $ready = $quotes->changeStatus($id, 'READY', (int) $row['version_number'], $userId);
    if ($ready !== []) {
        throw new RuntimeException('ready ' . json_encode($ready));
    }
    $row = $quoteRows->find($id);
    $accepted = $quotes->accept($id, [
        'accepted_by_name' => 'Test Buyer',
        'acceptance_method' => 'EMAIL',
    ], (int) $row['version_number'], $userId);
    if ($accepted !== []) {
        throw new RuntimeException('accept ' . json_encode($accepted));
    }
    $row = $quoteRows->find($id);
    $job = $quotes->convert($id, ['title' => $name], (int) $row['version_number'], $userId);
    if ($job['errors'] !== []) {
        throw new RuntimeException('job ' . json_encode($job['errors']));
    }

    return ['customer_id' => (int) $customer['id'], 'quote_id' => $id, 'job_id' => (int) $job['id'], 'quote' => $quoteRows->find($id)];
};

$issue = static function (int $id) use ($invoices, $finance, $userId): array {
    $row = $finance->invoice($id);
    $errors = $invoices->issue($id, (int) $row['version_number'], $userId);
    if ($errors !== []) {
        throw new RuntimeException('issue ' . json_encode($errors));
    }

    return $finance->invoice($id);
};

$full = $accepted('Full invoice', '10000', []);
$eq('quote total with vat', '11500.00', (string) $full['quote']['total']);
$draft = $invoices->create([
    'customer_id' => $full['customer_id'],
    'quote_id' => $full['quote_id'],
    'job_id' => $full['job_id'],
    'invoice_type' => 'STANDARD',
], $userId);
if ($draft['errors'] !== []) {
    $fail('full draft ' . json_encode($draft['errors']));
} else {
    $issued = $issue((int) $draft['id']);
    $eq('full invoice subtotal', '10000.00', (string) $issued['subtotal']);
    $eq('full invoice vat', '1500.00', (string) $issued['vat_amount']);
    $eq('full invoice total', '11500.00', (string) $issued['total']);
    if (!str_starts_with((string) $issued['invoice_number'], 'SFI-')) {
        $fail('invoice number ' . $issued['invoice_number']);
    } else {
        $ok('invoice number ' . $issued['invoice_number']);
    }
    if ((string) $issued['customer_address_snapshot'] !== '12 Old Road') {
        $fail('snapshot missing');
    } else {
        $ok('customer address snapshotted');
    }
    $customer = (new \App\Repositories\CustomerRepository())->find($full['customer_id']);
    (new CustomerService())->update($full['customer_id'], array_merge($customer, [
        'billing_address' => '99 New Street',
        'active' => '1',
    ]));
    $settings = new SettingRepository();
    $settings->put('address', 'New company yard');
    $settings->put('default_vat_percent', '20');
    SettingsService::forget();
    $again = $finance->invoice((int) $issued['id']);
    if ((string) $again['customer_address_snapshot'] !== '12 Old Road' || (string) $again['vat_rate'] !== (string) $issued['vat_rate']) {
        $fail('issued invoice changed after master data edit');
    } else {
        $ok('issued invoice stayed historical');
    }
    $html = \App\Helpers\View::capture('invoices/pdf', ['invoice' => $again, 'items' => $finance->items((int) $again['id']), 'company' => 'Sign-Forge']);
    if (!str_contains($html, '12 Old Road') || str_contains($html, '99 New Street') || str_contains($html, 'internal_notes')) {
        $fail('pdf did not keep the historical address');
    } else {
        $ok('pdf uses the issued snapshot');
    }
    $settings->put('default_vat_percent', '15');
    $settings->put('address', '');
    SettingsService::forget();
}

$depositJob = $accepted('Deposit job', '23000', [
    'vat_mode' => 'INCLUSIVE',
    'deposit_type' => 'PERCENTAGE',
    'deposit_value' => '50',
]);
$eq('inclusive commercial', '23000.00', (string) $depositJob['quote']['total']);
$deposit = $invoices->create([
    'customer_id' => $depositJob['customer_id'],
    'quote_id' => $depositJob['quote_id'],
    'job_id' => $depositJob['job_id'],
    'invoice_type' => 'DEPOSIT',
], $userId);
$depositIssued = $issue((int) $deposit['id']);
$eq('deposit invoice', '11500.00', (string) $depositIssued['total']);
$second = $invoices->create([
    'customer_id' => $depositJob['customer_id'],
    'quote_id' => $depositJob['quote_id'],
    'job_id' => $depositJob['job_id'],
    'invoice_type' => 'DEPOSIT',
], $userId);
if ($second['errors'] === []) {
    $fail('second deposit was allowed');
} else {
    $ok('second deposit refused');
}
$final = $invoices->create([
    'customer_id' => $depositJob['customer_id'],
    'quote_id' => $depositJob['quote_id'],
    'job_id' => $depositJob['job_id'],
    'invoice_type' => 'FINAL',
], $userId);
$finalRow = $finance->invoice((int) $final['id']);
$eq('final after deposit', '11500.00', (string) $finalRow['total']);

$progressJob = $accepted('Progress job', '100000', [
    'vat_mode' => 'NO_VAT',
    'deposit_type' => 'FIXED_AMOUNT',
    'deposit_value' => '30000',
]);
$progressDeposit = $invoices->create([
    'customer_id' => $progressJob['customer_id'],
    'quote_id' => $progressJob['quote_id'],
    'job_id' => $progressJob['job_id'],
    'invoice_type' => 'DEPOSIT',
], $userId);
$issue((int) $progressDeposit['id']);
$progress = $invoices->create([
    'customer_id' => $progressJob['customer_id'],
    'quote_id' => $progressJob['quote_id'],
    'job_id' => $progressJob['job_id'],
    'invoice_type' => 'PROGRESS',
    'progress_amount' => '40000',
], $userId);
$issue((int) $progress['id']);
$position = $invoices->position($progressJob['quote_id'], $progressJob['job_id']);
$eq('invoiced to date', '70000.00', $position['invoiced']);
$eq('remaining to invoice', '30000.00', $position['remaining']);

$payCustomer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Payer ' . $stamp,
    'active' => '1',
], $userId);
$manual = $invoices->create([
    'customer_id' => $payCustomer['id'],
    'invoice_type' => 'STANDARD',
    'description' => 'Counter sale',
    'amount' => '10000',
    'vat_mode' => 'NO_VAT',
], $userId);
$open = $issue((int) $manual['id']);
$payments = new PaymentService();
$part = $payments->record([
    'customer_id' => $payCustomer['id'],
    'amount' => '4000',
    'payment_method' => 'EFT',
    'allocations' => [(int) $open['id'] => '4000'],
], $userId);
$open = $finance->invoice((int) $open['id']);
$eq('partial paid', '4000.00', (string) $open['amount_paid']);
$eq('partial balance', '6000.00', (string) $open['balance_due']);
if ((string) $open['status'] !== 'PARTIALLY_PAID') {
    $fail('status ' . $open['status']);
} else {
    $ok('partially paid');
}
foreach (['3000', '2000', '1000'] as $amount) {
    $payments->record([
        'customer_id' => $payCustomer['id'],
        'amount' => $amount,
        'payment_method' => 'CASH',
        'allocations' => [(int) $open['id'] => $amount],
    ], $userId);
}
$open = $finance->invoice((int) $open['id']);
$eq('settled paid', '10000.00', (string) $open['amount_paid']);
$eq('settled balance', '0.00', (string) $open['balance_due']);
if ((string) $open['status'] !== 'PAID') {
    $fail('paid status ' . $open['status']);
} else {
    $ok('paid');
}

$splitCustomer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Split ' . $stamp,
    'active' => '1',
], $userId);
$makeOpen = static function (int $customerId, string $amount) use ($invoices, $userId, $issue): array {
    $row = $invoices->create([
        'customer_id' => $customerId,
        'invoice_type' => 'STANDARD',
        'description' => 'Sale',
        'amount' => $amount,
        'vat_mode' => 'NO_VAT',
    ], $userId);

    return $issue((int) $row['id']);
};
$invoiceA = $makeOpen((int) $splitCustomer['id'], '5000');
$invoiceB = $makeOpen((int) $splitCustomer['id'], '7000');
$bundle = $payments->record([
    'customer_id' => $splitCustomer['id'],
    'amount' => '15000',
    'payment_method' => 'EFT',
    'allocations' => [
        (int) $invoiceA['id'] => '5000',
        (int) $invoiceB['id'] => '7000',
    ],
], $userId);
$account = $finance->account((int) $splitCustomer['id']);
$eq('unallocated credit', '3000.00', $account['unallocated']);

$creditCustomer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Credit ' . $stamp,
    'active' => '1',
], $userId);
$creditInvoice = $makeOpen((int) $creditCustomer['id'], '10000');
$credits = new CreditNoteService();
$note = $credits->create([
    'customer_id' => $creditCustomer['id'],
    'invoice_id' => $creditInvoice['id'],
    'description' => 'Short charge',
    'amount' => '2000',
    'reason' => 'Agreed reduction',
    'vat_mode' => 'NO_VAT',
], $userId);
$credits->issue((int) $note['id'], $userId);
$creditInvoice = $finance->invoice((int) $creditInvoice['id']);
$eq('original invoice total', '10000.00', (string) $creditInvoice['total']);
$eq('net after credit', '8000.00', (string) $creditInvoice['balance_due']);

$variationJob = $accepted('Variation job', '20000', ['vat_mode' => 'NO_VAT']);
$variations = new VariationService();
$variation = $variations->create($variationJob['job_id'], ['description' => 'Extra letters'], $userId);
$variations->addItem((int) $variation['id'], [
    'description' => 'Letters',
    'quantity' => '1',
    'unit_price' => '5000',
], $userId);
$approved = $variations->approve((int) $variation['id'], $userId, 'Site manager', 'EMAIL');
if ($approved !== []) {
    $fail('approve ' . json_encode($approved));
}
$billed = $invoices->create([
    'customer_id' => $variationJob['customer_id'],
    'quote_id' => $variationJob['quote_id'],
    'job_id' => $variationJob['job_id'],
    'invoice_type' => 'PROGRESS',
    'progress_amount' => '10000',
], $userId);
$issue((int) $billed['id']);
$commercial = $invoices->position($variationJob['quote_id'], $variationJob['job_id']);
$eq('commercial with variation', '25000.00', $commercial['commercial']);
$eq('remaining after variation', '15000.00', $commercial['remaining']);
$jobFinance = (new JobFinancialService())->report($variationJob['job_id']);
$eq('job commercial', '25000.00', (string) $jobFinance['commercial']);
$eq('job invoiced', '10000.00', (string) $jobFinance['invoiced']);

$aged = $makeOpen((int) $creditCustomer['id'], '5000');
Database::connection()->prepare('UPDATE invoices SET due_date = ? WHERE id = ?')->execute([
    (new DateTimeImmutable('today'))->modify('-40 days')->format('Y-m-d'),
    (int) $aged['id'],
]);
$bucket = null;
foreach ((new DebtorAgeingService())->report()['lines'] as $line) {
    if ((int) $line['id'] === (int) $aged['id']) {
        $bucket = $line['bucket'];
    }
}
if ($bucket !== 'DAYS_31_60') {
    $fail('ageing ' . (string) $bucket);
} else {
    $ok('ageing 31-60');
}

$reversal = $payments->record([
    'customer_id' => $creditCustomer['id'],
    'amount' => '5000',
    'payment_method' => 'EFT',
    'allocations' => [(int) $aged['id'] => '5000'],
], $userId);
$payments->reverse((int) $reversal['id'], $userId, 'Entered on the wrong customer');
$reversed = $finance->payment((int) $reversal['id']);
$aged = $finance->invoice((int) $aged['id']);
if ((string) $reversed['status'] !== 'REVERSED') {
    $fail('payment status ' . $reversed['status']);
} else {
    $ok('payment remains reversed');
}
$eq('balance restored', '5000.00', (string) $aged['balance_due']);

$tooMuch = $payments->record([
    'customer_id' => $creditCustomer['id'],
    'amount' => '10',
    'payment_method' => 'CASH',
    'allocations' => [(int) $aged['id'] => '99999'],
], $userId);
if ($tooMuch['errors'] === []) {
    $fail('over allocation accepted');
} else {
    $ok('over allocation rejected');
}
$tooCredit = $credits->create([
    'customer_id' => $creditCustomer['id'],
    'invoice_id' => $aged['id'],
    'description' => 'Too much',
    'amount' => '9000',
    'reason' => 'Should fail',
], $userId);
$creditErrors = $credits->issue((int) $tooCredit['id'], $userId);
if ($creditErrors === []) {
    $fail('credit over application accepted');
} else {
    $ok('credit over application rejected');
}

$roles = [];
foreach ((new RoleRepository())->all() as $role) {
    $roles[(string) $role['code']] = (int) $role['id'];
}
$installerId = (new UserRepository())->insert([
    'name' => 'Installer ' . $stamp,
    'email' => 'installer-' . $stamp . '@signforge.local',
    'password_hash' => password_hash('Install#2026', PASSWORD_DEFAULT),
    'role_id' => $roles['INSTALLER'],
    'active' => 1,
    'must_change_password' => 0,
]);
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/finance_security.php')
    . ' ' . $installerId . ' ' . (int) $manual['id'];
exec($command . ' 2>&1', $output);
$text = implode("\n", $output);
foreach (['installer cannot issue', 'tampered total ignored', 'form token'] as $needle) {
    if (!str_contains($text, $needle)) {
        $fail($needle . ' :: ' . $text);
    } else {
        $ok($needle);
    }
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} finance checks failed.\n");
    exit(1);
}
fwrite(STDOUT, "Finance checks passed.\n");

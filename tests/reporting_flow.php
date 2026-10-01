<?php

declare(strict_types=1);

/**
 * Reporting, alerts, automation, backup, and permission checks.
 *
 *   php tests/reporting_flow.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Repositories\NotificationRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\SystemRepository;
use App\Repositories\UserRepository;
use App\Services\AlertService;
use App\Services\AuthorizationService;
use App\Services\AutomationService;
use App\Services\BackupService;
use App\Services\CustomerService;
use App\Services\QuoteService;
use App\Services\ReportService;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
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
$stamp = date('YmdHis') . random_int(10, 99);
$pdo = Database::connection();

$salesRole = $pdo->query("SELECT id FROM roles WHERE code = 'SALES'")->fetch();
$salesEmail = 'sales-report-' . $stamp . '@signforge.local';
$salesId = (new UserRepository())->insert([
    'name' => 'Sales Report ' . $stamp,
    'email' => $salesEmail,
    'password_hash' => password_hash('Report#Sales2026', PASSWORD_DEFAULT),
    'role_id' => (int) $salesRole['id'],
    'active' => 1,
    'must_change_password' => 0,
]);
$sales = (new UserRepository())->find($salesId);
foreach (['reports.executive', 'reports.profitability', 'reports.finance', 'system.backup', 'audit.view', 'system.logs'] as $code) {
    if (AuthorizationService::allows($sales, $code)) {
        $fail('sales can use ' . $code);
    } else {
        $ok('sales denied ' . $code);
    }
}
if (!AuthorizationService::allows($sales, 'reports.sales')) {
    $fail('sales cannot open sales reports');
} else {
    $ok('sales can open sales reports');
}

$customer = (new CustomerService())->create([
    'customer_type' => 'BUSINESS',
    'company_name' => 'Report Co ' . $stamp,
    'billing_address' => '1 Report Road',
    'active' => '1',
], $userId);
$quotes = new QuoteService();
$quoteRows = new QuoteRepository();
$created = $quotes->create(['customer_id' => $customer['id'], 'quote_date' => date('Y-m-d')], $userId);
if ($created['errors'] !== []) {
    $fail('quote create ' . json_encode($created['errors']));
    exit(1);
}
$quoteId = (int) $created['id'];
$row = $quoteRows->find($quoteId);
$line = $quotes->addCustomLine($quoteId, [
    'customer_description' => 'Board',
    'quantity' => '1',
    'unit_cost' => '10',
    'final_sell_price' => '1000',
    'override_reason' => 'Test price',
], (int) $row['version_number'], $userId);
if ($line['errors'] !== []) {
    $fail('quote line ' . json_encode($line['errors']));
    exit(1);
}
$row = $quoteRows->find($quoteId);
$saved = $quotes->save($quoteId, ['quote_date' => date('Y-m-d')], (int) $row['version_number'], $userId);
if ($saved !== []) {
    $fail('quote save ' . json_encode($saved));
    exit(1);
}
$row = $quoteRows->find($quoteId);
$status = $quotes->changeStatus($quoteId, 'SENT', (int) $row['version_number'], $userId, 'Sent for the report test');
if ($status !== []) {
    $fail('quote sent ' . json_encode($status));
} else {
    $ok('quote marked sent');
}
$reminders = new NotificationRepository();
$rule = $pdo->query("SELECT id FROM automation_rules WHERE trigger_type = 'QUOTE_SENT' AND active = 1 ORDER BY id LIMIT 1")->fetch();
$dedupe = 'rule:' . $rule['id'] . ':quote:' . $quoteId;
if ($reminders->countReminders($dedupe) !== 1) {
    $fail('expected one follow-up reminder, got ' . $reminders->countReminders($dedupe));
} else {
    $ok('one follow-up reminder');
}
$linked = $pdo->prepare('SELECT user_id, entity_id FROM reminders WHERE dedupe_key = ?');
$linked->execute([$dedupe]);
$reminder = $linked->fetch();
if ((int) $reminder['entity_id'] !== $quoteId || (int) $reminder['user_id'] !== $userId) {
    $fail('reminder is not linked to the quote and salesperson');
} else {
    $ok('reminder linked to quote and salesperson');
}
(new AutomationService())->fire('QUOTE_SENT', 'quote', $quoteId, $userId);
if ($reminders->countReminders($dedupe) !== 1) {
    $fail('automation duplicated the reminder');
} else {
    $ok('second run did not duplicate the reminder');
}

$invoiceNumber = 'SFI-TEST-' . $stamp;
$pdo->prepare(
    "INSERT INTO invoices (invoice_number, customer_id, invoice_date, due_date, status, subtotal, subtotal_after_discount, total, balance_due)
     VALUES (?, ?, '2026-08-01', '2026-08-10', 'ISSUED', 1000, 1000, 1000, 1000)"
)->execute([$invoiceNumber, $customer['id']]);
$invoiceId = (int) $pdo->lastInsertId();
$alerts = new AlertService();
$alerts->evaluate();
$alerts->evaluate();
$key = 'INVOICE_OVERDUE:invoice:' . $invoiceId;
$count = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE dedupe_key = " . $pdo->quote($key))->fetchColumn();
if ($count !== 1) {
    $fail('overdue alert count ' . $count);
} else {
    $ok('overdue invoice alert created once');
}

$backup = (new BackupService())->create($userId);
$path = base_path('storage/backups');
$files = glob($path . '/signforge-*.sql') ?: [];
$latest = $files === [] ? '' : (string) end($files);
if (!$backup['ok'] || $latest === '' || !is_file($latest) || filesize($latest) < 1) {
    $fail('backup did not produce a file: ' . $backup['message']);
} else {
    $ok('backup file exists');
}
if (str_contains($latest, '/public/')) {
    $fail('backup is inside the public folder');
} else {
    $ok('backup is outside the public folder');
}
$record = (new SystemRepository())->backup((int) $backup['id']);
if ($record === null || (string) $record['status'] !== 'SUCCESS' || (int) $record['file_size'] < 1) {
    $fail('backup history is not successful');
} else {
    $ok('backup history records success');
}

$report = (new ReportService())->build('executive', (new ReportService())->range(['preset' => 'this_month']), [
    'show_cost' => true,
    'group' => 'job',
]);
if (($report['title'] ?? '') !== 'Executive' || ($report['kpis'] ?? []) === []) {
    $fail('executive report did not build');
} else {
    $ok('executive report builds from operational data');
}

$base = 'http://127.0.0.1:8741';
$probe = @fopen($base . '/login', 'r');
if ($probe === false) {
    $fail('dev server is not running for the permission URL check');
} else {
    fclose($probe);
    $jar = tempnam(sys_get_temp_dir(), 'sf');
    $request = static function (string $method, string $path, array $fields = []) use ($base, $jar): array {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_COOKIEJAR => $jar,
            CURLOPT_COOKIEFILE => $jar,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($fields !== []) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, (string) $raw];
    };
    [$status, $raw] = $request('GET', '/login');
    if (!preg_match('/name="_token" value="([^"]+)"/', $raw, $match) && !preg_match('/name="csrf-token" content="([^"]+)"/', $raw, $match)) {
        $fail('login page has no token');
    } else {
        [$status] = $request('POST', '/login', [
            '_token' => html_entity_decode($match[1], ENT_QUOTES),
            'email' => $salesEmail,
            'password' => 'Report#Sales2026',
        ]);
        if ($status !== 302) {
            $fail('sales login status ' . $status);
        } else {
            $ok('sales signed in');
        }
        foreach ([
            '/reports/executive' => 403,
            '/reports/profitability' => 403,
            '/reports/finance' => 403,
            '/admin/backups' => 403,
            '/admin/audit' => 403,
            '/admin/logs' => 403,
            '/reports/sales' => 200,
        ] as $path => $expect) {
            [$status] = $request('GET', $path);
            if ($status !== $expect) {
                $fail($path . ' status ' . $status . ' expected ' . $expect);
            } else {
                $ok($path . ' ' . $expect);
            }
        }
    }
    @unlink((string) $jar);
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} reporting flow checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "reporting flow checks passed.\n");

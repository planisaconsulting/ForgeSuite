<?php

declare(strict_types=1);

/**
 * v1.1 Phase 11 release checks that do not need a second database.
 *
 *   php tests/v11_phase11.php
 *   php tests/v11_phase11.php number quote
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Services\NumberingService;
use App\Services\SystemHealthService;

if (($argv[1] ?? '') === 'number') {
    $method = (string) ($argv[2] ?? 'quote');
    $service = new NumberingService();
    fwrite(STDOUT, $service->{$method}() . "\n");
    exit(0);
}

$failures = 0;
$ok = static function (string $label, bool $pass, string $detail = '') use (&$failures): void {
    if ($pass) {
        fwrite(STDOUT, "ok   {$label}\n");

        return;
    }
    fwrite(STDERR, "FAIL {$label}" . ($detail !== '' ? " {$detail}" : '') . "\n");
    $failures++;
};

$dir = sys_get_temp_dir() . '/sf-log-' . bin2hex(random_bytes(4));
mkdir($dir);
$log = $dir . '/app.log';
file_put_contents($log, str_repeat('x', 40));
$health = new SystemHealthService();
$ok('small log stays', $health->rotateLogIfLarge($log, 100, 2) === false && is_file($log));
file_put_contents($log, str_repeat('y', 120));
$ok('large log rotates', $health->rotateLogIfLarge($log, 100, 2) === true && !is_file($log));
$archives = glob($dir . '/app-*.log') ?: [];
$ok('one archive kept', count($archives) === 1);
file_put_contents($log, str_repeat('a', 120));
$health->rotateLogIfLarge($log, 100, 2);
file_put_contents($log, str_repeat('b', 120));
$health->rotateLogIfLarge($log, 100, 2);
file_put_contents($log, str_repeat('c', 120));
$health->rotateLogIfLarge($log, 100, 2);
$kept = glob($dir . '/app-*.log') ?: [];
$ok('archive cap is two', count($kept) === 2);
foreach (array_merge($kept, [$log]) as $path) {
    if (is_file($path)) {
        unlink($path);
    }
}
rmdir($dir);

$pdo = Database::connection();
$floats = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ("float","double")'
)->fetchColumn();
$ok('no float money columns', $floats === 0, (string) $floats);
$stockColumn = (int) $pdo->query(
    'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "products" AND COLUMN_NAME = "stock_quantity"'
)->fetchColumn();
$ok('stock is not a quantity column', $stockColumn === 0);

$required = [
    'quotes' => 'quote_number',
    'jobs' => 'job_number',
    'invoices' => 'invoice_number',
    'purchase_orders' => 'po_number',
    'projects' => 'project_number',
    'customer_assets' => 'asset_number',
    'expenses' => 'expense_number',
    'shipments' => 'shipment_number',
];
foreach ($required as $table => $column) {
    $count = (int) $pdo->query(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '
        . $pdo->quote($table) . ' AND COLUMN_NAME = ' . $pdo->quote($column) . ' AND NON_UNIQUE = 0'
    )->fetchColumn();
    $ok("unique {$table}.{$column}", $count > 0);
}

$invoiceMismatch = (int) $pdo->query(
    'SELECT COUNT(*) FROM invoices i
     WHERE i.status NOT IN ("DRAFT","CANCELLED")
     AND i.balance_due <> ROUND(i.total - (
        SELECT COALESCE(SUM(a.amount),0) FROM payment_allocations a
        INNER JOIN payments p ON p.id = a.payment_id
        WHERE a.invoice_id = i.id AND a.reversed_at IS NULL AND p.status = "RECORDED"
     ) - (
        SELECT COALESCE(SUM(total),0) FROM credit_notes c
        WHERE c.invoice_id = i.id AND c.status IN ("ISSUED","APPLIED")
     ), 2)'
)->fetchColumn();
$ok('issued invoice balances match allocations', $invoiceMismatch === 0, (string) $invoiceMismatch);

$expenseDup = (int) $pdo->query(
    'SELECT COUNT(*) FROM (SELECT allocation_id FROM expense_cost_postings GROUP BY allocation_id HAVING COUNT(*) > 1) d'
)->fetchColumn();
$ok('expense cost posted once', $expenseDup === 0);
$logisticsDup = (int) $pdo->query(
    'SELECT COUNT(*) FROM (SELECT source_type, source_id FROM logistics_costs GROUP BY source_type, source_id HAVING COUNT(*) > 1) d'
)->fetchColumn();
$ok('logistics cost source is unique', $logisticsDup === 0);

$procs = [];
$pipes = [];
for ($i = 0; $i < 20; $i++) {
    $pair = [];
    $procs[] = proc_open(
        [PHP_BINARY, __FILE__, 'number', 'quote'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pair
    );
    $pipes[] = $pair;
}
$numbers = [];
$errors = 0;
foreach ($procs as $index => $proc) {
    if (!is_resource($proc)) {
        $errors++;
        continue;
    }
    $numbers[] = trim((string) stream_get_contents($pipes[$index][1]));
    $err = trim((string) stream_get_contents($pipes[$index][2]));
    if ($err !== '') {
        $errors++;
    }
    fclose($pipes[$index][1]);
    fclose($pipes[$index][2]);
    proc_close($proc);
}
$unique = array_unique(array_filter($numbers));
$ok('20 concurrent quote numbers', count($unique) === 20 && $errors === 0, 'unique=' . count($unique) . ' errors=' . $errors);

if ($failures > 0) {
    fwrite(STDERR, "PHASE11_FAIL {$failures}\n");
    exit(1);
}
fwrite(STDOUT, "PHASE11_OK\n");

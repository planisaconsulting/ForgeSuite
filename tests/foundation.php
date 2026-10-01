<?php

declare(strict_types=1);

/**
 * Checks the Step 1 database and the display helpers.
 *
 * Run this after database/install.php, before anyone changes the seeded
 * administrator password. It does not start the web server.
 *
 *   php tests/foundation.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;
use App\Models\Setting;
use App\Models\User;

$failed = 0;

$expect = static function (bool $condition, string $message) use (&$failed): void {
    if ($condition) {
        fwrite(STDOUT, "ok  {$message}" . PHP_EOL);

        return;
    }

    fwrite(STDERR, "FAIL {$message}" . PHP_EOL);
    $failed++;
};

$pdo = Database::connection();

$expectedTables = [
    'users',
    'categories',
    'products',
    'product_price_history',
    'pricing_levels',
    'customers',
    'quote_sequences',
    'quotes',
    'quote_items',
    'settings',
];

$stmt = $pdo->prepare('SHOW TABLES');
$stmt->execute();
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$missing = array_values(array_diff($expectedTables, $tables));
$expect($missing === [], $missing === [] ? 'all foundation tables exist' : 'missing tables: ' . implode(', ', $missing));

$admin = User::findByEmail('admin@signforge.local');
$expect(is_array($admin), 'admin account exists');
$expect(
    is_array($admin) && password_verify('Forge#Admin2026', (string) $admin['password_hash']),
    'seeded admin password verifies'
);
$expect(is_array($admin) && (int) $admin['must_change_password'] === 1, 'admin must change the seeded password');
$expect(is_array($admin) && (string) $admin['role'] === 'admin', 'admin role is admin');

$counts = [
    'categories' => 16,
    'pricing_levels' => 4,
    'products' => 9,
    'settings' => 12,
    'customers' => 0,
    'quotes' => 0,
];

foreach ($counts as $table => $expectedCount) {
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $table);
    $countStmt->execute();
    $actual = (int) $countStmt->fetchColumn();
    $expect($actual === $expectedCount, "{$table} has {$expectedCount} rows (found {$actual})");
}

$expect(Setting::get('quote_prefix') === 'SFQ', 'quote prefix comes from settings');
$expect(Setting::get('default_vat_percent') === '15', 'VAT rate comes from settings');
$expect(Setting::get('currency_code') === 'ZAR', 'currency code comes from settings');
$expect(Setting::get('currency_symbol') === 'R', 'currency symbol comes from settings');

$levelStmt = $pdo->prepare(
    'SELECT code, markup_percent FROM pricing_levels ORDER BY sort_order'
);
$levelStmt->execute();
$levels = $levelStmt->fetchAll();
$expect(count($levels) === 4, 'four pricing levels are stored as data');
$expect($levels[0]['code'] === 'Q1' && (float) $levels[0]['markup_percent'] === 100.0, 'Q1 markup is a database value');

$vinylStmt = $pdo->prepare(
    'SELECT pricing_method, roll_width_mm, cost_price, waste_threshold_percent, standard_waste_percent, default_waste_policy
     FROM products WHERE sku = :sku'
);
$vinylStmt->execute(['sku' => 'SF-PV-1300']);
$vinyl = $vinylStmt->fetch();
$expect(is_array($vinyl) && $vinyl['pricing_method'] === 'AREA', 'printable vinyl is priced by area');
$expect(is_array($vinyl) && (float) $vinyl['roll_width_mm'] === 1300.0, 'printable vinyl roll is 1300 mm');
$expect(is_array($vinyl) && (float) $vinyl['cost_price'] === 50.0, 'printable vinyl cost is R50 per m2');
$expect(is_array($vinyl) && (float) $vinyl['waste_threshold_percent'] === 50.0, 'waste threshold is stored on the product');
$expect(is_array($vinyl) && (float) $vinyl['standard_waste_percent'] === 10.0, 'manufacturing waste is stored separately');
$expect(is_array($vinyl) && $vinyl['default_waste_policy'] === 'ACTUAL', 'default policy does not auto-charge roll waste');

$expect(money('1234.5') === 'R 1,234.50', 'money display uses R 1,234.50');
$expect(money('0') === 'R 0.00', 'zero money displays as R 0.00');
$expect(money('-10') === '-R 10.00', 'negative money keeps the sign');

if ($failed > 0) {
    fwrite(STDERR, $failed . " check(s) failed." . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Foundation checks passed." . PHP_EOL);

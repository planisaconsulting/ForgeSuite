<?php

declare(strict_types=1);

/**
 * Report formulas without the database.
 *
 *   php tests/reporting.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Decimal;
use App\Services\ReportMath;
use App\Services\ReportService;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$eq = static function (string $label, ?string $expected, ?string $actual) use ($fail, $ok): void {
    if ($expected !== $actual && !($expected !== null && $actual !== null && Decimal::cmp($expected, $actual) === 0)) {
        $fail($label . ' expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));

        return;
    }
    $ok($label);
};

$eq('count conversion', '60.00', ReportMath::countConversion('6', '4'));
$eq('drafts are not in the formula', '60.00', ReportMath::countConversion('6', '4'));
$eq('value conversion', '75.00', ReportMath::valueConversion('7500', '2500'));
$eq('no decided quotes', null, ReportMath::countConversion('0', '0'));
$eq('waste rate', '10.00', ReportMath::wasteRate('10', '90'));
$eq('waste with no quantity', null, ReportMath::wasteRate('0', '0'));

$job = ReportService::jobProfit('50000', '0', '12000', '8000', '5000', '20000');
$eq('job actual', '25000.00', $job['actual']);
$eq('job profit', '25000.00', $job['profit']);
$eq('job margin', '50.00', $job['margin']);

$customer = ReportService::customerProfit([
    ['commercial' => '60000.00', 'actual' => '40000.00'],
    ['commercial' => '40000.00', 'actual' => '25000.00'],
]);
$eq('customer commercial', '100000.00', $customer['commercial']);
$eq('customer cost', '65000.00', $customer['actual']);
$eq('customer profit', '35000.00', $customer['profit']);
$eq('customer margin', '35.00', $customer['margin']);

$eq('debtor total', '24000.00', ReportService::debtorTotal([
    '10000.00', '5000.00', '4000.00', '3000.00', '2000.00',
]));
$eq('zero earlier period', null, ReportMath::change('10', '0'));
$eq('csv formula', "'=SUM(A1:A10)", ReportMath::csvCell('=SUM(A1:A10)'));
$eq('csv plus', "'+1", ReportMath::csvCell('+1'));
$eq('csv at', "'@cmd", ReportMath::csvCell('@cmd'));
$eq('csv plain', 'Vinyl', ReportMath::csvCell('Vinyl'));

if ($failures > 0) {
    fwrite(STDERR, "{$failures} reporting checks failed.\n");
    exit(1);
}

fwrite(STDOUT, "reporting checks passed.\n");

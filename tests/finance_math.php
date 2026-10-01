<?php

declare(strict_types=1);

/**
 * Invoice arithmetic without the database.
 *
 *   php tests/finance_math.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Decimal;
use App\Services\FinanceMath;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$eq = static function (string $label, string $expected, string $actual) use ($fail, $ok): void {
    if (Decimal::cmp($expected, $actual) !== 0) {
        $fail("{$label} expected {$expected} got {$actual}");

        return;
    }
    $ok($label);
};

$full = FinanceMath::document([
    ['line_subtotal' => '10000.00'],
], 'NONE', '0', 'EXCLUSIVE', '15');
$eq('full subtotal', '10000.00', (string) $full['subtotal']);
$eq('full vat', '1500.00', (string) $full['vat_amount']);
$eq('full total', '11500.00', (string) $full['total']);
$lineVat = '0.00';
$lineTotal = '0.00';
foreach ($full['lines'] as $line) {
    $lineVat = Decimal::add($lineVat, (string) $line['vat_amount']);
    $lineTotal = Decimal::add($lineTotal, (string) $line['line_total']);
}
$eq('line vat matches header', (string) $full['vat_amount'], $lineVat);
$eq('line totals match grand total', (string) $full['total'], $lineTotal);

$deposit = FinanceMath::netForCustomerTotal('11500.00', 'INCLUSIVE', '15');
$depositDoc = FinanceMath::document([['line_subtotal' => $deposit]], 'NONE', '0', 'INCLUSIVE', '15');
$eq('inclusive deposit total', '11500.00', (string) $depositDoc['total']);

$eq('remaining after deposit and progress', '30000.00', FinanceMath::remaining('100000.00', '70000.00'));
$eq('balance after part payment', '6000.00', FinanceMath::balance('10000.00', '4000.00', '0'));
$eq('balance after credit', '8000.00', FinanceMath::balance('10000.00', '0.00', '2000.00'));
if (FinanceMath::storedStatus('10000', '4000', '0') !== 'PARTIALLY_PAID') {
    $fail('partial status');
} else {
    $ok('partial status');
}
if (FinanceMath::storedStatus('10000', '10000', '0') !== 'PAID') {
    $fail('paid status');
} else {
    $ok('paid status');
}
$today = '2026-10-01';
$due = (new DateTimeImmutable($today))->modify('-40 days')->format('Y-m-d');
if (FinanceMath::ageingBucket($due, $today) !== 'DAYS_31_60') {
    $fail('ageing bucket ' . FinanceMath::ageingBucket($due, $today));
} else {
    $ok('40 days overdue is 31-60');
}
if (FinanceMath::ageingBucket($today, $today) !== 'CURRENT') {
    $fail('current bucket');
} else {
    $ok('due today is current');
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} finance math checks failed.\n");
    exit(1);
}
fwrite(STDOUT, "Finance math checks passed.\n");

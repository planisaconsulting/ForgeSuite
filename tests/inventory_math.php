<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Decimal;
use App\Services\StockValuation;

$failures = 0;
$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};
$ok = static function (string $message): void {
    fwrite(STDOUT, "ok   {$message}\n");
};
$eq = static function (string $label, string $expected, string $actual) use ($fail, $ok): void {
    if (Decimal::cmp(Decimal::round($expected, 4), Decimal::round($actual, 4)) !== 0) {
        $fail("{$label} expected {$expected} got {$actual}");

        return;
    }
    $ok($label);
};

$eq('available after reserve', '70.0000', StockValuation::available('80', '10'));
$eq('available after consuming the reservation', '70.0000', StockValuation::available('70', '0'));
$eq('weighted average', '110.0000', StockValuation::weightedAverage('10', '100', '10', '120'));
$eq('weighted average from empty stock', '120.0000', StockValuation::weightedAverage('0', '100', '10', '120'));
$rate = StockValuation::wasteRate('120', '14');
$eq('waste rate', '10.45', $rate ?? '0');
if (StockValuation::wasteRate('0', '0') !== null) {
    $fail('empty waste rate should be blank');
} else {
    $ok('empty waste rate is blank');
}
$eq('reduced offcut', '50.0000', StockValuation::offcutUnitCost('100', 'REDUCED_COST', '50'));
$eq('zero offcut', '0.0000', StockValuation::offcutUnitCost('100', 'ZERO_COST', '50'));
$eq('full offcut', '100.0000', StockValuation::offcutUnitCost('100', 'FULL_COST', '50'));

if ($failures > 0) {
    fwrite(STDERR, "{$failures} inventory maths checks failed.\n");
    exit(1);
}
fwrite(STDOUT, "All inventory maths checks passed.\n");

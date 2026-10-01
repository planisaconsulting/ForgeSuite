<?php

declare(strict_types=1);

/**
 * Quote line snapshots and quote totals. No database.
 *
 *   php tests/quotes.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Decimal;
use App\Services\QuoteLineFactory;
use App\Services\QuoteTotals;

$failures = 0;

$assert = static function (string $label, string $expected, string $actual, int $scale = 2) use (&$failures): void {
    $left = Decimal::round($expected, $scale);
    $right = Decimal::round($actual, $scale);
    if (Decimal::cmp($left, $right) !== 0) {
        fwrite(STDERR, "FAIL {$label}\n  expected {$left}\n  actual   {$right}\n");
        $failures++;

        return;
    }
    fwrite(STDOUT, "ok   {$label}\n");
};

$assertSame = static function (string $label, string $expected, mixed $actual) use (&$failures): void {
    $actualText = $actual === null ? 'null' : (string) $actual;
    if ($expected !== $actualText) {
        fwrite(STDERR, "FAIL {$label}\n  expected {$expected}\n  actual   {$actualText}\n");
        $failures++;

        return;
    }
    fwrite(STDOUT, "ok   {$label}\n");
};

$level = ['id' => 1, 'code' => 'Q1', 'name' => 'Q1', 'markup_percent' => '65', 'active' => 1];

$run = static function () use ($assert, $assertSame, $level): void {
    $factory = new QuoteLineFactory();
    $totals = new QuoteTotals();

    $area = $factory->fromProduct([
        'id' => 9,
        'name' => 'Printable vinyl',
        'sku' => 'VINYL',
        'product_type' => 'MATERIAL',
        'pricing_method' => 'AREA',
        'cost_price' => '50.0000',
        'cost_unit' => 'm2',
        'standard_waste_percent' => '0',
        'roll_width_mm' => '1370',
        'default_waste_policy' => 'ACTUAL',
        'active' => 1,
    ], [
        'width_mm' => '1000',
        'height_mm' => '1000',
        'quantity' => '1',
        'waste_mode' => 'ACTUAL',
        'cost' => '1',
        'markup' => '999',
    ], $level, 1);
    $line = $area['line'];
    $assert('area line cost', '50.00', (string) $line['total_cost']);
    $assert('area line sell', '82.50', (string) $line['calculated_price']);
    $assert('area ignores posted cost', '50.00', (string) $line['unit_cost_snapshot']);

    $linear = $factory->fromProduct([
        'name' => 'Cut vinyl',
        'pricing_method' => 'LINEAR_METRE',
        'cost_price' => '48.0000',
        'cost_unit' => 'm',
        'standard_waste_percent' => '0',
        'active' => 1,
    ], ['length_mm' => '2500', 'quantity' => '4'], $level, 1);
    $assert('linear metres', '10.0000', (string) $linear['line']['billable_quantity'], 4);
    $assert('linear cost', '480.00', (string) $linear['line']['total_cost']);
    $assert('linear sell', '792.00', (string) $linear['line']['calculated_price']);

    $unit = $factory->fromProduct([
        'name' => 'Eyelet',
        'pricing_method' => 'UNIT',
        'cost_price' => '20.0000',
        'cost_unit' => 'unit',
        'standard_waste_percent' => '0',
        'active' => 1,
    ], ['quantity' => '3'], $level, 1);
    $assert('unit cost', '60.00', (string) $unit['line']['total_cost']);
    $assert('unit sell', '99.00', (string) $unit['line']['calculated_price']);

    $sheetProduct = [
        'name' => 'ACM sheet',
        'pricing_method' => 'SHEET',
        'cost_price' => '100.0000',
        'cost_unit' => 'sheet',
        'sheet_width_mm' => '1000',
        'sheet_height_mm' => '1000',
        'standard_waste_percent' => '0',
        'allow_rotation' => 0,
        'default_waste_policy' => 'ACTUAL',
        'active' => 1,
    ];
    $piece = ['width_mm' => '500', 'height_mm' => '500', 'quantity' => '1'];
    $sheetActual = $factory->fromProduct($sheetProduct, $piece + ['waste_mode' => 'ACTUAL'], $level, 1);
    $sheetFull = $factory->fromProduct($sheetProduct, $piece + ['waste_mode' => 'FULL_SHEET'], $level, 1);
    $assert('sheet actual cost', '25.00', (string) $sheetActual['line']['total_cost']);
    $assert('sheet full cost', '100.00', (string) $sheetFull['line']['total_cost']);
    $assertSame('sheet full waste mode', 'FULL_SHEET', $sheetFull['line']['waste_mode']);

    $vinyl = [
        'name' => 'Roll vinyl',
        'pricing_method' => 'AREA',
        'cost_price' => '50.0000',
        'cost_unit' => 'm2',
        'roll_width_mm' => '1300',
        'standard_waste_percent' => '0',
        'waste_threshold_percent' => '50',
        'allow_rotation' => 1,
        'default_waste_policy' => 'ACTUAL',
        'active' => 1,
    ];
    $size = ['width_mm' => '800', 'height_mm' => '1000', 'quantity' => '1'];
    $actual = $factory->fromProduct($vinyl, $size + ['waste_mode' => 'ACTUAL'], $level, 1);
    $consumed = $factory->fromProduct($vinyl, $size + ['waste_mode' => 'CONSUMED_WIDTH'], $level, 1);
    $manual = $factory->fromProduct($vinyl, $size + ['waste_mode' => 'MANUAL', 'manual_width_mm' => '1000'], $level, 1);
    $assert('roll actual cost', '40.00', (string) $actual['line']['total_cost']);
    $assert('roll consumed cost', '65.00', (string) $consumed['line']['total_cost']);
    $assert('roll manual cost', '50.00', (string) $manual['line']['total_cost']);
    $assertSame('roll actual mode stored', 'ACTUAL', $actual['line']['waste_mode']);
    $assertSame('roll consumed mode stored', 'CONSUMED_WIDTH', $consumed['line']['waste_mode']);

    $wasted = $factory->fromProduct([
        'name' => 'Vinyl with waste',
        'pricing_method' => 'AREA',
        'cost_price' => '50.0000',
        'cost_unit' => 'm2',
        'standard_waste_percent' => '10',
        'default_waste_policy' => 'ACTUAL',
        'active' => 1,
    ], ['width_mm' => '1000', 'height_mm' => '2000', 'quantity' => '1', 'waste_mode' => 'ACTUAL'], $level, 1);
    $assert('manufacturing waste cost', '110.00', (string) $wasted['line']['total_cost']);
    $assert('manufacturing waste sell', '181.50', (string) $wasted['line']['calculated_price']);

    $override = $factory->fromProduct([
        'name' => 'Negotiated panel',
        'pricing_method' => 'UNIT',
        'cost_price' => '100.0000',
        'cost_unit' => 'unit',
        'standard_waste_percent' => '0',
        'active' => 1,
    ], ['quantity' => '1', 'final_sell_price' => '70.00', 'override_reason' => 'Site price'], $level, 4);
    $assert('override keeps calculated', '165.00', (string) $override['line']['calculated_price']);
    $assert('override final sell', '70.00', (string) $override['line']['final_sell_price']);
    $assertSame('override flag', '1', (string) $override['line']['price_overridden']);
    $assertSame('override reason', 'Site price', $override['line']['override_reason']);

    $saved = $actual['line'];
    $rebuilt = $factory->productFromLine($saved);
    $again = $factory->fromProduct($rebuilt, $factory->inputFromLine($saved), $level, 1);
    $assert('saved snapshot still costs 50', '50.00', (string) $again['line']['unit_cost_snapshot']);
    $live = $factory->fromProduct(array_merge($vinyl, ['cost_price' => '70.0000']), $size + ['waste_mode' => 'ACTUAL'], $level, 1);
    $assert('live catalogue now costs 70', '70.00', (string) $live['line']['unit_cost_snapshot']);

    $base = [
        'line_total' => '100.00',
        'total_cost' => '40.00',
        'is_optional' => 0,
    ];
    $optional = [
        'line_total' => '25.00',
        'total_cost' => '10.00',
        'is_optional' => 1,
        'include_optional' => 0,
    ];
    $header = [
        'discount_type' => 'PERCENTAGE',
        'discount_value' => '10',
        'vat_mode' => 'EXCLUSIVE',
        'vat_rate' => '15',
        'deposit_type' => 'PERCENTAGE',
        'deposit_value' => '50',
    ];
    $summary = $totals->summarise([$base, $optional], $header);
    $assert('optional excluded from subtotal', '100.00', $summary['subtotal']);
    $assert('optional shown aside', '25.00', $summary['optional_total']);
    $assert('percentage discount', '10.00', $summary['discount_amount']);
    $assert('vat exclusive', '13.50', $summary['vat_amount']);
    $assert('vat exclusive total', '103.50', $summary['total']);
    $assert('deposit percentage', '51.75', $summary['deposit_amount']);

    $fixed = $totals->summarise([$base], [
        'discount_type' => 'FIXED_AMOUNT',
        'discount_value' => '30',
        'vat_mode' => 'EXCLUSIVE',
        'vat_rate' => '15',
        'deposit_type' => 'FIXED_AMOUNT',
        'deposit_value' => '40',
    ]);
    $assert('fixed discount', '30.00', $fixed['discount_amount']);
    $assert('fixed deposit', '40.00', $fixed['deposit_amount']);
    $assert('after fixed discount vat', '10.50', $fixed['vat_amount']);
    $assert('after fixed discount total', '80.50', $fixed['total']);

    $inclusive = $totals->summarise([$base], [
        'discount_type' => 'NONE',
        'vat_mode' => 'VAT_INCLUSIVE',
        'vat_rate' => '15',
        'deposit_type' => 'NONE',
    ]);
    $assert('vat inclusive total', '100.00', $inclusive['total']);
    $assert('vat inclusive tax', '13.04', $inclusive['vat_amount']);
    $assert('vat inclusive revenue', '86.96', $inclusive['revenue']);

    $none = $totals->summarise([$base], [
        'discount_type' => 'NONE',
        'vat_mode' => 'NO_VAT',
        'vat_rate' => '15',
        'deposit_type' => 'FIXED_AMOUNT',
        'deposit_value' => '500',
    ]);
    $assert('no vat amount', '0.00', $none['vat_amount']);
    $assert('no vat total', '100.00', $none['total']);
    $assert('deposit capped at total', '100.00', $none['deposit_amount']);
    $assertSame('below cost is false', '0', $none['below_cost'] ? '1' : '0');

    $custom = $factory->custom([
        'customer_description' => 'Custom fabricated reception sign',
        'quantity' => '2',
        'unit_cost' => '80',
        'internal_description' => 'Workshop only',
    ], $level, 1);
    $assertSame('custom flag', '1', (string) $custom['line']['is_custom_item']);
    $assert('custom cost', '160.00', (string) $custom['line']['total_cost']);
    $assert('custom sell', '264.00', (string) $custom['line']['calculated_price']);
};

if (extension_loaded('bcmath')) {
    Decimal::useBcmath(true);
    fwrite(STDOUT, "\n[bcmath]\n");
    $run();
}
Decimal::useBcmath(false);
fwrite(STDOUT, "\n[string]\n");
$run();

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAll quote calculation checks passed.\n");

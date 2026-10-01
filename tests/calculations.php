<?php

declare(strict_types=1);

/**
 * Pricing-engine checks. No database and no browser.
 *
 *   php tests/calculations.php
 *
 * The same cases run twice when bcmath is loaded: once with bcmath and once
 * with the string fallback, so a host without bcmath still matches.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Decimal;
use App\Services\MaterialConsumptionService;
use App\Services\PricingService;
use App\Services\WasteCalculationService;

$failures = 0;

$fail = static function (string $message) use (&$failures): void {
    fwrite(STDERR, "FAIL {$message}\n");
    $failures++;
};

$assert = static function (string $label, string $expected, string $actual, int $scale = 4) use (&$failures): void {
    $left = Decimal::round($expected, $scale);
    $right = Decimal::round($actual, $scale);
    if (Decimal::cmp($left, $right) !== 0) {
        fwrite(STDERR, "FAIL {$label}\n  expected {$left}\n  actual   {$right}\n");
        $failures++;

        return;
    }
    fwrite(STDOUT, "ok   {$label}\n");
};

$assertSame = static function (string $label, string $expected, ?string $actual) use (&$failures): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL {$label}\n  expected {$expected}\n  actual   " . ($actual ?? 'null') . "\n");
        $failures++;

        return;
    }
    fwrite(STDOUT, "ok   {$label}\n");
};

$run = static function (string $engine) use ($assert, $assertSame, $fail): void {
    fwrite(STDOUT, "\n[{$engine}]\n");
    $materials = new MaterialConsumptionService();
    $waste = new WasteCalculationService();
    $pricing = new PricingService($materials, $waste);

    $assert('area 800 x 1000', '0.8000', $materials->areaSquareMetres('800', '1000', '1'));
    $assert('area times quantity', '1.6000', $materials->areaSquareMetres('800', '1000', '2'));
    $assert('linear 2500 x 4', '10.0000', $materials->linearMetres('2500', '4'));

    $assert('manufacturing waste 10% on 2', '2.2000', $waste->costedQuantity('2', '10'));
    $assert('manufacturing waste 0%', '2.0000', $waste->costedQuantity('2', '0'));

    $roll = $materials->roll('1300', '800', '1000', '1', '50');
    $assert('roll actual area', '0.8000', (string) $roll['actual_area_m2']);
    $assert('roll consumed area', '1.3000', (string) $roll['consumed_area_m2']);
    $assert('roll waste area', '0.5000', (string) $roll['potential_waste_m2']);
    $assert('roll utilisation', '61.5385', (string) $roll['width_utilisation_percent']);
    $assert('roll unused width', '500.00', (string) $roll['unused_width_mm'], 2);
    if (empty($roll['warns'])) {
        $fail('roll 800 mm should warn above a 50% threshold');
    } else {
        fwrite(STDOUT, "ok   roll 800 mm warns\n");
    }

    $narrow = $materials->roll('1300', '500', '1000', '1', '50');
    $assert('narrow utilisation', '38.4615', (string) $narrow['width_utilisation_percent']);
    if (!empty($narrow['warns'])) {
        $fail('500 mm on 1300 must not warn at a 50% threshold');
    } else {
        fwrite(STDOUT, "ok   500 mm does not warn\n");
    }

    $exact = $materials->roll('1300', '650', '1000', '1', '50');
    $assert('exact threshold utilisation', '50.0000', (string) $exact['width_utilisation_percent']);
    if (!empty($exact['warns'])) {
        $fail('utilisation equal to the threshold must not warn');
    } else {
        fwrite(STDOUT, "ok   threshold equality does not warn\n");
    }

    $vinyl = [
        'pricing_method' => 'AREA',
        'cost_price' => '50.0000',
        'cost_unit' => 'm2',
        'roll_width_mm' => '1300',
        'standard_waste_percent' => '0',
        'waste_threshold_percent' => '50',
        'allow_rotation' => 1,
        'default_waste_policy' => 'ACTUAL',
    ];
    $size = ['width_mm' => '800', 'height_mm' => '1000', 'quantity' => '1', 'cost' => '1', 'markup' => '999'];

    $actual = $pricing->price($vinyl, $size + ['waste_mode' => 'ACTUAL'], []);
    $assert('ACTUAL billable ignores browser cost', '0.8000', (string) $actual['billable_quantity']);
    $assertSame('ACTUAL raw cost uses database cost', '40.00', (string) $actual['raw_cost']);

    $consumed = $pricing->price($vinyl, $size + ['waste_mode' => 'CONSUMED_WIDTH'], []);
    $assert('CONSUMED_WIDTH billable', '1.3000', (string) $consumed['billable_quantity']);
    $assertSame('CONSUMED_WIDTH raw cost', '65.00', (string) $consumed['raw_cost']);

    $manualWidth = $pricing->price($vinyl, $size + ['waste_mode' => 'MANUAL', 'manual_width_mm' => '1000'], []);
    $assert('MANUAL width billable', '1.0000', (string) $manualWidth['billable_quantity']);
    if (empty($manualWidth['manual'])) {
        $fail('manual width must be flagged');
    } else {
        fwrite(STDOUT, "ok   manual width is flagged\n");
    }

    $manualArea = $pricing->price($vinyl, $size + ['waste_mode' => 'MANUAL', 'manual_area' => '0.9500'], []);
    $assert('MANUAL area replaces the line', '0.9500', (string) $manualArea['billable_quantity']);

    $withWaste = $vinyl;
    $withWaste['standard_waste_percent'] = '10';
    $wasted = $pricing->price($withWaste, [
        'width_mm' => '1000',
        'height_mm' => '2000',
        'quantity' => '1',
        'waste_mode' => 'ACTUAL',
    ], []);
    $assert('2 m2 actual', '2.0000', (string) $wasted['actual_quantity']);
    $assert('2 m2 plus 10% costed', '2.2000', (string) $wasted['costed_quantity']);
    $assertSame('costed cost 2.2 x 50', '110.00', (string) $wasted['total_cost']);

    $levels = [
        ['code' => 'Q1', 'name' => 'Q1', 'markup_percent' => '65', 'active' => 1],
        ['code' => 'Q2', 'name' => 'Q2', 'markup_percent' => '50', 'active' => 1],
    ];
    $marked = $pricing->price([
        'pricing_method' => 'UNIT',
        'cost_price' => '100.0000',
        'cost_unit' => 'unit',
        'standard_waste_percent' => '0',
    ], ['quantity' => '1'], $levels);
    $assertSame('markup selling price', '165.00', (string) $marked['levels'][0]['selling_price']);
    $assertSame('gross profit', '65.00', (string) $marked['levels'][0]['gross_profit']);
    $assertSame('gross margin is not the markup', '39.39', (string) $marked['levels'][0]['gross_margin_percent']);

    $ten = $pricing->price([
        'pricing_method' => 'UNIT',
        'cost_price' => '18.5000',
        'cost_unit' => 'unit',
        'standard_waste_percent' => '0',
    ], ['quantity' => '10', 'cost' => '0.01'], []);
    $assert('10 units quantity', '10.0000', (string) $ten['billable_quantity']);
    $assertSame('10 x 18.50', '185.00', (string) $ten['total_cost']);

    $linear = $pricing->price([
        'pricing_method' => 'LINEAR_METRE',
        'cost_price' => '48.0000',
        'cost_unit' => 'm',
        'standard_waste_percent' => '0',
    ], ['length_mm' => '2500', 'quantity' => '4'], []);
    $assert('priced linear metres', '10.0000', (string) $linear['billable_quantity']);
    $assertSame('10 m x 48', '480.00', (string) $linear['total_cost']);

    $sheetProduct = [
        'pricing_method' => 'SHEET',
        'cost_price' => '100.0000',
        'cost_unit' => 'sheet',
        'sheet_width_mm' => '1000',
        'sheet_height_mm' => '1000',
        'standard_waste_percent' => '0',
        'allow_rotation' => 0,
        'default_waste_policy' => 'ACTUAL',
    ];
    $piece = ['width_mm' => '500', 'height_mm' => '500', 'quantity' => '1'];
    $sheetActual = $pricing->price($sheetProduct, $piece + ['waste_mode' => 'ACTUAL'], []);
    $sheetFull = $pricing->price($sheetProduct, $piece + ['waste_mode' => 'FULL_SHEET'], []);
    $assert('sheet actual area', '0.2500', (string) $sheetActual['actual_quantity']);
    $assertSame('sheet actual cost is the fraction', '25.00', (string) $sheetActual['total_cost']);
    $assertSame('full sheet cost', '100.00', (string) $sheetFull['total_cost']);
    $assert('full sheet count', '1.0000', (string) $sheetFull['billable_quantity']);

    $turned = $materials->sheet('1000', '500', '400', '900', '1', true);
    if (empty($turned['fits_on_sheet']) || empty($turned['rotated'])) {
        $fail('rotation should let 400 x 900 fit on 1000 x 500');
    } else {
        fwrite(STDOUT, "ok   sheet rotation fit\n");
    }
    $blocked = $materials->sheet('1000', '500', '400', '900', '1', false);
    if (!empty($blocked['fits_on_sheet'])) {
        $fail('400 x 900 must not fit on 1000 x 500 without rotation');
    } else {
        fwrite(STDOUT, "ok   sheet without rotation does not fit\n");
    }

    $labour = $pricing->price([
        'pricing_method' => 'HOUR',
        'cost_price' => '350.0000',
        'cost_unit' => 'hour',
        'standard_waste_percent' => '0',
    ], ['hours' => '1.5'], []);
    $assert('decimal hours', '1.5000', (string) $labour['billable_quantity']);
    $assertSame('1.5 hours x 350', '525.00', (string) $labour['total_cost']);

    $spec = $pricing->price([
        'pricing_method' => 'UNIT',
        'cost_price' => '650.0000',
        'cost_unit' => 'unit',
        'standard_waste_percent' => '0',
    ], ['quantity' => '1'], [['code' => 'Q1', 'name' => 'Q1', 'markup_percent' => '65', 'active' => 1]]);
    $assertSame('spec selling price', '1072.50', (string) $spec['levels'][0]['selling_price']);
    $assertSame('spec gross profit', '422.50', (string) $spec['levels'][0]['gross_profit']);
    $assertSame('spec margin', '39.39', (string) $spec['levels'][0]['gross_margin_percent']);

    $nest = $materials->nest([['width_mm' => '600', 'height_mm' => '1000', 'quantity' => '2']], '1300');
    if (!empty($nest['implemented'])) {
        $fail('nesting must stay unimplemented in phase 1');
    } else {
        fwrite(STDOUT, "ok   nesting hook is present and not active\n");
    }
};

if (extension_loaded('bcmath')) {
    Decimal::useBcmath(true);
    $run('bcmath');
}
Decimal::useBcmath(false);
$run('string');

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failed.\n");
    exit(1);
}

fwrite(STDOUT, "\nAll calculation checks passed.\n");

<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Units;
use App\Helpers\Decimal;

/**
 * Prices one product from its database row and the sizes the operator typed.
 *
 * Quotes, jobs, recipes, and the calculator must all call this. Do not copy
 * the formulas into a controller or into JavaScript.
 *
 * Cost and selling price stay separate:
 *   total cost    = costed quantity x unit cost
 *   selling price = total cost x (1 + markup percent / 100)
 *   gross profit  = selling price - total cost
 *   gross margin  = gross profit / selling price x 100
 *
 * Markup is not margin. A 50% markup on R100 is R150, and the margin on
 * that R150 is 33.33%, not 50%.
 *
 * Values named cost, markup, or total that arrive from the browser are
 * ignored. The product row and the pricing levels are the source of truth.
 */
final class PricingService
{
    public function __construct(
        private readonly MaterialConsumptionService $materials = new MaterialConsumptionService(),
        private readonly WasteCalculationService $waste = new WasteCalculationService()
    ) {
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $levels
     * @return array<string, mixed>
     */
    public function price(array $product, array $input, array $levels): array
    {
        $method = (string) ($product['pricing_method'] ?? '');
        $unitCost = $this->decimal((string) ($product['cost_price'] ?? '0'));
        $standardWaste = $this->decimal((string) ($product['standard_waste_percent'] ?? '0'));
        $errors = [];
        $warnings = [];

        $measure = $this->measure($method, $product, $input, $errors, $warnings);
        $billable = $measure['billable'];
        $costed = $errors === []
            ? $this->waste->costedQuantity($billable, $standardWaste)
            : '0';
        $rawCost = Decimal::mul($billable, $unitCost, Decimal::CALC_SCALE);
        $totalCost = Decimal::mul($costed, $unitCost, Decimal::CALC_SCALE);

        $pricedLevels = [];
        foreach ($levels as $level) {
            if (isset($level['active']) && (int) $level['active'] !== 1) {
                continue;
            }
            $markup = $this->decimal((string) ($level['markup_percent'] ?? '0'));
            $sell = Decimal::mul(
                $totalCost,
                Decimal::add('1', Decimal::div($markup, '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE),
                Decimal::CALC_SCALE
            );
            $profit = Decimal::sub($sell, $totalCost, Decimal::CALC_SCALE);
            $margin = Decimal::cmp($sell, '0') === 0
                ? null
                : Decimal::mul(Decimal::div($profit, $sell, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE);
            $pricedLevels[] = [
                'code' => (string) ($level['code'] ?? ''),
                'name' => (string) ($level['name'] ?? ''),
                'markup_percent' => Decimal::round($markup, 2),
                'selling_price' => Decimal::money($sell),
                'gross_profit' => Decimal::money($profit),
                'gross_margin_percent' => $margin === null ? null : Decimal::round($margin, 2),
            ];
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'method' => $method,
            'unit' => Units::label((string) ($product['cost_unit'] ?? Units::forMethod($method))),
            'cost_unit' => (string) ($product['cost_unit'] ?? Units::forMethod($method)),
            'unit_cost' => Decimal::money($unitCost),
            'actual_quantity' => Decimal::qty($measure['actual']),
            'billable_quantity' => Decimal::qty($billable),
            'costed_quantity' => Decimal::qty($costed),
            'quantity_unit' => $measure['quantity_unit'],
            'standard_waste_percent' => Decimal::round($standardWaste, 2),
            'raw_cost' => Decimal::money($rawCost),
            'total_cost' => Decimal::money($totalCost),
            'waste_mode' => $measure['mode'],
            'manual' => $measure['manual'],
            'roll' => $this->presentRoll($measure['roll']),
            'sheet' => $this->presentSheet($measure['sheet']),
            'rotation_hint' => $measure['rotation_hint'],
            'levels' => $pricedLevels,
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @param list<string> $errors
     * @param list<string> $warnings
     * @return array<string, mixed>
     */
    private function measure(string $method, array $product, array $input, array &$errors, array &$warnings): array
    {
        $quantity = $this->optional($input, 'quantity', '1');
        $blank = [
            'actual' => '0',
            'billable' => '0',
            'quantity_unit' => Units::label(Units::forMethod($method)),
            'mode' => 'ACTUAL',
            'manual' => false,
            'roll' => null,
            'sheet' => null,
            'rotation_hint' => null,
        ];

        if ($method === 'UNIT' || $method === 'CUSTOM') {
            $this->requirePositive($quantity, 'Quantity', $errors);

            return array_merge($blank, ['actual' => $quantity, 'billable' => $quantity, 'quantity_unit' => 'unit']);
        }

        if ($method === 'LITRE') {
            $litres = $this->optional($input, 'litres', $quantity);
            $this->requirePositive($litres, 'Litres', $errors);

            return array_merge($blank, ['actual' => $litres, 'billable' => $litres, 'quantity_unit' => 'litre']);
        }

        if ($method === 'HOUR') {
            $hours = $this->optional($input, 'hours', $quantity);
            $this->requirePositive($hours, 'Hours', $errors);

            return array_merge($blank, ['actual' => $hours, 'billable' => $hours, 'quantity_unit' => 'hour']);
        }

        if ($method === 'LINEAR_METRE') {
            $length = $this->optional($input, 'length_mm', '');
            $this->requirePositive($length, 'Length', $errors);
            $this->requirePositive($quantity, 'Quantity', $errors);
            if ($errors !== []) {
                return array_merge($blank, ['quantity_unit' => 'm']);
            }
            $metres = $this->materials->linearMetres($length, $quantity);

            return array_merge($blank, ['actual' => $metres, 'billable' => $metres, 'quantity_unit' => 'm']);
        }

        if ($method === 'AREA') {
            return array_merge($this->areaMeasure($product, $input, $quantity, $errors, $warnings), ['quantity_unit' => 'm²']);
        }

        if ($method === 'SHEET') {
            return array_merge($this->sheetMeasure($product, $input, $quantity, $errors, $warnings), ['quantity_unit' => 'sheet']);
        }

        $errors[] = 'This product has no pricing method.';

        return $blank;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @param list<string> $errors
     * @param list<string> $warnings
     * @return array<string, mixed>
     */
    private function areaMeasure(array $product, array $input, string $quantity, array &$errors, array &$warnings): array
    {
        $width = $this->optional($input, 'width_mm', '');
        $height = $this->optional($input, 'height_mm', '');
        $this->requirePositive($width, 'Width', $errors);
        $this->requirePositive($height, 'Height', $errors);
        $this->requirePositive($quantity, 'Quantity', $errors);
        $base = [
            'actual' => '0',
            'billable' => '0',
            'mode' => 'ACTUAL',
            'manual' => false,
            'roll' => null,
            'sheet' => null,
            'rotation_hint' => null,
        ];
        if ($errors !== []) {
            return $base;
        }

        $actual = $this->materials->areaSquareMetres($width, $height, $quantity);
        $rollWidth = $product['roll_width_mm'] ?? null;
        if ($rollWidth === null || $rollWidth === '') {
            return array_merge($base, ['actual' => $actual, 'billable' => $actual]);
        }

        $threshold = $product['waste_threshold_percent'] ?? null;
        $roll = $this->materials->roll(
            (string) $rollWidth,
            $width,
            $height,
            $quantity,
            $threshold === null ? null : (string) $threshold
        );
        if (!empty($roll['wider_than_roll'])) {
            $warnings[] = 'The print is wider than the roll.';
            if ((int) ($product['allow_rotation'] ?? 0) === 1 && Decimal::cmp($height, (string) $rollWidth) <= 0) {
                $base['rotation_hint'] = 'This product allows rotation. Swap width and height if the shorter side should run across the roll.';
            }
        } elseif (!empty($roll['warns'])) {
            $warnings[] = 'Width utilisation is above this product\'s waste threshold. Choose whether to charge the offcut. The threshold does not charge it for you.';
        }

        $mode = strtoupper($this->text($input, 'waste_mode', (string) ($product['default_waste_policy'] ?? 'ACTUAL')));
        if (!in_array($mode, ['ACTUAL', 'CONSUMED_WIDTH', 'MANUAL'], true)) {
            $mode = 'ACTUAL';
        }
        $chosen = $this->waste->billableRollQuantity(
            $roll,
            $quantity,
            $mode,
            $this->blankToNull($input, 'manual_width_mm'),
            $this->blankToNull($input, 'manual_area')
        );
        if ($chosen['error'] !== null) {
            $errors[] = (string) $chosen['error'];
        }
        if (!empty($chosen['manual'])) {
            $warnings[] = 'Manual pricing is in use. The billable area was typed, not taken from the print size.';
        }

        return array_merge($base, [
            'actual' => $actual,
            'billable' => (string) $chosen['billable'],
            'mode' => (string) $chosen['mode'],
            'manual' => (bool) $chosen['manual'],
            'roll' => $roll,
        ]);
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @param list<string> $errors
     * @param list<string> $warnings
     * @return array<string, mixed>
     */
    private function sheetMeasure(array $product, array $input, string $quantity, array &$errors, array &$warnings): array
    {
        $width = $this->optional($input, 'width_mm', '');
        $height = $this->optional($input, 'height_mm', '');
        $this->requirePositive($width, 'Width', $errors);
        $this->requirePositive($height, 'Height', $errors);
        $this->requirePositive($quantity, 'Quantity', $errors);
        $sheetWidth = (string) ($product['sheet_width_mm'] ?? '');
        $sheetHeight = (string) ($product['sheet_height_mm'] ?? '');
        if ($sheetWidth === '' || $sheetHeight === '') {
            $errors[] = 'This sheet product has no sheet size.';
        }
        $base = [
            'actual' => '0',
            'billable' => '0',
            'mode' => 'ACTUAL',
            'manual' => false,
            'roll' => null,
            'sheet' => null,
            'rotation_hint' => null,
        ];
        if ($errors !== []) {
            return $base;
        }

        $picture = $this->materials->sheet(
            $sheetWidth,
            $sheetHeight,
            $width,
            $height,
            $quantity,
            (int) ($product['allow_rotation'] ?? 0) === 1
        );
        if (!$picture['fits_on_sheet']) {
            $warnings[] = 'The piece does not fit on one sheet in a simple grid. Full-sheet charging still counts one sheet per piece. Check the size.';
        } elseif (!empty($picture['rotated'])) {
            $warnings[] = 'Rotation was considered for the sheet count. The piece was turned to fit more on the sheet.';
        }

        $mode = strtoupper($this->text($input, 'waste_mode', (string) ($product['default_waste_policy'] ?? 'ACTUAL')));
        if (!in_array($mode, ['ACTUAL', 'FULL_SHEET', 'MANUAL'], true)) {
            $mode = 'ACTUAL';
        }
        $chosen = $this->waste->billableSheet(
            $picture,
            $mode,
            $this->blankToNull($input, 'manual_area'),
            $this->blankToNull($input, 'manual_sheets')
        );
        if ($chosen['error'] !== null) {
            $errors[] = (string) $chosen['error'];
        }
        if (!empty($chosen['manual'])) {
            $warnings[] = 'Manual pricing is in use. The billable sheet measure was typed, not taken from the piece size.';
        }

        // Billable quantity for a sheet product is a sheet count (fractional
        // when charging actual area). Actual quantity shown beside it is area.
        return [
            'actual' => (string) $picture['actual_area_m2'],
            'billable' => (string) $chosen['billable_sheets'],
            'mode' => (string) $chosen['mode'],
            'manual' => (bool) $chosen['manual'],
            'roll' => null,
            'sheet' => $picture,
            'rotation_hint' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $roll
     * @return array<string, string|bool>|null
     */
    private function presentRoll(?array $roll): ?array
    {
        if ($roll === null) {
            return null;
        }

        return [
            'roll_width_mm' => (string) $roll['roll_width_mm'],
            'print_width_mm' => (string) $roll['print_width_mm'],
            'print_length_mm' => (string) $roll['print_length_mm'],
            'width_utilisation_percent' => Decimal::round((string) $roll['width_utilisation_percent'], 4),
            'unused_width_mm' => Decimal::round((string) $roll['unused_width_mm'], 2),
            'actual_area_m2' => Decimal::qty((string) $roll['actual_area_m2']),
            'consumed_area_m2' => Decimal::qty((string) $roll['consumed_area_m2']),
            'potential_waste_m2' => Decimal::qty((string) $roll['potential_waste_m2']),
            'wider_than_roll' => (bool) $roll['wider_than_roll'],
            'warns' => (bool) $roll['warns'],
        ];
    }

    /**
     * @param array<string, mixed>|null $sheet
     * @return array<string, string|bool|int>|null
     */
    private function presentSheet(?array $sheet): ?array
    {
        if ($sheet === null) {
            return null;
        }

        return [
            'sheet_width_mm' => (string) $sheet['sheet_width_mm'],
            'sheet_height_mm' => (string) $sheet['sheet_height_mm'],
            'sheets' => Decimal::qty((string) $sheet['sheets']),
            'pieces_per_sheet' => (int) $sheet['pieces_per_sheet'],
            'fits_on_sheet' => (bool) $sheet['fits_on_sheet'],
            'rotated' => (bool) $sheet['rotated'],
            'actual_area_m2' => Decimal::qty((string) $sheet['actual_area_m2']),
            'sheet_area_m2' => Decimal::qty((string) $sheet['sheet_area_m2']),
            'consumed_area_m2' => Decimal::qty((string) $sheet['consumed_area_m2']),
            'offcut_area_m2' => Decimal::qty((string) $sheet['offcut_area_m2']),
            'utilisation_percent' => Decimal::round((string) $sheet['utilisation_percent'], 4),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function text(array $input, string $key, string $default): string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return $default;
        }
        $value = strtoupper(trim((string) $input[$key]));

        return $value === '' ? $default : $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function optional(array $input, string $key, string $default): string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return $default;
        }
        $value = str_replace(',', '.', trim((string) $input[$key]));
        if (!Decimal::isNumeric($value)) {
            return $default;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function blankToNull(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null || trim((string) $input[$key]) === '') {
            return null;
        }
        $value = str_replace(',', '.', trim((string) $input[$key]));

        return Decimal::isNumeric($value) ? $value : null;
    }

    /**
     * @param list<string> $errors
     */
    private function requirePositive(string $value, string $label, array &$errors): void
    {
        if ($value === '' || !Decimal::isNumeric($value) || Decimal::cmp($value, '0') <= 0) {
            $errors[] = $label . ' must be greater than zero.';
        }
    }

    private function decimal(string $value): string
    {
        $value = str_replace(',', '.', trim($value));
        if (!Decimal::isNumeric($value)) {
            return '0';
        }

        return $value;
    }
}

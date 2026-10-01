<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ProductRepository;

/**
 * Turns a recipe, its inputs, and current component costs into one result.
 *
 * Quantities come from FormulaService. Area and roll pictures come from
 * MaterialConsumptionService. Manufacturing waste comes from
 * WasteCalculationService. The selling price of the assembled cost goes
 * through PricingService so markup is not calculated a second way.
 * A saved quote keeps its own snapshot and is not recalculated here.
 */
final class RecipeCalculationService
{
    private const MAX_DEPTH = 2;

    public function __construct(
        private readonly FormulaService $formulas = new FormulaService(),
        private readonly MaterialConsumptionService $consumption = new MaterialConsumptionService(),
        private readonly WasteCalculationService $waste = new WasteCalculationService(),
        private readonly PricingService $pricing = new PricingService(),
        private readonly UnitConversionService $units = new UnitConversionService(),
        private readonly SheetYieldService $sheets = new SheetYieldService(),
        private readonly ProductRepository $products = new ProductRepository(),
    ) {
    }

    /**
     * @param array<string, mixed> $recipe
     * @param list<array<string, mixed>> $inputs
     * @param list<array<string, mixed>> $items
     * @param array<string, mixed> $posted
     * @param array<string, mixed> $level
     * @param list<int> $stack
     * @return array<string, mixed>
     */
    public function calculate(
        array $recipe,
        array $inputs,
        array $items,
        array $posted,
        array $level,
        array $stack = []
    ): array {
        $recipeId = (int) ($recipe['id'] ?? 0);
        if (in_array($recipeId, $stack, true)) {
            return $this->failed('This recipe refers to itself.');
        }
        if (count($stack) > self::MAX_DEPTH) {
            return $this->failed('Nested recipes stop after two levels.');
        }
        $values = $this->variables($inputs, $posted);
        if (isset($values['error'])) {
            return $this->failed((string) $values['error']);
        }
        /** @var array<string, string> $variables */
        $variables = $values['variables'];
        $components = [];
        $errors = [];
        foreach ($items as $item) {
            if (!$this->conditionMet($item['condition_json'] ?? null, $posted)) {
                continue;
            }
            $nestedId = (int) ($item['nested_recipe_id'] ?? 0);
            if ($nestedId > 0) {
                $nested = $this->nested($nestedId, $posted, $level, array_merge($stack, [$recipeId]));
                if (!$nested['ok']) {
                    $errors[] = (string) ($nested['error'] ?? 'A nested recipe failed.');
                    continue;
                }
                foreach ($nested['components'] as $child) {
                    $child['description'] = (string) $item['description'] . ': ' . $child['description'];
                    $components[] = $child;
                }
                continue;
            }
            $built = $this->component($item, $variables, $posted);
            if ($built['error'] !== null) {
                $errors[] = $built['error'];
                continue;
            }
            $components[] = $built['row'];
        }
        if ($errors !== []) {
            return $this->failed(implode(' ', $errors));
        }

        $total = '0';
        foreach ($components as $row) {
            $total = Decimal::add($total, (string) $row['total_cost'], Decimal::CALC_SCALE);
        }
        $commercial = $this->pricing->price([
            'pricing_method' => 'UNIT',
            'cost_price' => Decimal::money($total),
            'standard_waste_percent' => '0',
            'cost_unit' => 'unit',
            'name' => (string) ($recipe['name'] ?? 'Recipe'),
        ], ['quantity' => '1'], [$level]);
        $priced = $commercial['levels'][0] ?? null;
        $sell = is_array($priced) ? (string) $priced['selling_price'] : Decimal::money($total);
        $profit = is_array($priced) ? (string) $priced['gross_profit'] : '0.00';
        $margin = is_array($priced) ? $priced['gross_margin_percent'] : null;

        return [
            'ok' => true,
            'error' => null,
            'inputs' => $values['echo'],
            'variables' => $variables,
            'components' => $components,
            'labour' => array_values(array_filter(
                $components,
                static fn (array $row): bool => $row['component_type'] === 'LABOUR'
            )),
            'total_cost' => Decimal::money($total),
            'selling_price' => $sell,
            'gross_profit' => $profit,
            'gross_margin' => $margin,
            'markup_percent' => (string) ($level['markup_percent'] ?? '0'),
            'face_area_each' => Decimal::round($variables['AREA_M2'], 4),
            'face_area_total' => Decimal::round(Decimal::mul($variables['AREA_M2'], $variables['Q'], Decimal::CALC_SCALE), 4),
            'production_route' => [],
        ];
    }

    /**
     * @param list<array<string, mixed>> $inputs
     * @param array<string, mixed> $posted
     * @return array<string, mixed>
     */
    public function variables(array $inputs, array $posted): array
    {
        $width = $this->number($posted['W'] ?? $posted['width_mm'] ?? '0');
        $height = $this->number($posted['H'] ?? $posted['height_mm'] ?? '0');
        $length = $this->number($posted['L'] ?? $posted['length_mm'] ?? '0');
        $depth = $this->number($posted['D'] ?? $posted['depth_mm'] ?? '0');
        $quantity = $this->number($posted['Q'] ?? $posted['quantity'] ?? '1');
        $echo = [
            'W' => $width,
            'H' => $height,
            'L' => $length,
            'D' => $depth,
            'Q' => $quantity,
        ];
        foreach ($inputs as $input) {
            $code = strtoupper(trim((string) $input['code']));
            if (in_array($code, ['W', 'H', 'L', 'D', 'Q', 'AREA_M2', 'PERIMETER_M'], true)) {
                continue;
            }
            $raw = $posted[$code] ?? $posted[strtolower($code)] ?? ($input['default_value'] ?? '');
            if ((string) ($input['input_type'] ?? '') === 'BOOLEAN') {
                $echo[$code] = $this->truthy($raw) ? '1' : '0';
                continue;
            }
            if ((string) ($input['input_type'] ?? '') === 'SELECT') {
                $echo[$code] = strtoupper(trim((string) $raw));
                continue;
            }
            if ((int) ($input['required'] ?? 0) === 1 && trim((string) $raw) === '') {
                return ['error' => (string) $input['label'] . ' is required.'];
            }
            if (trim((string) $raw) === '') {
                $echo[$code] = '0';
                continue;
            }
            $echo[$code] = $this->number($raw);
            if ($input['min_value'] !== null && $input['min_value'] !== '' && Decimal::cmp($echo[$code], (string) $input['min_value']) < 0) {
                return ['error' => (string) $input['label'] . ' is below the minimum.'];
            }
            if ($input['max_value'] !== null && $input['max_value'] !== '' && Decimal::cmp($echo[$code], (string) $input['max_value']) > 0) {
                return ['error' => (string) $input['label'] . ' is above the maximum.'];
            }
        }
        $area = $this->consumption->areaSquareMetres($width, $height, '1');
        $perimeter = $this->units->mmToM(Decimal::mul('2', Decimal::add($width, $height, Decimal::CALC_SCALE), Decimal::CALC_SCALE));
        $echo['AREA_M2'] = $area;
        $echo['PERIMETER_M'] = $perimeter;
        $numeric = $echo;
        foreach ($inputs as $input) {
            if ((string) ($input['input_type'] ?? '') === 'SELECT') {
                unset($numeric[strtoupper((string) $input['code'])]);
            }
        }

        return ['variables' => $numeric, 'echo' => $echo];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $variables
     * @param array<string, mixed> $posted
     * @return array{error: ?string, row: array<string, mixed>}
     */
    private function component(array $item, array $variables, array $posted): array
    {
        try {
            $raw = $this->formulas->evaluate((string) $item['quantity_formula'], $variables);
        } catch (FormulaRejected $e) {
            return ['error' => (string) $item['description'] . ': ' . $e->getMessage(), 'row' => []];
        }
        $rounded = $this->roundQty($raw, (string) ($item['rounding_rule'] ?? 'NONE'));
        $product = null;
        $productId = (int) ($item['product_id'] ?? 0);
        if ($productId > 0) {
            $product = $this->products->find($productId);
            if ($product === null) {
                return ['error' => (string) $item['description'] . ' refers to a missing product.', 'row' => []];
            }
        }
        $method = strtoupper((string) ($item['cost_calculation_method'] ?? 'PRODUCT'));
        $wastePercent = $item['waste_percent_override'];
        if ($wastePercent === null || $wastePercent === '') {
            $wastePercent = $product['standard_waste_percent'] ?? '0';
        }
        $billable = $rounded;
        $yield = null;
        if ($method === 'LABOUR' || $method === 'TRAVEL' || $method === 'FIXED') {
            $wastePercent = '0';
        }
        if ($product !== null && strtoupper((string) ($item['yield_mode'] ?? 'NONE')) === 'ROLL' && !empty($product['roll_width_mm'])) {
            $roll = $this->consumption->roll(
                (string) $product['roll_width_mm'],
                $variables['W'],
                $variables['H'],
                $variables['Q'],
                isset($product['waste_threshold_percent']) ? (string) $product['waste_threshold_percent'] : null
            );
            $chosen = $this->waste->billableRoll($roll, 'ACTUAL', null, null);
            $billable = (string) $chosen['billable'];
            $yield = ['mode' => 'ROLL', 'roll' => $roll];
        }
        if ($product !== null && strtoupper((string) ($item['yield_mode'] ?? 'NONE')) === 'SHEET' && !empty($product['sheet_width_mm'])) {
            $fit = $this->sheets->sheets(
                (string) $product['sheet_width_mm'],
                (string) $product['sheet_height_mm'],
                $variables['W'],
                $variables['H'],
                $variables['Q'],
                (int) ($product['allow_rotation'] ?? 0) === 1
            );
            $override = trim((string) ($posted['sheet_override'] ?? ''));
            $billable = $override !== '' && Decimal::isNumeric($override) ? $override : $fit['sheets'];
            $yield = ['mode' => 'SHEET', 'fit' => $fit, 'override' => $override !== ''];
        }
        $costed = $this->waste->costedQuantity($billable, (string) $wastePercent);
        $pack = $item['pack_size'] ?? null;
        $purchase = $costed;
        if ($pack !== null && $pack !== '' && Decimal::cmp((string) $pack, '0') > 0) {
            $purchase = $this->packs($costed, (string) $pack);
        }
        $unitCost = $product !== null ? (string) $product['cost_price'] : '0';
        $distance = null;
        if ($method === 'LABOUR') {
            $unit = strtoupper((string) ($item['unit'] ?? 'MIN'));
            $minutes = $unit === 'HOUR' ? $this->units->hoursToMinutes($costed) : $costed;
            $hours = $this->units->minutesToHours($minutes);
            $rate = $unitCost;
            if (Decimal::cmp($rate, '0') === 0) {
                $rate = (string) SettingsService::get('default_labour_hourly_cost', '0');
            }
            $total = Decimal::mul($hours, $rate, Decimal::CALC_SCALE);
            $unitCost = $rate;
            $costed = $minutes;
            $billable = $minutes;
        } elseif ($method === 'TRAVEL') {
            $distance = $rounded;
            $rate = (string) SettingsService::get('travel_rate_per_km', '0');
            $unitCost = $rate;
            $total = Decimal::mul($distance, $rate, Decimal::CALC_SCALE);
            $costed = $distance;
        } else {
            $total = Decimal::mul($costed, $unitCost, Decimal::CALC_SCALE);
        }

        return ['error' => null, 'row' => [
            'description' => (string) $item['description'],
            'component_type' => strtoupper((string) $item['component_type']),
            'product_id' => $productId > 0 ? $productId : null,
            'product_name' => $product['name'] ?? null,
            'quantity' => Decimal::round($billable, 4),
            'costed_quantity' => Decimal::round($costed, 4),
            'purchase_quantity' => Decimal::round($purchase, 4),
            'pack_size' => $pack === null || $pack === '' ? null : Decimal::round((string) $pack, 4),
            'waste_percent' => Decimal::round((string) $wastePercent, 2),
            'unit' => (string) ($item['unit'] ?? 'unit'),
            'unit_cost' => Decimal::round($unitCost, 4),
            'total_cost' => Decimal::money($total),
            'optional' => (int) ($item['optional'] ?? 0),
            'yield' => $yield,
            'distance_km' => $distance,
            'formula' => (string) $item['quantity_formula'],
        ]];
    }

    /**
     * @param array<string, mixed> $posted
     * @param array<string, mixed> $level
     * @param list<int> $stack
     * @return array<string, mixed>
     */
    private function nested(int $recipeId, array $posted, array $level, array $stack): array
    {
        $recipes = new \App\Repositories\RecipeRepository();
        $recipe = $recipes->find($recipeId);
        if ($recipe === null) {
            return $this->failed('A nested recipe is missing.');
        }

        return $this->calculate($recipe, $recipes->inputs($recipeId), $recipes->items($recipeId), $posted, $level, $stack);
    }

    private function conditionMet(mixed $json, array $posted): bool
    {
        if ($json === null || $json === '') {
            return true;
        }
        $data = is_array($json) ? $json : json_decode((string) $json, true);
        if (!is_array($data)) {
            return false;
        }
        $rules = isset($data['all']) && is_array($data['all']) ? $data['all'] : [$data];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                return false;
            }
            $code = strtoupper(trim((string) ($rule['input'] ?? '')));
            $op = strtoupper(trim((string) ($rule['op'] ?? 'EQ')));
            $expected = strtoupper(trim((string) ($rule['value'] ?? '')));
            $actualRaw = $posted[$code] ?? $posted[strtolower($code)] ?? '';
            $actual = strtoupper(trim((string) $actualRaw));
            if (in_array($op, ['EQ', 'NEQ'], true) && !Decimal::isNumeric($actual)) {
                $pass = $actual === $expected || ($this->truthy($actual) && $this->truthy($expected)) || (!$this->truthy($actual) && in_array($expected, ['NO', '0', 'FALSE'], true) && !$this->truthy($actualRaw));
                if ($op === 'NEQ') {
                    $pass = $actual !== $expected;
                }
                if (!$pass && $op === 'EQ' && $this->truthy($actualRaw) === $this->truthy($expected) && in_array($expected, ['YES', 'NO', '1', '0', 'TRUE', 'FALSE'], true)) {
                    $pass = true;
                }
                if (!$pass) {
                    return false;
                }
                continue;
            }
            if (!Decimal::isNumeric((string) $actualRaw) || !Decimal::isNumeric((string) ($rule['value'] ?? ''))) {
                if ($op === 'EQ' && $actual !== $expected) {
                    return false;
                }
                if ($op === 'NEQ' && $actual === $expected) {
                    return false;
                }
                continue;
            }
            $cmp = Decimal::cmp($this->number($actualRaw), $this->number($rule['value']));
            $ok = match ($op) {
                'EQ' => $cmp === 0,
                'NEQ' => $cmp !== 0,
                'GT' => $cmp > 0,
                'LT' => $cmp < 0,
                'GTE' => $cmp >= 0,
                'LTE' => $cmp <= 0,
                default => false,
            };
            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    private function roundQty(string $qty, string $rule): string
    {
        return match (strtoupper($rule)) {
            'CEIL' => $this->formulas->ceil($qty),
            'FLOOR' => $this->formulas->floor($qty),
            'ROUND' => Decimal::round($qty, 0),
            default => $qty,
        };
    }

    private function packs(string $quantity, string $pack): string
    {
        $raw = Decimal::div($quantity, $pack, Decimal::CALC_SCALE);
        $whole = explode('.', Decimal::round($raw, 4), 2)[0];
        if (Decimal::cmp($raw, $whole) > 0) {
            $whole = Decimal::add($whole, '1', 0);
        }

        return Decimal::mul($whole, $pack, Decimal::CALC_SCALE);
    }

    private function number(mixed $value): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '' || !Decimal::isNumeric($text)) {
            return '0';
        }

        return $text;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(strtoupper(trim((string) $value)), ['1', 'YES', 'TRUE', 'ON'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function failed(string $error): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'inputs' => [],
            'variables' => [],
            'components' => [],
            'labour' => [],
            'total_cost' => '0.00',
            'selling_price' => '0.00',
            'gross_profit' => '0.00',
            'gross_margin' => null,
            'markup_percent' => '0',
            'face_area_each' => '0',
            'face_area_total' => '0',
            'production_route' => [],
        ];
    }
}

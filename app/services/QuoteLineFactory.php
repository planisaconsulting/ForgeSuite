<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DiscountType;
use App\Helpers\Decimal;

/**
 * Builds one quote line from PricingService and freezes the figures.
 *
 * The product row passed in is the cost the line will keep. For a new line
 * that is the current catalogue row. For an edit that keeps saved pricing,
 * the caller passes a row rebuilt from the line snapshot, not the live product.
 * Posted costs and markups are ignored for catalogue lines.
 */
final class QuoteLineFactory
{
    public function __construct(private readonly PricingService $pricing = new PricingService())
    {
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @param array<string, mixed> $level
     * @return array{errors: list<string>, line: array<string, mixed>|null}
     */
    public function fromProduct(array $product, array $input, array $level, ?int $userId): array
    {
        $level['active'] = 1;
        $priced = $this->pricing->price($product, $input, [$level]);
        if (!$priced['ok']) {
            return ['errors' => $priced['errors'], 'line' => null];
        }

        $calculated = (string) ($priced['levels'][0]['selling_price'] ?? '0.00');
        $markup = (string) ($priced['levels'][0]['markup_percent'] ?? '0.00');
        $final = $calculated;
        $overridden = 0;
        $reason = null;
        $posted = trim((string) ($input['final_sell_price'] ?? ''));
        if ($posted !== '') {
            $postedMoney = $this->money($posted);
            if ($postedMoney !== null && Decimal::cmp($postedMoney, $calculated) !== 0) {
                $final = $postedMoney;
                $overridden = 1;
                $reason = trim((string) ($input['override_reason'] ?? ''));
                if ($reason === '') {
                    $reason = 'Negotiated price';
                }
            }
        }

        $commercial = $this->commercial($final, $input);
        $description = trim((string) ($input['customer_description'] ?? ''));
        if ($description === '') {
            $description = (string) ($product['name'] ?? 'Item');
        }

        $roll = $priced['roll'];
        $sheet = $priced['sheet'];
        $actualArea = null;
        $billableArea = null;
        $wasteArea = null;
        if (is_array($roll)) {
            $actualArea = (string) $roll['actual_area_m2'];
            $billableArea = (string) $priced['billable_quantity'];
            $wasteArea = (string) $roll['potential_waste_m2'];
        } elseif (is_array($sheet)) {
            $actualArea = (string) $sheet['actual_area_m2'];
            $wasteArea = (string) ($sheet['offcut_area_m2'] ?? '0');
        } elseif (($priced['quantity_unit'] ?? '') === 'm²') {
            $actualArea = (string) $priced['actual_quantity'];
            $billableArea = (string) $priced['billable_quantity'];
        }

        $raw = (string) $priced['raw_cost'];
        $totalCost = (string) $priced['total_cost'];
        $wasteCost = Decimal::money(Decimal::sub($totalCost, $raw));
        if (Decimal::cmp($wasteCost, '0') < 0) {
            $wasteCost = '0.00';
        }

        $quantity = $this->enteredQuantity($input, (string) $priced['method']);
        $line = [
            'product_id' => isset($product['id']) ? (int) $product['id'] : null,
            'is_custom_item' => 0,
            'is_optional' => !empty($input['is_optional']) ? 1 : 0,
            'include_optional' => !empty($input['include_optional']) ? 1 : 0,
            'product_name_snapshot' => (string) ($product['name'] ?? 'Item'),
            'product_description_snapshot' => blank_to_null($product['description'] ?? null),
            'sku_snapshot' => blank_to_null($product['sku'] ?? null),
            'product_type_snapshot' => blank_to_null($product['product_type'] ?? null),
            'pricing_method_snapshot' => (string) $priced['method'],
            'width_mm' => $this->nullableNumber($input['width_mm'] ?? null),
            'height_mm' => $this->nullableNumber($input['height_mm'] ?? null),
            'length_mm' => $this->nullableNumber($input['length_mm'] ?? null),
            'quantity' => Decimal::qty($quantity),
            'actual_quantity' => (string) $priced['actual_quantity'],
            'billable_quantity' => (string) $priced['billable_quantity'],
            'actual_area' => $actualArea,
            'billable_area' => $billableArea,
            'waste_area' => $wasteArea,
            'waste_mode' => (string) $priced['waste_mode'],
            'standard_waste_percent_snapshot' => (string) $priced['standard_waste_percent'],
            'cost_unit_snapshot' => (string) $priced['cost_unit'],
            'unit_cost_snapshot' => (string) $priced['unit_cost'],
            'base_cost' => $raw,
            'waste_cost' => $wasteCost,
            'total_cost' => $totalCost,
            'pricing_level_id' => isset($level['id']) ? (int) $level['id'] : null,
            'markup_percent_snapshot' => $markup,
            'calculated_price' => $calculated,
            'final_sell_price' => $final,
            'unit_sell_price' => $this->unitSell($commercial['line_total'], $quantity),
            'price_overridden' => $overridden,
            'override_reason' => $overridden === 1 ? $reason : null,
            'overridden_by' => $overridden === 1 ? $userId : null,
            'overridden_at' => $overridden === 1 ? date('Y-m-d H:i:s') : null,
            'line_discount_type' => $commercial['type'],
            'line_discount_value' => $commercial['value'],
            'discount_amount' => $commercial['discount'],
            'line_subtotal' => $final,
            'line_total' => $commercial['line_total'],
            'customer_description' => $description,
            'internal_description' => blank_to_null($input['internal_description'] ?? null),
            'measure_snapshot' => json_encode([
                'roll_width_mm' => $product['roll_width_mm'] ?? null,
                'sheet_width_mm' => $product['sheet_width_mm'] ?? null,
                'sheet_height_mm' => $product['sheet_height_mm'] ?? null,
                'waste_threshold_percent' => $product['waste_threshold_percent'] ?? null,
                'allow_rotation' => (int) ($product['allow_rotation'] ?? 0),
                'costed_quantity' => $priced['costed_quantity'],
                'quantity_unit' => $priced['quantity_unit'],
                'roll' => $roll,
                'sheet' => $sheet,
                'manual' => (bool) $priced['manual'],
                'warnings' => $priced['warnings'],
                'entered' => [
                    'manual_width_mm' => $input['manual_width_mm'] ?? null,
                    'manual_area' => $input['manual_area'] ?? null,
                    'manual_sheets' => $input['manual_sheets'] ?? null,
                    'litres' => $input['litres'] ?? null,
                    'hours' => $input['hours'] ?? null,
                ],
            ], JSON_THROW_ON_ERROR),
        ];

        return ['errors' => [], 'line' => $line];
    }

    /**
     * A line that is not in the catalogue. Cost and sell are what the user typed.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $level
     * @return array{errors: list<string>, line: array<string, mixed>|null}
     */
    public function custom(array $input, array $level, ?int $userId): array
    {
        $errors = [];
        $description = trim((string) ($input['customer_description'] ?? ''));
        if ($description === '') {
            $errors[] = 'Describe the custom item.';
        }
        $snapshotName = $description;
        if (mb_strlen($snapshotName) > 180) {
            $snapshotName = rtrim(mb_substr($snapshotName, 0, 177)) . '...';
        }
        $quantity = str_replace(',', '.', trim((string) ($input['quantity'] ?? '')));
        $unitCost = str_replace(',', '.', trim((string) ($input['unit_cost'] ?? '')));
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }
        if (!Decimal::isNumeric($unitCost) || Decimal::cmp($unitCost, '0') < 0) {
            $errors[] = 'Cost must be zero or more.';
        }
        if ($errors !== []) {
            return ['errors' => $errors, 'line' => null];
        }

        $markup = Decimal::round((string) ($level['markup_percent'] ?? '0'), 2);
        $totalCost = Decimal::money(Decimal::mul($unitCost, $quantity));
        $calculated = Decimal::money(Decimal::mul(
            $totalCost,
            Decimal::add('1', Decimal::div($markup, '100'))
        ));
        $posted = trim((string) ($input['final_sell_price'] ?? ''));
        $final = $calculated;
        $overridden = 0;
        $reason = null;
        if ($posted !== '') {
            $postedMoney = $this->money($posted);
            if ($postedMoney !== null && Decimal::cmp($postedMoney, $calculated) !== 0) {
                $final = $postedMoney;
                $overridden = 1;
                $reason = trim((string) ($input['override_reason'] ?? ''));
                if ($reason === '') {
                    $reason = 'Custom selling price';
                }
            }
        }
        $commercial = $this->commercial($final, $input);

        return ['errors' => [], 'line' => [
            'product_id' => null,
            'is_custom_item' => 1,
            'is_optional' => !empty($input['is_optional']) ? 1 : 0,
            'include_optional' => !empty($input['include_optional']) ? 1 : 0,
            'product_name_snapshot' => $snapshotName,
            'product_description_snapshot' => null,
            'sku_snapshot' => null,
            'product_type_snapshot' => 'SERVICE',
            'pricing_method_snapshot' => 'CUSTOM',
            'width_mm' => null,
            'height_mm' => null,
            'length_mm' => null,
            'quantity' => Decimal::qty($quantity),
            'actual_quantity' => Decimal::qty($quantity),
            'billable_quantity' => Decimal::qty($quantity),
            'actual_area' => null,
            'billable_area' => null,
            'waste_area' => null,
            'waste_mode' => null,
            'standard_waste_percent_snapshot' => '0.00',
            'cost_unit_snapshot' => 'unit',
            'unit_cost_snapshot' => Decimal::money($unitCost),
            'base_cost' => $totalCost,
            'waste_cost' => '0.00',
            'total_cost' => $totalCost,
            'pricing_level_id' => isset($level['id']) ? (int) $level['id'] : null,
            'markup_percent_snapshot' => $markup,
            'calculated_price' => $calculated,
            'final_sell_price' => $final,
            'unit_sell_price' => $this->unitSell($commercial['line_total'], $quantity),
            'price_overridden' => $overridden,
            'override_reason' => $reason,
            'overridden_by' => $overridden === 1 ? $userId : null,
            'overridden_at' => $overridden === 1 ? date('Y-m-d H:i:s') : null,
            'line_discount_type' => $commercial['type'],
            'line_discount_value' => $commercial['value'],
            'discount_amount' => $commercial['discount'],
            'line_subtotal' => $final,
            'line_total' => $commercial['line_total'],
            'customer_description' => $description,
            'internal_description' => blank_to_null($input['internal_description'] ?? null),
            'measure_snapshot' => json_encode(['custom' => true], JSON_THROW_ON_ERROR),
        ]];
    }

    /**
     * Catalogue shape rebuilt from a saved line, so a size edit keeps the
     * cost that was current when the line was priced.
     *
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    public function productFromLine(array $line): array
    {
        $snap = $this->snapshot($line);

        return [
            'id' => $line['product_id'] ?? null,
            'name' => (string) $line['product_name_snapshot'],
            'sku' => $line['sku_snapshot'] ?? null,
            'description' => $line['product_description_snapshot'] ?? null,
            'product_type' => $line['product_type_snapshot'] ?? null,
            'pricing_method' => (string) $line['pricing_method_snapshot'],
            'cost_price' => (string) $line['unit_cost_snapshot'],
            'cost_unit' => (string) ($line['cost_unit_snapshot'] ?? 'unit'),
            'standard_waste_percent' => (string) $line['standard_waste_percent_snapshot'],
            'roll_width_mm' => $snap['roll_width_mm'] ?? null,
            'sheet_width_mm' => $snap['sheet_width_mm'] ?? null,
            'sheet_height_mm' => $snap['sheet_height_mm'] ?? null,
            'waste_threshold_percent' => $snap['waste_threshold_percent'] ?? null,
            'allow_rotation' => (int) ($snap['allow_rotation'] ?? 0),
            'default_waste_policy' => (string) ($line['waste_mode'] ?? 'ACTUAL'),
            'active' => 1,
        ];
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    public function inputFromLine(array $line): array
    {
        $snap = $this->snapshot($line);
        $entered = is_array($snap['entered'] ?? null) ? $snap['entered'] : [];
        $method = (string) $line['pricing_method_snapshot'];

        return [
            'width_mm' => $line['width_mm'],
            'height_mm' => $line['height_mm'],
            'length_mm' => $line['length_mm'],
            'quantity' => $line['quantity'],
            'litres' => $method === 'LITRE' ? $line['quantity'] : ($entered['litres'] ?? null),
            'hours' => $method === 'HOUR' ? $line['quantity'] : ($entered['hours'] ?? null),
            'waste_mode' => $line['waste_mode'],
            'manual_width_mm' => $entered['manual_width_mm'] ?? null,
            'manual_area' => $entered['manual_area'] ?? null,
            'manual_sheets' => $entered['manual_sheets'] ?? null,
            'customer_description' => $line['customer_description'],
            'internal_description' => $line['internal_description'],
            'is_optional' => (int) $line['is_optional'] === 1,
            'include_optional' => (int) $line['include_optional'] === 1,
            'final_sell_price' => (int) $line['price_overridden'] === 1 ? $line['final_sell_price'] : '',
            'override_reason' => $line['override_reason'] ?? '',
            'line_discount_type' => $line['line_discount_type'] ?? 'NONE',
            'line_discount_value' => $line['line_discount_value'] ?? '0',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{type: string, value: string, discount: string, line_total: string}
     */
    private function commercial(string $final, array $input): array
    {
        $type = DiscountType::normalise((string) ($input['line_discount_type'] ?? 'NONE'));
        $value = str_replace(',', '.', trim((string) ($input['line_discount_value'] ?? '0')));
        if ($value === '' || !Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
            $value = '0';
        }
        $discount = '0.00';
        if ($type === DiscountType::Percentage->value) {
            $percent = Decimal::cmp($value, '100') > 0 ? '100' : $value;
            $discount = Decimal::money(Decimal::mul($final, Decimal::div($percent, '100')));
            $value = $percent;
        } elseif ($type === DiscountType::Fixed->value) {
            $discount = Decimal::cmp($value, $final) > 0 ? Decimal::money($final) : Decimal::money($value);
        }
        $total = Decimal::money(Decimal::sub($final, $discount));
        if (Decimal::cmp($total, '0') < 0) {
            $total = '0.00';
        }

        return [
            'type' => $type,
            'value' => Decimal::round($value, 4),
            'discount' => $discount,
            'line_total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function enteredQuantity(array $input, string $method): string
    {
        if ($method === 'LITRE') {
            return (string) ($input['litres'] ?? $input['quantity'] ?? '1');
        }
        if ($method === 'HOUR') {
            return (string) ($input['hours'] ?? $input['quantity'] ?? '1');
        }

        return (string) ($input['quantity'] ?? '1');
    }

    private function unitSell(string $lineTotal, string $quantity): string
    {
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') === 0) {
            return Decimal::money($lineTotal);
        }

        return Decimal::round(Decimal::div($lineTotal, $quantity), 4);
    }

    private function money(string $value): ?string
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || !Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
            return null;
        }

        return Decimal::money($value);
    }

    private function nullableNumber(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '' || !Decimal::isNumeric($value)) {
            return null;
        }

        return Decimal::round($value, 2);
    }

    /**
     * @param array<string, mixed> $line
     * @return array<string, mixed>
     */
    private function snapshot(array $line): array
    {
        $raw = $line['measure_snapshot'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}

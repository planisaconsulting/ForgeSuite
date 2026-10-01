<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Cost at several quantities.
 *
 * Sheet count is recalculated at each quantity, and a per-job setup is added
 * once. The unit cost is not quantity times the first unit cost.
 */
final class QuantityBreakService
{
    /**
     * @param list<string> $quantities
     * @return list<array<string, mixed>>
     */
    public function sheetBreaks(
        string $sheetWidth,
        string $sheetHeight,
        string $partWidth,
        string $partHeight,
        array $quantities,
        bool $allowRotation,
        string $kerf,
        string $margin,
        string $sheetCost,
        string $setupCost,
        string $markupPercent
    ): array {
        $sheets = new SheetYieldService();
        $pricing = new PricingMathService();
        $rows = [];
        foreach ($quantities as $quantity) {
            if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
                continue;
            }
            $layout = $sheets->calculate($sheetWidth, $sheetHeight, $partWidth, $partHeight, $quantity, $allowRotation, $kerf, $margin);
            $material = Decimal::mul((string) ($layout['sheets'] ?? '0'), $sheetCost, Decimal::CALC_SCALE);
            $total = Decimal::add($material, $setupCost, Decimal::CALC_SCALE);
            $unit = Decimal::div($total, $quantity, Decimal::CALC_SCALE);
            $sell = $pricing->sellFromMarkup($total, $markupPercent);
            $sellTotal = $sell['ok'] ? $sell['sell'] : Decimal::money($total);
            $unitSell = Decimal::div($sellTotal, $quantity, Decimal::CALC_SCALE);
            $rows[] = [
                'quantity' => Decimal::round($quantity, 2),
                'sheets' => (string) ($layout['sheets'] ?? '0'),
                'unit_cost' => Decimal::money($unit),
                'total_cost' => Decimal::money($total),
                'unit_sell' => Decimal::money($unitSell),
                'total_sell' => $sellTotal,
                'margin_percent' => $pricing->marginPercent($sellTotal, Decimal::money($total)),
            ];
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Markup and gross margin are different.
 *
 * Markup: sell = cost x (1 + markup / 100). A 40% markup on R1,000 is R1,400.
 * Gross margin: sell = cost / (1 - margin / 100). A 40% margin on R1,000 is R1,666.67.
 * Margin percent is (sell - cost) / sell x 100.
 */
final class PricingMathService
{
    /**
     * @return array{ok: bool, sell: string, error: string|null}
     */
    public function sellFromMargin(string $cost, string $marginPercent): array
    {
        if (!Decimal::isNumeric($cost) || !Decimal::isNumeric($marginPercent)) {
            return ['ok' => false, 'sell' => '0.00', 'error' => 'Cost and margin must be numbers.'];
        }
        if (Decimal::cmp($marginPercent, '0') < 0 || Decimal::cmp($marginPercent, '100') >= 0) {
            return ['ok' => false, 'sell' => '0.00', 'error' => 'Gross margin must be from 0 up to, but not including, 100%.'];
        }
        $factor = Decimal::sub('1', Decimal::div($marginPercent, '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        if (Decimal::cmp($factor, '0') <= 0) {
            return ['ok' => false, 'sell' => '0.00', 'error' => 'That margin cannot be priced.'];
        }

        return ['ok' => true, 'sell' => Decimal::money(Decimal::div($cost, $factor, Decimal::CALC_SCALE)), 'error' => null];
    }

    /**
     * @return array{ok: bool, sell: string, error: string|null}
     */
    public function sellFromMarkup(string $cost, string $markupPercent): array
    {
        if (!Decimal::isNumeric($cost) || !Decimal::isNumeric($markupPercent)) {
            return ['ok' => false, 'sell' => '0.00', 'error' => 'Cost and markup must be numbers.'];
        }
        $factor = Decimal::add('1', Decimal::div($markupPercent, '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE);

        return ['ok' => true, 'sell' => Decimal::money(Decimal::mul($cost, $factor, Decimal::CALC_SCALE)), 'error' => null];
    }

    public function marginPercent(string $sell, string $cost): ?string
    {
        if (!Decimal::isNumeric($sell) || !Decimal::isNumeric($cost) || Decimal::cmp($sell, '0') <= 0) {
            return null;
        }
        $profit = Decimal::sub($sell, $cost, Decimal::CALC_SCALE);

        return Decimal::round(Decimal::mul(Decimal::div($profit, $sell, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function discountImpact(string $cost, string $sell, string $discountAmount): array
    {
        $before = $this->marginPercent($sell, $cost);
        $afterSell = Decimal::sub($sell, $discountAmount, Decimal::CALC_SCALE);
        if (Decimal::cmp($afterSell, '0') < 0) {
            $afterSell = '0';
        }
        $after = $this->marginPercent(Decimal::money($afterSell), $cost);
        $points = null;
        if ($before !== null && $after !== null) {
            $points = Decimal::round(Decimal::sub($after, $before, Decimal::CALC_SCALE), 2);
        }

        return [
            'sell_before' => Decimal::money($sell),
            'margin_before' => $before,
            'discount' => Decimal::money($discountAmount),
            'sell_after' => Decimal::money($afterSell),
            'margin_after' => $after,
            'margin_points' => $points,
        ];
    }
}

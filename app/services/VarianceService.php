<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Variance is actual minus estimated.
 * Variance percent is (actual - estimated) / estimated x 100.
 * A zero estimate has no percent.
 * For cost and consumption, actual below estimate is favourable.
 */
final class VarianceService
{
    /**
     * @return array{variance: string, variance_percent: ?string, meaning: string}
     */
    public function compare(string $estimated, string $actual): array
    {
        $estimated = Decimal::isNumeric($estimated) ? $estimated : '0';
        $actual = Decimal::isNumeric($actual) ? $actual : '0';
        $difference = Decimal::sub($actual, $estimated, Decimal::CALC_SCALE);
        $percent = null;
        if (Decimal::cmp($estimated, '0') > 0) {
            $percent = Decimal::round(Decimal::mul(Decimal::div($difference, $estimated, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE), 2);
        }
        $meaning = 'LEVEL';
        if (Decimal::cmp($difference, '0') < 0) {
            $meaning = 'FAVOURABLE';
        } elseif (Decimal::cmp($difference, '0') > 0) {
            $meaning = 'UNFAVOURABLE';
        }

        return [
            'variance' => Decimal::round($difference, 4),
            'variance_percent' => $percent,
            'meaning' => $meaning,
        ];
    }
}

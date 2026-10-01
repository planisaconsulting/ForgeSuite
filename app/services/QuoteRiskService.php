<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Quote review warnings from visible rules.
 *
 * INFO, WARNING, and REVIEW REQUIRED are labels, not a hidden score.
 * A warning does not stop the quote unless quote_risk_block is on and the
 * level is REVIEW REQUIRED and nobody has recorded an override reason.
 */
final class QuoteRiskService
{
    /**
     * @param array<string, mixed> $facts
     * @return array{level: string, warnings: list<array{level: string, code: string, message: string}>, blocked: bool}
     */
    public function assess(array $facts): array
    {
        $warnings = [];
        $margin = isset($facts['expected_margin']) ? (string) $facts['expected_margin'] : null;
        $target = (string) ($facts['target_margin'] ?? '35');
        if ($margin !== null && $margin !== '' && Decimal::isNumeric($margin) && Decimal::isNumeric($target) && Decimal::cmp($margin, $target) < 0) {
            $gap = Decimal::sub($target, $margin, Decimal::CALC_SCALE);
            $level = Decimal::cmp($gap, '10') >= 0 ? 'REVIEW_REQUIRED' : 'WARNING';
            $warnings[] = [
                'level' => $level,
                'code' => 'MARGIN',
                'message' => 'Expected margin is ' . Decimal::round($margin, 2) . '%. Target is ' . Decimal::round($target, 2) . '%.',
            ];
        }
        $age = (int) ($facts['cost_age_days'] ?? 0);
        $ageLimit = (int) ($facts['cost_age_limit'] ?? 90);
        if ($age > $ageLimit) {
            $warnings[] = [
                'level' => 'WARNING',
                'code' => 'COST_AGE',
                'message' => 'A supplier cost is ' . $age . ' days old. The review threshold is ' . $ageLimit . ' days.',
            ];
        }
        $move = (string) ($facts['price_move_percent'] ?? '0');
        $moveLimit = (string) ($facts['price_move_limit'] ?? '5');
        if (Decimal::isNumeric($move) && Decimal::isNumeric($moveLimit) && Decimal::cmp(Decimal::round($move, 2), '0') !== 0) {
            $absolute = Decimal::cmp($move, '0') < 0 ? Decimal::mul($move, '-1', Decimal::CALC_SCALE) : $move;
            if (Decimal::cmp($absolute, $moveLimit) >= 0) {
                $warnings[] = [
                    'level' => 'WARNING',
                    'code' => 'PRICE_VOLATILITY',
                    'message' => 'Material cost moved ' . Decimal::round($move, 2) . '% inside the review window.',
                ];
            }
        }
        $points = (string) ($facts['discount_margin_points'] ?? '0');
        if (Decimal::isNumeric($points) && Decimal::cmp($points, '-8') <= 0) {
            $warnings[] = [
                'level' => 'WARNING',
                'code' => 'DISCOUNT',
                'message' => 'The discount changes margin by ' . Decimal::round($points, 2) . ' percentage points.',
            ];
        }
        $variance = (string) ($facts['recipe_labour_variance_percent'] ?? '0');
        $sample = (int) ($facts['recipe_sample'] ?? 0);
        $minimum = (int) ($facts['minimum_sample'] ?? 5);
        if ($sample >= $minimum && Decimal::isNumeric($variance) && Decimal::cmp($variance, '15') >= 0) {
            $warnings[] = [
                'level' => 'WARNING',
                'code' => 'RECIPE_VARIANCE',
                'message' => 'Recipe labour on ' . $sample . ' jobs averages ' . Decimal::round($variance, 2) . '% above the estimate.',
            ];
        }
        if (!empty($facts['installation_unconfirmed'])) {
            $warnings[] = [
                'level' => 'WARNING',
                'code' => 'INSTALLATION',
                'message' => 'Installation access is not confirmed.',
            ];
        }
        if (!empty($facts['manual_override'])) {
            $warnings[] = [
                'level' => 'WARNING',
                'code' => 'OVERRIDE',
                'message' => 'A yield or quantity override is on this estimate.',
            ];
        }
        if (!empty($facts['missing_survey'])) {
            $warnings[] = [
                'level' => 'INFO',
                'code' => 'SURVEY',
                'message' => 'No site survey is linked.',
            ];
        }
        $validity = (int) ($facts['validity_days'] ?? 0);
        if (!empty($facts['material_volatile']) && $validity >= 30) {
            $warnings[] = [
                'level' => 'WARNING',
                'code' => 'VALIDITY',
                'message' => 'Material price volatility with a ' . $validity . ' day validity. Validity was not shortened.',
            ];
        }
        $level = 'INFO';
        foreach ($warnings as $warning) {
            if ($warning['level'] === 'REVIEW_REQUIRED') {
                $level = 'REVIEW_REQUIRED';
                break;
            }
            if ($warning['level'] === 'WARNING') {
                $level = 'WARNING';
            }
        }
        if ($warnings === []) {
            $level = 'INFO';
        }
        $blocked = !empty($facts['block_on_review']) && $level === 'REVIEW_REQUIRED' && trim((string) ($facts['override_reason'] ?? '')) === '';

        return ['level' => $level, 'warnings' => $warnings, 'blocked' => $blocked];
    }
}

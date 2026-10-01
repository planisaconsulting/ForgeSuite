<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Mean, median, range, and a trimmed mean.
 *
 * Every value stays in the sample. A value more than twice the median, or
 * less than half the median, is named as an outlier and is still shown.
 * A recommendation uses the median, which is less pulled by that outlier
 * than the mean. The trimmed mean drops the single lowest and single highest
 * value only when there are at least four values, and it is shown beside
 * the full mean.
 */
final class HistoricalStatsService
{
    /**
     * @param list<string> $values
     * @return array<string, mixed>
     */
    public function summarise(array $values, string $outlierMultiple = '2'): array
    {
        $clean = [];
        foreach ($values as $value) {
            if (Decimal::isNumeric((string) $value)) {
                $clean[] = (string) $value;
            }
        }
        $count = count($clean);
        if ($count === 0) {
            return [
                'count' => 0,
                'mean' => null,
                'median' => null,
                'min' => null,
                'max' => null,
                'trimmed_mean' => null,
                'outliers' => [],
                'recommendation_value' => null,
                'recommendation_basis' => 'NO HISTORY',
                'explanation' => 'There are no comparable values.',
                'confidence' => 'NO_HISTORY',
            ];
        }
        usort($clean, static fn (string $a, string $b): int => Decimal::cmp($a, $b));
        $sum = '0';
        foreach ($clean as $value) {
            $sum = Decimal::add($sum, $value, Decimal::CALC_SCALE);
        }
        $mean = Decimal::div($sum, (string) $count, Decimal::CALC_SCALE);
        $median = $this->median($clean);
        $outliers = [];
        if (Decimal::cmp($median, '0') > 0 && Decimal::isNumeric($outlierMultiple) && Decimal::cmp($outlierMultiple, '0') > 0) {
            $high = Decimal::mul($median, $outlierMultiple, Decimal::CALC_SCALE);
            $low = Decimal::div($median, $outlierMultiple, Decimal::CALC_SCALE);
            foreach ($clean as $value) {
                if (Decimal::cmp($value, $high) > 0 || Decimal::cmp($value, $low) < 0) {
                    $outliers[] = Decimal::round($value, 2);
                }
            }
        }
        $trimmed = $mean;
        if ($count >= 4) {
            $middle = array_slice($clean, 1, $count - 2);
            $trimmedSum = '0';
            foreach ($middle as $value) {
                $trimmedSum = Decimal::add($trimmedSum, $value, Decimal::CALC_SCALE);
            }
            $trimmed = Decimal::div($trimmedSum, (string) count($middle), Decimal::CALC_SCALE);
        }
        $explanation = 'The recommendation uses the median of ' . $count . ' values. Mean ' . Decimal::round($mean, 2) . ', range ' . Decimal::round($clean[0], 2) . ' to ' . Decimal::round($clean[$count - 1], 2) . '.';
        if ($outliers !== []) {
            $explanation .= ' These values are more than ' . $outlierMultiple . ' times away from the median and stay visible: ' . implode(', ', $outliers) . '. They are not removed from the mean or the range.';
        }

        return [
            'count' => $count,
            'mean' => Decimal::round($mean, 2),
            'median' => Decimal::round($median, 2),
            'min' => Decimal::round($clean[0], 2),
            'max' => Decimal::round($clean[$count - 1], 2),
            'trimmed_mean' => Decimal::round($trimmed, 2),
            'outliers' => $outliers,
            'recommendation_value' => Decimal::round($median, 2),
            'recommendation_basis' => 'MEDIAN',
            'explanation' => $explanation,
            'confidence' => 'ESTABLISHED_HISTORY',
        ];
    }

    public function confidence(int $count, int $minimum): string
    {
        if ($count < 1) {
            return 'NO_HISTORY';
        }
        if ($count < $minimum) {
            return 'LIMITED_HISTORY';
        }

        return 'ESTABLISHED_HISTORY';
    }

    /**
     * @param list<string> $sorted
     */
    private function median(array $sorted): string
    {
        $count = count($sorted);
        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return $sorted[$middle];
        }

        return Decimal::div(Decimal::add($sorted[$middle - 1], $sorted[$middle], Decimal::CALC_SCALE), '2', Decimal::CALC_SCALE);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Report formulas. These do not read the database.
 *
 * Count conversion = accepted decided quotes / all decided quotes.
 * Decided means accepted or declined. Draft, ready, and sent are not lost.
 *
 * Value conversion = accepted quote value / decided quote value.
 *
 * Waste rate = waste quantity / (production quantity + waste quantity).
 *
 * Operational gross profit = commercial value − actual job cost.
 * Gross margin = that profit / commercial value × 100.
 * Cash received is not used in either figure.
 *
 * A percentage change is omitted when the earlier figure is zero.
 */
final class ReportMath
{
    public static function countConversion(string $accepted, string $declined): ?string
    {
        $decided = Decimal::add($accepted, $declined);

        return self::percent($accepted, $decided);
    }

    public static function valueConversion(string $acceptedValue, string $declinedValue): ?string
    {
        $decided = Decimal::add($acceptedValue, $declinedValue);

        return self::percent($acceptedValue, $decided);
    }

    public static function wasteRate(string $waste, string $production): ?string
    {
        $base = Decimal::add($waste, $production);

        return self::percent($waste, $base);
    }

    public static function grossProfit(string $commercial, string $actualCost): string
    {
        return Decimal::money(Decimal::sub($commercial, $actualCost));
    }

    public static function margin(string $profit, string $commercial): ?string
    {
        return self::percent($profit, $commercial);
    }

    /**
     * Change from the comparison period. Null when the earlier number is zero.
     */
    public static function change(string $current, string $previous): ?string
    {
        if (Decimal::cmp($previous, '0') === 0) {
            return null;
        }
        $diff = Decimal::sub($current, $previous);

        return self::percent($diff, $previous);
    }

    public static function difference(string $actual, string $target): string
    {
        return Decimal::round(Decimal::sub($actual, $target), 2);
    }

    /**
     * Stop a spreadsheet treating an exported cell as a formula.
     */
    public static function csvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    public static function percent(string $part, string $whole): ?string
    {
        if (Decimal::cmp($whole, '0') === 0) {
            return null;
        }

        return Decimal::round(Decimal::mul(Decimal::div($part, $whole), '100'), 2);
    }
}

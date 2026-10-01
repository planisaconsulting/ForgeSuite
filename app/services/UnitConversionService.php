<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Conversions used by recipes. Formulas should use the variables this
 * service feeds (AREA_M2, PERIMETER_M) instead of repeating 1000 or 1000000.
 */
final class UnitConversionService
{
    public function mmToM(string $mm): string
    {
        return Decimal::div($mm, '1000', Decimal::CALC_SCALE);
    }

    public function mToMm(string $metres): string
    {
        return Decimal::mul($metres, '1000', Decimal::CALC_SCALE);
    }

    public function squareMmToM2(string $squareMm): string
    {
        return Decimal::div($squareMm, '1000000', Decimal::CALC_SCALE);
    }

    public function m2ToSquareMm(string $squareMetres): string
    {
        return Decimal::mul($squareMetres, '1000000', Decimal::CALC_SCALE);
    }

    public function mlToLitre(string $ml): string
    {
        return Decimal::div($ml, '1000', Decimal::CALC_SCALE);
    }

    public function litreToMl(string $litres): string
    {
        return Decimal::mul($litres, '1000', Decimal::CALC_SCALE);
    }

    public function minutesToHours(string $minutes): string
    {
        return Decimal::div($minutes, '60', Decimal::CALC_SCALE);
    }

    public function hoursToMinutes(string $hours): string
    {
        return Decimal::mul($hours, '60', Decimal::CALC_SCALE);
    }
}

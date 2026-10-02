<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Connected load and power-supply planning from a stored profile.
 * The load factor comes from the profile. It is not a fixed 80 percent.
 * Cable size and circuit protection are not calculated.
 */
final class ElectricalEstimateService
{
    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    public function modules(string $areaM2, string $letterCount, array $profile): array
    {
        $method = strtoupper((string) ($profile['method_code'] ?? 'MODULES_PER_M2'));
        $wattsEach = (string) ($profile['module_watts'] ?? '0');
        if (!Decimal::isNumeric($wattsEach) || Decimal::cmp($wattsEach, '0') <= 0) {
            return ['ok' => false, 'error' => 'The LED profile has no module wattage.', 'modules' => '0', 'load_w' => '0', 'trace' => []];
        }
        $trace = [];
        if ($method === 'PER_LETTER') {
            $each = (string) ($profile['modules_per_letter'] ?? '0');
            if (!Decimal::isNumeric($each) || Decimal::cmp($each, '0') <= 0) {
                return ['ok' => false, 'error' => 'The LED profile has no modules-per-letter value.', 'modules' => '0', 'load_w' => '0', 'trace' => []];
            }
            $raw = Decimal::mul($letterCount, $each, Decimal::CALC_SCALE);
            $trace[] = SignMath::trace('LED modules', 'Per letter', $letterCount . ' letters x ' . $each, $raw);
        } elseif ($method === 'GRID_SPACING') {
            $spacing = (string) ($profile['spacing_mm'] ?? '0');
            if (!Decimal::isNumeric($spacing) || Decimal::cmp($spacing, '0') <= 0) {
                return ['ok' => false, 'error' => 'The LED profile has no spacing.', 'modules' => '0', 'load_w' => '0', 'trace' => []];
            }
            $spacingM = Decimal::div($spacing, '1000', Decimal::CALC_SCALE);
            $cell = Decimal::mul($spacingM, $spacingM, Decimal::CALC_SCALE);
            if (Decimal::cmp($cell, '0') === 0) {
                return ['ok' => false, 'error' => 'The LED spacing is too small to use.', 'modules' => '0', 'load_w' => '0', 'trace' => []];
            }
            $raw = Decimal::div($areaM2, $cell, Decimal::CALC_SCALE);
            $trace[] = SignMath::trace('LED modules', 'Grid spacing', $areaM2 . ' m2 / (' . $spacing . ' mm grid)', $raw);
        } else {
            $per = (string) ($profile['modules_per_m2'] ?? '0');
            if (!Decimal::isNumeric($per) || Decimal::cmp($per, '0') <= 0) {
                return ['ok' => false, 'error' => 'The LED profile has no modules per square metre.', 'modules' => '0', 'load_w' => '0', 'trace' => []];
            }
            $raw = Decimal::mul($areaM2, $per, Decimal::CALC_SCALE);
            $trace[] = SignMath::trace('LED modules', 'Modules per m2', $areaM2 . ' m2 x ' . $per, $raw);
        }
        $modules = SignMath::ceilWhole($raw);
        $load = Decimal::mul($modules, $wattsEach, Decimal::CALC_SCALE);
        $trace[] = SignMath::trace('Connected load', 'Modules x module watts', $modules . ' x ' . $wattsEach . ' W', Decimal::round($load, 4) . ' W');

        return [
            'ok' => true,
            'error' => null,
            'modules' => $modules,
            'load_w' => Decimal::round($load, 4),
            'method' => $method,
            'trace' => $trace,
            'disclaimer' => EngineeringLimits::ELECTRICAL,
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    public function powerSupplies(string $loadW, array $profile): array
    {
        $rated = (string) ($profile['rated_watts'] ?? '0');
        $percent = (string) ($profile['max_load_percent'] ?? '');
        if (!Decimal::isNumeric($rated) || Decimal::cmp($rated, '0') <= 0) {
            return ['ok' => false, 'error' => 'The PSU profile has no rated wattage.', 'count' => '0', 'trace' => []];
        }
        if ($percent === '' || !Decimal::isNumeric($percent) || Decimal::cmp($percent, '0') <= 0 || Decimal::cmp($percent, '100') > 0) {
            return ['ok' => false, 'error' => 'The PSU profile needs a maximum loading percent.', 'count' => '0', 'trace' => []];
        }
        $usable = Decimal::mul($rated, Decimal::div($percent, '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $requiredRated = Decimal::div($loadW, Decimal::div($percent, '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $count = SignMath::ceilWhole(Decimal::div($loadW, $usable, Decimal::CALC_SCALE));
        $trace = [
            SignMath::trace('PSU usable planning capacity', 'Rated watts x configured load percent', $rated . ' W x ' . $percent . '%', Decimal::round($usable, 4) . ' W'),
            SignMath::trace('Required rated capacity', 'Connected load / configured load factor', $loadW . ' / ' . $percent . '%', Decimal::round($requiredRated, 4) . ' W'),
            SignMath::trace('PSU count', 'Whole units of this profile', $loadW . ' W / ' . Decimal::round($usable, 4) . ' W', $count),
        ];

        return [
            'ok' => true,
            'error' => null,
            'count' => $count,
            'usable_each_w' => Decimal::round($usable, 4),
            'required_rated_w' => Decimal::round($requiredRated, 4),
            'suggestion' => $count . ' x ' . Decimal::round($rated, 0) . ' W ' . (string) ($profile['name'] ?? 'PSU'),
            'one_unit_sufficient' => Decimal::cmp($loadW, $usable) <= 0,
            'trace' => $trace,
            'disclaimer' => EngineeringLimits::VERIFY_ELECTRICAL . ' ' . EngineeringLimits::ELECTRICAL,
        ];
    }
}

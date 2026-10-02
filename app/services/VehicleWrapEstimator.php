<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Vehicle wrap quantities from panel dimensions and RollYieldService.
 * A roll-width comparison is a recommendation. It does not change the selected material.
 */
final class VehicleWrapEstimator
{
    /** @var list<string> */
    public const PANELS = [
        'LEFT_FRONT_DOOR', 'LEFT_REAR_DOOR', 'RIGHT_FRONT_DOOR', 'RIGHT_REAR_DOOR',
        'BONNET', 'ROOF', 'TAILGATE', 'CANOPY_LEFT', 'CANOPY_RIGHT', 'CANOPY_REAR',
        'LEFT_FULL_SIDE', 'RIGHT_FULL_SIDE', 'FRONT_BUMPER', 'REAR_BUMPER', 'CUSTOM',
    ];

    /** @var list<string> */
    public const COVERAGE = ['DECALS', 'DOOR_BRANDING', 'PARTIAL_WRAP', 'HALF_WRAP', 'FULL_WRAP', 'ROOF', 'BONNET', 'TAILGATE', 'CANOPY', 'CUSTOM'];

    public function __construct(private readonly RollYieldService $rolls = new RollYieldService())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @param list<array<string, mixed>> $panels
     * @return array<string, mixed>
     */
    public function calculate(array $input, array $panels): array
    {
        $result = SignMath::blank('VEHICLE_WRAP');
        $selected = array_map(static fn ($code): string => strtoupper(trim((string) $code)), (array) ($input['panels'] ?? []));
        $chosen = [];
        foreach ($panels as $panel) {
            if (in_array(strtoupper((string) $panel['panel_code']), $selected, true)) {
                $chosen[] = $panel;
            }
        }
        if ($chosen === []) {
            $result['blocked'] = 'Select at least one panel.';

            return $result;
        }
        $warnAt = SettingsService::get('dimension_warn_mm', '8000');
        $qty = SignMath::quantity((string) ($input['quantity'] ?? '1'));
        $roll = (string) ($input['roll_width_mm'] ?? '1370');
        $artwork = '0';
        $printArea = '0';
        $layouts = [];
        foreach ($chosen as $panel) {
            $width = (string) $panel['width_mm'];
            $height = (string) $panel['height_mm'];
            if (Decimal::cmp($width, $warnAt) > 0 || Decimal::cmp($height, $warnAt) > 0) {
                $result['warnings'][] = (string) $panel['panel_code'] . ' is larger than ' . $warnAt . ' mm. The size was not changed.';
            }
            $bleed = (string) ($panel['bleed_mm'] ?? $input['bleed_mm'] ?? '0');
            $overlap = (string) ($panel['overlap_mm'] ?? $input['overlap_mm'] ?? '0');
            $extra = Decimal::mul(Decimal::add($bleed, $overlap, Decimal::CALC_SCALE), '2', Decimal::CALC_SCALE);
            $printW = Decimal::add($width, $extra, Decimal::CALC_SCALE);
            $printH = Decimal::add($height, $extra, Decimal::CALC_SCALE);
            $area = SignMath::areaM2($width, $height);
            $printed = SignMath::areaM2($printW, $printH);
            $artwork = Decimal::add($artwork, $area, Decimal::CALC_SCALE);
            $printArea = Decimal::add($printArea, $printed, Decimal::CALC_SCALE);
            $result['trace'][] = SignMath::trace(
                (string) $panel['panel_code'] . ' print size',
                'Panel plus bleed and overlap on each side',
                $width . ' x ' . $height . ' mm, bleed ' . $bleed . ' mm, overlap ' . $overlap . ' mm',
                Decimal::round($printW, 2) . ' x ' . Decimal::round($printH, 2) . ' mm'
            );
            $layout = $this->rolls->calculate(
                $roll,
                $printW,
                $printH,
                $qty,
                !empty($input['allow_rotation']),
                !empty($input['direction_sensitive'])
            );
            $layouts[] = $layout + ['panel' => (string) $panel['panel_code']];
        }
        $linear = '0';
        $consumed = '0';
        $graphic = '0';
        $fits = true;
        foreach ($layouts as $layout) {
            if (empty($layout['fits'])) {
                $fits = false;
                $result['warnings'][] = (string) $layout['panel'] . ' does not fit the selected ' . $roll . ' mm roll.';
            }
            $linear = Decimal::add($linear, (string) ($layout['linear_mm'] ?? '0'), Decimal::CALC_SCALE);
            $consumed = Decimal::add($consumed, (string) ($layout['consumed_area_m2'] ?? '0'), Decimal::CALC_SCALE);
            $graphic = Decimal::add($graphic, (string) ($layout['artwork_area_m2'] ?? '0'), Decimal::CALC_SCALE);
        }
        $metres = Decimal::div($linear, '1000', Decimal::CALC_SCALE);
        $result['trace'][] = SignMath::trace('Graphic area', 'Sum of selected panel areas', count($chosen) . ' panels', Decimal::round($artwork, 4) . ' m2');
        $result['trace'][] = SignMath::trace('Roll length', 'Sum of per-panel layouts on the selected roll', $roll . ' mm roll', Decimal::round($metres, 4) . ' m');
        $result['materials'][] = [
            'role' => 'VINYL',
            'description' => 'Printable wrap vinyl',
            'quantity' => Decimal::round($metres, 4),
            'unit' => 'm',
            'product_id' => (int) ($input['vinyl_product_id'] ?? 0) ?: null,
            'source' => 'ESTIMATOR',
        ];
        if (!empty($input['laminate'])) {
            $result['trace'][] = SignMath::trace(
                'Laminate',
                'Consumed roll area, not the graphic area alone',
                'Selected roll ' . $roll . ' mm',
                Decimal::round($consumed, 4) . ' m2'
            );
            $result['materials'][] = [
                'role' => 'LAMINATE',
                'description' => 'Laminate matched to the print layout',
                'quantity' => Decimal::round($consumed, 4),
                'unit' => 'm2',
                'product_id' => (int) ($input['laminate_product_id'] ?? 0) ?: null,
                'source' => 'ESTIMATOR',
            ];
        }
        $widths = (array) ($input['compare_widths'] ?? ['1050', '1370', '1520']);
        foreach ($widths as $width) {
            $optionLinear = '0';
            $optionConsumed = '0';
            $optionFits = true;
            foreach ($chosen as $panel) {
                $bleed = (string) ($panel['bleed_mm'] ?? '0');
                $overlap = (string) ($panel['overlap_mm'] ?? '0');
                $extra = Decimal::mul(Decimal::add($bleed, $overlap, Decimal::CALC_SCALE), '2', Decimal::CALC_SCALE);
                $layout = $this->rolls->calculate(
                    (string) $width,
                    Decimal::add((string) $panel['width_mm'], $extra, Decimal::CALC_SCALE),
                    Decimal::add((string) $panel['height_mm'], $extra, Decimal::CALC_SCALE),
                    $qty,
                    !empty($input['allow_rotation']),
                    !empty($input['direction_sensitive'])
                );
                if (empty($layout['fits'])) {
                    $optionFits = false;
                }
                $optionLinear = Decimal::add($optionLinear, (string) ($layout['linear_mm'] ?? '0'), Decimal::CALC_SCALE);
                $optionConsumed = Decimal::add($optionConsumed, (string) ($layout['consumed_area_m2'] ?? '0'), Decimal::CALC_SCALE);
            }
            $result['roll_options'][] = [
                'roll_width_mm' => (string) $width,
                'linear_metres' => Decimal::round(Decimal::div($optionLinear, '1000', Decimal::CALC_SCALE), 4),
                'consumed_area_m2' => Decimal::round($optionConsumed, 4),
                'fits' => $optionFits,
                'selected' => Decimal::cmp((string) $width, $roll) === 0,
            ];
        }
        $complexity = strtoupper((string) ($input['complexity'] ?? 'STANDARD'));
        if (!in_array($complexity, ['STANDARD', 'MODERATE', 'COMPLEX', 'SPECIALIST'], true)) {
            $complexity = 'STANDARD';
        }
        $hoursEach = SettingsService::get('wrap_hours_' . strtolower($complexity), '1.5');
        $factor = SettingsService::get('wrap_factor_' . strtolower($complexity), '1');
        $baseHours = Decimal::mul(Decimal::mul((string) count($chosen), $hoursEach, Decimal::CALC_SCALE), $qty, Decimal::CALC_SCALE);
        $removal = !empty($input['removal']) ? SettingsService::get('wrap_removal_hours', '1') : '0';
        $prep = (string) ($input['prep_hours'] ?? '0');
        if (!Decimal::isNumeric($prep) || Decimal::cmp($prep, '0') < 0) {
            $prep = '0';
        }
        $siteHours = Decimal::add(Decimal::add($baseHours, $removal, Decimal::CALC_SCALE), $prep, Decimal::CALC_SCALE);
        $result['trace'][] = SignMath::trace(
            'Install hours',
            'Panels x hours for ' . $complexity . ' from settings, plus removal and preparation',
            count($chosen) . ' x ' . $hoursEach . ' h, removal ' . $removal . ' h, preparation ' . $prep . ' h',
            Decimal::round($siteHours, 2) . ' h before the complexity factor'
        );
        $access = strtoupper((string) ($input['access_equipment'] ?? 'LADDER'));
        $accessKey = 'access_cost_' . strtolower($access);
        $equipment = SettingsService::get($accessKey, '0');
        $result['installation'] = [
            'installers' => (string) ($input['installers'] ?? '1'),
            'site_hours' => Decimal::round($siteHours, 4),
            'hourly_rate' => (string) ($input['hourly_rate'] ?? '0'),
            'access_multiplier' => $factor,
            'access_category' => $complexity,
            'equipment_cost' => $equipment,
            'distance_km' => (string) ($input['distance_km'] ?? '0'),
            'rate_per_km' => (string) ($input['rate_per_km'] ?? '0'),
            'height_category' => $access,
        ];
        $result['geometry'] = [
            'graphic_area_m2' => Decimal::round($artwork, 4),
            'print_area_m2' => Decimal::round($printArea, 4),
            'linear_metres' => Decimal::round($metres, 4),
            'consumed_area_m2' => Decimal::round($consumed, 4),
            'fits' => $fits,
            'measurement_source' => (string) ($input['measurement_source'] ?? 'MANUAL'),
        ];
        $result['yield'] = $result['geometry'];
        $result['route'] = ['PRINT', 'LAMINATE', 'CUT', 'INSTALL'];
        $result['customer_text'] = 'Vehicle branding, ' . count($chosen) . ' panels';
        $result['schematic'] = $this->schematic($chosen);
        if (($input['measurement_source'] ?? '') !== 'VERIFIED_TEMPLATE') {
            $result['reviews']['technical'] = true;
            $result['warnings'][] = 'These panel sizes are not from a verified template. Confirm them before production.';
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $panels
     */
    private function schematic(array $panels): string
    {
        $y = 10;
        $parts = [];
        foreach ($panels as $panel) {
            $w = max(20, (int) Decimal::round(Decimal::div((string) $panel['width_mm'], '10', 2), 0));
            $h = max(12, (int) Decimal::round(Decimal::div((string) $panel['height_mm'], '10', 2), 0));
            $parts[] = '<rect x="10" y="' . $y . '" width="' . $w . '" height="' . $h . '" fill="none" stroke="currentColor"/>';
            $parts[] = '<text x="14" y="' . ($y + 12) . '" font-size="10">' . htmlspecialchars((string) $panel['panel_code'], ENT_QUOTES, 'UTF-8') . '</text>';
            $y += $h + 8;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 ' . ($y + 24) . '"><title>' . htmlspecialchars(EngineeringLimits::SCHEMATIC, ENT_QUOTES, 'UTF-8') . '</title>' . implode('', $parts) . '</svg>';
    }
}

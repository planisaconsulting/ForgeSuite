<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * How many graphics fit across a roll, and how many linear metres that uses.
 *
 * Usable width is the roll minus the edge margin on both sides.
 * Across-roll count is floor((usable + spacing) / (graphic + spacing)).
 * Both orientations are compared when rotation is allowed and the product
 * is not direction sensitive. The lower linear length wins. A tie keeps the
 * original orientation. Side-by-side copies are counted. This is not a
 * claimed perfect nest.
 */
final class RollYieldService
{
    /**
     * @return array<string, mixed>
     */
    public function calculate(
        string $rollWidthMm,
        string $graphicWidthMm,
        string $graphicLengthMm,
        string $quantity,
        bool $allowRotation,
        bool $directionSensitive = false,
        string $horizontalSpacingMm = '0',
        string $verticalSpacingMm = '0',
        string $edgeMarginMm = '0'
    ): array {
        $rotate = $allowRotation && !$directionSensitive;
        $original = $this->orientation($rollWidthMm, $graphicWidthMm, $graphicLengthMm, $quantity, $horizontalSpacingMm, $verticalSpacingMm, $edgeMarginMm, false);
        $turned = $rotate
            ? $this->orientation($rollWidthMm, $graphicLengthMm, $graphicWidthMm, $quantity, $horizontalSpacingMm, $verticalSpacingMm, $edgeMarginMm, true)
            : null;
        $chosen = $original;
        if ($turned !== null && $turned['fits'] && (!$original['fits'] || Decimal::cmp($turned['linear_mm'], $original['linear_mm']) < 0)) {
            $chosen = $turned;
        }

        return $chosen + [
            'label' => 'BEST_LAYOUT_FOUND',
            'algorithm' => 'Across-the-roll grid. Spacing sits between graphics. Edge margin is removed from the width and from both ends of the length. Direction-sensitive material is not rotated.',
            'direction_sensitive' => $directionSensitive,
            'rotation_allowed' => $allowRotation,
            'alternative' => $turned,
        ];
    }

    /**
     * Compare roll widths. Nothing is chosen for the user.
     *
     * @param list<array{width_mm: string, unit_cost: string, label?: string, remaining_mm?: string}> $rolls
     * @return list<array<string, mixed>>
     */
    public function compare(
        array $rolls,
        string $graphicWidthMm,
        string $graphicLengthMm,
        string $quantity,
        bool $allowRotation,
        bool $directionSensitive = false,
        string $horizontalSpacingMm = '0',
        string $verticalSpacingMm = '0',
        string $edgeMarginMm = '0'
    ): array {
        $options = [];
        foreach ($rolls as $roll) {
            $layout = $this->calculate(
                (string) $roll['width_mm'],
                $graphicWidthMm,
                $graphicLengthMm,
                $quantity,
                $allowRotation,
                $directionSensitive,
                $horizontalSpacingMm,
                $verticalSpacingMm,
                $edgeMarginMm
            );
            $metres = Decimal::div((string) $layout['linear_mm'], '1000', Decimal::CALC_SCALE);
            $cost = Decimal::mul($metres, (string) ($roll['unit_cost'] ?? '0'), Decimal::CALC_SCALE);
            $remaining = (string) ($roll['remaining_mm'] ?? '');
            $remnant = null;
            if ($remaining !== '' && Decimal::isNumeric($remaining) && $layout['fits']) {
                $left = Decimal::sub($remaining, (string) $layout['linear_mm'], Decimal::CALC_SCALE);
                if (Decimal::cmp($left, '500') >= 0) {
                    $remnant = 'POTENTIAL REUSABLE REMNANT of ' . Decimal::round(Decimal::div($left, '1000', Decimal::CALC_SCALE), 2) . ' m. It is not added to inventory until production records it.';
                }
            }
            $options[] = [
                'label' => (string) ($roll['label'] ?? ($roll['width_mm'] . ' mm')),
                'roll_width_mm' => Decimal::round((string) $roll['width_mm'], 2),
                'linear_metres' => Decimal::round($metres, 4),
                'waste_percent' => $layout['waste_percent'],
                'material_cost' => Decimal::money($cost),
                'fits' => $layout['fits'],
                'across' => $layout['across'],
                'rotated' => $layout['rotated'],
                'consumed_area_m2' => $layout['consumed_area_m2'],
                'artwork_area_m2' => $layout['artwork_area_m2'],
                'remnant' => $remnant,
                'layout' => $layout,
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function orientation(
        string $rollWidth,
        string $acrossMm,
        string $alongMm,
        string $quantity,
        string $horizontalSpacing,
        string $verticalSpacing,
        string $edgeMargin,
        bool $rotated
    ): array {
        $spacingH = $this->nonNegative($horizontalSpacing);
        $spacingV = $this->nonNegative($verticalSpacing);
        $margin = $this->nonNegative($edgeMargin);
        $usable = Decimal::sub($rollWidth, Decimal::mul($margin, '2', Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $across = $this->acrossCount($usable, $acrossMm, $spacingH);
        $areas = new MaterialConsumptionService();
        $artwork = $areas->areaSquareMetres($acrossMm, $alongMm, $quantity);
        if ($across < 1 || Decimal::cmp($quantity, '0') <= 0 || Decimal::cmp($alongMm, '0') <= 0) {
            return $this->miss($rollWidth, $acrossMm, $alongMm, $rotated, $artwork);
        }
        $rowCount = $this->rows($quantity, $across);
        $linear = Decimal::add(
            Decimal::mul($margin, '2', Decimal::CALC_SCALE),
            Decimal::add(
                Decimal::mul((string) $rowCount, $alongMm, Decimal::CALC_SCALE),
                Decimal::mul((string) max(0, $rowCount - 1), $spacingV, Decimal::CALC_SCALE),
                Decimal::CALC_SCALE
            ),
            Decimal::CALC_SCALE
        );
        $consumed = $areas->areaSquareMetres($rollWidth, $linear, '1');
        $unusedWidth = Decimal::sub(
            $usable,
            Decimal::add(
                Decimal::mul((string) $across, $acrossMm, Decimal::CALC_SCALE),
                Decimal::mul((string) max(0, $across - 1), $spacingH, Decimal::CALC_SCALE),
                Decimal::CALC_SCALE
            ),
            Decimal::CALC_SCALE
        );
        $unusedArea = $areas->areaSquareMetres($unusedWidth, $linear, '1');
        $waste = Decimal::cmp($consumed, '0') > 0
            ? Decimal::round(Decimal::mul(Decimal::div(Decimal::sub($consumed, $artwork, Decimal::CALC_SCALE), $consumed, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE), 2)
            : null;

        return [
            'fits' => true,
            'rotated' => $rotated,
            'across' => $across,
            'rows' => $rowCount,
            'linear_mm' => Decimal::round($linear, 2),
            'linear_metres' => Decimal::round(Decimal::div($linear, '1000', Decimal::CALC_SCALE), 4),
            'artwork_area_m2' => Decimal::round($artwork, 4),
            'consumed_area_m2' => Decimal::round($consumed, 4),
            'unused_width_mm' => Decimal::round($unusedWidth, 2),
            'unused_width_area_m2' => Decimal::round($unusedArea, 4),
            'waste_percent' => $waste,
            'roll_width_mm' => Decimal::round($rollWidth, 2),
            'graphic_across_mm' => Decimal::round($acrossMm, 2),
            'graphic_along_mm' => Decimal::round($alongMm, 2),
        ];
    }

    private function acrossCount(string $usable, string $part, string $spacing): int
    {
        if (Decimal::cmp($part, '0') <= 0 || Decimal::cmp($usable, $part) < 0) {
            return 0;
        }
        $span = Decimal::add($usable, $spacing, Decimal::CALC_SCALE);
        $pitch = Decimal::add($part, $spacing, Decimal::CALC_SCALE);
        $bits = explode('.', Decimal::round(Decimal::div($span, $pitch, Decimal::CALC_SCALE), 4), 2);

        return (int) ($bits[0] === '' ? '0' : $bits[0]);
    }

    private function rows(string $quantity, int $across): int
    {
        $raw = Decimal::div($quantity, (string) $across, Decimal::CALC_SCALE);
        $bits = explode('.', Decimal::round($raw, 4), 2);
        $whole = $bits[0] === '' ? '0' : $bits[0];
        if (Decimal::cmp($raw, $whole) > 0) {
            return (int) $whole + 1;
        }

        return (int) $whole;
    }

    /**
     * @return array<string, mixed>
     */
    private function miss(string $rollWidth, string $acrossMm, string $alongMm, bool $rotated, string $artwork): array
    {
        return [
            'fits' => false,
            'rotated' => $rotated,
            'across' => 0,
            'rows' => 0,
            'linear_mm' => '0.00',
            'linear_metres' => '0.0000',
            'artwork_area_m2' => Decimal::round($artwork, 4),
            'consumed_area_m2' => '0.0000',
            'unused_width_mm' => Decimal::round($rollWidth, 2),
            'unused_width_area_m2' => '0.0000',
            'waste_percent' => null,
            'roll_width_mm' => Decimal::round($rollWidth, 2),
            'graphic_across_mm' => Decimal::round($acrossMm, 2),
            'graphic_along_mm' => Decimal::round($alongMm, 2),
        ];
    }

    private function nonNegative(string $value): string
    {
        if (!Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
            return '0';
        }

        return $value;
    }
}

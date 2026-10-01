<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Rectangular sheet fit.
 *
 * sheets() is the Phase 7 grid with no kerf and no edge margin. calculate()
 * is the Phase 9 grid: both orientations, kerf between parts, and a margin
 * inside the sheet. Identical parts use that grid. Mixed sizes use
 * RectangularNestingService. Neither claims a perfect layout.
 */
final class SheetYieldService
{
    /**
     * @return array{per_sheet: string, sheets: string, rotated: bool, fits: bool}
     */
    public function sheets(
        string $sheetWidth,
        string $sheetHeight,
        string $panelWidth,
        string $panelHeight,
        string $quantity,
        bool $allowRotation
    ): array {
        $straight = $this->fit($sheetWidth, $sheetHeight, $panelWidth, $panelHeight);
        $rotated = $allowRotation ? $this->fit($sheetWidth, $sheetHeight, $panelHeight, $panelWidth) : '0';
        $useRotated = Decimal::cmp($rotated, $straight) > 0;
        $per = $useRotated ? $rotated : $straight;
        if (Decimal::cmp($per, '0') <= 0) {
            return ['per_sheet' => '0', 'sheets' => '0', 'rotated' => false, 'fits' => false];
        }
        $sheets = $this->ceilDiv($quantity, $per);

        return [
            'per_sheet' => $per,
            'sheets' => $sheets,
            'rotated' => $useRotated,
            'fits' => true,
        ];
    }

    private function fit(string $sheetW, string $sheetH, string $panelW, string $panelH): string
    {
        if (Decimal::cmp($panelW, '0') <= 0 || Decimal::cmp($panelH, '0') <= 0) {
            return '0';
        }
        if (Decimal::cmp($panelW, $sheetW) > 0 || Decimal::cmp($panelH, $sheetH) > 0) {
            return '0';
        }
        $across = $this->whole(Decimal::div($sheetW, $panelW, Decimal::CALC_SCALE));
        $down = $this->whole(Decimal::div($sheetH, $panelH, Decimal::CALC_SCALE));

        return Decimal::mul($across, $down, 0);
    }

    private function whole(string $value): string
    {
        $bits = explode('.', Decimal::round($value, 4), 2);

        return $bits[0] === '' ? '0' : $bits[0];
    }

    private function ceilDiv(string $quantity, string $per): string
    {
        $raw = Decimal::div($quantity, $per, Decimal::CALC_SCALE);
        $whole = $this->whole($raw);
        if (Decimal::cmp($raw, $whole) > 0) {
            return Decimal::add($whole, '1', 0);
        }

        return $whole;
    }

    /**
     * Identical rectangular parts on a rectangular sheet.
     *
     * Usable size is the sheet minus the edge margin on every side.
     * Parts per side is floor((usable + kerf) / (part + kerf)), which leaves
     * a kerf gap between parts and none past the last part.
     * Both orientations are tried when rotation is allowed. The orientation
     * with more parts per sheet wins. A tie keeps the original orientation.
     * The result is the best grid found, not a proved optimum.
     *
     * @return array<string, mixed>
     */
    public function calculate(
        string $sheetWidth,
        string $sheetHeight,
        string $partWidth,
        string $partHeight,
        string $quantity,
        bool $allowRotation,
        string $kerfMm = '0',
        string $edgeMarginMm = '0'
    ): array {
        $kerf = $this->nonNegative($kerfMm);
        $margin = $this->nonNegative($edgeMarginMm);
        $usableW = Decimal::sub($sheetWidth, Decimal::mul($margin, '2', Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $usableH = Decimal::sub($sheetHeight, Decimal::mul($margin, '2', Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $straight = $this->pitched($usableW, $usableH, $partWidth, $partHeight, $kerf);
        $turned = $allowRotation ? $this->pitched($usableW, $usableH, $partHeight, $partWidth, $kerf) : ['per' => '0', 'across' => '0', 'down' => '0'];
        $useRotated = Decimal::cmp($turned['per'], $straight['per']) > 0;
        $chosen = $useRotated ? $turned : $straight;
        $orientationW = $useRotated ? $partHeight : $partWidth;
        $orientationH = $useRotated ? $partWidth : $partHeight;
        $areas = new MaterialConsumptionService();
        $used = $areas->areaSquareMetres($partWidth, $partHeight, $quantity);
        $sheetArea = $areas->areaSquareMetres($sheetWidth, $sheetHeight, '1');
        if (Decimal::cmp($chosen['per'], '0') <= 0 || Decimal::cmp($quantity, '0') <= 0) {
            return $this->emptyLayout($sheetWidth, $sheetHeight, $partWidth, $partHeight, $kerf, $margin, $usableW, $usableH, $used);
        }
        $sheets = $this->ceilDiv($quantity, $chosen['per']);
        $consumed = Decimal::mul($sheetArea, $sheets, Decimal::CALC_SCALE);
        $unused = Decimal::cmp($consumed, $used) > 0 ? Decimal::sub($consumed, $used, Decimal::CALC_SCALE) : '0';
        $waste = Decimal::cmp($consumed, '0') > 0
            ? Decimal::mul(Decimal::div($unused, $consumed, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE)
            : '0';

        return [
            'label' => 'BEST_LAYOUT_FOUND',
            'algorithm' => 'Identical-part grid. Kerf separates cuts. Edge margin is unused. Rotation is tested when allowed. Not a perfect layout.',
            'per_sheet' => $chosen['per'],
            'sheets' => $sheets,
            'rotated' => $useRotated,
            'fits' => true,
            'across' => (int) $chosen['across'],
            'down' => (int) $chosen['down'],
            'orientation_width_mm' => Decimal::round($orientationW, 2),
            'orientation_height_mm' => Decimal::round($orientationH, 2),
            'used_area_m2' => Decimal::round($used, 4),
            'consumed_area_m2' => Decimal::round($consumed, 4),
            'unused_area_m2' => Decimal::round($unused, 4),
            'waste_percent' => Decimal::round($waste, 2),
            'kerf_mm' => Decimal::round($kerf, 2),
            'edge_margin_mm' => Decimal::round($margin, 2),
            'usable_width_mm' => Decimal::round($usableW, 2),
            'usable_height_mm' => Decimal::round($usableH, 2),
            'part_width_mm' => Decimal::round($partWidth, 2),
            'part_height_mm' => Decimal::round($partHeight, 2),
            'sheet_width_mm' => Decimal::round($sheetWidth, 2),
            'sheet_height_mm' => Decimal::round($sheetHeight, 2),
            'remainders' => $this->remainders($usableW, $usableH, $orientationW, $orientationH, $kerf, $chosen['across'], $chosen['down']),
            'placements' => $this->placements($margin, $orientationW, $orientationH, $kerf, $chosen['across'], $chosen['down'], $quantity, $sheets),
        ];
    }

    /**
     * @param list<array{width_mm: string, height_mm: string, quantity: string, label?: string}> $parts
     * @return array<string, mixed>
     */
    public function nest(
        string $sheetWidth,
        string $sheetHeight,
        array $parts,
        bool $allowRotation,
        string $kerfMm = '0',
        string $edgeMarginMm = '0'
    ): array {
        return (new RectangularNestingService())->pack($sheetWidth, $sheetHeight, $parts, $allowRotation, $kerfMm, $edgeMarginMm);
    }

    /**
     * @return array{per: string, across: string, down: string}
     */
    private function pitched(string $usableW, string $usableH, string $partW, string $partH, string $kerf): array
    {
        $across = $this->fitsAcross($usableW, $partW, $kerf);
        $down = $this->fitsAcross($usableH, $partH, $kerf);
        if ($across === '0' || $down === '0') {
            return ['per' => '0', 'across' => '0', 'down' => '0'];
        }

        return ['per' => Decimal::mul($across, $down, 0), 'across' => $across, 'down' => $down];
    }

    private function fitsAcross(string $usable, string $part, string $kerf): string
    {
        if (Decimal::cmp($part, '0') <= 0 || Decimal::cmp($usable, $part) < 0) {
            return '0';
        }
        $span = Decimal::add($usable, $kerf, Decimal::CALC_SCALE);
        $pitch = Decimal::add($part, $kerf, Decimal::CALC_SCALE);

        return $this->whole(Decimal::div($span, $pitch, Decimal::CALC_SCALE));
    }

    private function nonNegative(string $value): string
    {
        if (!Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
            return '0';
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyLayout(
        string $sheetWidth,
        string $sheetHeight,
        string $partWidth,
        string $partHeight,
        string $kerf,
        string $margin,
        string $usableW,
        string $usableH,
        string $used
    ): array {
        return [
            'label' => 'BEST_LAYOUT_FOUND',
            'algorithm' => 'Identical-part grid. The part does not fit the usable sheet.',
            'per_sheet' => '0',
            'sheets' => '0',
            'rotated' => false,
            'fits' => false,
            'across' => 0,
            'down' => 0,
            'used_area_m2' => Decimal::round($used, 4),
            'consumed_area_m2' => '0.0000',
            'unused_area_m2' => '0.0000',
            'waste_percent' => null,
            'kerf_mm' => Decimal::round($kerf, 2),
            'edge_margin_mm' => Decimal::round($margin, 2),
            'usable_width_mm' => Decimal::round($usableW, 2),
            'usable_height_mm' => Decimal::round($usableH, 2),
            'part_width_mm' => Decimal::round($partWidth, 2),
            'part_height_mm' => Decimal::round($partHeight, 2),
            'sheet_width_mm' => Decimal::round($sheetWidth, 2),
            'sheet_height_mm' => Decimal::round($sheetHeight, 2),
            'remainders' => [],
            'placements' => [],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function remainders(string $usableW, string $usableH, string $partW, string $partH, string $kerf, string $across, string $down): array
    {
        $usedW = Decimal::sub(
            Decimal::add(Decimal::mul($across, $partW, Decimal::CALC_SCALE), Decimal::mul(Decimal::sub($across, '1', 0), $kerf, Decimal::CALC_SCALE), Decimal::CALC_SCALE),
            '0',
            Decimal::CALC_SCALE
        );
        $usedH = Decimal::add(
            Decimal::mul($down, $partH, Decimal::CALC_SCALE),
            Decimal::mul(Decimal::sub($down, '1', 0), $kerf, Decimal::CALC_SCALE),
            Decimal::CALC_SCALE
        );
        $rows = [];
        $side = Decimal::sub($usableW, $usedW, Decimal::CALC_SCALE);
        if (Decimal::cmp($side, '50') >= 0) {
            $rows[] = [
                'width_mm' => Decimal::round($side, 2),
                'height_mm' => Decimal::round($usedH, 2),
                'note' => 'POTENTIAL REUSABLE REMNANT beside the grid. Inventory is not created until production records the offcut.',
            ];
        }
        $below = Decimal::sub($usableH, $usedH, Decimal::CALC_SCALE);
        if (Decimal::cmp($below, '50') >= 0) {
            $rows[] = [
                'width_mm' => Decimal::round($usableW, 2),
                'height_mm' => Decimal::round($below, 2),
                'note' => 'POTENTIAL REUSABLE REMNANT below the grid. Inventory is not created until production records the offcut.',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placements(
        string $margin,
        string $partW,
        string $partH,
        string $kerf,
        string $across,
        string $down,
        string $quantity,
        string $sheets
    ): array {
        $placed = [];
        $remaining = $quantity;
        $sheetCount = (int) $sheets;
        $acrossN = (int) $across;
        $downN = (int) $down;
        for ($sheet = 1; $sheet <= $sheetCount && Decimal::cmp($remaining, '0') > 0; $sheet++) {
            for ($row = 0; $row < $downN && Decimal::cmp($remaining, '0') > 0; $row++) {
                for ($col = 0; $col < $acrossN && Decimal::cmp($remaining, '0') > 0; $col++) {
                    $x = Decimal::add($margin, Decimal::mul((string) $col, Decimal::add($partW, $kerf, Decimal::CALC_SCALE), Decimal::CALC_SCALE), Decimal::CALC_SCALE);
                    $y = Decimal::add($margin, Decimal::mul((string) $row, Decimal::add($partH, $kerf, Decimal::CALC_SCALE), Decimal::CALC_SCALE), Decimal::CALC_SCALE);
                    $placed[] = [
                        'sheet' => $sheet,
                        'x' => Decimal::round($x, 2),
                        'y' => Decimal::round($y, 2),
                        'width' => Decimal::round($partW, 2),
                        'height' => Decimal::round($partH, 2),
                    ];
                    $remaining = Decimal::sub($remaining, '1', 0);
                }
            }
        }

        return $placed;
    }
}

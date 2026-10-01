<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Shelf packing for a few rectangular parts.
 *
 * Parts are expanded, then sorted by the longer side, then by area.
 * Each part is placed on the first shelf it fits. A new shelf opens when
 * it does not. A new sheet opens when the next shelf would pass the margin.
 * Rotation, when allowed, tries the original orientation first and then the
 * quarter turn. Kerf is the gap between parts and between shelves.
 *
 * This is a deterministic first-fit shelf heuristic. The label is
 * BEST_LAYOUT_FOUND. It is not a perfect layout.
 */
final class RectangularNestingService
{
    private const PART_LIMIT = 80;

    /**
     * @param list<array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    public function pack(
        string $sheetWidth,
        string $sheetHeight,
        array $parts,
        bool $allowRotation,
        string $kerfMm = '0',
        string $edgeMarginMm = '0'
    ): array {
        $kerf = $this->nonNegative($kerfMm);
        $margin = $this->nonNegative($edgeMarginMm);
        $pieces = $this->expand($parts);
        if (isset($pieces['error'])) {
            return [
                'label' => 'BEST_LAYOUT_FOUND',
                'fits' => false,
                'error' => $pieces['error'],
                'sheets' => '0',
                'placements' => [],
                'unplaced' => [],
                'used_area_m2' => '0.0000',
                'consumed_area_m2' => '0.0000',
                'unused_area_m2' => '0.0000',
                'waste_percent' => null,
            ];
        }
        usort($pieces['rows'], static function (array $a, array $b): int {
            $long = Decimal::cmp($b['long'], $a['long']);
            if ($long !== 0) {
                return $long;
            }

            return Decimal::cmp($b['area'], $a['area']);
        });
        $usableRight = Decimal::sub($sheetWidth, $margin, Decimal::CALC_SCALE);
        $usableBottom = Decimal::sub($sheetHeight, $margin, Decimal::CALC_SCALE);
        $placements = [];
        $unplaced = [];
        $sheet = 1;
        $shelves = [];
        foreach ($pieces['rows'] as $piece) {
            $placed = $this->placeOnShelves($shelves, $piece, $allowRotation, $kerf, $margin, $usableRight, $usableBottom, $sheet);
            if ($placed === null) {
                $sheet++;
                $shelves = [];
                $placed = $this->placeOnShelves($shelves, $piece, $allowRotation, $kerf, $margin, $usableRight, $usableBottom, $sheet);
            }
            if ($placed === null) {
                $unplaced[] = $piece['label'];
                $sheet--;
                continue;
            }
            $placements[] = $placed['placement'];
            $shelves = $placed['shelves'];
        }
        $areas = new MaterialConsumptionService();
        $used = '0';
        foreach ($pieces['rows'] as $piece) {
            if (in_array($piece['label'], $unplaced, true)) {
                continue;
            }
            $used = Decimal::add($used, $areas->areaSquareMetres($piece['width'], $piece['height'], '1'), Decimal::CALC_SCALE);
        }
        $sheets = $placements === [] ? '0' : (string) max(array_column($placements, 'sheet'));
        $consumed = $areas->areaSquareMetres($sheetWidth, $sheetHeight, $sheets === '0' ? '0' : $sheets);
        $unused = Decimal::cmp($consumed, $used) > 0 ? Decimal::sub($consumed, $used, Decimal::CALC_SCALE) : '0';
        $waste = Decimal::cmp($consumed, '0') > 0
            ? Decimal::round(Decimal::mul(Decimal::div($unused, $consumed, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE), 2)
            : null;

        return [
            'label' => 'BEST_LAYOUT_FOUND',
            'algorithm' => 'First-fit shelf packing. Parts are sorted by the longer side. Rotation is tried only when allowed. This is the best layout this heuristic found, not a perfect layout.',
            'fits' => $unplaced === [] && $placements !== [],
            'error' => null,
            'sheets' => $sheets,
            'placements' => $placements,
            'unplaced' => $unplaced,
            'used_area_m2' => Decimal::round($used, 4),
            'consumed_area_m2' => Decimal::round($consumed, 4),
            'unused_area_m2' => Decimal::round($unused, 4),
            'waste_percent' => $waste,
            'sheet_width_mm' => Decimal::round($sheetWidth, 2),
            'sheet_height_mm' => Decimal::round($sheetHeight, 2),
        ];
    }

    /**
     * @param list<array<string, mixed>> $parts
     * @return array{rows: list<array<string, string>>, error?: string}
     */
    private function expand(array $parts): array
    {
        $rows = [];
        foreach ($parts as $part) {
            $qty = (int) ($part['quantity'] ?? 1);
            $width = (string) ($part['width_mm'] ?? '0');
            $height = (string) ($part['height_mm'] ?? '0');
            $label = (string) ($part['label'] ?? ($width . ' x ' . $height));
            if (!Decimal::isNumeric($width) || !Decimal::isNumeric($height) || $qty < 1) {
                return ['rows' => [], 'error' => 'Each part needs a width, a height, and a quantity.'];
            }
            for ($i = 0; $i < $qty; $i++) {
                if (count($rows) >= self::PART_LIMIT) {
                    return ['rows' => [], 'error' => 'Mixed nesting stops at ' . self::PART_LIMIT . ' parts. Use the identical-part grid for a long run.'];
                }
                $rows[] = [
                    'width' => $width,
                    'height' => $height,
                    'label' => $label,
                    'long' => Decimal::cmp($width, $height) >= 0 ? $width : $height,
                    'area' => Decimal::mul($width, $height, Decimal::CALC_SCALE),
                ];
            }
        }

        return ['rows' => $rows];
    }

    /**
     * @param list<array{y: string, height: string, x: string}> $shelves
     * @param array<string, string> $piece
     * @return array{placement: array<string, mixed>, shelves: list<array{y: string, height: string, x: string}>}|null
     */
    private function placeOnShelves(
        array $shelves,
        array $piece,
        bool $allowRotation,
        string $kerf,
        string $margin,
        string $usableRight,
        string $usableBottom,
        int $sheet
    ): ?array {
        $orientations = [['width' => $piece['width'], 'height' => $piece['height'], 'rotated' => false]];
        if ($allowRotation && Decimal::cmp($piece['width'], $piece['height']) !== 0) {
            $orientations[] = ['width' => $piece['height'], 'height' => $piece['width'], 'rotated' => true];
        }
        foreach ($shelves as $index => $shelf) {
            foreach ($orientations as $orientation) {
                if (Decimal::cmp($orientation['height'], $shelf['height']) > 0) {
                    continue;
                }
                $next = Decimal::add($shelf['x'], $orientation['width'], Decimal::CALC_SCALE);
                if (Decimal::cmp($next, $usableRight) > 0) {
                    continue;
                }
                $placement = $this->placement($sheet, $shelf['x'], $shelf['y'], $orientation);
                $shelves[$index]['x'] = Decimal::add($next, $kerf, Decimal::CALC_SCALE);

                return ['placement' => $placement, 'shelves' => $shelves];
            }
        }
        foreach ($orientations as $orientation) {
            $y = $shelves === [] ? $margin : Decimal::add($shelves[count($shelves) - 1]['y'], Decimal::add($shelves[count($shelves) - 1]['height'], $kerf, Decimal::CALC_SCALE), Decimal::CALC_SCALE);
            $bottom = Decimal::add($y, $orientation['height'], Decimal::CALC_SCALE);
            $right = Decimal::add($margin, $orientation['width'], Decimal::CALC_SCALE);
            if (Decimal::cmp($bottom, $usableBottom) > 0 || Decimal::cmp($right, $usableRight) > 0) {
                continue;
            }
            $shelves[] = ['y' => $y, 'height' => $orientation['height'], 'x' => Decimal::add($right, $kerf, Decimal::CALC_SCALE)];

            return ['placement' => $this->placement($sheet, $margin, $y, $orientation), 'shelves' => $shelves];
        }

        return null;
    }

    /**
     * @param array{width: string, height: string, rotated: bool} $orientation
     * @return array<string, mixed>
     */
    private function placement(int $sheet, string $x, string $y, array $orientation): array
    {
        return [
            'sheet' => $sheet,
            'x' => Decimal::round($x, 2),
            'y' => Decimal::round($y, 2),
            'width' => Decimal::round($orientation['width'], 2),
            'height' => Decimal::round($orientation['height'], 2),
            'rotated' => $orientation['rotated'],
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

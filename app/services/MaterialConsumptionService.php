<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Turns millimetre sizes into the quantities a price is based on.
 *
 * This class does not apply markup and it does not decide who pays for
 * offcuts. WasteCalculationService makes that decision. A later nesting
 * pass should start from pieces() and replace the single-size methods
 * without changing the quantity strings they already return.
 */
final class MaterialConsumptionService
{
    public function areaSquareMetres(string $widthMm, string $heightMm, string $quantity): string
    {
        $squareMm = Decimal::mul($widthMm, $heightMm, Decimal::CALC_SCALE);
        $one = Decimal::div($squareMm, '1000000', Decimal::CALC_SCALE);

        return Decimal::mul($one, $quantity, Decimal::CALC_SCALE);
    }

    public function linearMetres(string $lengthMm, string $quantity): string
    {
        $one = Decimal::div($lengthMm, '1000', Decimal::CALC_SCALE);

        return Decimal::mul($one, $quantity, Decimal::CALC_SCALE);
    }

    /**
     * Roll-width picture for one print size repeated $quantity times.
     *
     * Width utilisation is print width / roll width. It does not change when
     * the print gets longer. Quantity scales area, not the percentage.
     *
     * @return array<string, mixed>
     */
    public function roll(
        string $rollWidthMm,
        string $printWidthMm,
        string $printLengthMm,
        string $quantity,
        ?string $thresholdPercent
    ): array {
        $actual = $this->areaSquareMetres($printWidthMm, $printLengthMm, $quantity);
        $consumed = $this->areaSquareMetres($rollWidthMm, $printLengthMm, $quantity);
        $waste = Decimal::sub($consumed, $actual, Decimal::CALC_SCALE);
        $utilisation = Decimal::mul(
            Decimal::div($printWidthMm, $rollWidthMm, Decimal::CALC_SCALE),
            '100',
            Decimal::CALC_SCALE
        );
        $unused = Decimal::sub($rollWidthMm, $printWidthMm, 2);
        $widerThanRoll = Decimal::cmp($printWidthMm, $rollWidthMm) > 0;
        $warns = false;
        if (!$widerThanRoll && $thresholdPercent !== null && $thresholdPercent !== '' && Decimal::cmp($thresholdPercent, '0') > 0) {
            // Equal to the threshold does not warn. The threshold is a decision aid.
            $warns = Decimal::cmp($utilisation, $thresholdPercent) > 0;
        }

        return [
            'roll_width_mm' => Decimal::round($rollWidthMm, 2),
            'print_width_mm' => Decimal::round($printWidthMm, 2),
            'print_length_mm' => Decimal::round($printLengthMm, 2),
            'width_utilisation_percent' => $utilisation,
            'unused_width_mm' => $unused,
            'actual_area_m2' => $actual,
            'consumed_area_m2' => $consumed,
            'potential_waste_m2' => $widerThanRoll ? '0' : $waste,
            'wider_than_roll' => $widerThanRoll,
            'warns' => $warns,
        ];
    }

    /**
     * Sheet picture without a nesting optimiser.
     *
     * Pieces are placed on a plain grid. If the product allows rotation, both
     * orientations are tried and the one that fits more pieces is kept.
     * That is not a 2D nest: mixed sizes and awkward offcuts are ignored.
     *
     * @return array<string, mixed>
     */
    public function sheet(
        string $sheetWidthMm,
        string $sheetHeightMm,
        string $pieceWidthMm,
        string $pieceHeightMm,
        string $quantity,
        bool $allowRotation
    ): array {
        $actual = $this->areaSquareMetres($pieceWidthMm, $pieceHeightMm, $quantity);
        $sheetArea = $this->areaSquareMetres($sheetWidthMm, $sheetHeightMm, '1');
        $fit = $this->bestGrid($sheetWidthMm, $sheetHeightMm, $pieceWidthMm, $pieceHeightMm, $allowRotation);
        $perSheet = $fit['per_sheet'];
        if ($perSheet < 1 || Decimal::cmp($quantity, '0') <= 0) {
            $sheets = Decimal::cmp($quantity, '0') > 0 ? Decimal::ceil($quantity) : '0';
            $fits = false;
        } else {
            $fits = true;
            $sheets = Decimal::ceil(Decimal::div($quantity, (string) $perSheet, Decimal::CALC_SCALE));
        }
        $consumed = Decimal::mul($sheetArea, $sheets, Decimal::CALC_SCALE);
        $offcut = Decimal::cmp($consumed, $actual) > 0
            ? Decimal::sub($consumed, $actual, Decimal::CALC_SCALE)
            : '0';
        $utilisation = Decimal::cmp($consumed, '0') > 0
            ? Decimal::mul(Decimal::div($actual, $consumed, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE)
            : '0';

        return [
            'sheet_width_mm' => Decimal::round($sheetWidthMm, 2),
            'sheet_height_mm' => Decimal::round($sheetHeightMm, 2),
            'piece_width_mm' => Decimal::round($pieceWidthMm, 2),
            'piece_height_mm' => Decimal::round($pieceHeightMm, 2),
            'rotated' => $fit['rotated'],
            'across' => $fit['across'],
            'down' => $fit['down'],
            'pieces_per_sheet' => $perSheet,
            'fits_on_sheet' => $fits,
            'sheets' => $sheets,
            'actual_area_m2' => $actual,
            'sheet_area_m2' => $sheetArea,
            'consumed_area_m2' => $consumed,
            'offcut_area_m2' => $offcut,
            'utilisation_percent' => $utilisation,
        ];
    }

    /**
     * Reserved for a later nesting pass. Phase 1 does not place pieces.
     *
     * @param list<array{width_mm: string, height_mm: string, quantity: string}> $pieces
     * @return array<string, mixed>
     */
    public function nest(array $pieces, string $rollWidthMm): array
    {
        return [
            'implemented' => false,
            'roll_width_mm' => $rollWidthMm,
            'pieces' => $pieces,
            'placements' => [],
            'note' => 'Nesting is not calculated yet. Price each piece on its own.',
        ];
    }

    /**
     * @return array{per_sheet: int, across: int, down: int, rotated: bool}
     */
    private function bestGrid(
        string $sheetWidth,
        string $sheetHeight,
        string $pieceWidth,
        string $pieceHeight,
        bool $allowRotation
    ): array {
        $upright = $this->grid($sheetWidth, $sheetHeight, $pieceWidth, $pieceHeight);
        if (!$allowRotation) {
            return $upright + ['rotated' => false];
        }
        $turned = $this->grid($sheetWidth, $sheetHeight, $pieceHeight, $pieceWidth);
        if ($turned['per_sheet'] > $upright['per_sheet']) {
            return $turned + ['rotated' => true];
        }

        return $upright + ['rotated' => false];
    }

    /**
     * @return array{per_sheet: int, across: int, down: int}
     */
    private function grid(string $sheetWidth, string $sheetHeight, string $pieceWidth, string $pieceHeight): array
    {
        if (Decimal::cmp($pieceWidth, '0') <= 0 || Decimal::cmp($pieceHeight, '0') <= 0) {
            return ['per_sheet' => 0, 'across' => 0, 'down' => 0];
        }
        if (Decimal::cmp($pieceWidth, $sheetWidth) > 0 || Decimal::cmp($pieceHeight, $sheetHeight) > 0) {
            return ['per_sheet' => 0, 'across' => 0, 'down' => 0];
        }
        $across = (int) Decimal::floorDiv($sheetWidth, $pieceWidth);
        $down = (int) Decimal::floorDiv($sheetHeight, $pieceHeight);

        return [
            'per_sheet' => $across * $down,
            'across' => $across,
            'down' => $down,
        ];
    }
}

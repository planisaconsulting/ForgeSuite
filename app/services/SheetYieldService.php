<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Rectangular sheet fit. This is not a nesting optimiser.
 * The user can override the sheet count on the job.
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
}

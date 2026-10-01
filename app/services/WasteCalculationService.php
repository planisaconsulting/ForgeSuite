<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Separates the two kinds of waste a sign shop cares about.
 *
 * 1. Roll or sheet offcut. The operator chooses ACTUAL, CONSUMED_WIDTH,
 *    FULL_SHEET, or MANUAL. This class never makes that choice from the
 *    threshold. The threshold is only a warning on the roll picture.
 * 2. Manufacturing waste. standard_waste_percent is applied to whatever
 *    billable quantity the operator chose.
 *
 * Manual area replaces the whole line. Manual width uses the print length
 * (rolls) or the piece height (sheets) times quantity.
 */
final class WasteCalculationService
{
    public function costedQuantity(string $billable, string $standardWastePercent): string
    {
        $factor = Decimal::add('1', Decimal::div($standardWastePercent, '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE);

        return Decimal::mul($billable, $factor, Decimal::CALC_SCALE);
    }

    /**
     * @param array<string, mixed> $roll From MaterialConsumptionService::roll()
     * @return array{billable: string, mode: string, manual: bool, error: string|null}
     */
    public function billableRoll(array $roll, string $mode, ?string $manualWidthMm, ?string $manualArea): array
    {
        if (!empty($roll['wider_than_roll']) && $mode === 'CONSUMED_WIDTH') {
            return [
                'billable' => (string) $roll['actual_area_m2'],
                'mode' => 'ACTUAL',
                'manual' => false,
                'error' => 'The print is wider than the roll, so consumed-width charging does not apply. Actual area was used.',
            ];
        }

        if ($mode === 'CONSUMED_WIDTH') {
            return [
                'billable' => (string) $roll['consumed_area_m2'],
                'mode' => 'CONSUMED_WIDTH',
                'manual' => false,
                'error' => null,
            ];
        }

        if ($mode === 'MANUAL') {
            if ($manualArea !== null && $manualArea !== '') {
                return [
                    'billable' => Decimal::round($manualArea, Decimal::CALC_SCALE),
                    'mode' => 'MANUAL',
                    'manual' => true,
                    'error' => null,
                ];
            }
            if ($manualWidthMm !== null && $manualWidthMm !== '') {
                $length = (string) $roll['print_length_mm'];
                $billable = (new MaterialConsumptionService())->areaSquareMetres($manualWidthMm, $length, '1');
                // roll() already multiplied length by quantity inside the areas.
                // Manual width must do the same. print_length_mm is one piece,
                // so quantity has to be applied here. The roll result does not
                // return quantity, so the caller passes length for one piece
                // and we need quantity. See billableRollQuantity().
                return [
                    'billable' => $billable,
                    'mode' => 'MANUAL',
                    'manual' => true,
                    'error' => null,
                ];
            }

            return [
                'billable' => '0',
                'mode' => 'MANUAL',
                'manual' => true,
                'error' => 'Enter a manual width or a manual area.',
            ];
        }

        return [
            'billable' => (string) $roll['actual_area_m2'],
            'mode' => 'ACTUAL',
            'manual' => false,
            'error' => null,
        ];
    }

    /**
     * Manual roll width is one piece wide. Quantity still multiplies the length.
     *
     * @param array<string, mixed> $roll
     * @return array{billable: string, mode: string, manual: bool, error: string|null}
     */
    public function billableRollQuantity(
        array $roll,
        string $quantity,
        string $mode,
        ?string $manualWidthMm,
        ?string $manualArea
    ): array {
        $chosen = $this->billableRoll($roll, $mode, null, $manualArea);
        if ($mode !== 'MANUAL' || ($manualArea !== null && $manualArea !== '')) {
            return $chosen;
        }
        if ($manualWidthMm === null || $manualWidthMm === '') {
            return $chosen;
        }
        $billable = (new MaterialConsumptionService())->areaSquareMetres(
            $manualWidthMm,
            (string) $roll['print_length_mm'],
            $quantity
        );

        return [
            'billable' => $billable,
            'mode' => 'MANUAL',
            'manual' => true,
            'error' => null,
        ];
    }

    /**
     * Sheet cost is per sheet. Billable quantity here is a number of sheets,
     * including a fraction when the operator charges only the piece area.
     *
     * @param array<string, mixed> $sheet
     * @return array{billable_sheets: string, mode: string, manual: bool, error: string|null}
     */
    public function billableSheet(array $sheet, string $mode, ?string $manualArea, ?string $manualSheets): array
    {
        $sheetArea = (string) $sheet['sheet_area_m2'];
        if (Decimal::cmp($sheetArea, '0') <= 0) {
            return [
                'billable_sheets' => '0',
                'mode' => $mode,
                'manual' => false,
                'error' => 'The sheet size is missing, so it cannot be priced.',
            ];
        }

        if ($mode === 'FULL_SHEET') {
            return [
                'billable_sheets' => (string) $sheet['sheets'],
                'mode' => 'FULL_SHEET',
                'manual' => false,
                'error' => null,
            ];
        }

        if ($mode === 'MANUAL') {
            if ($manualSheets !== null && $manualSheets !== '') {
                return [
                    'billable_sheets' => Decimal::round($manualSheets, Decimal::CALC_SCALE),
                    'mode' => 'MANUAL',
                    'manual' => true,
                    'error' => null,
                ];
            }
            if ($manualArea !== null && $manualArea !== '') {
                $fraction = Decimal::div($manualArea, $sheetArea, Decimal::CALC_SCALE);

                return [
                    'billable_sheets' => $fraction,
                    'mode' => 'MANUAL',
                    'manual' => true,
                    'error' => null,
                ];
            }

            return [
                'billable_sheets' => '0',
                'mode' => 'MANUAL',
                'manual' => true,
                'error' => 'Enter a manual area or a manual sheet count.',
            ];
        }

        $fraction = Decimal::div((string) $sheet['actual_area_m2'], $sheetArea, Decimal::CALC_SCALE);

        return [
            'billable_sheets' => $fraction,
            'mode' => 'ACTUAL',
            'manual' => false,
            'error' => null,
        ];
    }
}

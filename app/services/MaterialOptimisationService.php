<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Collects sheet, roll, and offcut options.
 *
 * An offcut match is a suggestion. This service does not reserve or consume it.
 * Roll options are listed for the user to choose. The cheapest option is not applied.
 */
final class MaterialOptimisationService
{
    public function __construct(
        private readonly SheetYieldService $sheets = new SheetYieldService(),
        private readonly RollYieldService $rolls = new RollYieldService(),
        private readonly OffcutMatchingService $offcuts = new OffcutMatchingService(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $offcuts
     * @param list<array<string, mixed>> $rolls
     * @return array<string, mixed>
     */
    public function options(array $input, array $offcuts = [], array $rolls = []): array
    {
        $kind = strtoupper((string) ($input['kind'] ?? 'SHEET'));
        $rotation = !empty($input['allow_rotation']);
        $direction = !empty($input['direction_sensitive']);
        $sheet = null;
        $roll = null;
        $rollOptions = [];
        if ($kind === 'SHEET') {
            $sheet = $this->sheets->calculate(
                (string) ($input['sheet_width_mm'] ?? '0'),
                (string) ($input['sheet_height_mm'] ?? '0'),
                (string) ($input['part_width_mm'] ?? '0'),
                (string) ($input['part_height_mm'] ?? '0'),
                (string) ($input['quantity'] ?? '0'),
                $rotation && !$direction,
                (string) ($input['kerf_mm'] ?? '0'),
                (string) ($input['edge_margin_mm'] ?? '0')
            );
        } else {
            $roll = $this->rolls->calculate(
                (string) ($input['roll_width_mm'] ?? '0'),
                (string) ($input['part_width_mm'] ?? '0'),
                (string) ($input['part_height_mm'] ?? '0'),
                (string) ($input['quantity'] ?? '0'),
                $rotation,
                $direction,
                (string) ($input['horizontal_spacing_mm'] ?? '0'),
                (string) ($input['vertical_spacing_mm'] ?? '0'),
                (string) ($input['edge_margin_mm'] ?? '0')
            );
            if ($rolls !== []) {
                $rollOptions = $this->rolls->compare(
                    $rolls,
                    (string) ($input['part_width_mm'] ?? '0'),
                    (string) ($input['part_height_mm'] ?? '0'),
                    (string) ($input['quantity'] ?? '0'),
                    $rotation,
                    $direction,
                    (string) ($input['horizontal_spacing_mm'] ?? '0'),
                    (string) ($input['vertical_spacing_mm'] ?? '0'),
                    (string) ($input['edge_margin_mm'] ?? '0')
                );
            }
        }
        $matches = $this->offcuts->rank(
            (string) ($input['part_width_mm'] ?? '0'),
            (string) ($input['part_height_mm'] ?? '0'),
            $offcuts,
            $rotation,
            $direction,
            isset($input['location_id']) ? (int) $input['location_id'] : null
        );

        return [
            'sheet' => $sheet,
            'roll' => $roll,
            'roll_options' => $rollOptions,
            'offcuts' => $matches,
            'offcut_choice' => 'The user chooses USE OFFCUT or USE FULL SHEET. Nothing is consumed here.',
        ];
    }
}

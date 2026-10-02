<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Panel and simple frame quantities using SheetYieldService.
 * Offcuts are suggested later and are not consumed.
 */
final class PanelFrameEstimator
{
    public function __construct(private readonly SheetYieldService $sheets = new SheetYieldService())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $spec
     * @param array<string, mixed>|null $sheetProduct
     * @return array<string, mixed>
     */
    public function calculate(array $input, ?array $spec, ?array $sheetProduct): array
    {
        $result = SignMath::blank('PANEL_FRAME');
        $unit = (string) ($input['unit'] ?? 'mm');
        try {
            $width = SignMath::toMm((string) ($input['width'] ?? ''), $unit);
            $height = SignMath::toMm((string) ($input['height'] ?? ''), $unit);
            $qty = SignMath::quantity((string) ($input['quantity'] ?? '1'));
        } catch (\InvalidArgumentException $e) {
            $result['blocked'] = $e->getMessage();

            return $result;
        }
        $sheetW = (string) ($sheetProduct['sheet_width_mm'] ?? $input['sheet_width_mm'] ?? '0');
        $sheetH = (string) ($sheetProduct['sheet_height_mm'] ?? $input['sheet_height_mm'] ?? '0');
        if (Decimal::cmp($sheetW, '0') <= 0 || Decimal::cmp($sheetH, '0') <= 0) {
            $result['blocked'] = 'The sheet size is missing. It is not inferred from the product name.';

            return $result;
        }
        $rotate = $sheetProduct === null ? !empty($input['allow_rotation']) : (int) ($sheetProduct['allow_rotation'] ?? 0) === 1;
        $kerf = (string) ($sheetProduct['kerf_mm'] ?? $input['kerf_mm'] ?? '0');
        $margin = (string) ($sheetProduct['sheet_edge_margin_mm'] ?? $input['edge_margin_mm'] ?? '0');
        $yield = $this->sheets->calculate($sheetW, $sheetH, $width, $height, $qty, $rotate, $kerf, $margin);
        $result['yield'] = $yield;
        $result['trace'][] = SignMath::trace(
            'Panels per sheet',
            'SheetYieldService grid. Rotation follows the product. Kerf and edge margin come from the product.',
            $sheetW . ' x ' . $sheetH . ' sheet, ' . Decimal::round($width, 2) . ' x ' . Decimal::round($height, 2) . ' panel, quantity ' . $qty,
            (string) ($yield['per_sheet'] ?? '0') . ' per sheet, ' . (string) ($yield['sheets'] ?? '0') . ' sheets'
        );
        if (empty($yield['fits'])) {
            $result['blocked'] = 'The panel does not fit the sheet.';

            return $result;
        }
        $sheets = (string) $yield['sheets'];
        $result['materials'][] = [
            'role' => 'PANEL',
            'description' => (string) ($sheetProduct['name'] ?? 'Panel sheet'),
            'quantity' => $sheets,
            'unit' => 'sheet',
            'product_id' => (int) ($sheetProduct['id'] ?? $input['panel_product_id'] ?? 0) ?: null,
            'source' => 'ESTIMATOR',
        ];
        $graphics = strtoupper((string) ($input['graphics'] ?? 'NONE'));
        $result['trace'][] = SignMath::trace('Graphics', 'Recorded only. Vinyl quantity is a separate line when a product is chosen.', $graphics, $graphics);
        if ((int) ($input['graphic_product_id'] ?? 0) > 0) {
            $graphicArea = Decimal::mul(SignMath::areaM2($width, $height), $qty, Decimal::CALC_SCALE);
            $result['materials'][] = [
                'role' => 'GRAPHIC',
                'description' => 'Graphics ' . $graphics,
                'quantity' => Decimal::round($graphicArea, 4),
                'unit' => 'm2',
                'product_id' => (int) $input['graphic_product_id'],
                'source' => 'ESTIMATOR',
            ];
        }
        if (!empty($input['frame'])) {
            $perimeter = SignMath::mmToM(SignMath::rectPerimeterMm($width, $height));
            $braces = max(0, (int) ($input['brace_count'] ?? 0));
            $extra = Decimal::mul(SignMath::mmToM($width), (string) $braces, Decimal::CALC_SCALE);
            $frame = Decimal::mul(Decimal::add($perimeter, $extra, Decimal::CALC_SCALE), $qty, Decimal::CALC_SCALE);
            $result['trace'][] = SignMath::trace('Frame', 'Perimeter plus specification brace count, times quantity', Decimal::round($perimeter, 4) . ' m + ' . $braces . ' braces', Decimal::round($frame, 4) . ' m');
            $result['materials'][] = [
                'role' => 'FRAME',
                'description' => (string) ($spec['frame_profile'] ?? 'Frame'),
                'quantity' => Decimal::round($frame, 4),
                'unit' => 'm',
                'product_id' => (int) ($input['frame_product_id'] ?? 0) ?: null,
                'source' => 'SPECIFICATION',
            ];
        }
        $postLimit = $spec['max_post_height_mm'] ?? null;
        if ($postLimit !== null && $postLimit !== '' && Decimal::cmp($height, (string) $postLimit) > 0) {
            $result['reviews']['engineering'] = true;
            $result['outside'] = true;
            $result['warnings'][] = 'The panel height is above the post limit on this specification. ' . EngineeringLimits::STRUCTURAL;
        }
        $result['geometry'] = [
            'width_mm' => Decimal::round($width, 2),
            'height_mm' => Decimal::round($height, 2),
            'sheets' => $sheets,
            'per_sheet' => (string) ($yield['per_sheet'] ?? '0'),
            'waste_percent' => (string) ($yield['waste_percent'] ?? '0'),
        ];
        $result['route'] = ['CUT', 'FABRICATE', 'QC', 'INSTALL'];
        $result['customer_text'] = 'Panel ' . Decimal::round($width, 0) . ' x ' . Decimal::round($height, 0) . ' mm x ' . $qty;
        $result['schematic'] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 120"><title>' . htmlspecialchars(EngineeringLimits::SCHEMATIC, ENT_QUOTES, 'UTF-8') . '</title><rect x="20" y="20" width="180" height="80" fill="none" stroke="currentColor"/></svg>';

        return $result;
    }
}

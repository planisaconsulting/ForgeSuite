<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Face, return, trim, LED, and sheet quantities from parsed vector geometry.
 */
final class ChannelLetterEstimator
{
    public function __construct(
        private readonly SheetYieldService $sheets = new SheetYieldService(),
        private readonly ElectricalEstimateService $electrical = new ElectricalEstimateService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $led
     * @param array<string, mixed>|null $psu
     * @return array<string, mixed>
     */
    public function calculate(array $input, ?array $led, ?array $psu): array
    {
        $result = SignMath::blank('CHANNEL_LETTER');
        $svg = (string) ($input['svg'] ?? '');
        if (trim($svg) === '') {
            $result['blocked'] = 'Upload vector geometry. A photograph is not measured here.';

            return $result;
        }
        $geometry = SvgGeometry::analyse($svg);
        $result['warnings'] = array_merge($result['warnings'], $geometry['warnings'] ?? []);
        if (!$geometry['ok']) {
            $result['blocked'] = (string) $geometry['stopped'];

            return $result;
        }
        if ((int) $geometry['open_paths'] > 0 && Decimal::cmp((string) $geometry['area_mm2'], '0') <= 0) {
            $result['blocked'] = 'OPEN VECTOR PATH DETECTED. Face and return quantities are not calculated for an open path.';
            $result['geometry'] = $geometry;

            return $result;
        }
        $qty = SignMath::quantity((string) ($input['quantity'] ?? '1'));
        $allowance = (string) ($input['allowance_percent'] ?? '0');
        if (!Decimal::isNumeric($allowance) || Decimal::cmp($allowance, '0') < 0) {
            $result['blocked'] = 'The manufacturing allowance must be zero or more.';

            return $result;
        }
        $scale = '1';
        $nominal = trim((string) ($input['nominal_height_mm'] ?? ''));
        $bboxH = Decimal::sub((string) $geometry['bbox']['max_y'], (string) $geometry['bbox']['min_y'], Decimal::CALC_SCALE);
        if ($nominal !== '') {
            if (!Decimal::isNumeric($nominal) || Decimal::cmp($nominal, '0') <= 0 || Decimal::cmp($bboxH, '0') <= 0) {
                $result['blocked'] = 'Nominal height needs a closed bounding height.';

                return $result;
            }
            $scale = Decimal::div($nominal, $bboxH, Decimal::CALC_SCALE);
            $result['trace'][] = SignMath::trace('Scale', 'Nominal height / vector bounding height', $nominal . ' / ' . Decimal::round($bboxH, 2) . ' mm', Decimal::round($scale, 6));
        }
        $area = Decimal::mul((string) $geometry['area_m2'], Decimal::mul($scale, $scale, Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $perimeter = Decimal::mul((string) $geometry['perimeter_m'], $scale, Decimal::CALC_SCALE);
        $factor = Decimal::add('1', Decimal::div($allowance, '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $face = Decimal::mul(Decimal::mul($area, $factor, Decimal::CALC_SCALE), $qty, Decimal::CALC_SCALE);
        $returnM = Decimal::mul(Decimal::mul($perimeter, $factor, Decimal::CALC_SCALE), $qty, Decimal::CALC_SCALE);
        $depth = SignMath::toMm((string) ($input['return_depth'] ?? '0'), (string) ($input['return_unit'] ?? 'mm'));
        $depthM = SignMath::mmToM($depth);
        $strip = Decimal::mul($returnM, $depthM, Decimal::CALC_SCALE);
        $result['trace'][] = SignMath::trace('Vector area', 'Closed paths only. User units are millimetres, then scaled.', 'Parser version ' . SvgGeometry::VERSION, Decimal::round($area, 6) . ' m2');
        $result['trace'][] = SignMath::trace('Vector perimeter', 'Closed path length. Curves use 16 segments.', 'Scale ' . Decimal::round($scale, 6), Decimal::round($perimeter, 6) . ' m');
        $result['trace'][] = SignMath::trace('Face material', 'Vector area x allowance x quantity', Decimal::round($area, 6) . ' x ' . $allowance . '% x ' . $qty, Decimal::round($face, 4) . ' m2');
        $result['trace'][] = SignMath::trace('Return strip', 'Perimeter x allowance x quantity', Decimal::round($perimeter, 6) . ' m x ' . $allowance . '% x ' . $qty, Decimal::round($returnM, 4) . ' m');
        $result['materials'][] = $this->line('FACE', 'Face material', $face, 'm2', (int) ($input['face_product_id'] ?? 0));
        $result['materials'][] = $this->line('BACK', 'Back material', $face, 'm2', (int) ($input['back_product_id'] ?? 0));
        $result['materials'][] = $this->line('RETURN', 'Return material', $returnM, 'm', (int) ($input['return_product_id'] ?? 0));
        if (!empty($input['trim_cap'])) {
            $result['trace'][] = SignMath::trace('Trim cap', 'Same perimeter basis as the return, plus the allowance', Decimal::round($returnM, 4) . ' m', Decimal::round($returnM, 4) . ' m');
            $result['materials'][] = $this->line('TRIM', 'Trim cap', $returnM, 'm', (int) ($input['trim_product_id'] ?? 0));
        }
        $sheetW = (string) ($input['sheet_width_mm'] ?? '0');
        $sheetH = (string) ($input['sheet_height_mm'] ?? '0');
        if (Decimal::isNumeric($sheetW) && Decimal::cmp($sheetW, '0') > 0 && Decimal::isNumeric($sheetH) && Decimal::cmp($sheetH, '0') > 0) {
            $part = Decimal::round(Decimal::mul(self::sqrt($face), '1000', Decimal::CALC_SCALE), 2);
            $nest = $this->sheets->calculate($sheetW, $sheetH, $part, $part, '1', !empty($input['allow_rotation']), (string) ($input['kerf_mm'] ?? '0'), (string) ($input['edge_margin_mm'] ?? '0'));
            $result['yield'] = $nest;
            $result['trace'][] = SignMath::trace('Sheet yield', 'Square placeholder of the face area on the selected sheet. This is not a letter nest.', $part . ' mm square on ' . $sheetW . ' x ' . $sheetH, (string) ($nest['sheets'] ?? '0') . ' sheet');
            $result['warnings'][] = 'Sheet count uses a square of the same area. It is not a true letter nest.';
        }
        if (SignMath::yes($input['illuminated'] ?? null)) {
            if ($led === null) {
                $result['blocked'] = 'Choose an LED profile. Module spacing is not invented.';

                return $result;
            }
            $modules = $this->electrical->modules(Decimal::round(Decimal::mul($area, $qty, Decimal::CALC_SCALE), 6), $qty, $led);
            if (!$modules['ok']) {
                $result['blocked'] = (string) $modules['error'];

                return $result;
            }
            $result['trace'] = array_merge($result['trace'], $modules['trace']);
            $result['components'][] = [
                'role' => 'LED',
                'description' => (string) $led['name'],
                'quantity' => $modules['modules'],
                'unit' => 'unit',
                'product_id' => (int) ($led['product_id'] ?? 0) ?: null,
                'source' => 'ESTIMATOR',
            ];
            if ($psu === null) {
                $result['warnings'][] = 'No PSU profile was selected.';
                $result['reviews']['electrical'] = true;
            } else {
                $sized = $this->electrical->powerSupplies((string) $modules['load_w'], $psu);
                if (!$sized['ok']) {
                    $result['blocked'] = (string) $sized['error'];

                    return $result;
                }
                $result['trace'] = array_merge($result['trace'], $sized['trace']);
                $result['components'][] = [
                    'role' => 'PSU',
                    'description' => (string) $sized['suggestion'],
                    'quantity' => $sized['count'],
                    'unit' => 'unit',
                    'product_id' => (int) ($psu['product_id'] ?? 0) ?: null,
                    'source' => 'ESTIMATOR',
                ];
                $result['warnings'][] = (string) $sized['disclaimer'];
                $result['reviews']['electrical'] = true;
            }
        }
        $result['geometry'] = [
            'area_m2' => Decimal::round($area, 6),
            'perimeter_m' => Decimal::round($perimeter, 6),
            'face_m2' => Decimal::round($face, 4),
            'return_m' => Decimal::round($returnM, 4),
            'return_strip_m2' => Decimal::round($strip, 4),
            'preview_svg' => (string) $geometry['preview_svg'],
            'open_paths' => (int) $geometry['open_paths'],
        ];
        $result['schematic'] = (string) $geometry['preview_svg'];
        $result['route'] = ['CNC', 'FABRICATE', 'PAINT', 'ELECTRICAL', 'ASSEMBLE', 'QC', 'INSTALL'];
        $result['customer_text'] = 'Channel letters, ' . $qty . ' set';

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(string $role, string $description, string $quantity, string $unit, int $productId): array
    {
        return [
            'role' => $role,
            'description' => $description,
            'quantity' => Decimal::round($quantity, 4),
            'unit' => $unit,
            'product_id' => $productId > 0 ? $productId : null,
            'source' => 'ESTIMATOR',
        ];
    }

    private static function sqrt(string $value): string
    {
        if (Decimal::cmp($value, '0') <= 0) {
            return '0';
        }
        $guess = Decimal::div($value, '2', Decimal::CALC_SCALE);
        for ($n = 0; $n < 12; $n++) {
            $guess = Decimal::div(Decimal::add($guess, Decimal::div($value, $guess, Decimal::CALC_SCALE), Decimal::CALC_SCALE), '2', Decimal::CALC_SCALE);
        }

        return $guess;
    }
}

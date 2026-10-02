<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Rectangular lightbox manufacturing quantities.
 * Braces are used only when the caller supplies a count from an approved specification.
 */
final class LightboxEstimator
{
    public function __construct(private readonly ElectricalEstimateService $electrical = new ElectricalEstimateService())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $spec
     * @param array<string, mixed>|null $led
     * @param array<string, mixed>|null $psu
     * @return array<string, mixed>
     */
    public function calculate(array $input, ?array $spec, ?array $led, ?array $psu): array
    {
        $result = SignMath::blank('LIGHTBOX');
        $unit = (string) ($input['unit'] ?? 'mm');
        try {
            $width = SignMath::toMm((string) ($input['width'] ?? ''), $unit);
            $height = SignMath::toMm((string) ($input['height'] ?? ''), $unit);
            $depth = SignMath::toMm((string) ($input['depth'] ?? '0'), $unit);
            $qty = SignMath::quantity((string) ($input['quantity'] ?? '1'));
        } catch (\InvalidArgumentException $e) {
            $result['blocked'] = $e->getMessage();

            return $result;
        }
        if (Decimal::cmp($width, '0') <= 0 || Decimal::cmp($height, '0') <= 0 || Decimal::cmp($depth, '0') <= 0) {
            $result['blocked'] = 'Width, height, and depth must be greater than zero.';

            return $result;
        }
        $sides = (int) ($input['sides'] ?? 1);
        if ($sides !== 1 && $sides !== 2) {
            $result['blocked'] = 'Choose one or two faces.';

            return $result;
        }
        $area = SignMath::areaM2($width, $height);
        $faces = Decimal::mul(Decimal::mul($area, (string) $sides, Decimal::CALC_SCALE), $qty, Decimal::CALC_SCALE);
        $perimeterMm = SignMath::rectPerimeterMm($width, $height);
        $perimeterM = SignMath::mmToM($perimeterMm);
        $frame = Decimal::mul($perimeterM, $qty, Decimal::CALC_SCALE);
        $depthM = SignMath::mmToM($depth);
        $returns = Decimal::mul($frame, $depthM, Decimal::CALC_SCALE);
        $result['trace'][] = SignMath::trace('Face area', 'Width x height x faces x quantity', Decimal::round(SignMath::mmToM($width), 4) . ' m x ' . Decimal::round(SignMath::mmToM($height), 4) . ' m x ' . $sides . ' x ' . $qty, Decimal::round($faces, 4) . ' m2');
        $result['trace'][] = SignMath::trace('Frame perimeter', '2 x (width + height) x quantity', Decimal::round($width, 2) . ' x ' . Decimal::round($height, 2) . ' mm', Decimal::round($frame, 4) . ' m');
        $result['trace'][] = SignMath::trace('Returns', 'Perimeter x depth', Decimal::round($frame, 4) . ' m x ' . Decimal::round($depthM, 4) . ' m', Decimal::round($returns, 4) . ' m2');
        $braces = max(0, (int) ($input['brace_count'] ?? 0));
        $braceLinear = '0';
        if ($braces > 0) {
            $braceLinear = Decimal::mul(Decimal::mul(SignMath::mmToM($width), (string) $braces, Decimal::CALC_SCALE), $qty, Decimal::CALC_SCALE);
            $result['trace'][] = SignMath::trace('Braces', 'Count supplied by the specification, times the width', $braces . ' x ' . Decimal::round(SignMath::mmToM($width), 4) . ' m', Decimal::round($braceLinear, 4) . ' m');
        } else {
            $result['trace'][] = SignMath::trace('Braces', 'No brace count on this calculation', 'Approved specification did not supply a brace rule', '0 m');
        }
        $frameTotal = Decimal::add($frame, $braceLinear, Decimal::CALC_SCALE);
        $cut = [
            ['length_mm' => Decimal::round($width, 2), 'quantity' => (string) (2 * (int) SignMath::ceilWhole($qty))],
            ['length_mm' => Decimal::round($height, 2), 'quantity' => (string) (2 * (int) SignMath::ceilWhole($qty))],
        ];
        $result['materials'][] = ['role' => 'FRAME', 'description' => (string) ($spec['frame_profile'] ?? 'Frame profile'), 'quantity' => Decimal::round($frameTotal, 4), 'unit' => 'm', 'product_id' => (int) ($input['frame_product_id'] ?? 0) ?: null, 'source' => 'SPECIFICATION'];
        $result['materials'][] = ['role' => 'FACE', 'description' => (string) ($spec['face_material'] ?? 'Face'), 'quantity' => Decimal::round($faces, 4), 'unit' => 'm2', 'product_id' => (int) ($input['face_product_id'] ?? 0) ?: null, 'source' => 'SPECIFICATION'];
        $result['materials'][] = ['role' => 'RETURN', 'description' => (string) ($spec['return_material'] ?? 'Returns'), 'quantity' => Decimal::round($returns, 4), 'unit' => 'm2', 'product_id' => (int) ($input['return_product_id'] ?? 0) ?: null, 'source' => 'SPECIFICATION'];
        if ($spec !== null) {
            if ($spec['max_width_mm'] !== null && Decimal::cmp($width, (string) $spec['max_width_mm']) > 0) {
                $result['outside'] = true;
                $result['warnings'][] = 'Width is outside the specification limit.';
            }
            if ($spec['max_height_mm'] !== null && Decimal::cmp($height, (string) $spec['max_height_mm']) > 0) {
                $result['outside'] = true;
                $result['warnings'][] = 'Height is outside the specification limit.';
            }
        }
        if (SignMath::yes($input['illuminated'] ?? null)) {
            if ($led === null) {
                $result['blocked'] = 'Choose an LED profile.';

                return $result;
            }
            $modules = $this->electrical->modules(Decimal::round($faces, 6), $qty, $led);
            if (!$modules['ok']) {
                $result['blocked'] = (string) $modules['error'];

                return $result;
            }
            $result['trace'] = array_merge($result['trace'], $modules['trace']);
            $result['components'][] = ['role' => 'LED', 'description' => (string) $led['name'], 'quantity' => $modules['modules'], 'unit' => 'unit', 'product_id' => (int) ($led['product_id'] ?? 0) ?: null, 'source' => 'ESTIMATOR'];
            if ($psu !== null) {
                $sized = $this->electrical->powerSupplies((string) $modules['load_w'], $psu);
                if ($sized['ok']) {
                    $result['trace'] = array_merge($result['trace'], $sized['trace']);
                    $result['components'][] = ['role' => 'PSU', 'description' => (string) $sized['suggestion'], 'quantity' => $sized['count'], 'unit' => 'unit', 'product_id' => (int) ($psu['product_id'] ?? 0) ?: null, 'source' => 'ESTIMATOR'];
                    $result['warnings'][] = (string) $sized['disclaimer'];
                }
            }
            $result['reviews']['electrical'] = true;
        }
        $result['geometry'] = [
            'width_mm' => Decimal::round($width, 2),
            'height_mm' => Decimal::round($height, 2),
            'depth_mm' => Decimal::round($depth, 2),
            'sides' => $sides,
            'face_m2' => Decimal::round($faces, 4),
            'frame_m' => Decimal::round($frameTotal, 4),
            'return_m2' => Decimal::round($returns, 4),
            'cut_list' => $cut,
        ];
        $result['route'] = ['CUT', 'FABRICATE', 'PAINT', 'ELECTRICAL', 'ASSEMBLE', 'QC', 'PACK', 'INSTALL'];
        $result['customer_text'] = 'Lightbox ' . Decimal::round($width, 0) . ' x ' . Decimal::round($height, 0) . ' x ' . Decimal::round($depth, 0) . ' mm';
        $result['schematic'] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 120"><title>' . htmlspecialchars(EngineeringLimits::SCHEMATIC, ENT_QUOTES, 'UTF-8') . '</title><rect x="20" y="20" width="200" height="80" fill="none" stroke="currentColor"/><text x="28" y="64" font-size="12">' . htmlspecialchars(Decimal::round($width, 0) . ' x ' . Decimal::round($height, 0) . ' mm', ENT_QUOTES, 'UTF-8') . '</text></svg>';

        return $result;
    }
}

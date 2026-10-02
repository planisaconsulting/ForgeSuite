<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Manufacturing quantities for a pylon cabinet.
 * Foundation size is a typed allowance. It is not calculated from the sign size.
 */
final class PylonEstimator
{
    public function __construct(private readonly LightboxEstimator $box = new LightboxEstimator())
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
        $cabinet = $input;
        $cabinet['width'] = (string) ($input['cabinet_width'] ?? $input['width'] ?? '');
        $cabinet['height'] = (string) ($input['cabinet_height'] ?? $input['height'] ?? '');
        $cabinet['depth'] = (string) ($input['cabinet_depth'] ?? $input['depth'] ?? '200');
        $result = $this->box->calculate($cabinet, $spec, $led, $psu);
        $result['estimator_type'] = 'PYLON';
        if ($result['blocked'] !== null) {
            return $result;
        }
        $unit = (string) ($input['unit'] ?? 'mm');
        try {
            $overall = SignMath::toMm((string) ($input['overall_height'] ?? $input['height'] ?? '0'), $unit);
        } catch (\InvalidArgumentException $e) {
            $result['blocked'] = $e->getMessage();

            return $result;
        }
        $threshold = $spec['height_review_mm'] ?? null;
        $result['trace'][] = SignMath::trace('Overall height', 'Entered height. No wind or foundation size is derived from it.', Decimal::round($overall, 2) . ' mm', Decimal::round(SignMath::mmToM($overall), 4) . ' m');
        if ($threshold !== null && $threshold !== '' && Decimal::cmp($overall, (string) $threshold) > 0) {
            $result['reviews']['engineering'] = true;
            $result['outside'] = true;
            $result['warnings'][] = 'Height is above ' . $threshold . ' mm, the threshold on this specification. ' . EngineeringLimits::STRUCTURAL;
        }
        $foundation = trim((string) ($input['foundation_allowance'] ?? ''));
        if ($foundation !== '') {
            if (!Decimal::isNumeric($foundation) || Decimal::cmp($foundation, '0') < 0) {
                $result['blocked'] = 'Foundation allowance must be a number, or left blank.';

                return $result;
            }
            $result['materials'][] = [
                'role' => 'FOUNDATION',
                'description' => 'Manual foundation material allowance. Not a calculated footing.',
                'quantity' => '1',
                'unit' => 'allowance',
                'unit_cost' => Decimal::money($foundation),
                'product_id' => null,
                'source' => 'MANUAL',
            ];
            $result['trace'][] = SignMath::trace('Foundation', 'Manual allowance typed by the user', 'Not derived from sign dimensions', Decimal::money($foundation));
        }
        $result['warnings'][] = EngineeringLimits::STRUCTURAL;
        if ($spec !== null && (int) ($spec['engineering_review_required'] ?? 0) === 1) {
            $result['reviews']['engineering'] = true;
        }
        $result['customer_text'] = 'Pylon ' . Decimal::round($overall, 0) . ' mm overall';
        $result['route'] = ['CUT', 'FABRICATE', 'PAINT', 'ELECTRICAL', 'ASSEMBLE', 'QC', 'INSTALL'];

        return $result;
    }
}

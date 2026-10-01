<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Ranks offcuts that can physically hold a rectangle.
 *
 * A candidate fits only when both sides are large enough, in one orientation.
 * Area alone never qualifies an offcut. The score is deterministic:
 *
 *   score = 1000000000 - leftover area in square millimetres
 *         + 10000 when the stock location matches
 *         - 1 when the part must be rotated
 *
 * A higher score is a closer fit. The service does not consume stock.
 */
final class OffcutMatchingService
{
    /**
     * @param list<array<string, mixed>> $offcuts
     * @return list<array<string, mixed>>
     */
    public function rank(
        string $needWidth,
        string $needHeight,
        array $offcuts,
        bool $allowRotation,
        bool $directionSensitive = false,
        ?int $locationId = null
    ): array {
        $ranked = [];
        foreach ($offcuts as $offcut) {
            $fit = $this->fit(
                $needWidth,
                $needHeight,
                (string) ($offcut['width_mm'] ?? '0'),
                (string) ($offcut['height_mm'] ?? $offcut['length_mm'] ?? '0'),
                $allowRotation && !$directionSensitive
            );
            if ($fit === null) {
                continue;
            }
            $sameLocation = $locationId !== null && (int) ($offcut['stock_location_id'] ?? 0) === $locationId;
            $score = 1000000000 - (int) Decimal::round($fit['leftover_mm2'], 0);
            if ($sameLocation) {
                $score += 10000;
            }
            if ($fit['rotated']) {
                $score -= 1;
            }
            $ranked[] = [
                'inventory_item_id' => (int) ($offcut['id'] ?? 0),
                'code' => (string) ($offcut['inventory_code'] ?? ''),
                'width_mm' => Decimal::round((string) ($offcut['width_mm'] ?? '0'), 2),
                'height_mm' => Decimal::round((string) ($offcut['height_mm'] ?? $offcut['length_mm'] ?? '0'), 2),
                'rotated' => $fit['rotated'],
                'leftover_mm2' => Decimal::round($fit['leftover_mm2'], 2),
                'same_location' => $sameLocation,
                'score' => $score,
                'label' => 'OFFCUT MATCH FOUND',
            ];
        }
        usort($ranked, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'];
        });

        return $ranked;
    }

    /**
     * @return array{rotated: bool, leftover_mm2: string}|null
     */
    public function fit(string $needW, string $needH, string $offW, string $offH, bool $allowRotation): ?array
    {
        if (!$this->positive($needW) || !$this->positive($needH) || !$this->positive($offW) || !$this->positive($offH)) {
            return null;
        }
        $straight = Decimal::cmp($offW, $needW) >= 0 && Decimal::cmp($offH, $needH) >= 0;
        $turned = $allowRotation && Decimal::cmp($offW, $needH) >= 0 && Decimal::cmp($offH, $needW) >= 0;
        if (!$straight && !$turned) {
            return null;
        }
        $part = Decimal::mul($needW, $needH, Decimal::CALC_SCALE);
        $off = Decimal::mul($offW, $offH, Decimal::CALC_SCALE);

        return [
            'rotated' => !$straight && $turned,
            'leftover_mm2' => Decimal::sub($off, $part, Decimal::CALC_SCALE),
        ];
    }

    private function positive(string $value): bool
    {
        return Decimal::isNumeric($value) && Decimal::cmp($value, '0') > 0;
    }
}

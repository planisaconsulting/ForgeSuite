<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Installation cost as separate components.
 *
 * Labour, travel distance, travel time, equipment, accommodation, and
 * subcontract are not added into one hidden number. An access multiplier
 * is applied only to site labour and is returned with the result.
 * Distance is entered or taken from a stored site. There is no live map.
 */
final class InstallationEstimateService
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function estimate(array $input): array
    {
        $installers = $this->whole($input['installers'] ?? '1', 1);
        $siteHours = $this->number($input['site_hours'] ?? '0');
        $hourly = $this->number($input['hourly_rate'] ?? '0');
        $multiplier = $this->number($input['access_multiplier'] ?? '1');
        if (Decimal::cmp($multiplier, '0') <= 0) {
            $multiplier = '1';
        }
        $distance = $this->number($input['distance_km'] ?? '0');
        $trips = $this->whole($input['trips'] ?? '1', 1);
        $rateKm = $this->number($input['rate_per_km'] ?? '0');
        $travelHours = $this->number($input['travel_hours'] ?? '0');
        $equipment = $this->number($input['equipment_cost'] ?? '0');
        $nights = $this->whole($input['nights'] ?? '0', 0);
        $people = $this->whole($input['accommodation_people'] ?? $installers, 0);
        $nightCost = $this->number($input['cost_per_night'] ?? '0');
        $subcontract = $this->number($input['subcontract_cost'] ?? '0');
        $other = $this->number($input['other_cost'] ?? '0');

        $labour = Decimal::mul(
            Decimal::mul((string) $installers, $siteHours, Decimal::CALC_SCALE),
            Decimal::mul($hourly, $multiplier, Decimal::CALC_SCALE),
            Decimal::CALC_SCALE
        );
        $travel = Decimal::mul(Decimal::mul($distance, (string) $trips, Decimal::CALC_SCALE), $rateKm, Decimal::CALC_SCALE);
        $travelTime = Decimal::mul(
            Decimal::mul((string) $installers, $travelHours, Decimal::CALC_SCALE),
            $hourly,
            Decimal::CALC_SCALE
        );
        $accommodation = Decimal::mul(Decimal::mul((string) $nights, (string) $people, Decimal::CALC_SCALE), $nightCost, Decimal::CALC_SCALE);
        $components = [
            $this->line('LABOUR', 'Site labour', $labour, $installers . ' installers x ' . $siteHours . ' h x ' . $hourly . ' x access ' . $multiplier),
            $this->line('TRAVEL', 'Travel distance', $travel, $distance . ' km x ' . $trips . ' trips x ' . $rateKm . ' per km'),
            $this->line('TRAVEL', 'Travel time', $travelTime, $installers . ' installers x ' . $travelHours . ' h x ' . $hourly),
            $this->line('INSTALLATION', 'Equipment', $equipment, (string) ($input['height_category'] ?? 'Height equipment')),
            $this->line('INSTALLATION', 'Accommodation', $accommodation, $nights . ' nights x ' . $people . ' people x ' . $nightCost),
            $this->line('SUBCONTRACT', 'Subcontract', $subcontract, 'Subcontract snapshot'),
            $this->line('OTHER', 'Other', $other, 'Other installation cost'),
        ];
        $total = '0';
        foreach ($components as $component) {
            $total = Decimal::add($total, $component['estimated_cost'], Decimal::CALC_SCALE);
        }

        return [
            'access_category' => (string) ($input['access_category'] ?? 'STANDARD'),
            'access_multiplier' => Decimal::round($multiplier, 2),
            'height_category' => (string) ($input['height_category'] ?? 'GROUND'),
            'trips' => $trips,
            'components' => $components,
            'total_cost' => Decimal::money($total),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(string $type, string $description, string $cost, string $detail): array
    {
        return [
            'component_type' => $type,
            'description' => $description,
            'estimated_cost' => Decimal::money($cost),
            'detail' => $detail,
        ];
    }

    private function number(mixed $value): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '' || !Decimal::isNumeric($text) || Decimal::cmp($text, '0') < 0) {
            return '0';
        }

        return $text;
    }

    private function whole(mixed $value, int $fallback): int
    {
        $text = trim((string) $value);
        if ($text === '' || !ctype_digit($text)) {
            return $fallback;
        }

        return (int) $text;
    }
}

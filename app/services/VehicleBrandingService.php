<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Vehicle branding from measured areas. There is no external vehicle library.
 *
 * Design setup is charged once for a fleet unless setup_each is set.
 * Application and the other site steps are charged per vehicle.
 * Removal hours are explicit: none 0, light 0.5, moderate 1.5, heavy 3,
 * or the hours the user typed.
 */
final class VehicleBrandingService
{
    /** @var array<string, string> */
    public const REMOVAL_HOURS = [
        'NONE' => '0',
        'LIGHT' => '0.5',
        'MODERATE' => '1.5',
        'HEAVY' => '3',
    ];

    /** @var list<string> */
    public const CATEGORIES = [
        'DOOR_BRANDING',
        'PARTIAL_WRAP',
        'FULL_WRAP',
        'FLEET_BRANDING',
        'REFLECTIVE',
        'MAGNETICS',
        'WINDOW_GRAPHICS',
        'CUSTOM',
    ];

    /** @var list<string> */
    public const AREAS = ['left_side', 'right_side', 'bonnet', 'tailgate', 'roof', 'canopy', 'windows', 'other'];

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function estimate(array $input): array
    {
        $quantity = max(1, (int) ($input['vehicle_quantity'] ?? 1));
        $setupEach = !empty($input['setup_each']);
        $area = '0';
        $areas = [];
        foreach (self::AREAS as $key) {
            $value = $this->number($input[$key] ?? '0');
            $areas[$key] = Decimal::round($value, 4);
            $area = Decimal::add($area, $value, Decimal::CALC_SCALE);
        }
        $waste = $this->number($input['waste_percent'] ?? '0');
        $physical = Decimal::mul($area, Decimal::add('1', Decimal::div($waste, '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $fleetArea = Decimal::mul($area, (string) $quantity, Decimal::CALC_SCALE);
        $fleetPhysical = Decimal::mul($physical, (string) $quantity, Decimal::CALC_SCALE);
        $vinylRate = $this->number($input['vinyl_cost_m2'] ?? '0');
        $laminateRate = $this->number($input['laminate_cost_m2'] ?? '0');
        $tapeRate = $this->number($input['tape_cost_m2'] ?? '0');
        $reflectiveRate = $this->number($input['reflective_cost_m2'] ?? '0');
        $removalConsumable = $this->number($input['removal_consumable_cost'] ?? '0');
        $cleaning = $this->number($input['cleaning_cost'] ?? '0');
        $hourly = $this->number($input['hourly_rate'] ?? '0');
        $design = $this->number($input['design_hours'] ?? '0');
        $designHours = $setupEach ? Decimal::mul($design, (string) $quantity, Decimal::CALC_SCALE) : $design;
        $removal = $this->removalHours($input);
        $steps = [
            'preparation' => $this->number($input['preparation_hours'] ?? '0'),
            'removal' => $removal,
            'printing' => $this->number($input['printing_hours'] ?? '0'),
            'lamination' => $this->number($input['lamination_hours'] ?? '0'),
            'application' => $this->number($input['application_hours'] ?? '0'),
            'finishing' => $this->number($input['finishing_hours'] ?? '0'),
            'qc' => $this->number($input['qc_hours'] ?? '0'),
        ];
        $perVehicle = '0';
        foreach ($steps as $hours) {
            $perVehicle = Decimal::add($perVehicle, $hours, Decimal::CALC_SCALE);
        }
        $vehicleHours = Decimal::mul($perVehicle, (string) $quantity, Decimal::CALC_SCALE);
        $components = [
            $this->material('Print vinyl', $fleetPhysical, $vinylRate, 'Billable area ' . Decimal::round($fleetArea, 4) . ' m2. Physical with waste ' . Decimal::round($fleetPhysical, 4) . ' m2.'),
            $this->material('Laminate', $fleetPhysical, $laminateRate, 'Same physical area as the print vinyl.'),
            $this->material('Application tape', $fleetPhysical, $tapeRate, 'Included only when a rate is entered.'),
            $this->material('Reflective vinyl', $fleetPhysical, $reflectiveRate, 'Included only when a rate is entered.'),
            $this->line('MATERIAL', 'Removal consumables', Decimal::mul($removalConsumable, (string) $quantity, Decimal::CALC_SCALE), 'Per vehicle'),
            $this->line('MATERIAL', 'Cleaning consumables', Decimal::mul($cleaning, (string) $quantity, Decimal::CALC_SCALE), 'Per vehicle'),
            $this->line('LABOUR', 'Design setup', Decimal::mul($designHours, $hourly, Decimal::CALC_SCALE), $setupEach ? 'Charged on every vehicle.' : 'Charged once for the fleet.'),
        ];
        foreach ($steps as $name => $hours) {
            $totalHours = Decimal::mul($hours, (string) $quantity, Decimal::CALC_SCALE);
            $components[] = $this->line('LABOUR', ucfirst($name), Decimal::mul($totalHours, $hourly, Decimal::CALC_SCALE), $hours . ' h x ' . $quantity . ' vehicles');
        }
        $total = '0';
        foreach ($components as $component) {
            $total = Decimal::add($total, $component['estimated_cost'], Decimal::CALC_SCALE);
        }

        return [
            'category' => (string) ($input['category'] ?? 'CUSTOM'),
            'vehicle_quantity' => $quantity,
            'billable_area_each_m2' => Decimal::round($area, 4),
            'estimated_physical_each_m2' => Decimal::round($physical, 4),
            'billable_area_m2' => Decimal::round($fleetArea, 4),
            'estimated_physical_m2' => Decimal::round($fleetPhysical, 4),
            'design_hours' => Decimal::round($designHours, 2),
            'per_vehicle_hours' => Decimal::round($perVehicle, 2),
            'areas' => $areas,
            'components' => $components,
            'total_cost' => Decimal::money($total),
            'setup_each' => $setupEach,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function removalHours(array $input): string
    {
        $level = strtoupper(trim((string) ($input['removal_level'] ?? 'NONE')));
        if ($level === 'MANUAL') {
            return $this->number($input['removal_hours'] ?? '0');
        }

        return self::REMOVAL_HOURS[$level] ?? '0';
    }

    /**
     * @return array<string, mixed>
     */
    private function material(string $description, string $quantity, string $rate, string $detail): array
    {
        return $this->line('MATERIAL', $description, Decimal::mul($quantity, $rate, Decimal::CALC_SCALE), $detail);
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
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\OperationsRepository;
use App\Repositories\PlanningRepository;
use App\Repositories\UserRepository;

/**
 * Travel cost uses the rate stored on the usage row.
 *
 * The same trip is posted to job other costs once. A second save of the same
 * usage does not add the cost again. Machine hourly cost is separate and stays
 * off the job while machine_cost_in_job is 0, because recipe labour may
 * already include the machine.
 */
final class VehicleService
{
    public function __construct(
        private readonly PlanningRepository $planning = new PlanningRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly JobCostingService $costing = new JobCostingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function recordUsage(array $input, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'vehicles.manage') && !AuthorizationService::allows($actor, 'installations.complete')) {
            return ['errors' => ['_form' => 'You cannot record vehicle mileage.'], 'id' => null];
        }
        $resourceId = (int) ($input['vehicle_resource_id'] ?? 0);
        $vehicle = $this->planning->vehicle($resourceId);
        if ($vehicle === null) {
            return ['errors' => ['vehicle_resource_id' => 'Choose a vehicle.'], 'id' => null];
        }
        $start = trim((string) ($input['start_odometer'] ?? ''));
        $end = trim((string) ($input['end_odometer'] ?? ''));
        if (!Decimal::isNumeric($start) || !Decimal::isNumeric($end) || Decimal::cmp($end, $start) < 0) {
            return ['errors' => ['end_odometer' => 'The ending reading must be the same or higher than the start.'], 'id' => null];
        }
        $distance = Decimal::round(Decimal::sub($end, $start), 1);
        $rate = Decimal::round((string) SettingsService::get('travel_rate_per_km', '0'), 4);
        $cost = Decimal::money(Decimal::mul($distance, $rate));
        $jobId = (int) ($input['job_id'] ?? 0);
        $usageId = 0;
        Database::transaction(function () use ($resourceId, $jobId, $userId, $start, $end, $distance, $rate, $cost, $input, &$usageId): void {
            $usageId = $this->planning->insertUsage([
                'vehicle_resource_id' => $resourceId,
                'job_id' => $jobId > 0 ? $jobId : null,
                'user_id' => $userId,
                'start_odometer' => Decimal::round($start, 1),
                'end_odometer' => Decimal::round($end, 1),
                'distance_km' => $distance,
                'rate_per_km_snapshot' => $rate,
                'travel_cost' => $cost,
                'other_cost_id' => null,
                'usage_date' => date('Y-m-d'),
                'notes' => $this->blank($input['notes'] ?? null),
            ]);
            $this->planning->setOdometer($resourceId, Decimal::round($end, 1));
            if ($jobId > 0 && Decimal::cmp($cost, '0') > 0) {
                $reference = 'VEHICLE-USAGE-' . $usageId;
                if ($this->ops->otherCostByReference($jobId, $reference) === null) {
                    $otherId = $this->ops->insertOther([
                        'job_id' => $jobId,
                        'cost_type' => 'TRAVEL',
                        'description' => 'Vehicle travel ' . $distance . ' km',
                        'supplier_id' => null,
                        'quantity' => $distance,
                        'unit_cost' => Decimal::money($rate),
                        'total_cost' => $cost,
                        'reference' => $reference,
                        'created_by' => $userId,
                    ]);
                    $this->planning->attachUsageCost($usageId, $otherId);
                    $this->costing->refresh($jobId);
                }
            }
            $this->audit->record('vehicle', $resourceId, 'MILEAGE_RECORDED', null, [
                'usage_id' => $usageId,
                'distance_km' => $distance,
                'travel_cost' => $cost,
            ], $userId);
        });

        return ['errors' => [], 'id' => $usageId];
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 255);
    }
}

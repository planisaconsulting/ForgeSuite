<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\PlanningRepository;
use App\Repositories\UserRepository;

/**
 * Maintenance removes the resource from the schedule for the downtime window.
 */
final class MaintenanceService
{
    /** @var list<string> */
    public const TYPES = ['SERVICE', 'REPAIR', 'INSPECTION', 'CALIBRATION', 'CLEANING', 'OTHER'];

    public function __construct(
        private readonly PlanningRepository $planning = new PlanningRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function schedule(array $input, int $userId): array
    {
        if (!AuthorizationService::allows((new UserRepository())->find($userId), 'maintenance.manage')) {
            return ['errors' => ['_form' => 'You cannot record maintenance.'], 'id' => null];
        }
        $resourceId = (int) ($input['resource_id'] ?? 0);
        $resource = $this->planning->resource($resourceId);
        if ($resource === null) {
            return ['errors' => ['resource_id' => 'Choose a machine or vehicle.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['maintenance_type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) {
            return ['errors' => ['maintenance_type' => 'Choose a maintenance type.'], 'id' => null];
        }
        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            return ['errors' => ['description' => 'Describe the work.'], 'id' => null];
        }
        $start = strtotime(str_replace('T', ' ', (string) ($input['downtime_start'] ?? '')));
        $end = strtotime(str_replace('T', ' ', (string) ($input['downtime_end'] ?? '')));
        if ($start === false || $end === false || $end <= $start) {
            return ['errors' => ['downtime_start' => 'Enter the downtime start and end.'], 'id' => null];
        }
        $cost = trim((string) ($input['cost'] ?? '0'));
        if (!Decimal::isNumeric($cost) || Decimal::cmp($cost, '0') < 0) {
            return ['errors' => ['cost' => 'Enter a cost of zero or more.'], 'id' => null];
        }
        $id = 0;
        Database::transaction(function () use ($input, $userId, $resourceId, $type, $description, $start, $end, $cost, &$id): void {
            $id = $this->planning->insertMaintenance([
                'resource_id' => $resourceId,
                'maintenance_type' => $type,
                'scheduled_date' => date('Y-m-d', $start),
                'completed_date' => null,
                'downtime_start' => date('Y-m-d H:i:s', $start),
                'downtime_end' => date('Y-m-d H:i:s', $end),
                'description' => mb_substr($description, 0, 255),
                'supplier_id' => ((int) ($input['supplier_id'] ?? 0)) > 0 ? (int) $input['supplier_id'] : null,
                'cost' => Decimal::money($cost),
                'meter_reading' => $this->nullableNumber($input['meter_reading'] ?? null),
                'status' => 'PLANNED',
                'notes' => $this->blank($input['notes'] ?? null),
                'created_by' => $userId,
            ]);
            $this->planning->insertDowntime([
                'resource_id' => $resourceId,
                'maintenance_record_id' => $id,
                'started_at' => date('Y-m-d H:i:s', $start),
                'ended_at' => date('Y-m-d H:i:s', $end),
                'reason' => $type . ': ' . $description,
                'created_by' => $userId,
            ]);
            $this->planning->insertUnavailability([
                'resource_id' => $resourceId,
                'start_datetime' => date('Y-m-d H:i:s', $start),
                'end_datetime' => date('Y-m-d H:i:s', $end),
                'reason_type' => 'MAINTENANCE',
                'description' => $description,
                'status' => 'ACTIVE',
                'created_by' => $userId,
            ]);
            $this->audit->record('maintenance', $id, 'MAINTENANCE_SCHEDULED', null, [
                'resource_id' => $resourceId,
                'start' => date('Y-m-d H:i:s', $start),
            ], $userId);
        });

        return ['errors' => [], 'id' => $id];
    }

    private function nullableNumber(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return Decimal::round($text, 2);
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}

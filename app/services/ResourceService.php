<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\PlanningRepository;
use App\Repositories\UserRepository;

/**
 * People, teams, machines, vehicles, equipment, work areas, and subcontractors
 * are all resources. Scheduling assigns those resources to existing work.
 */
final class ResourceService
{
    /** @var list<string> */
    public const TYPES = ['USER', 'TEAM', 'MACHINE', 'VEHICLE', 'EQUIPMENT', 'WORK_AREA', 'SUBCONTRACTOR'];

    /** @var list<string> */
    public const CAPACITY_TYPES = ['MINUTES', 'HOURS', 'UNITS', 'AREA', 'CUSTOM'];

    /** @var list<string> */
    public const STATUSES = ['AVAILABLE', 'IN_USE', 'MAINTENANCE', 'OUT_OF_SERVICE', 'RETIRED'];

    /** @var list<string> */
    public const UNAVAILABLE = ['LEAVE', 'SICK', 'TRAINING', 'MAINTENANCE', 'BREAKDOWN', 'BOOKED_OUT', 'PUBLIC_HOLIDAY', 'OTHER'];

    public function __construct(
        private readonly PlanningRepository $planning = new PlanningRepository(),
        private readonly AuditService $audit = new AuditService(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    public function ensurePeople(): void
    {
        foreach ($this->planning->activeUsers() as $user) {
            if ($this->planning->resourceForUser((int) $user['id']) !== null) {
                continue;
            }
            $this->planning->insertResource([
                'resource_type' => 'USER',
                'code' => 'USR-' . $user['id'],
                'name' => (string) $user['name'],
                'description' => null,
                'capacity_type' => 'MINUTES',
                'default_daily_capacity' => '480.00',
                'concurrent_capacity' => 1,
                'internal_hourly_cost' => null,
                'internal_cost_per_km' => null,
                'linked_user_id' => (int) $user['id'],
                'linked_team_id' => null,
                'linked_supplier_id' => null,
                'status' => 'AVAILABLE',
                'active' => 1,
            ]);
        }
        foreach ($this->planning->teams() as $team) {
            if ($this->planning->resourceForTeam((int) $team['id']) !== null) {
                continue;
            }
            $this->planning->insertResource([
                'resource_type' => 'TEAM',
                'code' => 'TEAM-' . $team['id'],
                'name' => (string) $team['name'],
                'description' => null,
                'capacity_type' => 'MINUTES',
                'default_daily_capacity' => '480.00',
                'concurrent_capacity' => 1,
                'internal_hourly_cost' => null,
                'internal_cost_per_km' => null,
                'linked_user_id' => null,
                'linked_team_id' => (int) $team['id'],
                'linked_supplier_id' => null,
                'status' => 'AVAILABLE',
                'active' => (int) $team['active'] === 1 ? 1 : 0,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function save(?int $id, array $input, int $userId): array
    {
        if (!AuthorizationService::allows((new UserRepository())->find($userId), 'resources.manage')
            && !AuthorizationService::allows((new UserRepository())->find($userId), 'machines.manage')
            && !AuthorizationService::allows((new UserRepository())->find($userId), 'vehicles.manage')) {
            return ['errors' => ['_form' => 'You cannot change resources.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['resource_type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) {
            return ['errors' => ['resource_type' => 'Choose a resource type.'], 'id' => null];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['name' => 'Name is required.'], 'id' => null];
        }
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if ($code === '') {
            $code = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($name)) ?? 'RES', 0, 12);
        }
        $status = strtoupper(trim((string) ($input['status'] ?? 'AVAILABLE')));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'AVAILABLE';
        }
        $capacityType = strtoupper(trim((string) ($input['capacity_type'] ?? 'MINUTES')));
        if (!in_array($capacityType, self::CAPACITY_TYPES, true)) {
            $capacityType = 'MINUTES';
        }
        $daily = trim((string) ($input['default_daily_capacity'] ?? '480'));
        if (!Decimal::isNumeric($daily) || Decimal::cmp($daily, '0') < 0) {
            return ['errors' => ['default_daily_capacity' => 'Enter a capacity of zero or more.'], 'id' => null];
        }
        $concurrent = max(1, (int) ($input['concurrent_capacity'] ?? ($type === 'WORK_AREA' ? 8 : 1)));
        $data = [
            'resource_type' => $type,
            'code' => mb_substr($code, 0, 40),
            'name' => mb_substr($name, 0, 180),
            'description' => $this->blank($input['description'] ?? null),
            'capacity_type' => $capacityType,
            'default_daily_capacity' => Decimal::round($daily, 2),
            'concurrent_capacity' => $concurrent,
            'internal_hourly_cost' => $this->moneyOrNull($input['internal_hourly_cost'] ?? null),
            'internal_cost_per_km' => $this->moneyOrNull($input['internal_cost_per_km'] ?? null),
            'linked_user_id' => $this->positive($input['linked_user_id'] ?? null),
            'linked_team_id' => $this->positive($input['linked_team_id'] ?? null),
            'linked_supplier_id' => $this->positive($input['linked_supplier_id'] ?? null),
            'status' => $status,
            'active' => (int) ($input['active'] ?? 1) === 1 ? 1 : 0,
        ];
        try {
            $saved = Database::transaction(function () use ($id, $data, $userId): int {
                if ($id === null) {
                    $newId = $this->planning->insertResource($data);
                    $this->audit->record('resource', $newId, 'RESOURCE_CREATED', null, $data, $userId);

                    return $newId;
                }
                $this->planning->updateResource($id, $data);
                $this->audit->record('resource', $id, 'RESOURCE_UPDATED', null, $data, $userId);

                return $id;
            });
        } catch (\Throwable) {
            return ['errors' => ['_form' => 'That code is already in use.'], 'id' => null];
        }

        return ['errors' => [], 'id' => $saved];
    }

    /**
     * Marks a machine or vehicle out of service and lists the bookings that
     * are affected. It does not move those bookings.
     *
     * @return array{ok: bool, affected: list<int>, error?: string}
     */
    public function markOutOfService(int $resourceId, string $reason, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'machines.manage') && !AuthorizationService::allows($actor, 'resources.manage')) {
            return ['ok' => false, 'affected' => [], 'error' => 'You cannot change this resource.'];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'affected' => [], 'error' => 'A breakdown needs a reason.'];
        }

        return Database::transaction(function () use ($resourceId, $reason, $userId): array {
            $resource = $this->planning->lockResource($resourceId);
            if ($resource === null) {
                return ['ok' => false, 'affected' => [], 'error' => 'That resource was not found.'];
            }
            $this->planning->setResourceStatus($resourceId, 'OUT_OF_SERVICE');
            $now = date('Y-m-d H:i:s');
            $this->planning->insertDowntime([
                'resource_id' => $resourceId,
                'maintenance_record_id' => null,
                'started_at' => $now,
                'ended_at' => null,
                'reason' => $reason,
                'created_by' => $userId,
            ]);
            $this->planning->insertUnavailability([
                'resource_id' => $resourceId,
                'start_datetime' => $now,
                'end_datetime' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'reason_type' => 'BREAKDOWN',
                'description' => $reason,
                'status' => 'ACTIVE',
                'created_by' => $userId,
            ]);
            $affected = [];
            foreach ($this->planning->futureEntriesForResource($resourceId, $now) as $entry) {
                $affected[] = (int) $entry['id'];
                $owner = (int) ($entry['created_by'] ?? 0);
                if ($owner > 0) {
                    $this->notifications->send(
                        $owner,
                        null,
                        'RESOURCE_CONFLICT',
                        (string) $resource['name'] . ' is out of service',
                        $reason . ' Scheduled work was left where it is so a planner can move it.',
                        'schedule_entry',
                        (int) $entry['id'],
                        'HIGH',
                        'breakdown-' . $resourceId . '-' . $entry['id']
                    );
                }
            }
            $this->audit->record('resource', $resourceId, 'OUT_OF_SERVICE', null, [
                'reason' => $reason,
                'affected' => $affected,
            ], $userId);

            return ['ok' => true, 'affected' => $affected];
        });
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addUnavailability(array $input, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'staff_availability.manage') && !AuthorizationService::allows($actor, 'resources.manage')) {
            return ['_form' => 'You cannot record unavailability.'];
        }
        $resourceId = (int) ($input['resource_id'] ?? 0);
        if ($this->planning->resource($resourceId) === null) {
            return ['resource_id' => 'Choose a resource.'];
        }
        $reason = strtoupper(trim((string) ($input['reason_type'] ?? '')));
        if (!in_array($reason, self::UNAVAILABLE, true)) {
            return ['reason_type' => 'Choose a reason.'];
        }
        $start = strtotime(str_replace('T', ' ', (string) ($input['start_datetime'] ?? '')));
        $end = strtotime(str_replace('T', ' ', (string) ($input['end_datetime'] ?? '')));
        if ($start === false || $end === false || $end <= $start) {
            return ['start_datetime' => 'Enter a start and end.'];
        }
        $this->planning->insertUnavailability([
            'resource_id' => $resourceId,
            'start_datetime' => date('Y-m-d H:i:s', $start),
            'end_datetime' => date('Y-m-d H:i:s', $end),
            'reason_type' => $reason,
            'description' => $this->blank($input['description'] ?? null),
            'status' => 'ACTIVE',
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function capacity(int $days): array
    {
        $days = in_array($days, [7, 14, 30], true) ? $days : 7;
        $from = date('Y-m-d');
        $to = date('Y-m-d', strtotime('+' . ($days - 1) . ' days'));
        $capacity = new CapacityService();
        $entries = $this->planning->scheduledMinutesByResource($from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00');
        $rows = [];
        foreach ($this->planning->resources(null, true) as $resource) {
            if (!in_array((string) $resource['resource_type'], ['USER', 'TEAM', 'MACHINE', 'WORK_AREA', 'VEHICLE'], true)) {
                continue;
            }
            $available = 0;
            if (in_array((string) $resource['status'], ['OUT_OF_SERVICE', 'RETIRED'], true) || (int) $resource['active'] !== 1) {
                $available = 0;
            } else {
                $cursor = strtotime($from) ?: time();
                $end = strtotime($to) ?: $cursor;
                while ($cursor <= $end) {
                    $date = date('Y-m-d', $cursor);
                    $schedule = $this->planning->scheduleForResource((int) $resource['id'], $date) ?? $this->planning->defaultSchedule();
                    if ($schedule !== null) {
                        $available += $capacity->availableMinutes(
                            $schedule,
                            $date,
                            $this->planning->exceptionOn($date),
                            $this->planning->unavailabilityBetween((int) $resource['id'], $date . ' 00:00:00', $date . ' 23:59:59')
                        );
                    }
                    $cursor = strtotime($date . ' +1 day') ?: ($cursor + 86400);
                }
            }
            $scheduled = 0;
            foreach ($entries as $entry) {
                if ((int) $entry['resource_id'] !== (int) $resource['id']) {
                    continue;
                }
                $scheduled += (int) $entry['estimated_minutes'];
            }
            $summary = $capacity->summarise((string) $available, (string) $scheduled);
            $rows[] = [
                'resource' => $resource,
                'summary' => $summary,
                'overloaded' => $summary['utilisation_percent'] !== null && Decimal::cmp($summary['utilisation_percent'], '100') > 0,
                'underused' => $summary['utilisation_percent'] !== null && Decimal::cmp($summary['utilisation_percent'], '50') < 0 && $available > 0,
            ];
        }

        return ['from' => $from, 'to' => $to, 'days' => $days, 'rows' => $rows];
    }

    private function positive(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function moneyOrNull(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return Decimal::round($text, 4);
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}

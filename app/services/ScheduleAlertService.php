<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanningRepository;

/**
 * Operational reminders. Each one uses a dedupe key so the hourly cron
 * does not stack copies.
 */
final class ScheduleAlertService
{
    public function __construct(
        private readonly PlanningRepository $planning = new PlanningRepository(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    public function evaluate(): int
    {
        $created = 0;
        $roleId = $this->planning->roleId('MANAGEMENT');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        foreach ($this->planning->installationsBetween($tomorrow, $tomorrow) as $row) {
            $user = (int) ($row['assigned_user_id'] ?? 0);
            if ($user < 1) {
                continue;
            }
            if ($this->notifications->send(
                $user,
                null,
                'INSTALLATION_TOMORROW',
                'Installation tomorrow',
                (string) $row['job_number'] . ' is booked for ' . $tomorrow . '.',
                'installation',
                (int) $row['id'],
                'NORMAL',
                'install-tomorrow-' . $row['id'] . '-' . $tomorrow
            )) {
                $created++;
            }
        }
        foreach ($this->planning->maintenanceDue(date('Y-m-d', strtotime('+7 days'))) as $row) {
            if ($this->notifications->send(
                null,
                $roleId,
                'MACHINE_MAINTENANCE_DUE',
                'Maintenance due',
                (string) $row['resource_name'] . ' on ' . (string) $row['scheduled_date'] . '.',
                'maintenance',
                (int) $row['id'],
                'NORMAL',
                'maint-due-' . $row['id']
            )) {
                $created++;
            }
        }
        foreach ($this->planning->serviceDueVehicles(date('Y-m-d', strtotime('+14 days'))) as $row) {
            if ($this->notifications->send(
                null,
                $roleId,
                'VEHICLE_SERVICE_DUE',
                'Vehicle service due',
                (string) $row['registration_number'] . ' by ' . (string) $row['next_service_date'] . '.',
                'resource',
                (int) $row['id'],
                'NORMAL',
                'vehicle-service-' . $row['id']
            )) {
                $created++;
            }
        }

        return $created;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanningRepository;

/**
 * Short actions for an installer on site.
 *
 * Travel and installation can open a time entry. The installer can correct
 * that time later from the job if they have permission.
 */
final class FieldWorkService
{
    public function __construct(
        private readonly PlanningRepository $planning = new PlanningRepository(),
        private readonly JobService $jobs = new JobService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function act(int $installationId, string $action, int $userId, array $input): array
    {
        $row = $this->planning->installation($installationId);
        if ($row === null) {
            return ['_form' => 'That installation was not found.'];
        }
        $status = match (strtoupper($action)) {
            'START_TRAVEL' => 'EN_ROUTE',
            'ARRIVED' => 'ON_SITE',
            'START_INSTALLATION' => 'IN_PROGRESS',
            'COMPLETE' => 'COMPLETE',
            default => '',
        };
        if ($status === '') {
            return ['_form' => 'That field action is not available.'];
        }
        if ($status === 'IN_PROGRESS') {
            $this->jobs->stopTimer($userId);
            $this->jobs->startTimer((int) $row['job_id'], ['work_type' => 'INSTALLATION', 'description' => 'Installation'], $userId);
        } elseif ($status === 'EN_ROUTE') {
            $this->jobs->startTimer((int) $row['job_id'], ['work_type' => 'OTHER', 'description' => 'Travel'], $userId);
        } elseif ($status === 'COMPLETE') {
            $this->jobs->stopTimer($userId);
        }

        return $this->jobs->updateInstallation((int) $row['job_id'], $installationId, [
            'status' => $status,
            'scheduled_date' => $row['scheduled_date'],
            'scheduled_start_time' => $row['scheduled_start_time'],
            'assigned_team_id' => $row['assigned_team_id'],
            'assigned_user_id' => $row['assigned_user_id'],
            'completion_notes' => $input['completion_notes'] ?? $row['completion_notes'],
            'customer_signoff_name' => $input['customer_signoff_name'] ?? '',
        ], (int) $row['version_number'], $userId);
    }
}

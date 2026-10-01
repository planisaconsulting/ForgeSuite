<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProjectRepository;
use App\Services\NotificationService;

/**
 * Health is a calculated signal with reasons. It does not change the
 * project status. AT_RISK as a status is only set by a person.
 */
final class ProjectHealthService
{
    public function __construct(
        private readonly ProjectRepository $projects = new ProjectRepository(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    /**
     * @return array{health: string, reasons: list<string>}
     */
    public function assess(int $projectId): array
    {
        $project = $this->projects->find($projectId);
        if ($project === null) {
            return ['health' => 'ON_TRACK', 'reasons' => []];
        }
        if ((string) $project['status'] === 'COMPLETED') {
            return ['health' => 'COMPLETE', 'reasons' => ['The project is operationally complete.']];
        }
        $reasons = [];
        $blocked = false;
        $overdue = false;
        $risk = false;
        foreach ($this->projects->milestones($projectId) as $milestone) {
            if ((string) $milestone['status'] === 'BLOCKED' && (int) $milestone['blocking'] === 1) {
                $blocked = true;
                $reasons[] = 'Blocking milestone: ' . (string) $milestone['name'];
            }
            $due = (string) ($milestone['current_due_date'] ?? '');
            if ($due !== '' && $due < date('Y-m-d') && !in_array((string) $milestone['status'], ['COMPLETE', 'CANCELLED'], true)) {
                $overdue = true;
                $reasons[] = 'Milestone overdue: ' . (string) $milestone['name'];
            }
        }
        $counts = $this->projects->counts($projectId);
        if ((int) $counts['sites_late'] > 0) {
            $overdue = true;
            $reasons[] = (int) $counts['sites_late'] . ' site' . ((int) $counts['sites_late'] === 1 ? '' : 's') . ' past the current target';
        }
        $jobs = $this->projects->attentionJobs($projectId);
        $blockedJobs = 0;
        $artwork = 0;
        foreach ($jobs as $job) {
            if ((string) $job['status'] === 'ON_HOLD') {
                $blockedJobs++;
            }
            if (in_array((string) $job['status'], ['AWAITING_ARTWORK', 'AWAITING_CUSTOMER_APPROVAL'], true)) {
                $artwork++;
            }
        }
        if ($blockedJobs > 0) {
            $blocked = true;
            $reasons[] = $blockedJobs . ' job' . ($blockedJobs === 1 ? '' : 's') . ' on hold';
        }
        if ($artwork > 0) {
            $risk = true;
            $reasons[] = $artwork . ' job' . ($artwork === 1 ? '' : 's') . ' waiting on artwork';
        }
        $snags = $this->projects->snagSummary($projectId);
        if ((int) $snags['critical_snags'] > 0) {
            $risk = true;
            $reasons[] = (int) $snags['critical_snags'] . ' critical snag' . ((int) $snags['critical_snags'] === 1 ? '' : 's');
        }
        $target = (string) ($project['current_target_date'] ?? '');
        if ($target !== '' && $target < date('Y-m-d') && !in_array((string) $project['status'], ['COMPLETED', 'CANCELLED'], true)) {
            $overdue = true;
            $reasons[] = 'Project target date has passed';
        }
        foreach ($this->projects->risks($projectId) as $row) {
            if ((string) $row['status'] === 'OPEN' && ((int) $row['probability'] * (int) $row['impact']) >= 15) {
                $risk = true;
                $reasons[] = 'Open high risk: ' . (string) $row['title'];
            }
        }
        $health = 'ON_TRACK';
        if ($blocked) {
            $health = 'BLOCKED';
        } elseif ($overdue) {
            $health = 'OVERDUE';
        } elseif ($risk) {
            $health = 'AT_RISK';
        }
        if ($reasons === [] && $health === 'ON_TRACK') {
            $reasons[] = 'No overdue milestones, late sites, or blocking jobs were found.';
        }

        return ['health' => $health, 'reasons' => array_slice($reasons, 0, 8)];
    }

    public function notifyDue(): int
    {
        $sent = 0;
        foreach ($this->projects->dueMilestones(2) as $milestone) {
            $manager = (int) ($milestone['project_manager_user_id'] ?? 0);
            if ($manager < 1) {
                continue;
            }
            $overdue = (string) $milestone['current_due_date'] < date('Y-m-d');
            $ok = $this->notifications->send(
                $manager,
                null,
                'PROJECT_MILESTONE',
                $overdue ? 'Milestone overdue' : 'Milestone approaching',
                (string) $milestone['project_number'] . ': ' . (string) $milestone['name'],
                'project',
                (int) $milestone['project_id'],
                $overdue ? 'HIGH' : 'NORMAL',
                'project-milestone-' . $milestone['id'] . '-' . $milestone['current_due_date']
            );
            if ($ok) {
                $sent++;
            }
            BusinessEventDispatcher::emit(
                'PROJECT_MILESTONE_DUE',
                'PROJECT',
                (int) $milestone['project_id'],
                null,
                ['milestone_id' => (int) $milestone['id']]
            );
        }

        return $sent;
    }
}

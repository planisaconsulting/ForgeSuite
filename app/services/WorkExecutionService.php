<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\PlanningRepository;
use App\Repositories\UserRepository;

/**
 * Starts, finishes, and blocks the tasks that already exist on a job.
 */
final class WorkExecutionService
{
    public function __construct(
        private readonly PlanningRepository $planning = new PlanningRepository(),
        private readonly JobService $jobs = new JobService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function startTask(int $taskId, int $userId): array
    {
        $task = $this->planning->task($taskId);
        if ($task === null) {
            return ['_form' => 'That task was not found.'];
        }
        if (!$this->canTouch($task, $userId)) {
            return ['_form' => 'You cannot start this task.'];
        }

        return Database::transaction(function () use ($task, $taskId, $userId): array {
            $this->planning->setTaskStatus($taskId, 'IN_PROGRESS', true, false);
            $this->jobs->startTimer((int) $task['job_id'], [
                'work_type' => 'OTHER',
                'description' => (string) $task['title'],
            ], $userId);
            $this->audit->record('job_task', $taskId, 'TASK_STARTED', null, ['status' => 'IN_PROGRESS'], $userId);

            return [];
        });
    }

    /**
     * @return array<string, string>
     */
    public function completeTask(int $taskId, int $userId): array
    {
        $task = $this->planning->task($taskId);
        if ($task === null) {
            return ['_form' => 'That task was not found.'];
        }
        if (!$this->canTouch($task, $userId)) {
            return ['_form' => 'You cannot complete this task.'];
        }

        return Database::transaction(function () use ($task, $taskId, $userId): array {
            $this->planning->setTaskStatus($taskId, 'COMPLETE', false, true);
            $this->jobs->stopTimer($userId);
            $this->audit->record('job_task', $taskId, 'TASK_COMPLETED', null, ['status' => 'COMPLETE'], $userId);
            $manager = (int) ($this->planning->job((int) $task['job_id'])['project_manager_id'] ?? 0);
            if ($manager > 0) {
                $this->notifications->send(
                    $manager,
                    null,
                    'SYSTEM',
                    'Task completed',
                    (string) $task['title'] . ' was marked complete.',
                    'job_task',
                    $taskId,
                    'NORMAL',
                    'task-done-' . $taskId
                );
            }

            return [];
        });
    }

    /**
     * @return array<string, string>
     */
    public function blockTask(int $taskId, int $reasonId, string $note, int $userId): array
    {
        $task = $this->planning->task($taskId);
        $reason = $this->planning->blockReason($reasonId);
        if ($task === null || $reason === null) {
            return ['_form' => 'Choose a task and a block reason.'];
        }
        if (!$this->canTouch($task, $userId)) {
            return ['_form' => 'You cannot block this task.'];
        }
        $text = trim($note) !== '' ? trim($note) : (string) $reason['name'];

        return Database::transaction(function () use ($task, $taskId, $reason, $text, $userId): array {
            $this->planning->setTaskStatus($taskId, 'BLOCKED', false, false);
            $this->planning->insertBlock([
                'job_id' => (int) $task['job_id'],
                'task_id' => $taskId,
                'stage_id' => null,
                'reason_id' => (int) $reason['id'],
                'reason_text' => mb_substr($text, 0, 255),
                'created_by' => $userId,
            ]);
            $this->audit->record('job_task', $taskId, 'TASK_BLOCKED', null, ['reason' => $text], $userId);

            return [];
        });
    }

    /**
     * @param array<string, mixed> $task
     */
    private function canTouch(array $task, int $userId): bool
    {
        $actor = (new UserRepository())->find($userId);
        if (AuthorizationService::allows($actor, 'production.update') || AuthorizationService::allows($actor, 'schedule.manage')) {
            return true;
        }

        return (int) ($task['assigned_to'] ?? 0) === $userId;
    }
}

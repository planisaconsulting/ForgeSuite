<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProjectRepository;

/**
 * Project milestones and finish-to-start dependencies.
 * Completing a milestone does not complete a job.
 */
final class ProjectMilestoneService
{
    public function __construct(
        private readonly ProjectRepository $projects = new ProjectRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function add(int $projectId, array $input, int $userId): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['name' => 'Milestone name is required.'];
        }
        $type = strtoupper(trim((string) ($input['milestone_type'] ?? 'CUSTOM')));
        if ($type === '') {
            $type = 'CUSTOM';
        }
        $due = $this->date($input['current_due_date'] ?? null);
        $weight = trim((string) ($input['weight'] ?? ''));
        $id = $this->projects->insertMilestone([
            'project_id' => $projectId,
            'project_site_id' => $this->nullableInt($input['project_site_id'] ?? null),
            'name' => mb_substr($name, 0, 160),
            'description' => $this->blank($input['description'] ?? null),
            'milestone_type' => mb_substr($type, 0, 40),
            'status' => 'NOT_STARTED',
            'weight' => $weight === '' ? null : $weight,
            'original_due_date' => $due,
            'current_due_date' => $due,
            'depends_on_milestone_id' => $this->nullableInt($input['depends_on_milestone_id'] ?? null),
            'responsible_user_id' => $this->nullableInt($input['responsible_user_id'] ?? null),
            'blocking' => empty($input['blocking']) ? 0 : 1,
            'sequence' => max(1, (int) ($input['sequence'] ?? 1)),
        ]);
        $this->audit->record('project', $projectId, 'MILESTONE_ADDED', null, ['milestone_id' => $id, 'name' => $name], $userId);
        BusinessEventDispatcher::emit('PROJECT_MILESTONE_DUE', 'PROJECT', $projectId, $userId, ['milestone_id' => $id]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function setStatus(int $milestoneId, string $status, int $version, int $userId): array
    {
        $status = strtoupper($status);
        if (!in_array($status, ['NOT_STARTED', 'IN_PROGRESS', 'BLOCKED', 'COMPLETE', 'CANCELLED'], true)) {
            return ['status' => 'That milestone status is not valid.'];
        }
        $row = $this->projects->milestone($milestoneId);
        if ($row === null) {
            return ['_form' => 'That milestone was not found.'];
        }
        if ($status === 'IN_PROGRESS' && !$this->projects->predecessorComplete($milestoneId)) {
            return ['_form' => 'The previous milestone has to finish first.'];
        }
        $completed = $status === 'COMPLETE' ? date('Y-m-d H:i:s') : null;
        $ok = $this->projects->updateMilestone($milestoneId, [
            'name' => (string) $row['name'],
            'status' => $status,
            'current_due_date' => $row['current_due_date'],
            'weight' => $row['weight'],
            'blocking' => (int) $row['blocking'],
            'completed_at' => $completed,
            'responsible_user_id' => $row['responsible_user_id'],
        ], $version);
        if (!$ok) {
            return ['_form' => 'Someone else updated this milestone. Reload and try again.'];
        }
        $this->audit->record('project', (int) $row['project_id'], 'MILESTONE_' . $status, null, ['milestone_id' => $milestoneId], $userId);
        if ($status === 'COMPLETE') {
            BusinessEventDispatcher::emit('PROJECT_MILESTONE_COMPLETED', 'PROJECT', (int) $row['project_id'], $userId, ['milestone_id' => $milestoneId]);
        }

        return [];
    }

    public function copyTemplate(int $projectId, int $templateId): void
    {
        $sequence = 1;
        foreach ($this->projects->templateMilestones($templateId) as $row) {
            $this->projects->insertMilestone([
                'project_id' => $projectId,
                'project_site_id' => null,
                'name' => (string) $row['name'],
                'description' => null,
                'milestone_type' => (string) $row['milestone_type'],
                'status' => 'NOT_STARTED',
                'weight' => (string) $row['weight'],
                'original_due_date' => null,
                'current_due_date' => null,
                'depends_on_milestone_id' => null,
                'responsible_user_id' => null,
                'blocking' => (int) $row['blocking'],
                'sequence' => $sequence,
            ]);
            $sequence++;
        }
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}

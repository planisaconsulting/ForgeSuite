<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\WorkshopCodes;
use App\Helpers\Database;
use App\Repositories\WorkshopRepository;

final class SnagService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function create(int $jobId, array $input, int $userId): array
    {
        if (!can('snags.manage')) {
            return ['_form' => 'You cannot add a snag.'];
        }
        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            return ['description' => 'Describe the outstanding item.'];
        }
        $priority = strtoupper(trim((string) ($input['priority'] ?? 'NORMAL')));
        if (!in_array($priority, ['LOW', 'NORMAL', 'HIGH', 'CRITICAL'], true)) {
            $priority = 'NORMAL';
        }
        $id = 0;
        Database::transaction(function () use ($jobId, $input, $userId, $description, $priority, &$id): void {
            $id = $this->workshop->insertSnag([
                'job_id' => $jobId,
                'installation_id' => ((int) ($input['installation_id'] ?? 0)) > 0 ? (int) $input['installation_id'] : null,
                'description' => mb_substr($description, 0, 255),
                'priority' => $priority,
                'assigned_to' => ((int) ($input['assigned_to'] ?? 0)) > 0 ? (int) $input['assigned_to'] : null,
                'target_date' => blank_to_null($input['target_date'] ?? null),
                'status' => WorkshopCodes::SNAG_OPEN,
                'created_by' => $userId,
            ]);
            $this->audit->record('job', $jobId, 'SNAG_CREATED', null, ['snag_id' => $id, 'priority' => $priority], $userId);
            (new AutomationService())->fire('SNAG_CREATED', 'job', $jobId, $userId);
            (new WorkshopNotifier())->send('SNAG_CREATED', 'Snag created', $description, 'job', $jobId);
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function update(int $id, string $status, int $userId): array
    {
        if (!can('snags.manage')) {
            return ['_form' => 'You cannot update a snag.'];
        }
        $status = strtoupper($status);
        if (!in_array($status, [WorkshopCodes::SNAG_OPEN, WorkshopCodes::SNAG_PROGRESS, WorkshopCodes::SNAG_RESOLVED, WorkshopCodes::SNAG_CANCELLED], true)) {
            return ['status' => 'That snag status is not valid.'];
        }
        $snag = $this->workshop->snag($id);
        if ($snag === null) {
            return ['_form' => 'That snag was not found.'];
        }
        $this->workshop->updateSnag($id, [
            'status' => $status,
            'assigned_to' => $snag['assigned_to'],
            'resolved_at' => in_array($status, [WorkshopCodes::SNAG_RESOLVED, WorkshopCodes::SNAG_CANCELLED], true) ? date('Y-m-d H:i:s') : null,
            'priority' => $snag['priority'],
        ]);
        $this->audit->record('job', (int) $snag['job_id'], 'SNAG_UPDATED', ['status' => $snag['status']], ['status' => $status], $userId);

        return [];
    }

    public function flagOverdue(): int
    {
        $count = 0;
        foreach ($this->workshop->overdueSnags() as $snag) {
            (new WorkshopNotifier())->send('SNAG_OVERDUE', 'Snag overdue', (string) $snag['description'], 'job', (int) $snag['job_id']);
            $count++;
        }

        return $count;
    }
}

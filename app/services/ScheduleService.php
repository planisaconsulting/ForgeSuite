<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\PlanningRepository;
use App\Repositories\UserRepository;

/**
 * Books existing jobs, tasks, stages, installations, and surveys onto resources.
 *
 * The browser sends a proposal. This service rechecks the resource inside a
 * transaction and rejects anything the conflict rules do not allow.
 */
final class ScheduleService
{
    /** @var list<string> */
    public const ENTITY_TYPES = ['JOB', 'JOB_TASK', 'PRODUCTION_STAGE', 'INSTALLATION', 'SITE_SURVEY', 'MAINTENANCE', 'OTHER'];

    /** @var list<string> */
    public const STATUSES = ['PLANNED', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];

    public function __construct(
        private readonly PlanningRepository $planning = new PlanningRepository(),
        private readonly SchedulingConflictService $conflicts = new SchedulingConflictService(),
        private readonly CapacityService $capacity = new CapacityService(),
        private readonly JobReadinessService $readiness = new JobReadinessService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function book(array $input, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'schedule.manage')) {
            return ['ok' => false, 'error' => 'You cannot change the schedule.', 'conflicts' => []];
        }
        $parsed = $this->parse($input);
        if (isset($parsed['error'])) {
            return ['ok' => false, 'error' => $parsed['error'], 'conflicts' => []];
        }

        return Database::transaction(function () use ($parsed, $userId, $actor): array {
            $check = $this->checkResources($parsed, 0);
            $decision = $this->decide($check, $parsed, $actor);
            if (!$decision['ok']) {
                return $decision;
            }
            $id = $this->planning->insertEntry($this->entryRow($parsed, $userId, $decision['override']));
            $this->storeResources($id, $parsed['resource_ids']);
            $this->audit->record('schedule_entry', $id, 'SCHEDULED', null, [
                'start' => $parsed['start'],
                'end' => $parsed['end'],
                'resources' => $parsed['resource_ids'],
            ], $userId);

            return ['ok' => true, 'id' => $id, 'warnings' => $decision['warnings'], 'conflicts' => []];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function move(int $entryId, string $start, string $end, int $userId, string $reason, array $resourceIds = []): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'schedule.manage')) {
            return ['ok' => false, 'error' => 'You cannot change the schedule.', 'conflicts' => []];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['ok' => false, 'error' => 'A reschedule needs a reason.', 'conflicts' => []];
        }

        return Database::transaction(function () use ($entryId, $start, $end, $userId, $reason, $resourceIds, $actor): array {
            $entry = $this->planning->entry($entryId);
            if ($entry === null) {
                return ['ok' => false, 'error' => 'That schedule entry was not found.', 'conflicts' => []];
            }
            if ((int) $entry['locked'] === 1) {
                return ['ok' => false, 'error' => 'That booking is locked.', 'conflicts' => []];
            }
            $ids = $resourceIds !== [] ? $resourceIds : [(int) $entry['resource_id']];
            $parsed = $this->parse([
                'entity_type' => $entry['entity_type'],
                'entity_id' => $entry['entity_id'],
                'job_id' => $entry['job_id'],
                'resource_id' => $ids[0],
                'resource_ids' => $ids,
                'start_datetime' => $start,
                'end_datetime' => $end,
                'estimated_minutes' => $entry['estimated_minutes'],
                'status' => $entry['status'],
                'customer_visible' => $entry['customer_visible'],
                'notes' => $entry['notes'],
            ]);
            if (isset($parsed['error'])) {
                return ['ok' => false, 'error' => $parsed['error'], 'conflicts' => []];
            }
            $check = $this->checkResources($parsed, $entryId);
            $decision = $this->decide($check, $parsed, $actor);
            if (!$decision['ok']) {
                return $decision;
            }
            $this->planning->insertHistory([
                'schedule_entry_id' => $entryId,
                'old_start' => $entry['start_datetime'],
                'old_end' => $entry['end_datetime'],
                'new_start' => $parsed['start'],
                'new_end' => $parsed['end'],
                'changed_by' => $userId,
                'reason' => $reason,
            ]);
            $this->planning->updateEntry($entryId, [
                'resource_id' => $parsed['resource_ids'][0],
                'start_datetime' => $parsed['start'],
                'end_datetime' => $parsed['end'],
                'estimated_minutes' => $parsed['estimated_minutes'],
                'status' => $parsed['status'],
                'customer_visible' => $parsed['customer_visible'],
                'notes' => $parsed['notes'],
                'override_reason' => $decision['override'] ? $parsed['override_reason'] : $entry['override_reason'],
                'override_by' => $decision['override'] ? $userId : $entry['override_by'],
                'override_at' => $decision['override'] ? date('Y-m-d H:i:s') : $entry['override_at'],
            ]);
            $this->planning->clearEntryResources($entryId);
            $this->storeResources($entryId, $parsed['resource_ids']);
            $this->notifyMove($entry, $parsed['start'], $userId);
            $this->audit->record('schedule_entry', $entryId, 'RESCHEDULED', [
                'start' => $entry['start_datetime'],
            ], ['start' => $parsed['start'], 'reason' => $reason], $userId);

            return ['ok' => true, 'id' => $entryId, 'warnings' => $decision['warnings'], 'conflicts' => []];
        });
    }

    /**
     * Keeps the customer promised date. Internal target changes are separate.
     *
     * @return array<string, string>
     */
    public function changeInternalTarget(int $jobId, string $newDate, string $reason, int $userId): array
    {
        $job = $this->planning->job($jobId);
        if ($job === null) {
            return ['_form' => 'That job was not found.'];
        }
        $date = $this->date($newDate);
        if ($date === null) {
            return ['target_date' => 'Enter a valid target date.'];
        }
        $reason = trim($reason);
        $current = $job['target_date'] !== null ? (string) $job['target_date'] : '';
        if ($current === $date) {
            return [];
        }
        if ($reason === '') {
            return ['target_change_reason' => 'Changing the internal target date needs a reason.'];
        }
        $original = $job['original_target_date'] ?? ($current !== '' ? $current : $date);
        Database::transaction(function () use ($jobId, $date, $original, $job, $reason, $userId, $current): void {
            $this->planning->updatePlanningDates($jobId, $original !== null ? (string) $original : null, $job['customer_promised_date'] !== null ? (string) $job['customer_promised_date'] : null);
            \App\Helpers\Database::connection()->prepare('UPDATE jobs SET target_date = ? WHERE id = ?')->execute([$date, $jobId]);
            $this->audit->record('job', $jobId, 'INTERNAL_TARGET_CHANGED', ['target_date' => $current], [
                'target_date' => $date,
                'reason' => $reason,
            ], $userId);
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function setCustomerPromise(int $jobId, ?string $date, string $reason, int $userId): array
    {
        $job = $this->planning->job($jobId);
        if ($job === null) {
            return ['_form' => 'That job was not found.'];
        }
        $clean = $date === null || trim($date) === '' ? null : $this->date($date);
        if ($date !== null && trim($date) !== '' && $clean === null) {
            return ['customer_promised_date' => 'Enter a valid customer date.'];
        }
        $current = $job['customer_promised_date'] !== null ? (string) $job['customer_promised_date'] : '';
        $next = $clean ?? '';
        if ($current === $next) {
            return [];
        }
        if (trim($reason) === '') {
            return ['promise_reason' => 'Changing the customer promised date needs a reason.'];
        }
        $original = $job['original_target_date'] ?? $job['target_date'];
        Database::transaction(function () use ($jobId, $original, $clean, $current, $reason, $userId): void {
            $this->planning->updatePlanningDates($jobId, $original !== null ? (string) $original : null, $clean);
            $this->audit->record('job', $jobId, 'CUSTOMER_PROMISE_CHANGED', ['customer_promised_date' => $current], [
                'customer_promised_date' => $clean,
                'reason' => $reason,
            ], $userId);
        });

        return [];
    }

    public function linkStageSequence(int $jobId): int
    {
        $stages = $this->planning->stagesForJob($jobId);
        $labour = $this->planning->expectedLabour($jobId);
        $created = 0;
        $previous = null;
        foreach ($stages as $stage) {
            if ($stage['estimated_minutes'] === null) {
                $minutes = $this->matchLabour($labour, (string) $stage['stage_name']);
                if ($minutes !== null) {
                    $this->planning->setStageEstimate((int) $stage['id'], $minutes);
                }
            }
            if ($previous !== null && !$this->planning->dependencyExists($previous, (int) $stage['id'])) {
                $this->planning->insertStageDependency($previous, (int) $stage['id']);
                $created++;
            }
            $previous = (int) $stage['id'];
        }

        return $created;
    }

    /**
     * @param list<array<string, mixed>> $labour
     */
    public function matchLabour(array $labour, string $stageName): ?int
    {
        $needle = strtolower($stageName);
        foreach ($labour as $row) {
            $description = strtolower((string) $row['description']);
            if ($description === '' || (!str_contains($description, $needle) && !str_contains($needle, $description))) {
                continue;
            }
            $minutes = (int) round((float) $row['expected_minutes']);

            return max(0, $minutes);
        }

        return null;
    }

    /**
     * @return array{date: string, label: string}|null
     */
    public function earliestCompletion(int $jobId): ?array
    {
        $stages = $this->planning->stagesForJob($jobId);
        $minutes = [];
        foreach ($stages as $stage) {
            if ((string) $stage['status'] === 'COMPLETE') {
                continue;
            }
            $estimate = (int) ($stage['estimated_minutes'] ?? 0);
            if ($estimate > 0) {
                $minutes[] = $estimate;
            }
        }
        if ($minutes === []) {
            return null;
        }
        $schedule = $this->planning->defaultSchedule() ?? [];
        $finish = $this->capacity->earliestFinish(time(), $minutes, $schedule, []);

        return [
            'date' => date('Y-m-d', $finish),
            'label' => 'Estimated earliest completion',
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array{conflicts: list<array<string, mixed>>, warnings: list<string>}
     */
    private function checkResources(array $parsed, int $excludeId): array
    {
        $conflicts = [];
        $warnings = [];
        $confirmed = in_array($parsed['status'], ['CONFIRMED', 'IN_PROGRESS'], true);
        foreach ($parsed['resource_ids'] as $resourceId) {
            $resource = $this->planning->lockResource($resourceId);
            $date = substr($parsed['start'], 0, 10);
            $schedule = $resource === null ? null : ($this->planning->scheduleForResource($resourceId, $date) ?? $this->planning->defaultSchedule());
            $exception = $this->planning->exceptionOn($date);
            $away = $resource === null ? [] : $this->planning->unavailabilityBetween($resourceId, $parsed['start'], $parsed['end']);
            $unavailable = $away[0]['reason_type'] ?? '';
            $facts = [
                'exists' => $resource !== null,
                'active' => $resource !== null && (int) $resource['active'] === 1,
                'status' => $resource['status'] ?? 'AVAILABLE',
                'concurrent_capacity' => $resource['concurrent_capacity'] ?? 1,
                'overlaps' => $resource === null ? 0 : $this->planning->overlapCount($resourceId, $parsed['start'], $parsed['end'], $excludeId),
                'unavailable_reason' => $unavailable,
                'non_working_day' => $exception !== null && (int) $exception['working_day_override'] !== 1,
                'exception_name' => $exception['name'] ?? '',
                'outside_hours' => $schedule !== null && !$this->capacity->insideWorkingHours($schedule, $parsed['start'], $parsed['end'], $exception),
            ];
            $facts = array_merge($facts, $this->workFacts($parsed, $confirmed));
            $result = $this->conflicts->assess($facts);
            foreach ($result['reasons'] as $reason) {
                $reason['resource_id'] = $resourceId;
                $reason['resource_name'] = (string) ($resource['name'] ?? '');
                $conflicts[] = $reason;
            }
        }
        if (!$confirmed && $parsed['job_id'] !== null) {
            $state = $this->readiness->materialState(
                $this->planning->requiredQuantity((int) $parsed['job_id']),
                $this->planning->reservedQuantity((int) $parsed['job_id'])
            );
            if ($state === 'SHORTAGE') {
                $warnings[] = 'Material shortage. This booking stays tentative until stock is reserved or the start is authorised.';
            }
        }

        return ['conflicts' => $conflicts, 'warnings' => $warnings];
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, mixed>
     */
    private function workFacts(array $parsed, bool $confirmed): array
    {
        $facts = [
            'dependency_open' => false,
            'artwork_blocked' => false,
            'material_shortage' => false,
        ];
        if (!$confirmed) {
            return $facts;
        }
        if ($parsed['entity_type'] === 'PRODUCTION_STAGE') {
            $open = $this->planning->openPredecessors((int) $parsed['entity_id']);
            if ($open !== []) {
                $facts['dependency_open'] = true;
                $facts['dependency_message'] = (string) $open[0]['stage_name'] . ' must finish before this step can be confirmed.';
            }
        }
        if ($parsed['job_id'] !== null) {
            $job = $this->planning->job((int) $parsed['job_id']);
            if ($job !== null && $this->planning->artworkRequiredCount((int) $parsed['job_id']) > 0 && empty($job['artwork_override_by']) && $this->planning->approvedArtworkCount((int) $parsed['job_id']) === 0) {
                $stage = $parsed['entity_type'] === 'PRODUCTION_STAGE' ? $this->planning->stage((int) $parsed['entity_id']) : null;
                $name = strtolower((string) ($stage['stage_name'] ?? ''));
                if (!str_contains($name, 'artwork') && !str_contains($name, 'design')) {
                    $facts['artwork_blocked'] = true;
                }
            }
            if ($this->readiness->materialState($this->planning->requiredQuantity((int) $parsed['job_id']), $this->planning->reservedQuantity((int) $parsed['job_id'])) === 'SHORTAGE') {
                $facts['material_shortage'] = true;
            }
        }

        return $facts;
    }

    /**
     * @param array{conflicts: list<array<string, mixed>>, warnings: list<string>} $check
     * @param array<string, mixed> $parsed
     * @param array<string, mixed>|null $actor
     * @return array<string, mixed>
     */
    private function decide(array $check, array $parsed, ?array $actor): array
    {
        if ($check['conflicts'] === []) {
            return ['ok' => true, 'warnings' => $check['warnings'], 'override' => false, 'conflicts' => []];
        }
        $hard = false;
        foreach ($check['conflicts'] as $conflict) {
            if (empty($conflict['overridable'])) {
                $hard = true;
            }
        }
        $reason = trim((string) ($parsed['override_reason'] ?? ''));
        if (!$hard && $reason !== '' && AuthorizationService::allows($actor, 'schedule.override_conflict')) {
            return ['ok' => true, 'warnings' => $check['warnings'], 'override' => true, 'conflicts' => []];
        }

        return [
            'ok' => false,
            'error' => (string) ($check['conflicts'][0]['message'] ?? 'The schedule conflicts.'),
            'code' => (string) ($check['conflicts'][0]['code'] ?? 'CONFLICT'),
            'conflicts' => $check['conflicts'],
            'warnings' => $check['warnings'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function parse(array $input): array
    {
        $type = strtoupper(trim((string) ($input['entity_type'] ?? 'OTHER')));
        if (!in_array($type, self::ENTITY_TYPES, true)) {
            return ['error' => 'That schedule type is not supported.'];
        }
        $status = strtoupper(trim((string) ($input['status'] ?? 'PLANNED')));
        if (!in_array($status, self::STATUSES, true) || $status === 'CANCELLED') {
            return ['error' => 'Choose a schedule status.'];
        }
        $start = $this->dateTime((string) ($input['start_datetime'] ?? ''));
        $end = $this->dateTime((string) ($input['end_datetime'] ?? ''));
        if ($start === null || $end === null || $end <= $start) {
            return ['error' => 'Enter a start and an end, with the end after the start.'];
        }
        $ids = [];
        $primary = (int) ($input['resource_id'] ?? 0);
        if ($primary > 0) {
            $ids[] = $primary;
        }
        $extra = $input['resource_ids'] ?? [];
        if (!is_array($extra)) {
            $extra = [$extra];
        }
        foreach ($extra as $value) {
            $id = (int) $value;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return ['error' => 'Choose a resource.'];
        }
        $entityId = (int) ($input['entity_id'] ?? 0);
        if ($entityId < 1) {
            return ['error' => 'Choose the work being scheduled.'];
        }
        $jobId = (int) ($input['job_id'] ?? 0);
        $minutes = (int) ($input['estimated_minutes'] ?? 0);
        if ($minutes < 1) {
            $minutes = (int) round(($end - $start) / 60);
        }

        return [
            'entity_type' => $type,
            'entity_id' => $entityId,
            'job_id' => $jobId > 0 ? $jobId : null,
            'resource_ids' => $ids,
            'start' => date('Y-m-d H:i:s', $start),
            'end' => date('Y-m-d H:i:s', $end),
            'estimated_minutes' => $minutes,
            'status' => $status,
            'customer_visible' => (int) ($input['customer_visible'] ?? 0) === 1 ? 1 : 0,
            'notes' => $this->blank($input['notes'] ?? null),
            'override_reason' => trim((string) ($input['override_reason'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     * @return array<string, mixed>
     */
    private function entryRow(array $parsed, int $userId, bool $override): array
    {
        return [
            'entity_type' => $parsed['entity_type'],
            'entity_id' => $parsed['entity_id'],
            'job_id' => $parsed['job_id'],
            'resource_id' => $parsed['resource_ids'][0],
            'start_datetime' => $parsed['start'],
            'end_datetime' => $parsed['end'],
            'estimated_minutes' => $parsed['estimated_minutes'],
            'status' => $parsed['status'],
            'locked' => 0,
            'customer_visible' => $parsed['customer_visible'],
            'notes' => $parsed['notes'],
            'override_reason' => $override ? $parsed['override_reason'] : null,
            'override_by' => $override ? $userId : null,
            'override_at' => $override ? date('Y-m-d H:i:s') : null,
            'created_by' => $userId,
        ];
    }

    /**
     * @param list<int> $resourceIds
     */
    private function storeResources(int $entryId, array $resourceIds): void
    {
        $first = true;
        foreach ($resourceIds as $resourceId) {
            $this->planning->addEntryResource($entryId, $resourceId, $first ? 'PRIMARY' : 'ASSIGNED');
            $first = false;
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function notifyMove(array $entry, string $newStart, int $userId): void
    {
        $threshold = (int) SettingsService::get('schedule_change_notify_minutes', '30');
        $old = strtotime((string) $entry['start_datetime']);
        $new = strtotime($newStart);
        if ($old === false || $new === false) {
            return;
        }
        if ((int) round(abs($new - $old) / 60) < max(1, $threshold)) {
            return;
        }
        $resource = $this->planning->resource((int) $entry['resource_id']);
        $linked = (int) ($resource['linked_user_id'] ?? 0);
        if ($linked < 1 || $linked === $userId) {
            return;
        }
        $this->notifications->send(
            $linked,
            null,
            'SCHEDULE_CHANGED',
            'Your scheduled work moved',
            'A booking moved to ' . $newStart . '.',
            'schedule_entry',
            (int) $entry['id'],
            'NORMAL',
            'schedule-move-' . $entry['id'] . '-' . str_replace([' ', ':'], '', $newStart)
        );
    }

    private function dateTime(string $value): ?int
    {
        $value = trim(str_replace('T', ' ', $value));
        if ($value === '') {
            return null;
        }
        $parsed = strtotime($value);

        return $parsed === false ? null : $parsed;
    }

    private function date(string $value): ?string
    {
        $parsed = strtotime(trim($value));
        if ($parsed === false) {
            return null;
        }

        return date('Y-m-d', $parsed);
    }

    private function blank(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 255);
    }
}

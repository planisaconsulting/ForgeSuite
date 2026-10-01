<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ArtworkApprovalMethod;
use App\Domain\ArtworkStatus;
use App\Domain\DeliveryMethod;
use App\Domain\InstallationStatus;
use App\Domain\JobItemStatus;
use App\Domain\JobStatus;
use App\Domain\MaterialSource;
use App\Domain\OtherCostType;
use App\Domain\QcStatus;
use App\Domain\TaskStatus;
use App\Domain\TaskType;
use App\Domain\WorkType;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\JobRepository;
use App\Repositories\OperationsRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\UserRepository;

/**
 * Operational work on a job that already exists.
 *
 * Quote conversion calls seedFromQuote inside its own transaction.
 * Commercial snapshots are taken then and are not rewritten when the
 * catalogue or the quotation changes later.
 */
final class JobService
{
    public function __construct(
        private readonly JobRepository $jobs = new JobRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly QuoteRepository $quotes = new QuoteRepository(),
        private readonly QuoteTotals $totals = new QuoteTotals(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly UserRepository $users = new UserRepository(),
        private readonly SupplierRepository $suppliers = new SupplierRepository(),
        private readonly JobWorkflowService $workflow = new JobWorkflowService(),
        private readonly JobCostingService $costing = new JobCostingService(),
        private readonly MaterialUsageService $materials = new MaterialUsageService(stock: new JobStockHook()),
        private readonly AttachmentService $files = new AttachmentService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * Copy included quote lines onto the job and store the commercial baseline.
     *
     * @param array<string, mixed> $quote
     * @param array<string, mixed> $input
     */
    public function seedFromQuote(int $jobId, array $quote, array $input, int $userId): void
    {
        $lines = $this->quotes->items((int) $quote['id']);
        $summary = $this->totals->summarise($lines, $quote);
        $delivery = DeliveryMethod::normalise((string) ($input['delivery_method'] ?? ''));
        $installFlag = DeliveryMethod::from($delivery)->needsInstallation() ? 1 : 0;
        $opportunityId = (int) ($quote['opportunity_id'] ?? 0);
        $this->jobs->storeBaseline($jobId, [
            'opportunity_id' => $opportunityId > 0 ? $opportunityId : null,
            'description' => blank_to_null($input['description'] ?? null),
            'delivery_method' => $delivery,
            'quoted_revenue' => $summary['revenue'],
            'quoted_cost' => $summary['cost'],
            'production_due_date' => $this->dateOrNull($input['production_due_date'] ?? null),
            'installation_date' => $this->dateOrNull($input['installation_date'] ?? null),
            'site_address' => blank_to_null($input['site_address'] ?? null),
            'site_contact_name' => blank_to_null($input['site_contact_name'] ?? null),
            'site_contact_phone' => blank_to_null($input['site_contact_phone'] ?? null),
            'customer_po_number' => blank_to_null($input['customer_po_number'] ?? null),
        ]);
        $sort = 10;
        foreach ($lines as $line) {
            if (!$this->totals->included($line)) {
                continue;
            }
            $hour = strtoupper((string) ($line['pricing_method_snapshot'] ?? '')) === 'HOUR';
            $itemId = $this->ops->insertItem([
                'job_id' => $jobId,
                'quote_item_id' => (int) $line['id'],
                'sort_order' => $sort,
                'product_id' => !empty($line['product_id']) ? (int) $line['product_id'] : null,
                'description' => (string) ($line['customer_description'] ?: $line['product_name_snapshot']),
                'internal_description' => blank_to_null($line['internal_description'] ?? null),
                'width_mm' => $line['width_mm'],
                'height_mm' => $line['height_mm'],
                'length_mm' => $line['length_mm'],
                'quantity' => $line['quantity'],
                'production_status' => JobItemStatus::NotStarted->value,
                'artwork_required' => $hour ? 0 : 1,
                'installation_required' => $installFlag,
                'notes' => null,
            ]);
            $snapshot = (new \App\Repositories\RecipeRepository())->snapshotForItem((int) $line['id']);
            if ($snapshot !== null) {
                (new RecipeJobGenerator())->apply($jobId, $itemId, $snapshot, $userId);
                $sort += 10;
                continue;
            }
            $productId = (int) ($line['product_id'] ?? 0);
            $billable = (string) ($line['billable_quantity'] ?? '0');
            if ($productId > 0 && Decimal::isNumeric($billable) && Decimal::cmp($billable, '0') > 0) {
                $qty = Decimal::round($billable, 4);
                $unit = substr(trim((string) ($line['cost_unit_snapshot'] ?? 'unit')), 0, 20);
                $this->ops->insertRequirement([
                    'job_id' => $jobId,
                    'job_item_id' => $itemId,
                    'product_id' => $productId,
                    'required_quantity' => $qty,
                    'unit' => $unit === '' ? 'unit' : $unit,
                    'calculated_quantity' => $qty,
                    'manual_adjustment' => '0.0000',
                    'final_required_quantity' => $qty,
                    'source' => MaterialSource::Quote->value,
                    'notes' => 'Quoted billable quantity',
                    'created_by' => $userId,
                ]);
            }
            $sort += 10;
        }
        $this->jobs->insertHistory($jobId, null, JobStatus::New->value, $userId, 'Created from ' . (string) $quote['quote_number']);
        $this->audit->record('job', $jobId, 'JOB_CREATED', null, [
            'quote_id' => (int) $quote['id'],
            'revision' => (int) $quote['revision_number'],
            'quoted_revenue' => $summary['revenue'],
            'quoted_cost' => $summary['cost'],
        ], $userId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function saveOverview(int $jobId, array $input, int $version, int $userId): array
    {
        if (!can('jobs.edit')) {
            return ['_form' => 'You cannot edit this job.'];
        }
        try {
            Database::transaction(function () use ($jobId, $input, $version, $userId): void {
                $job = $this->locked($jobId, $version);
                if ((string) $job['status'] === JobStatus::Cancelled->value && !can('jobs.reopen')) {
                    throw new JobRejected(['_form' => 'A cancelled job stays locked.']);
                }
                $title = trim((string) ($input['title'] ?? ''));
                if ($title === '') {
                    throw new JobRejected(['title' => 'Job title is required.']);
                }
                $priority = strtoupper(trim((string) ($input['priority'] ?? 'NORMAL')));
                if (!in_array($priority, ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true)) {
                    throw new JobRejected(['priority' => 'Choose a priority.']);
                }
                $assigned = $this->userOrNull($input['assigned_to'] ?? null, 'assigned_to', 'Choose a staff member.');
                $manager = $this->userOrNull($input['project_manager_id'] ?? null, 'project_manager_id', 'Choose a project manager.');
                $data = [
                    'title' => $title,
                    'description' => blank_to_null($input['description'] ?? null),
                    'priority' => $priority,
                    'assigned_to' => $assigned,
                    'project_manager_id' => $manager,
                    'target_date' => $this->requireDate($input['target_date'] ?? null, 'target_date'),
                    'production_due_date' => $this->requireDate($input['production_due_date'] ?? null, 'production_due_date'),
                    'installation_date' => $this->requireDate($input['installation_date'] ?? null, 'installation_date'),
                    'delivery_method' => DeliveryMethod::normalise((string) ($input['delivery_method'] ?? '')),
                    'site_address' => blank_to_null($input['site_address'] ?? null),
                    'site_contact_name' => blank_to_null($input['site_contact_name'] ?? null),
                    'site_contact_phone' => blank_to_null($input['site_contact_phone'] ?? null),
                    'customer_po_number' => blank_to_null($input['customer_po_number'] ?? null),
                    'customer_notes' => blank_to_null($input['customer_notes'] ?? null),
                    'production_notes' => can('production.update') || can('jobs.edit')
                        ? blank_to_null($input['production_notes'] ?? $job['production_notes'])
                        : $job['production_notes'],
                    'installation_notes' => can('installations.schedule') || can('jobs.edit')
                        ? blank_to_null($input['installation_notes'] ?? $job['installation_notes'])
                        : $job['installation_notes'],
                    'internal_notes' => can('costing.view')
                        ? blank_to_null($input['internal_notes'] ?? null)
                        : $job['internal_notes'],
                ];
                $this->jobs->updateOverview($jobId, $data, $version);
                if ((int) ($job['assigned_to'] ?? 0) !== (int) ($assigned ?? 0)) {
                    $this->audit->record('job', $jobId, 'JOB_ASSIGNED', ['assigned_to' => $job['assigned_to']], [
                        'assigned_to' => $assigned,
                    ], $userId);
                }
            });
        } catch (JobRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function changeStatus(int $jobId, string $to, int $version, int $userId, string $notes = '', string $overrideReason = ''): array
    {
        if (!can('jobs.change_status') && !(strtoupper($to) === JobStatus::Completed->value && can('jobs.complete'))) {
            return ['_form' => 'You cannot change this job status.'];
        }
        if (strtoupper($to) === JobStatus::Completed->value && !can('jobs.complete')) {
            return ['_form' => 'Completing a job needs authorisation.'];
        }
        try {
            Database::transaction(function () use ($jobId, $to, $version, $userId, $notes, $overrideReason): void {
                $job = $this->locked($jobId, $version);
                $decision = $this->workflow->evaluate($job, $to, $notes, $overrideReason, $this->checks($job));
                if (!$decision['ok']) {
                    throw new JobRejected($decision['errors']);
                }
                $status = strtoupper(trim($to));
                $completedAt = $job['completed_at'];
                $completedBy = $job['completed_by'];
                if ($status === JobStatus::Completed->value) {
                    $completedAt = date('Y-m-d H:i:s');
                    $completedBy = $userId;
                } elseif ((string) $job['status'] === JobStatus::Completed->value) {
                    $completedAt = null;
                    $completedBy = null;
                }
                $this->jobs->updateStatus($jobId, [
                    'status' => $status,
                    'artwork_override_by' => $decision['artwork_override'] ? $userId : null,
                    'artwork_override_reason' => $decision['artwork_override'] ? trim($overrideReason) : null,
                    'artwork_override_at' => $decision['artwork_override'] ? date('Y-m-d H:i:s') : null,
                    'completion_override_by' => $decision['completion_override'] ? $userId : null,
                    'completion_override_reason' => $decision['completion_override'] ? trim($overrideReason) : null,
                    'completion_override_at' => $decision['completion_override'] ? date('Y-m-d H:i:s') : null,
                    'completed_at' => $completedAt,
                    'completed_by' => $completedBy,
                ], $version);
                $this->jobs->insertHistory($jobId, (string) $job['status'], $status, $userId, blank_to_null($notes));
                $action = 'JOB_STATUS_CHANGED';
                if ($status === JobStatus::Completed->value) {
                    $action = 'JOB_COMPLETED';
                } elseif (in_array((string) $job['status'], [JobStatus::Completed->value, JobStatus::Cancelled->value], true)) {
                    $action = 'JOB_REOPENED';
                }
                $this->audit->record('job', $jobId, $action, ['status' => $job['status']], [
                    'status' => $status,
                    'notes' => blank_to_null($notes),
                ], $userId);
                if ($decision['artwork_override']) {
                    $this->audit->record('job', $jobId, 'APPROVAL_OVERRIDDEN', null, [
                        'reason' => trim($overrideReason),
                    ], $userId);
                }
            });
        } catch (JobRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addTask(int $jobId, array $input, int $userId): array
    {
        if (!can('production.update') && !can('jobs.edit')) {
            return ['_form' => 'You cannot add tasks.'];
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['title' => 'Task title is required.'];
        }
        $type = strtoupper(trim((string) ($input['task_type'] ?? 'OTHER')));
        if (!in_array($type, TaskType::values(), true)) {
            $type = TaskType::Other->value;
        }
        $itemId = (int) ($input['job_item_id'] ?? 0);
        if ($itemId > 0 && !$this->itemOnJob($jobId, $itemId)) {
            return ['job_item_id' => 'That item is not on this job.'];
        }
        $this->ops->insertTask([
            'job_id' => $jobId,
            'job_item_id' => $itemId > 0 ? $itemId : null,
            'title' => $title,
            'description' => blank_to_null($input['description'] ?? null),
            'task_type' => $type,
            'assigned_to' => $this->optionalUser($input['assigned_to'] ?? null),
            'assigned_team_id' => $this->optionalTeam($input['assigned_team_id'] ?? null),
            'priority' => in_array(strtoupper((string) ($input['priority'] ?? 'NORMAL')), ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true)
                ? strtoupper((string) $input['priority']) : 'NORMAL',
            'status' => TaskStatus::Todo->value,
            'due_date' => $this->dateOrNull($input['due_date'] ?? null),
            'estimated_minutes' => $this->minutesOrNull($input['estimated_minutes'] ?? null),
            'sort_order' => 100,
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function updateTask(int $jobId, int $taskId, string $status, int $version, int $userId): array
    {
        if (!can('production.update') && !can('jobs.change_status')) {
            return ['_form' => 'You cannot update this task.'];
        }
        $status = strtoupper(trim($status));
        if (!in_array($status, TaskStatus::values(), true)) {
            return ['status' => 'That task status is not valid.'];
        }
        try {
            Database::transaction(function () use ($jobId, $taskId, $status, $version, $userId): void {
                $task = $this->ops->task($taskId);
                if ($task === null || (int) $task['job_id'] !== $jobId) {
                    throw new JobRejected(['_form' => 'That task is not on this job.']);
                }
                if ((int) $task['version_number'] !== $version) {
                    throw new JobConflictException('This task was modified by another user. Reload before saving.');
                }
                $started = $task['started_at'];
                $completed = $task['completed_at'];
                if ($status === TaskStatus::InProgress->value && $started === null) {
                    $started = date('Y-m-d H:i:s');
                }
                if ($status === TaskStatus::Complete->value) {
                    $completed = date('Y-m-d H:i:s');
                    if ($started === null) {
                        $started = $completed;
                    }
                }
                $affected = $this->ops->updateTask($taskId, $jobId, [
                    'status' => $status,
                    'assigned_to' => $task['assigned_to'],
                    'started_at' => $started,
                    'completed_at' => $status === TaskStatus::Complete->value ? $completed : ($status === TaskStatus::InProgress->value ? null : $task['completed_at']),
                    'actual_minutes' => $task['actual_minutes'],
                ], $version);
                if ($affected < 1) {
                    throw new JobConflictException('This task was modified by another user. Reload before saving.');
                }
                if ($status === TaskStatus::InProgress->value) {
                    $this->audit->record('job', $jobId, 'TASK_STARTED', null, ['task_id' => $taskId], $userId);
                }
                if ($status === TaskStatus::Complete->value) {
                    $this->audit->record('job', $jobId, 'TASK_COMPLETED', null, ['task_id' => $taskId], $userId);
                }
            });
        } catch (JobRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * Copy a template onto the job. The template itself is not changed.
     *
     * @return array<string, string>
     */
    public function applyTemplate(int $jobId, int $templateId, ?int $itemId, int $userId): array
    {
        if (!can('production.update')) {
            return ['_form' => 'You cannot apply a production route.'];
        }
        $stages = $this->ops->templateStages($templateId);
        if ($stages === []) {
            return ['template_id' => 'Choose a production route.'];
        }
        if ($itemId !== null && $itemId > 0 && !$this->itemOnJob($jobId, $itemId)) {
            return ['job_item_id' => 'That item is not on this job.'];
        }
        $itemId = ($itemId !== null && $itemId > 0) ? $itemId : null;
        foreach ($this->ops->stages($jobId) as $existing) {
            $existingItem = $existing['job_item_id'] === null ? null : (int) $existing['job_item_id'];
            if ($existingItem === $itemId) {
                return ['_form' => 'A production route is already on this job. Edit these stages. The template stays unchanged.'];
            }
        }
        Database::transaction(function () use ($jobId, $stages, $itemId, $userId): void {
            $sort = 10;
            foreach ($stages as $stage) {
                $this->ops->insertStage($jobId, $itemId, (int) $stage['production_stage_id'], $sort);
                $this->ops->insertTask([
                    'job_id' => $jobId,
                    'job_item_id' => $itemId,
                    'title' => (string) $stage['name'],
                    'description' => null,
                    'task_type' => TaskType::fromStageName((string) $stage['name']),
                    'assigned_to' => null,
                    'assigned_team_id' => null,
                    'priority' => 'NORMAL',
                    'status' => TaskStatus::Todo->value,
                    'due_date' => null,
                    'estimated_minutes' => null,
                    'sort_order' => $sort,
                    'created_by' => $userId,
                ]);
                $sort += 10;
            }
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function updateStage(int $jobId, int $stageId, string $status, int $userId): array
    {
        if (!can('production.update')) {
            return ['_form' => 'You cannot update production.'];
        }
        $status = strtoupper(trim($status));
        if (!in_array($status, ['NOT_STARTED', 'IN_PROGRESS', 'BLOCKED', 'COMPLETE'], true)) {
            return ['status' => 'That stage status is not valid.'];
        }
        $stage = $this->ops->stage($stageId);
        if ($stage === null || (int) $stage['job_id'] !== $jobId) {
            return ['_form' => 'That stage is not on this job.'];
        }
        $started = $stage['started_at'];
        $completed = $status === 'COMPLETE' ? date('Y-m-d H:i:s') : null;
        if ($status === 'IN_PROGRESS' && $started === null) {
            $started = date('Y-m-d H:i:s');
        }
        $this->ops->updateStage($stageId, $jobId, $status, $started, $completed, $userId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function updateItemStatus(int $jobId, int $itemId, string $status): array
    {
        if (!can('production.update')) {
            return ['_form' => 'You cannot update production items.'];
        }
        $status = strtoupper(trim($status));
        if (!in_array($status, JobItemStatus::values(), true)) {
            return ['status' => 'That item status is not valid.'];
        }
        if (!$this->itemOnJob($jobId, $itemId)) {
            return ['_form' => 'That item is not on this job.'];
        }
        $this->ops->updateItemStatus($itemId, $jobId, $status);

        return [];
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function storeArtwork(int $jobId, array $file, array $input, int $userId, bool $requireUpload = true): array
    {
        if (!can('artwork.upload')) {
            return ['_form' => 'You cannot upload artwork.'];
        }
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['title' => 'Name this proof.'];
        }
        $checked = $this->files->inspect($file, $requireUpload);
        if (!$checked['ok']) {
            return $checked['errors'];
        }
        $itemId = (int) ($input['job_item_id'] ?? 0);
        if ($itemId > 0 && !$this->itemOnJob($jobId, $itemId)) {
            return ['job_item_id' => 'That item is not on this job.'];
        }
        $stored = $this->files->place('artwork', $jobId, $checked, $requireUpload);
        if (isset($stored['errors'])) {
            return $stored['errors'];
        }
        Database::transaction(function () use ($jobId, $itemId, $title, $checked, $stored, $input, $userId): void {
            $revision = $this->ops->nextArtworkRevision($jobId, $title);
            $id = $this->ops->insertArtwork([
                'job_id' => $jobId,
                'job_item_id' => $itemId > 0 ? $itemId : null,
                'title' => $title,
                'revision_number' => $revision,
                'original_filename' => $checked['original'],
                'stored_filename' => $stored['relative'],
                'mime_type' => $checked['mime'],
                'file_size' => $checked['size'],
                'status' => ArtworkStatus::Draft->value,
                'uploaded_by' => $userId,
                'notes' => blank_to_null($input['notes'] ?? null),
            ]);
            $this->ops->supersedeTitle($jobId, $title, $id);
            $this->audit->record('job', $jobId, 'ARTWORK_UPLOADED', null, [
                'artwork_id' => $id,
                'title' => $title,
                'revision' => $revision,
            ], $userId);
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function setArtworkStatus(int $jobId, int $artworkId, string $status, int $version): array
    {
        if (!can('artwork.upload') && !can('artwork.approve_record')) {
            return ['_form' => 'You cannot change this proof.'];
        }
        $status = strtoupper(trim($status));
        if (!in_array($status, ArtworkStatus::values(), true) || $status === ArtworkStatus::Approved->value) {
            return ['status' => 'Record customer approval separately. Do not mark a proof approved from this list.'];
        }
        $art = $this->ops->artwork($artworkId);
        if ($art === null || (int) $art['job_id'] !== $jobId) {
            return ['_form' => 'That proof is not on this job.'];
        }
        if ($this->ops->setArtworkStatus($artworkId, $status, $version) < 1) {
            throw new JobConflictException('This proof was modified by another user. Reload before saving.');
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function recordApproval(int $jobId, int $artworkId, array $input, int $userId): array
    {
        if (!can('artwork.approve_record')) {
            return ['_form' => 'You cannot record artwork approval.'];
        }
        $name = trim((string) ($input['customer_name'] ?? ''));
        $method = strtoupper(trim((string) ($input['approval_method'] ?? '')));
        if ($name === '') {
            return ['customer_name' => 'Who approved the proof?'];
        }
        if (ArtworkApprovalMethod::tryFrom($method) === null) {
            return ['approval_method' => 'Choose how the approval was received.'];
        }
        $art = $this->ops->artwork($artworkId);
        if ($art === null || (int) $art['job_id'] !== $jobId) {
            return ['_form' => 'That proof is not on this job.'];
        }
        if ((string) $art['status'] === ArtworkStatus::Superseded->value) {
            return ['_form' => 'Approve the current revision. This one has been superseded.'];
        }
        Database::transaction(function () use ($artworkId, $jobId, $name, $method, $input, $userId): void {
            $this->ops->insertApproval([
                'job_artwork_id' => $artworkId,
                'approval_status' => ArtworkStatus::Approved->value,
                'customer_name' => $name,
                'approval_method' => $method,
                'reference' => blank_to_null($input['reference'] ?? null),
                'notes' => blank_to_null($input['notes'] ?? null),
                'approved_at' => date('Y-m-d H:i:s'),
                'recorded_by' => $userId,
            ]);
            $this->ops->markArtworkApproved($artworkId, $name);
            $this->audit->record('job', $jobId, 'ARTWORK_APPROVED', null, [
                'artwork_id' => $artworkId,
                'customer_name' => $name,
                'approval_method' => $method,
            ], $userId);
        });

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function recordMaterial(int $jobId, array $input, int $userId): array
    {
        $job = $this->jobs->find($jobId);
        if ($job === null) {
            return ['errors' => ['_form' => 'That job was not found.'], 'id' => null];
        }
        if ((string) $job['status'] === JobStatus::Cancelled->value) {
            return ['errors' => ['_form' => 'A cancelled job stays locked.'], 'id' => null];
        }

        return $this->materials->record($jobId, $input, $userId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addRequirement(int $jobId, array $input, int $userId): array
    {
        if (!can('materials.record_usage') && !can('jobs.edit')) {
            return ['_form' => 'You cannot add material requirements.'];
        }
        $productId = (int) ($input['product_id'] ?? 0);
        $product = $productId > 0 ? $this->products->find($productId) : null;
        if ($product === null) {
            return ['product_id' => 'Choose a product.'];
        }
        $quantity = trim((string) ($input['quantity'] ?? ''));
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
            return ['quantity' => 'Enter a quantity greater than zero.'];
        }
        $itemId = (int) ($input['job_item_id'] ?? 0);
        if ($itemId > 0 && !$this->itemOnJob($jobId, $itemId)) {
            return ['job_item_id' => 'That item is not on this job.'];
        }
        $qty = Decimal::round($quantity, 4);
        $unit = substr(trim((string) ($product['cost_unit'] ?? 'unit')), 0, 20);
        $this->ops->insertRequirement([
            'job_id' => $jobId,
            'job_item_id' => $itemId > 0 ? $itemId : null,
            'product_id' => $productId,
            'required_quantity' => $qty,
            'unit' => $unit === '' ? 'unit' : $unit,
            'calculated_quantity' => $qty,
            'manual_adjustment' => '0.0000',
            'final_required_quantity' => $qty,
            'source' => MaterialSource::Manual->value,
            'notes' => blank_to_null($input['notes'] ?? null),
            'created_by' => $userId,
        ]);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addTime(int $jobId, array $input, int $userId): array
    {
        if (!can('time.record')) {
            return ['_form' => 'You cannot record time.'];
        }
        $work = strtoupper(trim((string) ($input['work_type'] ?? '')));
        if (WorkType::tryFrom($work) === null) {
            return ['work_type' => 'Choose the kind of work.'];
        }
        $hours = (int) ($input['hours'] ?? 0);
        $mins = (int) ($input['minutes'] ?? 0);
        if ($hours < 0 || $mins < 0 || $mins > 59 || ($hours === 0 && $mins === 0)) {
            return ['minutes' => 'Enter a duration.'];
        }
        $minutes = ($hours * 60) + $mins;
        $itemId = (int) ($input['job_item_id'] ?? 0);
        if ($itemId > 0 && !$this->itemOnJob($jobId, $itemId)) {
            return ['job_item_id' => 'That item is not on this job.'];
        }
        $taskId = (int) ($input['task_id'] ?? 0);
        if ($taskId > 0) {
            $task = $this->ops->task($taskId);
            if ($task === null || (int) $task['job_id'] !== $jobId) {
                return ['task_id' => 'That task is not on this job.'];
            }
        }
        $rate = $this->hourlyCost($userId);
        $total = Decimal::money(Decimal::mul($rate, Decimal::div((string) $minutes, '60')));
        Database::transaction(function () use ($jobId, $itemId, $taskId, $userId, $work, $minutes, $rate, $total, $input): void {
            $this->ops->insertTime([
                'job_id' => $jobId,
                'job_item_id' => $itemId > 0 ? $itemId : null,
                'task_id' => $taskId > 0 ? $taskId : null,
                'user_id' => $userId,
                'work_type' => $work,
                'started_at' => date('Y-m-d H:i:s'),
                'ended_at' => date('Y-m-d H:i:s'),
                'minutes' => $minutes,
                'hourly_cost_snapshot' => $rate,
                'total_cost' => $total,
                'description' => blank_to_null($input['description'] ?? null),
            ]);
            $this->audit->record('job', $jobId, 'TIME_RECORDED', null, [
                'minutes' => $minutes,
                'work_type' => $work,
            ], $userId);
            $this->costing->refresh($jobId);
        });

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function startTimer(int $jobId, array $input, int $userId): array
    {
        if (!can('time.record')) {
            return ['_form' => 'You cannot record time.'];
        }
        if ($this->ops->openTimer($userId) !== null) {
            return ['_form' => 'Stop the timer that is already running.'];
        }
        $work = strtoupper(trim((string) ($input['work_type'] ?? 'OTHER')));
        if (WorkType::tryFrom($work) === null) {
            $work = WorkType::Other->value;
        }
        $this->ops->insertTime([
            'job_id' => $jobId,
            'job_item_id' => null,
            'task_id' => null,
            'user_id' => $userId,
            'work_type' => $work,
            'started_at' => date('Y-m-d H:i:s'),
            'ended_at' => null,
            'minutes' => 0,
            'hourly_cost_snapshot' => $this->hourlyCost($userId),
            'total_cost' => '0.00',
            'description' => blank_to_null($input['description'] ?? null),
        ]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function stopTimer(int $userId): array
    {
        if (!can('time.record')) {
            return ['_form' => 'You cannot record time.'];
        }
        $open = $this->ops->openTimer($userId);
        if ($open === null) {
            return ['_form' => 'No timer is running.'];
        }
        $started = strtotime((string) $open['started_at']);
        $seconds = $started === false ? 0 : max(0, time() - $started);
        $minutes = (int) round($seconds / 60);
        $rate = Decimal::money((string) $open['hourly_cost_snapshot']);
        $total = Decimal::money(Decimal::mul($rate, Decimal::div((string) $minutes, '60')));
        $affected = $this->ops->closeTime((int) $open['id'], $userId, date('Y-m-d H:i:s'), $minutes, $total);
        if ($affected < 1) {
            return ['_form' => 'That timer was already stopped.'];
        }
        $this->audit->record('job', (int) $open['job_id'], 'TIME_RECORDED', null, [
            'minutes' => $minutes,
            'timer' => true,
        ], $userId);
        $this->costing->refresh((int) $open['job_id']);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addOtherCost(int $jobId, array $input, int $userId): array
    {
        if (!can('costing.edit')) {
            return ['_form' => 'You cannot add other costs.'];
        }
        $type = strtoupper(trim((string) ($input['cost_type'] ?? '')));
        if (OtherCostType::tryFrom($type) === null) {
            return ['cost_type' => 'Choose a cost type.'];
        }
        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            return ['description' => 'Describe this cost.'];
        }
        $quantity = trim((string) ($input['quantity'] ?? '1'));
        $unit = trim((string) ($input['unit_cost'] ?? ''));
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
            return ['quantity' => 'Enter a quantity greater than zero.'];
        }
        if (!Decimal::isNumeric($unit) || Decimal::cmp($unit, '0') < 0) {
            return ['unit_cost' => 'Enter a unit cost.'];
        }
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        if ($supplierId > 0 && $this->suppliers->find($supplierId) === null) {
            return ['supplier_id' => 'That supplier was not found.'];
        }
        $total = Decimal::money(Decimal::mul($quantity, $unit));
        Database::transaction(function () use ($jobId, $type, $description, $supplierId, $quantity, $unit, $total, $input, $userId): void {
            $this->ops->insertOther([
                'job_id' => $jobId,
                'cost_type' => $type,
                'description' => $description,
                'supplier_id' => $supplierId > 0 ? $supplierId : null,
                'quantity' => Decimal::round($quantity, 4),
                'unit_cost' => Decimal::money($unit),
                'total_cost' => $total,
                'reference' => blank_to_null($input['reference'] ?? null),
                'created_by' => $userId,
            ]);
            $this->costing->refresh($jobId);
        });

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function recordQuality(int $jobId, array $input, int $userId): array
    {
        if (!can('production.update')) {
            return ['_form' => 'You cannot record quality checks.'];
        }
        $check = trim((string) ($input['check_type'] ?? ''));
        $status = strtoupper(trim((string) ($input['status'] ?? '')));
        if ($check === '') {
            return ['check_type' => 'Choose a check.'];
        }
        if (QcStatus::tryFrom($status) === null) {
            return ['status' => 'Choose a result.'];
        }
        $itemId = (int) ($input['job_item_id'] ?? 0);
        if ($itemId > 0 && !$this->itemOnJob($jobId, $itemId)) {
            return ['job_item_id' => 'That item is not on this job.'];
        }
        Database::transaction(function () use ($jobId, $itemId, $check, $status, $input, $userId): void {
            $checkId = $this->ops->insertQuality([
                'job_id' => $jobId,
                'job_item_id' => $itemId > 0 ? $itemId : null,
                'check_type' => $check,
                'status' => $status,
                'notes' => blank_to_null($input['notes'] ?? null),
                'rework_task_id' => null,
                'checked_by' => $userId,
            ]);
            if (in_array($status, [QcStatus::Fail->value, QcStatus::ReworkRequired->value], true)) {
                $taskId = $this->ops->insertTask([
                    'job_id' => $jobId,
                    'job_item_id' => $itemId > 0 ? $itemId : null,
                    'title' => 'Rework: ' . $check,
                    'description' => blank_to_null($input['notes'] ?? null),
                    'task_type' => TaskType::Other->value,
                    'assigned_to' => null,
                    'assigned_team_id' => null,
                    'priority' => 'HIGH',
                    'status' => TaskStatus::Todo->value,
                    'due_date' => null,
                    'estimated_minutes' => null,
                    'sort_order' => 200,
                    'created_by' => $userId,
                ]);
                $this->ops->linkRework($checkId, $taskId);
                $this->audit->record('job', $jobId, 'QC_FAILED', null, [
                    'check' => $check,
                    'status' => $status,
                ], $userId);
                $this->audit->record('job', $jobId, 'REWORK_CREATED', null, [
                    'task_id' => $taskId,
                    'check' => $check,
                ], $userId);
            }
        });

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function scheduleInstallation(int $jobId, array $input, int $userId): array
    {
        if (!can('installations.schedule')) {
            return ['errors' => ['_form' => 'You cannot schedule an installation.'], 'id' => null];
        }
        $job = $this->jobs->find($jobId);
        if ($job === null) {
            return ['errors' => ['_form' => 'That job was not found.'], 'id' => null];
        }
        $date = $this->dateOrNull($input['scheduled_date'] ?? null);
        $teamId = $this->optionalTeam($input['assigned_team_id'] ?? null);
        $user = $this->optionalUser($input['assigned_user_id'] ?? null);
        $status = $date === null ? InstallationStatus::NotScheduled->value : InstallationStatus::Scheduled->value;
        $id = 0;
        Database::transaction(function () use ($job, $jobId, $input, $date, $teamId, $user, $status, $userId, &$id): void {
            $id = $this->ops->insertInstallation([
                'job_id' => $jobId,
                'scheduled_date' => $date,
                'scheduled_start_time' => $this->timeOrNull($input['scheduled_start_time'] ?? null),
                'estimated_duration_minutes' => $this->minutesOrNull($input['estimated_duration_minutes'] ?? null),
                'site_address' => blank_to_null($input['site_address'] ?? null) ?? $job['site_address'],
                'site_contact_name' => blank_to_null($input['site_contact_name'] ?? null) ?? $job['site_contact_name'],
                'site_contact_phone' => blank_to_null($input['site_contact_phone'] ?? null) ?? $job['site_contact_phone'],
                'assigned_team_id' => $teamId,
                'assigned_user_id' => $user,
                'status' => $status,
                'installation_notes' => blank_to_null($input['installation_notes'] ?? null) ?? $job['installation_notes'],
                'created_by' => $userId,
            ]);
            $sort = 10;
            foreach ($this->ops->checklistTemplate() as $row) {
                $this->ops->insertChecklistItem($id, (string) $row['label'], $sort);
                $sort += 10;
            }
            $this->audit->record('job', $jobId, 'INSTALLATION_SCHEDULED', null, [
                'installation_id' => $id,
                'scheduled_date' => $date,
            ], $userId);
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function updateInstallation(int $jobId, int $installationId, array $input, int $version, int $userId): array
    {
        $status = strtoupper(trim((string) ($input['status'] ?? '')));
        if (InstallationStatus::tryFrom($status) === null) {
            return ['status' => 'That installation status is not valid.'];
        }
        $completing = $status === InstallationStatus::Complete->value;
        if ($completing && !can('installations.complete')) {
            return ['_form' => 'You cannot complete an installation.'];
        }
        if (!$completing && !can('installations.schedule') && !can('installations.complete')) {
            return ['_form' => 'You cannot update this installation.'];
        }
        try {
            Database::transaction(function () use ($jobId, $installationId, $input, $version, $userId, $status, $completing): void {
                $row = $this->ops->installation($installationId);
                if ($row === null || (int) $row['job_id'] !== $jobId) {
                    throw new JobRejected(['_form' => 'That installation is not on this job.']);
                }
                if ((int) $row['version_number'] !== $version) {
                    throw new JobConflictException('This installation was modified by another user. Reload before saving.');
                }
                $signoff = trim((string) ($input['customer_signoff_name'] ?? ''));
                $affected = $this->ops->updateInstallation($installationId, $jobId, [
                    'status' => $status,
                    'scheduled_date' => $this->dateOrNull($input['scheduled_date'] ?? $row['scheduled_date']),
                    'scheduled_start_time' => $this->timeOrNull($input['scheduled_start_time'] ?? $row['scheduled_start_time']),
                    'assigned_team_id' => $this->optionalTeam($input['assigned_team_id'] ?? $row['assigned_team_id']),
                    'assigned_user_id' => $this->optionalUser($input['assigned_user_id'] ?? $row['assigned_user_id']),
                    'arrival_time' => $status === InstallationStatus::OnSite->value && $row['arrival_time'] === null
                        ? date('Y-m-d H:i:s') : $row['arrival_time'],
                    'started_at' => in_array($status, [InstallationStatus::InProgress->value, InstallationStatus::Complete->value], true) && $row['started_at'] === null
                        ? date('Y-m-d H:i:s') : $row['started_at'],
                    'completed_at' => $completing ? date('Y-m-d H:i:s') : $row['completed_at'],
                    'completion_notes' => blank_to_null($input['completion_notes'] ?? $row['completion_notes']),
                    'customer_signoff_name' => $signoff === '' ? $row['customer_signoff_name'] : $signoff,
                    'signoff_date' => $signoff === '' ? $row['signoff_date'] : date('Y-m-d'),
                    'signoff_notes' => blank_to_null($input['signoff_notes'] ?? $row['signoff_notes']),
                ], $version);
                if ($affected < 1) {
                    throw new JobConflictException('This installation was modified by another user. Reload before saving.');
                }
                if ($completing) {
                    $this->audit->record('job', $jobId, 'INSTALLATION_COMPLETED', null, [
                        'installation_id' => $installationId,
                        'customer_signoff_name' => $signoff,
                    ], $userId);
                }
            });
        } catch (JobRejected $e) {
            return $e->errors;
        }

        return [];
    }

    public function toggleChecklist(int $jobId, int $installationId, int $itemId, bool $checked, int $userId): array
    {
        if (!can('installations.complete') && !can('installations.schedule')) {
            return ['_form' => 'You cannot update the checklist.'];
        }
        $row = $this->ops->installation($installationId);
        if ($row === null || (int) $row['job_id'] !== $jobId) {
            return ['_form' => 'That installation is not on this job.'];
        }
        $this->ops->toggleChecklist($itemId, $installationId, $checked ? 1 : 0, $userId);

        return [];
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, string>
     */
    public function completionPhoto(int $jobId, int $installationId, array $file, string $notes, int $userId): array
    {
        if (!can('attachments.manage') && !can('installations.complete')) {
            return ['_form' => 'You cannot upload completion photos.'];
        }
        $row = $this->ops->installation($installationId);
        if ($row === null || (int) $row['job_id'] !== $jobId) {
            return ['_form' => 'That installation is not on this job.'];
        }

        return $this->files->store('installation', $installationId, $file, $userId, 'COMPLETION_PHOTO', $notes);
    }

    /**
     * @return array<string, string>
     */
    public function archive(int $jobId, bool $archived, int $userId): array
    {
        if (!can('jobs.complete')) {
            return ['_form' => 'You cannot archive a job.'];
        }
        $job = $this->jobs->find($jobId);
        if ($job === null) {
            return ['_form' => 'That job was not found.'];
        }
        $this->jobs->setArchived($jobId, $archived ? 1 : 0);
        $this->audit->record('job', $jobId, $archived ? 'JOB_ARCHIVED' : 'JOB_RESTORED', null, [], $userId);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function saveCatalogueStage(array $input): array
    {
        if (!can('settings.manage')) {
            return ['_form' => 'You cannot change production stages.'];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['name' => 'Stage name is required.'];
        }
        try {
            $this->ops->insertCatalogueStage($name, trim((string) ($input['description'] ?? '')), (int) ($input['sort_order'] ?? 0));
        } catch (\Throwable) {
            return ['name' => 'That stage already exists.'];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function saveTemplate(array $input): array
    {
        if (!can('settings.manage')) {
            return ['_form' => 'You cannot change production routes.'];
        }
        $name = trim((string) ($input['name'] ?? ''));
        $code = strtoupper(preg_replace('/[^A-Z0-9_]+/', '_', strtoupper(trim((string) ($input['code'] ?? '')))) ?? '');
        $code = trim($code, '_');
        if ($name === '' || $code === '') {
            return ['name' => 'Name and code are required.'];
        }
        $stageIds = $input['stage_ids'] ?? [];
        if (!is_array($stageIds) || $stageIds === []) {
            return ['stage_ids' => 'Choose at least one stage.'];
        }
        try {
            Database::transaction(function () use ($code, $name, $input, $stageIds): void {
                $id = $this->ops->insertTemplate($code, $name, blank_to_null($input['description'] ?? null));
                $sort = 10;
                foreach ($stageIds as $stageId) {
                    $stageId = (int) $stageId;
                    if ($stageId < 1) {
                        continue;
                    }
                    $this->ops->insertTemplateStage($id, $stageId, $sort);
                    $sort += 10;
                }
            });
        } catch (\Throwable) {
            return ['_form' => 'That route code is already in use, or a stage was chosen twice.'];
        }

        return [];
    }

    public function setTeamMember(int $teamId, int $userId, bool $member): array
    {
        if (!can('settings.manage')) {
            return ['_form' => 'You cannot change teams.'];
        }
        if ($this->optionalTeam($teamId) === null || $this->users->find($userId) === null) {
            return ['_form' => 'That team or user was not found.'];
        }
        $this->ops->setTeamMember($teamId, $userId, $member);

        return [];
    }

    /**
     * @param array<string, mixed> $job
     * @return array{artwork_blocking: bool, items_open: bool, qc_blocking: bool, installation_open: bool}
     */
    public function checks(array $job): array
    {
        $id = (int) $job['id'];
        $delivery = DeliveryMethod::tryFrom((string) ($job['delivery_method'] ?? '')) ?? DeliveryMethod::Installation;

        return [
            'artwork_blocking' => !$this->ops->artworkApproved($id),
            'items_open' => $this->ops->itemsOpen($id),
            'qc_blocking' => $this->ops->qcBlocking($id),
            'installation_open' => $delivery->needsInstallation() && !$this->ops->installationComplete($id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function locked(int $id, int $version): array
    {
        $job = $this->jobs->lock($id);
        if ($job === null) {
            throw new JobRejected(['_form' => 'That job was not found.']);
        }
        if ((int) $job['version_number'] !== $version) {
            throw new JobConflictException('This job was modified by another user. Reload before saving.');
        }

        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    private function userOrNull(mixed $value, string $field, string $message): ?int
    {
        $id = (int) $value;
        if ($id < 1) {
            return null;
        }
        if ($this->users->find($id) === null) {
            throw new JobRejected([$field => $message]);
        }

        return $id;
    }

    private function optionalUser(mixed $value): ?int
    {
        $id = (int) $value;
        if ($id < 1 || $this->users->find($id) === null) {
            return null;
        }

        return $id;
    }

    private function optionalTeam(mixed $value): ?int
    {
        $id = (int) $value;
        if ($id < 1) {
            return null;
        }
        foreach ($this->ops->teams() as $team) {
            if ((int) $team['id'] === $id) {
                return $id;
            }
        }

        return null;
    }

    private function itemOnJob(int $jobId, int $itemId): bool
    {
        $item = $this->ops->item($itemId);

        return $item !== null && (int) $item['job_id'] === $jobId;
    }

    private function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function requireDate(mixed $value, string $field): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $date = $this->dateOrNull($raw);
        if ($date === null) {
            throw new JobRejected([$field => 'Enter a date.']);
        }

        return $date;
    }

    private function timeOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }

    private function minutesOrNull(mixed $value): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function hourlyCost(int $userId): string
    {
        $user = $this->users->find($userId);
        $rate = $user['hourly_cost'] ?? null;
        if ($rate === null || $rate === '') {
            $rate = SettingsService::get('default_labour_hourly_cost', '0') ?? '0';
        }
        if (!Decimal::isNumeric((string) $rate)) {
            $rate = '0';
        }

        return Decimal::money((string) $rate);
    }
}

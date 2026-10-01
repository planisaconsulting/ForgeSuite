<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ProjectRepository;

/**
 * Creates and updates a project. Jobs, invoices, and stock stay in their own services.
 */
final class ProjectService
{
    /** @var list<string> */
    public const STATUSES = ['DRAFT', 'PLANNING', 'ACTIVE', 'ON_HOLD', 'AT_RISK', 'COMPLETED', 'CANCELLED', 'ARCHIVED'];

    public function __construct(
        private readonly ProjectRepository $projects = new ProjectRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly ProjectMilestoneService $milestones = new ProjectMilestoneService(),
        private readonly ProjectFinancialService $financials = new ProjectFinancialService(),
        private readonly ProjectProgressService $progress = new ProjectProgressService(),
        private readonly ProjectHealthService $health = new ProjectHealthService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $templateId = (int) ($input['template_id'] ?? 0);
        $template = $templateId > 0 ? $this->projects->template($templateId) : null;
        $id = 0;
        Database::transaction(function () use ($input, $userId, $template, &$id): void {
            $target = $this->date($input['target_date'] ?? null);
            $id = $this->projects->insert([
                'project_number' => $this->numbers->project(),
                'customer_id' => (int) $input['customer_id'],
                'name' => mb_substr(trim((string) $input['name']), 0, 180),
                'description' => $this->blank($input['description'] ?? null),
                'project_type_id' => $this->nullableInt($input['project_type_id'] ?? null),
                'template_id' => $template === null ? null : (int) $template['id'],
                'template_version_copied' => $template === null ? null : (int) $template['template_version'],
                'status' => 'DRAFT',
                'priority' => $this->priority($input['priority'] ?? 'NORMAL'),
                'account_manager_user_id' => $this->nullableInt($input['account_manager_user_id'] ?? null),
                'project_manager_user_id' => $this->nullableInt($input['project_manager_user_id'] ?? null),
                'salesperson_user_id' => $this->nullableInt($input['salesperson_user_id'] ?? null),
                'primary_contact_id' => $this->nullableInt($input['primary_contact_id'] ?? null),
                'start_date' => $this->date($input['start_date'] ?? null),
                'original_target_date' => $target,
                'current_target_date' => $target,
                'currency_code' => 'ZAR',
                'commercial_mode' => $this->mode($input['commercial_mode'] ?? 'PROJECT'),
                'source_opportunity_id' => $this->nullableInt($input['source_opportunity_id'] ?? null),
                'source_quote_id' => $this->nullableInt($input['source_quote_id'] ?? null),
                'created_by' => $userId,
            ]);
            if ($template !== null) {
                $this->milestones->copyTemplate($id, (int) $template['id']);
            }
            $opportunityId = $this->nullableInt($input['source_opportunity_id'] ?? null);
            if ($opportunityId !== null) {
                $this->projects->linkOpportunity($opportunityId, $id);
            }
            $quoteId = $this->nullableInt($input['source_quote_id'] ?? null);
            if ($quoteId !== null) {
                $this->projects->linkQuote($quoteId, $id, null);
            }
            $manager = $this->nullableInt($input['project_manager_user_id'] ?? null);
            if ($manager !== null) {
                $this->projects->assignTeam($id, $manager, 'PROJECT_MANAGER');
            }
        });
        $this->audit->record('project', $id, 'PROJECT_CREATED', null, ['name' => trim((string) $input['name'])], $userId);
        BusinessEventDispatcher::emit('PROJECT_CREATED', 'PROJECT', $id, $userId, []);
        $this->refreshCache($id);
        $manager = $this->nullableInt($input['project_manager_user_id'] ?? null);
        if ($manager !== null) {
            $project = $this->projects->find($id);
            $this->notifications->send(
                $manager,
                null,
                'PROJECT_ASSIGNED',
                'Project assigned',
                (string) ($project['project_number'] ?? 'Project') . ' is assigned to you.',
                'project',
                $id,
                'NORMAL',
                'project-assign-' . $id . '-' . $manager
            );
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input, int $version, int $userId): array
    {
        $existing = $this->projects->find($id);
        if ($existing === null) {
            return ['_form' => 'That project was not found.'];
        }
        $errors = $this->validate($input);
        if ($errors !== []) {
            return $errors;
        }
        $status = strtoupper(trim((string) ($input['status'] ?? $existing['status'])));
        if (!in_array($status, self::STATUSES, true) || in_array($status, ['COMPLETED', 'ARCHIVED'], true)) {
            $status = (string) $existing['status'];
        }
        $ok = $this->projects->update($id, [
            'name' => mb_substr(trim((string) $input['name']), 0, 180),
            'description' => $this->blank($input['description'] ?? null),
            'project_type_id' => $this->nullableInt($input['project_type_id'] ?? null),
            'status' => $status,
            'priority' => $this->priority($input['priority'] ?? 'NORMAL'),
            'account_manager_user_id' => $this->nullableInt($input['account_manager_user_id'] ?? null),
            'project_manager_user_id' => $this->nullableInt($input['project_manager_user_id'] ?? null),
            'salesperson_user_id' => $this->nullableInt($input['salesperson_user_id'] ?? null),
            'primary_contact_id' => $this->nullableInt($input['primary_contact_id'] ?? null),
            'start_date' => $this->date($input['start_date'] ?? null),
            'current_target_date' => $existing['current_target_date'],
            'commercial_mode' => $this->mode($input['commercial_mode'] ?? $existing['commercial_mode']),
        ], $version);
        if (!$ok) {
            return ['_form' => 'Someone else saved this project. Reload and try again.'];
        }
        if ($status !== (string) $existing['status']) {
            $this->audit->record('project', $id, 'PROJECT_STATUS', ['status' => $existing['status']], ['status' => $status], $userId);
            BusinessEventDispatcher::emit('PROJECT_STATUS_CHANGED', 'PROJECT', $id, $userId, ['status' => $status]);
            if ($status === 'ACTIVE' && (string) $existing['status'] === 'DRAFT') {
                BusinessEventDispatcher::emit('PROJECT_STARTED', 'PROJECT', $id, $userId, []);
            }
        }
        $this->refreshCache($id);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function shiftTarget(int $projectId, string $newDate, string $reason, ?string $notes, int $version, int $userId): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            return ['current_target_date' => 'Enter a valid target date.'];
        }
        $reason = strtoupper(trim($reason));
        if ($reason === '') {
            $reason = 'OTHER';
        }
        if (SettingsService::get('project_date_reason_required', '0') === '1' && $reason === 'OTHER' && trim((string) $notes) === '') {
            return ['reason' => 'Add a reason for the date change.'];
        }
        $project = $this->projects->find($projectId);
        if ($project === null) {
            return ['_form' => 'That project was not found.'];
        }
        $ok = $this->projects->shiftProjectDate($projectId, $newDate, $version);
        if (!$ok) {
            return ['_form' => 'Someone else saved this project. Reload and try again.'];
        }
        $this->projects->insertDateChange([
            'project_id' => $projectId,
            'entity_type' => 'PROJECT',
            'entity_id' => $projectId,
            'original_date' => $project['original_target_date'],
            'previous_date' => $project['current_target_date'],
            'new_date' => $newDate,
            'reason_code' => mb_substr($reason, 0, 40),
            'notes' => $this->blank($notes),
            'changed_by' => $userId,
        ]);
        $this->audit->record('project', $projectId, 'PROJECT_DATE', [
            'current_target_date' => $project['current_target_date'],
        ], [
            'current_target_date' => $newDate,
            'reason' => $reason,
        ], $userId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function shiftSiteTarget(int $siteId, string $newDate, string $reason, ?string $notes, int $version, int $userId): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            return ['current_target_date' => 'Enter a valid target date.'];
        }
        $site = $this->projects->site($siteId);
        if ($site === null) {
            return ['_form' => 'That site was not found.'];
        }
        $ok = $this->projects->shiftSiteDate($siteId, $newDate, $version);
        if (!$ok) {
            return ['_form' => 'Someone else saved this site. Reload and try again.'];
        }
        $this->projects->insertDateChange([
            'project_id' => (int) $site['project_id'],
            'entity_type' => 'SITE',
            'entity_id' => $siteId,
            'original_date' => $site['original_target_date'],
            'previous_date' => $site['current_target_date'],
            'new_date' => $newDate,
            'reason_code' => strtoupper(trim($reason)) ?: 'OTHER',
            'notes' => $this->blank($notes),
            'changed_by' => $userId,
        ]);
        $this->audit->record('project_site', $siteId, 'SITE_DATE', null, ['current_target_date' => $newDate], $userId);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addWave(int $projectId, array $input, int $userId): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['name' => 'Wave name is required.'];
        }
        $this->projects->insertWave([
            'project_id' => $projectId,
            'name' => mb_substr($name, 0, 160),
            'sequence' => max(1, (int) ($input['sequence'] ?? 1)),
            'planned_start' => $this->date($input['planned_start'] ?? null),
            'planned_end' => $this->date($input['planned_end'] ?? null),
            'status' => 'PLANNED',
        ]);
        $this->audit->record('project', $projectId, 'SITE_WAVE', null, ['name' => $name], $userId);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addRisk(int $projectId, array $input, int $userId): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['title' => 'Risk title is required.'];
        }
        $probability = max(1, min(5, (int) ($input['probability'] ?? 1)));
        $impact = max(1, min(5, (int) ($input['impact'] ?? 1)));
        $id = $this->projects->insertRisk([
            'project_id' => $projectId,
            'project_site_id' => $this->nullableInt($input['project_site_id'] ?? null),
            'title' => mb_substr($title, 0, 180),
            'description' => $this->blank($input['description'] ?? null),
            'category' => strtoupper(trim((string) ($input['category'] ?? 'OTHER'))) ?: 'OTHER',
            'probability' => $probability,
            'impact' => $impact,
            'owner_user_id' => $this->nullableInt($input['owner_user_id'] ?? null),
            'mitigation' => $this->blank($input['mitigation'] ?? null),
            'due_date' => $this->date($input['due_date'] ?? null),
        ]);
        $this->audit->record('project', $projectId, 'RISK_CREATED', null, ['risk_id' => $id, 'score' => $probability * $impact], $userId);
        BusinessEventDispatcher::emit('PROJECT_RISK_CREATED', 'PROJECT', $projectId, $userId, ['risk_id' => $id]);

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addIssue(int $projectId, array $input, int $userId): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['title' => 'Issue title is required.'];
        }
        $id = $this->projects->insertIssue([
            'project_id' => $projectId,
            'project_site_id' => $this->nullableInt($input['project_site_id'] ?? null),
            'job_id' => $this->nullableInt($input['job_id'] ?? null),
            'milestone_id' => $this->nullableInt($input['milestone_id'] ?? null),
            'title' => mb_substr($title, 0, 180),
            'description' => $this->blank($input['description'] ?? null),
            'category' => strtoupper(trim((string) ($input['category'] ?? 'OTHER'))) ?: 'OTHER',
            'owner_user_id' => $this->nullableInt($input['owner_user_id'] ?? null),
        ]);
        $this->audit->record('project', $projectId, 'ISSUE_CREATED', null, ['issue_id' => $id], $userId);
        BusinessEventDispatcher::emit('PROJECT_ISSUE_CREATED', 'PROJECT', $projectId, $userId, ['issue_id' => $id]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function resolveIssue(int $issueId, string $status, int $version, int $userId): array
    {
        $status = strtoupper($status);
        if (!in_array($status, ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'], true)) {
            return ['status' => 'That issue status is not valid.'];
        }
        $issue = $this->projects->issue($issueId);
        if ($issue === null) {
            return ['_form' => 'That issue was not found.'];
        }
        if (!$this->projects->setIssueStatus($issueId, $status, $version)) {
            return ['_form' => 'Someone else updated this issue. Reload and try again.'];
        }
        $this->audit->record('project', (int) $issue['project_id'], 'ISSUE_' . $status, null, ['issue_id' => $issueId], $userId);
        if ($status === 'RESOLVED') {
            BusinessEventDispatcher::emit('PROJECT_ISSUE_RESOLVED', 'PROJECT', (int) $issue['project_id'], $userId, ['issue_id' => $issueId]);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function proposeChange(int $projectId, array $input, int $userId): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            return ['title' => 'Change title is required.'];
        }
        $commercial = $this->money($input['commercial_impact'] ?? '0');
        if ($commercial === null) {
            return ['commercial_impact' => 'Commercial impact must be an amount.'];
        }
        $cost = $this->money($input['cost_impact'] ?? '0');
        if ($cost === null) {
            return ['cost_impact' => 'Cost impact must be an amount.'];
        }
        $gate = (new ApprovalService())->gate('PROJECT', $projectId, 'CHANGE', $userId, [
            'commercial_impact' => $commercial,
        ], $title);
        $status = $gate['blocked'] ? 'AWAITING_APPROVAL' : 'DRAFT';
        $number = 'PC-' . str_pad((string) $this->projects->nextChangeNumber($projectId), 3, '0', STR_PAD_LEFT);
        $this->projects->insertChange([
            'project_id' => $projectId,
            'change_number' => $number,
            'title' => mb_substr($title, 0, 180),
            'description' => $this->blank($input['description'] ?? null),
            'status' => $status,
            'commercial_impact' => $commercial,
            'cost_impact' => $cost,
            'schedule_impact_days' => (int) ($input['schedule_impact_days'] ?? 0),
            'sites_affected' => max(0, (int) ($input['sites_affected'] ?? 0)),
            'approval_request_id' => $gate['request_id'],
            'created_by' => $userId,
        ]);
        $this->audit->record('project', $projectId, 'COMMERCIAL_CHANGE_DRAFT', null, ['number' => $number], $userId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function decideChange(int $changeId, string $decision, int $version, int $userId): array
    {
        $decision = strtoupper($decision);
        if (!in_array($decision, ['APPROVED', 'DECLINED', 'CANCELLED'], true)) {
            return ['status' => 'That decision is not valid.'];
        }
        $change = $this->projects->change($changeId);
        if ($change === null) {
            return ['_form' => 'That change was not found.'];
        }
        if (!$this->projects->setChangeStatus($changeId, $decision, $version)) {
            return ['_form' => 'Someone else updated this change. Reload and try again.'];
        }
        if ($decision === 'APPROVED') {
            $this->financials->recordChangeValue(
                (int) $change['project_id'],
                $changeId,
                (string) $change['commercial_impact'],
                (string) $change['title']
            );
            $this->refreshCache((int) $change['project_id']);
            BusinessEventDispatcher::emit('PROJECT_CHANGE_APPROVED', 'PROJECT', (int) $change['project_id'], $userId, ['change_id' => $changeId]);
        }
        $this->audit->record('project', (int) $change['project_id'], 'COMMERCIAL_CHANGE_' . $decision, null, ['change_id' => $changeId], $userId);

        return [];
    }

    public function linkJob(int $projectId, int $jobId, ?int $siteId, string $treatment, int $userId): array
    {
        $treatment = strtoupper($treatment);
        if (!in_array($treatment, ['INCLUDED_IN_CONTRACT', 'ADDITIONAL', 'STANDALONE'], true)) {
            $treatment = 'ADDITIONAL';
        }
        $this->projects->attachJob($jobId, $projectId, $siteId, $treatment, 'STANDARD', null, null);
        $this->audit->record('project', $projectId, 'SITE_JOB_LINKED', null, ['job_id' => $jobId, 'treatment' => $treatment], $userId);
        $this->refreshCache($projectId);

        return [];
    }

    public function refreshCache(int $projectId): void
    {
        $before = $this->projects->find($projectId);
        $money = $this->financials->statement($projectId);
        $progress = $this->progress->calculate($projectId);
        $health = $this->health->assess($projectId);
        $this->projects->cacheFigures(
            $projectId,
            $money['commercial_value'],
            $money['estimated_cost'],
            $money['actual_cost'],
            $progress['percent'],
            $health['health'],
            implode("\n", $health['reasons'])
        );
        if ($health['health'] === 'AT_RISK' && (string) ($before['project_health'] ?? '') !== 'AT_RISK') {
            BusinessEventDispatcher::emit('PROJECT_AT_RISK', 'PROJECT', $projectId, null, ['reasons' => count($health['reasons'])]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function workspace(int $projectId): array
    {
        $project = $this->projects->find($projectId);
        if ($project === null) {
            return [];
        }
        $this->refreshCache($projectId);
        $project = $this->projects->find($projectId) ?? $project;

        return [
            'project' => $project,
            'counts' => $this->projects->counts($projectId),
            'financials' => $this->financials->statement($projectId),
            'progress' => $this->progress->calculate($projectId),
            'health' => $this->health->assess($projectId),
            'sites' => $this->projects->sites($projectId),
            'jobs' => $this->projects->jobs($projectId, null),
            'milestones' => $this->projects->milestones($projectId),
            'next' => $this->projects->nextMilestone($projectId),
            'attention_sites' => $this->projects->attentionSites($projectId),
            'attention_jobs' => $this->projects->attentionJobs($projectId),
            'waves' => $this->projects->waves($projectId),
            'risks' => $this->projects->risks($projectId),
            'issues' => $this->projects->issues($projectId),
            'notes' => $this->projects->notes($projectId, false),
            'team' => $this->projects->team($projectId),
            'contacts' => $this->projects->contacts($projectId),
            'documents' => $this->projects->documents($projectId, false),
            'changes' => $this->projects->changes($projectId),
            'budgets' => $this->projects->budgets($projectId),
            'costs' => $this->projects->costs($projectId),
            'materials' => $this->projects->materialDemand($projectId, null, null),
            'purchasing' => $this->projects->purchaseSummary($projectId),
            'production' => $this->projects->productionCounts($projectId),
            'installations' => $this->projects->installationCounts($projectId),
            'snags' => $this->projects->snagSummary($projectId),
            'dates' => $this->projects->dateChanges($projectId),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];
        if ((int) ($input['customer_id'] ?? 0) < 1) {
            $errors['customer_id'] = 'Choose a customer.';
        }
        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors['name'] = 'Project name is required.';
        }

        return $errors;
    }

    private function priority(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));

        return in_array($value, ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true) ? $value : 'NORMAL';
    }

    private function mode(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));

        return $value === 'SITE_JOB' ? 'SITE_JOB' : 'PROJECT';
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
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

    private function money(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '0.00';
        }
        if (!Decimal::isNumeric($raw)) {
            return null;
        }

        return Decimal::round($raw, 2);
    }
}

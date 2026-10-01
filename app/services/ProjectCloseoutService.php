<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProjectRepository;

/**
 * Operational completion is separate from financial closure.
 * An open invoice blocks completion only when that rule is turned on.
 */
final class ProjectCloseoutService
{
    public function __construct(
        private readonly ProjectRepository $projects = new ProjectRepository(),
        private readonly ProjectFinancialService $financials = new ProjectFinancialService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array{blockers: list<string>, warnings: list<string>, ready: bool}
     */
    public function review(int $projectId): array
    {
        $blockers = [];
        $warnings = [];
        $sites = $this->projects->incompleteSites($projectId);
        if ($sites > 0) {
            $blockers[] = $sites . ' site' . ($sites === 1 ? '' : 's') . ' are not complete.';
        }
        $jobs = $this->projects->incompleteJobs($projectId);
        if ($jobs > 0) {
            $blockers[] = $jobs . ' job' . ($jobs === 1 ? '' : 's') . ' are not complete.';
        }
        $snags = $this->projects->snagSummary($projectId);
        if ((int) $snags['critical_snags'] > 0 && SettingsService::get('project_closeout_block_critical_snags', '1') === '1') {
            $blockers[] = (int) $snags['critical_snags'] . ' critical snag' . ((int) $snags['critical_snags'] === 1 ? '' : 's') . ' still open.';
        } elseif ((int) $snags['open_snags'] > 0) {
            $warnings[] = (int) $snags['open_snags'] . ' snag' . ((int) $snags['open_snags'] === 1 ? '' : 's') . ' still open.';
        }
        $money = $this->financials->statement($projectId);
        if (\App\Helpers\Decimal::cmp($money['outstanding'], '0') > 0) {
            $message = 'Outstanding invoices: ' . $money['outstanding'] . '. This is cash still to collect, not a reason by itself to keep the sites open.';
            if (SettingsService::get('project_closeout_block_open_invoices', '0') === '1') {
                $blockers[] = $message;
            } else {
                $warnings[] = $message;
            }
        }
        $purchases = $this->projects->openPurchaseCommitments($projectId);
        if ($purchases > 0) {
            $warnings[] = $purchases . ' purchase request' . ($purchases === 1 ? '' : 's') . ' still open.';
        }
        $risks = $this->projects->openRisks($projectId);
        if ($risks > 0) {
            $warnings[] = $risks . ' open risk' . ($risks === 1 ? '' : 's') . '.';
        }
        $issues = $this->projects->openIssues($projectId);
        if ($issues > 0) {
            $warnings[] = $issues . ' open issue' . ($issues === 1 ? '' : 's') . '.';
        }
        $handover = 0;
        foreach ($this->projects->documents($projectId, false) as $document) {
            if ((string) $document['category'] === 'HANDOVER') {
                $handover++;
            }
        }
        if ($handover === 0) {
            $warnings[] = 'No handover document is filed on the project.';
        }

        return ['blockers' => $blockers, 'warnings' => $warnings, 'ready' => $blockers === []];
    }

    /**
     * @return array<string, string>
     */
    public function complete(int $projectId, string $notes, int $userId, bool $acknowledgeWarnings): array
    {
        $review = $this->review($projectId);
        if (!$review['ready']) {
            return ['_form' => 'Closeout is blocked. ' . implode(' ', $review['blockers'])];
        }
        if ($review['warnings'] !== [] && !$acknowledgeWarnings) {
            return ['_form' => 'Read the financial and snag warnings, then confirm operational completion.'];
        }
        $this->projects->complete($projectId, $userId, trim($notes), date('Y-m-d'));
        $this->audit->record('project', $projectId, 'PROJECT_COMPLETED', null, ['notes' => trim($notes)], $userId);
        BusinessEventDispatcher::emit('PROJECT_COMPLETED', 'PROJECT', $projectId, $userId, []);
        $project = $this->projects->find($projectId);
        if ($project !== null && (int) ($project['project_manager_user_id'] ?? 0) > 0) {
            (new NotificationService())->send(
                (int) $project['project_manager_user_id'],
                null,
                'PROJECT_COMPLETED',
                'Project completed',
                (string) $project['project_number'] . ' is operationally complete.',
                'project',
                $projectId,
                'NORMAL',
                'project-complete-' . $projectId
            );
        }

        return [];
    }

    /**
     * Customer-safe handover. Costs, margin, risks, and internal notes are omitted.
     *
     * @return array<string, mixed>
     */
    public function handover(int $projectId): array
    {
        $project = $this->projects->find($projectId);
        if ($project === null) {
            return [];
        }
        $sites = [];
        foreach ($this->projects->sites($projectId) as $site) {
            $sites[] = [
                'code' => (string) $site['site_code'],
                'name' => (string) $site['site_name'],
                'status' => (string) $site['status'],
                'completed' => (string) ($site['actual_completion_date'] ?? ''),
                'city' => (string) ($site['city'] ?? ''),
            ];
        }

        return [
            'project_number' => (string) $project['project_number'],
            'name' => (string) $project['name'],
            'customer' => trim((string) ($project['company_name'] ?: ($project['first_name'] . ' ' . $project['last_name']))),
            'description' => (string) ($project['description'] ?? ''),
            'completed' => (string) ($project['actual_completion_date'] ?? ''),
            'sites' => $sites,
            'documents' => $this->projects->documents($projectId, true),
            'notes' => $this->projects->notes($projectId, true),
        ];
    }
}

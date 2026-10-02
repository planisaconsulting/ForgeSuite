<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ExpenseRepository;

/**
 * My Work, search, and data-quality rows point at the records that own the state.
 */
final class OperationalWorkspaceService
{
    public function __construct(
        private readonly ExpenseRepository $repo = new ExpenseRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return list<array<string, string>>
     */
    public function inbox(int $userId): array
    {
        $items = [];
        if (can('expenses.approve')) {
            foreach ($this->repo->awaitingApproval(30) as $row) {
                if ((int) $row['submitted_by'] === $userId && !can('expenses.view_all')) {
                    continue;
                }
                $items[] = [
                    'section' => 'Approvals',
                    'title' => 'Expense ' . $row['expense_number'],
                    'detail' => (string) $row['description'],
                    'due' => (string) $row['expense_date'],
                    'priority' => 'Normal',
                    'href' => '/expenses/' . $row['id'],
                ];
            }
        }
        $today = date('Y-m-d');
        foreach ($this->repo->tasksBetween('2000-01-01', $today, $userId) as $task) {
            if ((string) $task['due_date'] >= $today || in_array((string) $task['status'], ['DONE', 'COMPLETE', 'COMPLETED', 'CANCELLED'], true)) {
                continue;
            }
            $priority = strtoupper((string) ($task['priority'] ?? 'NORMAL'));
            $items[] = [
                'section' => 'Overdue',
                'title' => (string) $task['title'],
                'detail' => (string) $task['job_number'],
                'due' => (string) $task['due_date'],
                'priority' => in_array($priority, ['URGENT', 'HIGH', 'NORMAL', 'LOW'], true) ? ucfirst(strtolower($priority)) : 'Normal',
                'href' => '/work',
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, string>>
     */
    public function search(string $term, int $userId): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $prefix = str_replace(['%', '_'], '', $term) . '%';
        $hits = [];
        if (can('expenses.view')) {
            foreach ($this->repo->searchExpenses($prefix, 15, can('expenses.view_all') ? null : $userId) as $row) {
                $hits[] = $this->hit('Expense', (string) $row['expense_number'], (string) $row['description'], (string) ($row['merchant_name'] ?? ''), (string) $row['status'], (string) $row['expense_date'], '/expenses/' . $row['id']);
            }
        }
        if (can('jobs.view')) {
            foreach ($this->repo->searchJobs($prefix, 15) as $row) {
                $hits[] = $this->hit('Job', (string) $row['job_number'], (string) $row['title'], '', (string) $row['status'], (string) $row['created_at'], '/jobs/' . $row['id']);
            }
        }
        if (can('invoices.view')) {
            foreach ($this->repo->searchInvoices($prefix, 15) as $row) {
                $hits[] = $this->hit('Invoice', (string) ($row['invoice_number'] ?? ''), (string) ($row['customer_name_snapshot'] ?? ''), '', (string) $row['status'], (string) $row['invoice_date'], '/invoices/' . $row['id']);
            }
        }

        return $hits;
    }

    /**
     * @return array{created: int, issues: list<array<string, mixed>>}
     */
    public function scanDataQuality(int $userId): array
    {
        if (!can('data_quality.manage') && !can('data_quality.view')) {
            return ['created' => 0, 'issues' => []];
        }
        $created = 0;
        foreach ($this->repo->staleProductionFiles() as $row) {
            $key = 'stale-file-' . $row['file_id'];
            $saved = $this->repo->insertIssue(
                $key,
                'BLOCKING',
                'JOB',
                (int) $row['job_id'],
                'Released job ' . $row['job_number'] . ' still points at superseded file ' . $row['version_label'] . '.',
                '/jobs/' . $row['job_id']
            );
            if ($saved) {
                $created++;
                $this->audit->record('data_quality', (int) $row['job_id'], 'DATA_QUALITY_ISSUE_CREATED', null, ['key' => $key], $userId);
                BusinessEventDispatcher::emit('DATA_QUALITY_ISSUE_CREATED', 'JOB', (int) $row['job_id'], $userId, ['key' => $key]);
            }
        }

        return ['created' => $created, 'issues' => $this->repo->openIssues(50)];
    }

    /**
     * @return array<string, mixed>
     */
    public function costTrace(int $jobId): array
    {
        $report = (new JobCostingService())->report($jobId);

        return [
            'quoted_revenue' => $report['quoted_revenue'] ?? '0.00',
            'quoted_cost' => $report['quoted_cost'] ?? '0.00',
            'estimated_cost' => $report['quoted_cost'] ?? '0.00',
            'actual_material_cost' => $report['actual_material_cost'] ?? '0.00',
            'actual_labour_cost' => $report['actual_labour_cost'] ?? '0.00',
            'actual_other_cost' => $report['actual_other_cost'] ?? '0.00',
            'actual_total_cost' => $report['actual_total_cost'] ?? '0.00',
            'gross_profit' => $report['actual_profit'] ?? '0.00',
            'margin_percent' => $report['actual_margin_percent'] ?? null,
            'other_lines' => $this->repo->otherLines($jobId),
            'mileage' => $this->repo->mileageForJob($jobId),
            'logistics' => $this->repo->logisticsLines($jobId),
        ];
    }

    public function favourite(int $userId, string $type, int $entityId, string $label): void
    {
        $this->repo->favourite($userId, strtoupper($type), $entityId, mb_substr($label, 0, 180));
    }

    /**
     * @return list<array{name: string, entity: string, status: string}>
     */
    public function suggestedViews(): array
    {
        return [
            ['name' => 'My open quotes', 'entity' => 'QUOTE', 'status' => 'DRAFT'],
            ['name' => 'Installations this week', 'entity' => 'INSTALLATION', 'status' => 'SCHEDULED'],
            ['name' => 'Expenses awaiting approval', 'entity' => 'EXPENSE', 'status' => 'SUBMITTED'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function hit(string $type, string $number, string $title, string $context, string $status, string $date, string $href): array
    {
        return [
            'type' => $type,
            'number' => $number,
            'title' => $title,
            'context' => $context,
            'status' => $status,
            'date' => $date,
            'href' => $href,
        ];
    }
}

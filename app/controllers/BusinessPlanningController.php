<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Decimal;
use App\Helpers\View;
use App\Repositories\InventoryRepository;
use App\Repositories\ForecastRepository;
use App\Services\ApiClientService;
use App\Services\BacklogService;
use App\Services\BudgetService;
use App\Services\CapacityForecastService;
use App\Services\CashForecastService;
use App\Services\DataQualityService;
use App\Services\ForecastSnapshotService;
use App\Services\ImportService;
use App\Services\InvoiceForecastService;
use App\Services\MaterialPlanningService;
use App\Services\PlanningMath;
use App\Services\PurchaseRecommendationService;
use App\Services\SalesForecastService;
use App\Services\ScenarioService;

/**
 * Planning screens keep actual, committed, forecast, and target figures apart.
 */
final class BusinessPlanningController
{
    public function overview(): void
    {
        $sales = (new SalesForecastService())->report('30');
        $backlog = (new BacklogService())->report();
        $cash = (new CashForecastService())->report('DUE_DATE');
        View::render('planning/page', [
            'title' => 'Business overview',
            'activeNav' => 'planning',
            'generated_at' => $sales['generated_at'],
            'sections' => [
                $this->figure('Actual is not on this card', 'These totals are labelled. They are not one revenue number.'),
                $this->figure('Weighted pipeline', $sales['weighted_pipeline'], 'Open opportunities × entered probability. This is not expected revenue.'),
                $this->figure('Raw pipeline', $sales['raw_pipeline'], 'Open opportunity value before probability.'),
                $this->figure('Backlog', $backlog['total'], $backlog['formula']),
                $this->figure('Contractual receivables', $cash['contractual_receivables'], $cash['warning']),
            ],
        ]);
    }

    public function sales(): void
    {
        $report = (new SalesForecastService())->report((string) ($_GET['horizon'] ?? '30'));
        (new ForecastSnapshotService())->store('SALES', date('Y-m-d'), $report['horizon']['start'], $report['horizon']['end'], ['horizon' => $report['horizon']['label']], [
            'weighted_pipeline' => $report['weighted_pipeline'],
            'raw_pipeline' => $report['raw_pipeline'],
        ], (int) auth_user()['id']);
        $rows = [];
        foreach ($report['rows'] as $row) {
            $rows[] = [$row['number'], $row['bucket'], $row['value'], $row['probability'] ?? '—', $row['weighted'], $row['close']];
        }
        View::render('planning/page', [
            'title' => 'Sales forecast',
            'activeNav' => 'planning-sales',
            'generated_at' => $report['generated_at'],
            'sections' => [
                $this->figure('Weighted pipeline', $report['weighted_pipeline'], 'Not expected revenue.'),
                $this->figure('Quoted pipeline', $report['quoted_pipeline'], 'Open quote totals. No probability is assigned from quote age.'),
                $this->table(['Opportunity', 'Bucket', 'Value', 'Probability', 'Weighted', 'Expected close'], $rows),
            ],
            'notes' => $report['assumptions'],
        ]);
    }

    public function backlog(): void
    {
        $report = (new BacklogService())->report();
        $rows = [];
        foreach ($report['rows'] as $row) {
            $rows[] = [$row['job_number'], $row['commercial'], $row['completed_scope'], $row['backlog'], $row['invoiced'], $row['bucket'], $row['status']];
        }
        View::render('planning/page', [
            'title' => 'Backlog',
            'activeNav' => 'planning-backlog',
            'generated_at' => $report['generated_at'],
            'sections' => [
                $this->figure('Backlog', $report['total'], $report['formula']),
                $this->table(['Job', 'Commercial', 'Completed scope', 'Backlog', 'Invoiced', 'Period', 'Status'], $rows),
            ],
        ]);
    }

    public function cash(): void
    {
        $scenario = (string) ($_GET['scenario'] ?? 'DUE_DATE');
        $report = (new CashForecastService())->report($scenario);
        $rows = [];
        foreach ($report['receivable_lines'] as $line) {
            $rows[] = [$line['invoice_number'], $line['amount'], $line['due_date'], $line['basis']];
        }
        $schedule = (new InvoiceForecastService())->schedule();
        $forecastRows = [];
        foreach ($schedule['lines'] as $line) {
            $forecastRows[] = [$line['job_number'], $line['amount'], $line['expected_date'], $line['basis']];
        }
        View::render('planning/page', [
            'title' => 'Cash visibility',
            'activeNav' => 'planning-cash',
            'generated_at' => $report['generated_at'],
            'sections' => [
                $this->figure('Contractual receivables', $report['contractual_receivables'], 'Issued invoices. Scenario: ' . $report['scenario']),
                $this->figure('Forecast future invoices', $report['forecast_future_invoices'], $report['forecast_note']),
                $this->figure('Purchasing cash requirement', $report['purchasing_cash'], 'Approved purchase orders. Not an accounting payable.'),
                $this->table(['Invoice', 'Outstanding', 'Due', 'Basis'], $rows),
                $this->table(['Job', 'Potential amount', 'Expected date', 'Basis'], $forecastRows),
            ],
            'notes' => array_merge([$report['warning']], $report['assumptions'], $schedule['assumptions']),
        ]);
    }

    public function materials(): void
    {
        $planning = new ForecastRepository();
        $inventory = new InventoryRepository();
        $rows = [];
        foreach ($planning->firmDemand() as $row) {
            $onHand = $inventory->onHand((int) $row['product_id']);
            $reserved = $inventory->reserved((int) $row['product_id']);
            $available = Decimal::sub($onHand, $reserved);
            $rows[] = [
                (string) $row['product_name'],
                (string) $row['job_number'],
                'FIRM',
                (string) $row['quantity'],
                (string) $row['required_by'],
                $available,
            ];
        }
        View::render('planning/page', [
            'title' => 'Material planning',
            'activeNav' => 'planning-materials',
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => [
                $this->table(['Product', 'Source', 'Category', 'Quantity', 'Required by', 'Available'], $rows),
            ],
            'notes' => ['Firm demand comes from accepted jobs that are not complete. Forecast demand is not included in this list.'],
        ]);
    }

    public function mrp(): void
    {
        View::render('planning/page', [
            'title' => 'MRP',
            'activeNav' => 'planning-mrp',
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => [
                $this->table(
                    ['Product', 'Order quantity', 'Required by', 'Order by', 'Status'],
                    array_map(static fn (array $row): array => [
                        (string) $row['product_name'],
                        (string) $row['order_quantity'],
                        (string) ($row['required_by_date'] ?? ''),
                        (string) ($row['recommended_order_date'] ?? ''),
                        (string) $row['status'],
                    ], (new ForecastRepository())->recommendations())
                ),
            ],
            'notes' => [
                'Net requirement = gross firm demand + safety stock − available stock − incoming stock that arrives on or before the required date.',
                'A recommendation does not create a supplier order until someone converts it.',
            ],
            'action' => can('mrp.manage') ? ['href' => '/planning/mrp/refresh', 'label' => 'Refresh now', 'method' => 'post'] : null,
        ]);
    }

    public function refreshMrp(): void
    {
        $ids = (new PurchaseRecommendationService())->refresh((int) auth_user()['id']);
        flash('success', count($ids) . ' recommendation(s) prepared. No purchase order was sent.');
        redirect('/planning/mrp');
    }

    public function purchasing(): void
    {
        $this->mrp();
    }

    public function reviewRecommendation(string $id): void
    {
        $errors = (new PurchaseRecommendationService())->review((int) $id, (string) ($_POST['status'] ?? ''), (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Recommendation updated.' : (string) reset($errors));
        redirect('/planning/mrp');
    }

    public function convertRecommendation(string $id): void
    {
        $result = (new PurchaseRecommendationService())->createRequest((int) $id, (int) auth_user()['id']);
        flash($result['id'] !== null ? 'success' : 'error', $result['id'] !== null ? 'Purchase request created. The supplier order was not issued.' : (string) reset($result['errors']));
        redirect('/planning/mrp');
    }

    public function capacity(): void
    {
        $summary = (new CapacityForecastService())->summarise(
            (string) ($_GET['available'] ?? '0'),
            (string) ($_GET['scheduled'] ?? '0'),
            (string) ($_GET['unscheduled'] ?? '0'),
            (string) ($_GET['forecast'] ?? '0')
        );
        View::render('planning/page', [
            'title' => 'Capacity forecast',
            'activeNav' => 'planning-capacity',
            'generated_at' => (string) $summary['generated_at'],
            'sections' => [
                $this->figure('Available', (string) $summary['available_hours'], 'Hours available in the period.'),
                $this->figure('Scheduled', (string) $summary['scheduled_hours'], 'Committed work already on the schedule.'),
                $this->figure('Unscheduled committed', (string) $summary['unscheduled_committed_hours'], 'Accepted work that is not on the schedule yet.'),
                $this->figure('Remaining committed capacity', (string) $summary['remaining_committed_hours'], 'Available minus scheduled minus unscheduled committed.'),
                $this->figure('Forecast pipeline', (string) $summary['forecast_hours'], 'Shown separately. It is not committed workload.'),
            ],
            'notes' => [(string) $summary['assumptions'], (string) $summary['bottleneck']],
        ]);
    }

    public function calendar(): void
    {
        $horizon = PlanningMath::horizon((string) ($_GET['horizon'] ?? '30'));
        $planning = new ForecastRepository();
        $rows = [];
        foreach ($planning->calendarQuotes($horizon['start'], $horizon['end']) as $row) {
            $rows[] = [(string) $row['on_date'], 'Quote decision', (string) $row['quote_number'], '/quotes/' . $row['id']];
        }
        foreach ($planning->calendarJobs($horizon['start'], $horizon['end']) as $row) {
            $rows[] = [(string) $row['target_date'], 'Job deadline', (string) $row['job_number'], '/jobs/' . $row['id']];
        }
        foreach ($planning->calendarInvoices($horizon['start'], $horizon['end']) as $row) {
            $rows[] = [(string) $row['due_date'], 'Invoice due', (string) ($row['invoice_number'] ?? ''), '/invoices/' . $row['id']];
        }
        View::render('planning/page', [
            'title' => 'Planning calendar',
            'activeNav' => 'planning-calendar',
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => [$this->table(['Date', 'Kind', 'Record', 'Open'], $rows)],
            'notes' => ['This is an aggregated view. The schedule board remains the place where work is booked.'],
        ]);
    }

    public function scenarios(): void
    {
        View::render('planning/scenarios', [
            'title' => 'Scenarios',
            'activeNav' => 'planning-scenarios',
            'rows' => (new ForecastRepository())->scenarios(),
        ]);
    }

    public function runScenario(): void
    {
        $result = (new ScenarioService())->run($_POST, (int) auth_user()['id']);
        flash($result['id'] !== null ? 'success' : 'error', $result['id'] !== null ? 'Scenario stored. Live costs were not changed.' : (string) reset($result['errors']));
        redirect('/planning/scenarios');
    }

    public function budgets(): void
    {
        View::render('planning/budgets', [
            'title' => 'Operational budget',
            'activeNav' => 'budgets',
            'rows' => (new ForecastRepository())->budgets(),
        ]);
    }

    public function saveBudget(): void
    {
        $result = (new BudgetService())->create($_POST, (int) auth_user()['id']);
        flash($result['id'] !== null ? 'success' : 'error', $result['id'] !== null ? 'Budget line stored.' : (string) reset($result['errors']));
        redirect('/budgets');
    }

    public function budgetActual(string $id): void
    {
        $lines = (new BudgetService())->lines((int) $id);
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = [$line['metric_code'], $line['period'], $line['target'], $line['actual'], $line['variance'], (string) ($line['variance_percent'] ?? '—')];
        }
        View::render('planning/page', [
            'title' => 'Budget vs actual',
            'activeNav' => 'budget-actual',
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => [$this->table(['Metric', 'Period', 'Budget', 'Actual', 'Variance', 'Variance %'], $rows)],
            'notes' => ['Variance = actual − budget. This is an operational budget, not a set of accounting journals.'],
        ]);
    }

    public function targets(): void
    {
        View::render('planning/targets', [
            'title' => 'Targets',
            'activeNav' => 'planning-targets',
            'rows' => (new ForecastRepository())->targets(),
        ]);
    }

    public function saveTarget(): void
    {
        if (!can('targets.manage')) {
            deny_access('You cannot edit targets.');
        }
        (new ForecastRepository())->insertTarget([
            'name' => mb_substr(trim((string) ($_POST['name'] ?? 'Target')), 0, 180),
            'metric_code' => strtoupper(trim((string) ($_POST['metric_code'] ?? 'INVOICED_VALUE'))),
            'scope_type' => strtoupper(trim((string) ($_POST['scope_type'] ?? 'COMPANY'))),
            'scope_id' => ((int) ($_POST['scope_id'] ?? 0)) > 0 ? (int) $_POST['scope_id'] : null,
            'period_start' => (string) ($_POST['period_start'] ?? date('Y-m-01')),
            'period_end' => (string) ($_POST['period_end'] ?? date('Y-m-t')),
            'target_amount' => Decimal::money((string) ($_POST['target_amount'] ?? '0')),
            'notes' => blank_to_null($_POST['notes'] ?? null),
            'created_by' => (int) auth_user()['id'],
        ]);
        flash('success', 'Target stored.');
        redirect('/budgets/targets');
    }

    public function accuracy(): void
    {
        $snapshots = (new ForecastRepository())->snapshots('SALES');
        $rows = [];
        foreach ($snapshots as $row) {
            $summary = json_decode((string) $row['result_summary_json'], true);
            $rows[] = [
                (string) $row['generated_at'],
                (string) $row['as_of_date'],
                is_array($summary) ? (string) ($summary['weighted_pipeline'] ?? '') : '',
            ];
        }
        View::render('planning/page', [
            'title' => 'Forecast accuracy',
            'activeNav' => 'report-accuracy',
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => [$this->table(['Generated', 'As of', 'Weighted pipeline'], $rows)],
            'notes' => ['Older snapshots are kept. Difference = actual − forecast once the period has closed.'],
        ]);
    }

    public function coverage(): void
    {
        $planning = new ForecastRepository();
        $inventory = new InventoryRepository();
        $seen = [];
        $rows = [];
        foreach ($planning->firmDemand() as $row) {
            $productId = (int) $row['product_id'];
            if (isset($seen[$productId])) {
                continue;
            }
            $seen[$productId] = true;
            $onHand = $inventory->onHand($productId);
            $reserved = $inventory->reserved($productId);
            $available = Decimal::sub($onHand, $reserved);
            $issued = $planning->issuedQuantity($productId, 28);
            $coverage = 'No issues in the last 28 days';
            if (Decimal::cmp($issued, '0') > 0) {
                $weekly = Decimal::div($issued, '4');
                $coverage = Decimal::cmp($weekly, '0') > 0
                    ? Decimal::round(Decimal::div($available, $weekly), 2) . ' weeks of historical use'
                    : 'No issues in the last 28 days';
            }
            $rows[] = [(string) $row['product_name'], $onHand, $reserved, $available, $coverage];
        }
        View::render('planning/page', [
            'title' => 'Stock coverage',
            'activeNav' => 'report-coverage',
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => [$this->table(['Product', 'On hand', 'Reserved', 'Available', 'Note'], $rows)],
            'notes' => ['Projected shortage uses firm dated demand in MRP. Historical coverage is not treated as a promise.'],
        ]);
    }

    public function quality(): void
    {
        $rows = [];
        foreach ((new DataQualityService())->issues() as $issue) {
            $rows[] = [$issue['issue'], $issue['label'], $issue['action'], $issue['href']];
        }
        View::render('planning/page', [
            'title' => 'Data quality',
            'activeNav' => 'report-quality',
            'generated_at' => date('Y-m-d H:i:s'),
            'sections' => [$this->table(['Issue', 'Record', 'Recommended action', 'Open'], $rows)],
        ]);
    }

    public function importForm(): void
    {
        View::render('planning/import', [
            'title' => 'Import',
            'activeNav' => 'imports',
            'preview' => null,
        ]);
    }

    public function importPreview(): void
    {
        $csv = (string) ($_POST['csv'] ?? '');
        if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            $csv = (string) file_get_contents($_FILES['file']['tmp_name']);
        }
        $preview = (new ImportService())->previewCustomers($csv);
        $_SESSION['import_preview'] = $preview;
        $_SESSION['import_filename'] = (string) ($_FILES['file']['name'] ?? 'customers.csv');
        View::render('planning/import', [
            'title' => 'Import preview',
            'activeNav' => 'imports',
            'preview' => $preview,
        ]);
    }

    public function importCommit(): void
    {
        $preview = $_SESSION['import_preview']['rows'] ?? null;
        if (!is_array($preview)) {
            flash('error', 'Preview the file before importing.');
            redirect('/admin/imports');
        }
        $result = (new ImportService())->commitCustomers($preview, (string) ($_SESSION['import_filename'] ?? 'customers.csv'), (int) auth_user()['id']);
        unset($_SESSION['import_preview']);
        flash('success', $result['imported'] . ' customer(s) imported. ' . $result['skipped'] . ' row(s) skipped.');
        redirect('/admin/imports');
    }

    public function exportCsv(): void
    {
        $report = (new SalesForecastService())->report('30');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sales-forecast.csv"');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['bucket', 'number', 'value', 'weighted']);
        fputcsv($out, ['WEIGHTED PIPELINE', '', '', $report['weighted_pipeline']]);
        foreach ($report['rows'] as $row) {
            fputcsv($out, [$row['bucket'], $row['number'], $row['value'], $row['weighted']]);
        }
        exit;
    }

    public function apiClients(): void
    {
        View::render('planning/integrations', [
            'title' => 'API clients',
            'activeNav' => 'api-clients',
            'mode' => 'api',
            'rows' => (new ForecastRepository())->apiClients(),
            'secret' => $_SESSION['api_secret'] ?? '',
        ]);
        unset($_SESSION['api_secret']);
    }

    public function saveApiClient(): void
    {
        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['scopes'] ?? '')))));
        $created = (new ApiClientService())->create((string) ($_POST['name'] ?? 'Client'), $scopes, (int) auth_user()['id']);
        $_SESSION['api_secret'] = $created['identifier'] . '.' . $created['secret'];
        flash('success', 'API client created. Copy the credential from this page.');
        redirect('/admin/api-clients');
    }

    public function webhooks(): void
    {
        View::render('planning/integrations', [
            'title' => 'Webhooks',
            'activeNav' => 'webhooks',
            'mode' => 'webhooks',
            'rows' => (new ForecastRepository())->webhooks(),
            'secret' => '',
        ]);
    }

    public function saveWebhook(): void
    {
        $events = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['events'] ?? '')))));
        (new ForecastRepository())->insertWebhook([
            'name' => mb_substr(trim((string) ($_POST['name'] ?? 'Webhook')), 0, 180),
            'endpoint_url' => trim((string) ($_POST['endpoint_url'] ?? '')),
            'secret' => bin2hex(random_bytes(16)),
            'event_types_json' => json_encode($events, JSON_THROW_ON_ERROR),
        ]);
        flash('success', 'Webhook subscription stored.');
        redirect('/admin/webhooks');
    }

    public function integrationLogs(): void
    {
        View::render('planning/integrations', [
            'title' => 'Integration logs',
            'activeNav' => 'integration-logs',
            'mode' => 'logs',
            'rows' => (new ForecastRepository())->syncLogs(),
            'secret' => '',
        ]);
    }

    /**
     * @param list<string> $head
     * @param list<array<int, mixed>> $rows
     * @return array<string, mixed>
     */
    private function table(array $head, array $rows): array
    {
        return ['kind' => 'table', 'head' => $head, 'rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function figure(string $label, string $value, string $note = ''): array
    {
        return ['kind' => 'figure', 'label' => $label, 'value' => $value, 'note' => $note];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\LostReason;
use App\Domain\OpportunityStatus;
use App\Helpers\Decimal;
use App\Repositories\ReportingRepository;

/**
 * Predefined management reports. Figures are summed from quotations,
 * jobs, invoices, stock, and purchases. They are not a second ledger.
 *
 * Operational gross profit is commercial value minus actual job cost.
 * Commercial value is the accepted quote total plus approved or invoiced variations.
 * Cash collected is reported separately and is not called revenue.
 */
final class ReportService
{
    public function __construct(
        private readonly ReportingRepository $data = new ReportingRepository(),
        private readonly DateRangeService $dates = new DateRangeService()
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{from: string, to: string, label: string, preset: string, compare_from: string, compare_to: string, compare_label: string}
     */
    public function range(array $query): array
    {
        return $this->dates->resolve(
            (string) ($query['preset'] ?? 'this_month'),
            isset($query['from']) ? (string) $query['from'] : null,
            isset($query['to']) ? (string) $query['to'] : null,
            (string) ($query['compare'] ?? 'previous')
        );
    }

    /**
     * @param array{from: string, to: string, label: string, preset: string, compare_from: string, compare_to: string, compare_label: string} $range
     * @param array{show_cost: bool, group: string} $options
     * @return array<string, mixed>
     */
    public function build(string $code, array $range, array $options): array
    {
        $showCost = (bool) $options['show_cost'];
        $report = match ($code) {
            'executive' => $this->executive($range, $showCost),
            'sales' => $this->sales($range),
            'customers' => $this->customers($range, $showCost),
            'jobs' => $this->jobs($range, $showCost, (string) $options['group']),
            'profitability' => $this->profitability($range, (string) $options['group']),
            'production' => $this->production($range, $showCost),
            'waste' => $this->waste($range, $showCost),
            'inventory' => $this->inventory($range, $showCost),
            'purchasing' => $this->purchasing($range),
            'finance' => $this->finance($range),
            'debtors' => $this->debtors(),
            default => null,
        };
        if ($report === null) {
            return [];
        }
        $report['code'] = $code;
        $report['range'] = $range;

        return $report;
    }

    /**
     * @return array{commercial: string, actual: string, profit: string, margin: ?string, variance: string, variance_percent: ?string}
     */
    public static function jobProfit(
        string $quoteTotal,
        string $variation,
        string $material,
        string $labour,
        string $other,
        string $quotedCost
    ): array {
        $commercial = Decimal::money(Decimal::add($quoteTotal, $variation));
        $actual = Decimal::money(Decimal::add(Decimal::add($material, $labour), $other));
        $profit = ReportMath::grossProfit($commercial, $actual);
        $variance = Decimal::money(Decimal::sub($actual, $quotedCost));

        return [
            'commercial' => $commercial,
            'actual' => $actual,
            'profit' => $profit,
            'margin' => ReportMath::margin($profit, $commercial),
            'variance' => $variance,
            'variance_percent' => ReportMath::percent($variance, $quotedCost),
        ];
    }

    /**
     * @param list<array{commercial: string, actual: string}> $jobs
     * @return array{commercial: string, actual: string, profit: string, margin: ?string, jobs: int}
     */
    public static function customerProfit(array $jobs): array
    {
        $commercial = '0.00';
        $actual = '0.00';
        foreach ($jobs as $job) {
            $commercial = Decimal::money(Decimal::add($commercial, $job['commercial']));
            $actual = Decimal::money(Decimal::add($actual, $job['actual']));
        }
        $profit = ReportMath::grossProfit($commercial, $actual);

        return [
            'commercial' => $commercial,
            'actual' => $actual,
            'profit' => $profit,
            'margin' => ReportMath::margin($profit, $commercial),
            'jobs' => count($jobs),
        ];
    }

    /**
     * @param array<string, string> $buckets
     */
    public static function debtorTotal(array $buckets): string
    {
        $total = '0.00';
        foreach ($buckets as $amount) {
            $total = Decimal::money(Decimal::add($total, $amount));
        }

        return $total;
    }

    /**
     * @param array<string, mixed> $report
     */
    public function csv(array $report): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }
        fputcsv($handle, ['section', 'columns follow']);
        foreach ($report['tables'] as $table) {
            $header = ['section'];
            foreach ($table['columns'] as $column) {
                $header[] = $column['label'];
            }
            fputcsv($handle, $header);
            foreach ($table['rows'] as $row) {
                $line = [$table['title']];
                foreach ($table['columns'] as $column) {
                    $line[] = ReportMath::csvCell((string) ($row[$column['key']] ?? ''));
                }
                fputcsv($handle, $line);
            }
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * @param array<string, mixed> $report
     */
    public function pdfHtml(array $report): string
    {
        $html = '<h1>' . htmlspecialchars((string) $report['title']) . '</h1>';
        $html .= '<p>' . htmlspecialchars((string) $report['range']['label']) . ' · '
            . htmlspecialchars((string) $report['range']['from']) . ' to '
            . htmlspecialchars((string) $report['range']['to']) . '</p>';
        foreach ($report['formulas'] as $formula) {
            $html .= '<p>' . htmlspecialchars((string) $formula) . '</p>';
        }
        foreach ($report['kpis'] as $kpi) {
            $html .= '<p><strong>' . htmlspecialchars((string) $kpi['label']) . '</strong> '
                . htmlspecialchars((string) $kpi['value']) . '</p>';
        }
        foreach ($report['tables'] as $table) {
            $html .= '<h2>' . htmlspecialchars((string) $table['title']) . '</h2><table border="1" cellpadding="4"><tr>';
            foreach ($table['columns'] as $column) {
                $html .= '<th>' . htmlspecialchars((string) $column['label']) . '</th>';
            }
            $html .= '</tr>';
            foreach ($table['rows'] as $row) {
                $html .= '<tr>';
                foreach ($table['columns'] as $column) {
                    $html .= '<td>' . htmlspecialchars((string) ($row[$column['key']] ?? '')) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</table>';
        }

        return $html;
    }

    /**
     * @param array{from: string, to: string, label: string, compare_from: string, compare_to: string, compare_label: string} $range
     * @return array<string, mixed>
     */
    private function executive(array $range, bool $showCost): array
    {
        $sales = $this->salesBlock($range);
        $production = $this->data->production($range['from'], $range['to']);
        $finance = $this->data->finance($range['from'], $range['to']);
        $jobs = $this->jobRows($range);
        $profit = self::customerProfit(array_map(static fn (array $row): array => [
            'commercial' => $row['commercial_raw'],
            'actual' => $row['actual_raw'],
        ], $jobs));
        $marginTarget = $this->data->target('TARGET_GROSS_MARGIN');
        $kpis = $sales['kpis'];
        $kpis[] = $this->kpi('Jobs completed', (string) $production['completed'], '/jobs?status=COMPLETED', 'Turnaround is created to completed.');
        $kpis[] = $this->kpi('Jobs overdue', (string) $production['overdue'], '/jobs?overdue=1', 'Target date has passed.');
        $kpis[] = $this->kpi('Invoices issued', money($finance['invoice_value']), '/invoices', 'Issued in the period. Not cash.');
        $kpis[] = $this->kpi('Cash collected', money($finance['payments']), '/payments', 'Payments received. Not revenue.');
        $kpis[] = $this->kpi('Outstanding debtors', money($finance['outstanding']), '/finance/debtors', 'Open invoice balances.');
        if ($showCost) {
            $margin = $profit['margin'];
            $kpis[] = $this->kpi(
                'Gross margin',
                $margin === null ? '—' : $margin . '%',
                '/reports/profitability',
                $this->targetHint($margin, $marginTarget, 'percentage points')
            );
        }
        $attention = [
            ['label' => 'Overdue jobs', 'value' => (string) $production['overdue'], 'href' => '/jobs?overdue=1'],
            ['label' => 'Quotes to follow up', 'value' => (string) count($this->data->quotesToFollow(date('Y-m-d'))), 'href' => '/reports/sales'],
            ['label' => 'Overdue debtors', 'value' => money($finance['overdue']), 'href' => '/finance/debtors'],
        ];

        return [
            'title' => 'Executive',
            'intro' => 'A short view of sales, work in progress, cash collected, and margin. Pipeline value is estimated and is not guaranteed revenue.',
            'formulas' => [
                'Count conversion = accepted decided quotes / (accepted + declined). Draft, ready, and sent are excluded.',
                'Gross margin = (commercial value − actual job cost) / commercial value × 100.',
                'Cash collected is payments received in the period.',
            ],
            'kpis' => $kpis,
            'charts' => $sales['charts'],
            'tables' => [
                ['title' => 'Attention', 'columns' => [
                    ['key' => 'label', 'label' => 'Item'],
                    ['key' => 'value', 'label' => 'Now'],
                ], 'rows' => $attention],
            ],
            'attention' => $attention,
        ];
    }

    /**
     * @param array{from: string, to: string, compare_from: string, compare_to: string, compare_label: string} $range
     * @return array<string, mixed>
     */
    private function sales(array $range): array
    {
        $block = $this->salesBlock($range);
        $pipeline = $this->pipelineRows();
        $people = $this->peopleRows($range);
        $lost = $this->lostRows($range);

        return [
            'title' => 'Sales',
            'intro' => 'Decided quotations are accepted or declined. Open quotations are not treated as lost. Pipeline amounts are estimates.',
            'formulas' => [
                'Count conversion = accepted count / (accepted count + declined count).',
                'Value conversion = accepted value / (accepted value + declined value).',
                'A change versus the comparison period is omitted when the earlier figure is zero.',
            ],
            'kpis' => $block['kpis'],
            'charts' => $block['charts'],
            'tables' => [
                ['title' => 'Pipeline by stage', 'columns' => [
                    ['key' => 'stage', 'label' => 'Stage'],
                    ['key' => 'count', 'label' => 'Opportunities'],
                    ['key' => 'value', 'label' => 'Estimated value'],
                ], 'rows' => $pipeline],
                ['title' => 'Open opportunities', 'columns' => [
                    ['key' => 'number', 'label' => 'Number', 'href' => 'href'],
                    ['key' => 'customer', 'label' => 'Customer'],
                    ['key' => 'salesperson', 'label' => 'Salesperson'],
                    ['key' => 'close', 'label' => 'Expected close'],
                    ['key' => 'value', 'label' => 'Estimated value'],
                ], 'rows' => $this->openOpportunityRows()],
                ['title' => 'Salespeople', 'columns' => [
                    ['key' => 'name', 'label' => 'Salesperson'],
                    ['key' => 'opportunities', 'label' => 'Opportunities'],
                    ['key' => 'quotes', 'label' => 'Quotes'],
                    ['key' => 'quote_value', 'label' => 'Quote value'],
                    ['key' => 'accepted', 'label' => 'Accepted'],
                    ['key' => 'accepted_value', 'label' => 'Accepted value'],
                    ['key' => 'conversion', 'label' => 'Count conversion'],
                    ['key' => 'average', 'label' => 'Average quote'],
                ], 'rows' => $people],
                ['title' => 'Lost opportunities', 'columns' => [
                    ['key' => 'reason', 'label' => 'Reason'],
                    ['key' => 'count', 'label' => 'Count'],
                    ['key' => 'value', 'label' => 'Estimated value'],
                    ['key' => 'share', 'label' => 'Share of lost value'],
                ], 'rows' => $lost],
                ['title' => 'Quotes to follow up', 'columns' => [
                    ['key' => 'number', 'label' => 'Quote', 'href' => 'href'],
                    ['key' => 'customer', 'label' => 'Customer'],
                    ['key' => 'value', 'label' => 'Value'],
                    ['key' => 'salesperson', 'label' => 'Salesperson'],
                    ['key' => 'follow', 'label' => 'Follow-up'],
                ], 'rows' => $this->followRows()],
            ],
        ];
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    private function customers(array $range, bool $showCost): array
    {
        $rows = $this->customerRows($range, $showCost);
        $columns = [
            ['key' => 'customer', 'label' => 'Customer', 'href' => 'href'],
            ['key' => 'quote_value', 'label' => 'Quote value'],
            ['key' => 'accepted', 'label' => 'Accepted value'],
            ['key' => 'invoiced', 'label' => 'Invoiced'],
            ['key' => 'paid', 'label' => 'Paid'],
            ['key' => 'outstanding', 'label' => 'Outstanding'],
        ];
        if ($showCost) {
            $columns[] = ['key' => 'profit', 'label' => 'Gross profit'];
            $columns[] = ['key' => 'margin', 'label' => 'Gross margin'];
        }
        $concentration = $this->concentration($rows, $showCost);

        return [
            'title' => 'Customers',
            'intro' => 'Amounts follow the selected quotation dates. Invoice and profit totals are the customer’s jobs and invoices, so an old job can appear beside a new quote.',
            'formulas' => [
                'Gross profit = commercial value − actual job cost, summed across the customer’s jobs in the period.',
                'Top 5 concentration = top 5 commercial value / all commercial value in the table.',
            ],
            'kpis' => [
                $this->kpi('Customers in view', (string) count($rows), '/customers', ''),
                $this->kpi('Top 5 share', $concentration['top5'], '/reports/customers', 'Share of commercial value.'),
            ],
            'charts' => array_slice(array_map(static fn (array $row): array => [
                'label' => $row['customer'],
                'value' => $row['commercial_raw'],
            ], $rows), 0, 8),
            'tables' => [
                ['title' => 'Sales and profit by customer', 'columns' => $columns, 'rows' => $rows],
                ['title' => 'Concentration', 'columns' => [
                    ['key' => 'label', 'label' => 'Group'],
                    ['key' => 'value', 'label' => 'Share'],
                ], 'rows' => $concentration['rows']],
            ],
        ];
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    private function jobs(array $range, bool $showCost, string $group): array
    {
        return $this->profitability($range, $group, $showCost, 'Jobs');
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    private function profitability(array $range, string $group, bool $showCost = true, string $title = 'Profitability'): array
    {
        $jobs = $this->jobRows($range);
        $target = $this->data->target('TARGET_GROSS_MARGIN') ?? '35';
        $low = [];
        foreach ($jobs as $job) {
            if (!$showCost || $job['margin_raw'] === null) {
                continue;
            }
            if (Decimal::cmp($job['margin_raw'], $target) < 0) {
                $low[] = $job;
            }
        }
        $totals = self::customerProfit(array_map(static fn (array $row): array => [
            'commercial' => $row['commercial_raw'],
            'actual' => $row['actual_raw'],
        ], $jobs));
        $grouped = $this->groupJobs($jobs, $group);
        $columns = [
            ['key' => 'label', 'label' => 'Group', 'href' => 'href'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'commercial', 'label' => 'Commercial value'],
            ['key' => 'quoted_cost', 'label' => 'Quoted cost'],
        ];
        if ($showCost) {
            $columns = array_merge($columns, [
                ['key' => 'material', 'label' => 'Actual material'],
                ['key' => 'labour', 'label' => 'Actual labour'],
                ['key' => 'other', 'label' => 'Actual other'],
                ['key' => 'actual', 'label' => 'Actual total'],
                ['key' => 'profit', 'label' => 'Gross profit'],
                ['key' => 'margin', 'label' => 'Gross margin'],
                ['key' => 'variance', 'label' => 'Cost variance'],
            ]);
        }
        $columns[] = ['key' => 'status', 'label' => 'Status'];
        $columns[] = ['key' => 'completed', 'label' => 'Completed'];

        return [
            'title' => $title,
            'intro' => 'Operational gross profit uses commercial value and actual job cost. It is not cash received and it is not accounting profit. Jobs below the margin target are listed for review, not marked as failures.',
            'formulas' => [
                'Commercial value = accepted quote total + approved or invoiced variations.',
                'Actual cost = actual material + actual labour + actual other.',
                'Gross profit = commercial value − actual cost.',
                'Gross margin = gross profit / commercial value × 100.',
                'Cost variance = actual cost − quoted cost. Variance % = variance / quoted cost.',
                'Low-margin list uses the active TARGET_GROSS_MARGIN setting.',
            ],
            'kpis' => $showCost ? [
                $this->kpi('Commercial value', money($totals['commercial']), '/reports/profitability', ''),
                $this->kpi('Actual cost', money($totals['actual']), '/reports/profitability', ''),
                $this->kpi('Gross profit', money($totals['profit']), '/reports/profitability', ''),
                $this->kpi('Gross margin', $totals['margin'] === null ? '—' : $totals['margin'] . '%', '/reports/profitability', $this->targetHint($totals['margin'], $target, 'percentage points')),
                $this->kpi('Below target', (string) count($low), '/reports/profitability', 'Target ' . $target . '%.'),
            ] : [
                $this->kpi('Jobs', (string) count($jobs), '/jobs', 'Cost columns need profitability access.'),
            ],
            'charts' => [],
            'tables' => [
                ['title' => 'Quoted cost and actual cost by ' . $group, 'columns' => $columns, 'rows' => $grouped],
                ['title' => 'Jobs under the margin target', 'columns' => $columns, 'rows' => $low],
            ],
        ];
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    private function production(array $range, bool $showCost): array
    {
        $production = $this->data->production($range['from'], $range['to']);
        $install = $this->data->installations($range['from'], $range['to']);
        $rework = $this->data->rework($range['from'], $range['to']);
        $labourRows = [];
        foreach ($this->data->labour($range['from'], $range['to']) as $row) {
            $labourRows[] = [
                'name' => (string) $row['name'],
                'work' => (string) $row['work_type'],
                'hours' => Decimal::round(Decimal::div((string) $row['minutes'], '60'), 2),
                'cost' => $showCost ? money((string) $row['cost']) : 'Hidden',
            ];
        }
        $jobLabour = [];
        foreach ($this->data->labourByJob($range['from'], $range['to']) as $row) {
            $estimated = Decimal::round(Decimal::div((string) $row['estimated'], '60'), 2);
            $actual = Decimal::round(Decimal::div((string) $row['minutes'], '60'), 2);
            $jobLabour[] = [
                'job' => (string) $row['job_number'],
                'href' => '/jobs/' . $row['id'],
                'customer' => customer_label($row),
                'estimated' => $estimated,
                'actual' => $actual,
                'variance' => Decimal::round(Decimal::sub($actual, $estimated), 2),
                'cost' => $showCost ? money((string) $row['cost']) : 'Hidden',
            ];
        }
        $stages = [];
        foreach ($this->data->bottlenecks() as $row) {
            $stages[] = [
                'stage' => (string) $row['name'],
                'hours' => $row['avg_hours'] === null ? '—' : Decimal::round((string) $row['avg_hours'], 1),
                'waiting' => (string) $row['waiting'],
                'oldest' => (string) ($row['oldest'] ?? '—'),
            ];
        }
        $done = $install['completed'];
        $scheduled = $install['scheduled'];
        $rate = ReportMath::percent((string) $done, (string) max(1, $scheduled));

        return [
            'title' => 'Production',
            'intro' => 'Turnaround is the hours from job created to job completed. It is not production-start to production-complete. These figures are not employee scores.',
            'formulas' => [
                'Turnaround = completed_at − created_at, averaged for jobs completed in the period.',
                'Labour variance = actual hours − estimated task hours.',
                'Installation completion rate = completed in period / scheduled in period.',
                'Rework cost uses the cost snapshot stored when the material or time was recorded.',
            ],
            'kpis' => [
                $this->kpi('Jobs completed', (string) $production['completed'], '/jobs?status=COMPLETED', 'Duration label: created to completed.'),
                $this->kpi('Average turnaround', $production['turnaround_hours'] === null ? '—' : $production['turnaround_hours'] . ' h', '/jobs', 'Created to completed.'),
                $this->kpi('Tasks completed', (string) $production['tasks_completed'], '/jobs', $production['task_hours'] === null ? '' : 'Average ' . $production['task_hours'] . ' h.'),
                $this->kpi('Overdue jobs', (string) $production['overdue'], '/jobs?overdue=1', ''),
                $this->kpi('Completed late', (string) $production['late'], '/jobs?status=COMPLETED', 'Completed after the target date.'),
                $this->kpi('QC failures', (string) $production['qc_fail'], '/reports/production', ''),
                $this->kpi('Rework jobs', (string) $rework['jobs'], '/reports/production', 'Not assigned as blame.'),
            ],
            'charts' => [
                ['label' => 'Completed', 'value' => (string) $production['completed']],
                ['label' => 'Overdue', 'value' => (string) $production['overdue']],
                ['label' => 'Late', 'value' => (string) $production['late']],
                ['label' => 'Rework', 'value' => (string) $rework['jobs']],
            ],
            'tables' => [
                ['title' => 'Time in production stage', 'columns' => [
                    ['key' => 'stage', 'label' => 'Stage'],
                    ['key' => 'hours', 'label' => 'Average hours in stage'],
                    ['key' => 'waiting', 'label' => 'Currently waiting'],
                    ['key' => 'oldest', 'label' => 'Oldest open stage'],
                ], 'rows' => $stages],
                ['title' => 'Labour by person and work type', 'columns' => [
                    ['key' => 'name', 'label' => 'Person'],
                    ['key' => 'work', 'label' => 'Work type'],
                    ['key' => 'hours', 'label' => 'Hours'],
                    ['key' => 'cost', 'label' => 'Internal cost'],
                ], 'rows' => $labourRows],
                ['title' => 'Estimated and actual hours by job', 'columns' => [
                    ['key' => 'job', 'label' => 'Job', 'href' => 'href'],
                    ['key' => 'customer', 'label' => 'Customer'],
                    ['key' => 'estimated', 'label' => 'Estimated hours'],
                    ['key' => 'actual', 'label' => 'Actual hours'],
                    ['key' => 'variance', 'label' => 'Variance'],
                    ['key' => 'cost', 'label' => 'Internal cost'],
                ], 'rows' => $jobLabour],
                ['title' => 'Installations', 'columns' => [
                    ['key' => 'label', 'label' => 'Measure'],
                    ['key' => 'value', 'label' => 'Value'],
                ], 'rows' => [
                    ['label' => 'Scheduled in period', 'value' => (string) $install['scheduled']],
                    ['label' => 'Completed in period', 'value' => (string) $install['completed']],
                    ['label' => 'Return required now', 'value' => (string) $install['return_required']],
                    ['label' => 'Average minutes', 'value' => (string) ($install['minutes'] ?? '—')],
                    ['label' => 'Completion rate', 'value' => $scheduled > 0 && $rate !== null ? $rate . '%' : '—'],
                    ['label' => 'Rework material cost', 'value' => $showCost ? money($rework['material_cost']) : 'Hidden'],
                    ['label' => 'Rework labour minutes', 'value' => (string) $rework['labour_minutes']],
                ]],
            ],
        ];
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    private function waste(array $range, bool $showCost): array
    {
        $byProduct = [];
        $productionQty = '0';
        $wasteQty = '0';
        $wasteCost = '0.00';
        foreach ($this->data->materialUsage($range['from'], $range['to']) as $row) {
            $id = (string) ($row['product_id'] ?? 'none');
            if (!isset($byProduct[$id])) {
                $byProduct[$id] = [
                    'product' => (string) ($row['product_name'] ?? 'Unspecified'),
                    'href' => $row['product_id'] ? '/products/' . $row['product_id'] : '',
                    'production' => '0',
                    'waste' => '0',
                    'rework' => '0',
                    'cost' => '0.00',
                    'jobs' => '0',
                ];
            }
            $qty = (string) $row['quantity'];
            $type = (string) $row['usage_type'];
            if ($type === 'PRODUCTION') {
                $byProduct[$id]['production'] = Decimal::add($byProduct[$id]['production'], $qty, 4);
                $productionQty = Decimal::add($productionQty, $qty, 4);
            } elseif ($type === 'WASTE') {
                $byProduct[$id]['waste'] = Decimal::add($byProduct[$id]['waste'], $qty, 4);
                $wasteQty = Decimal::add($wasteQty, $qty, 4);
                $wasteCost = Decimal::money(Decimal::add($wasteCost, (string) $row['cost']));
            } elseif ($type === 'REWORK') {
                $byProduct[$id]['rework'] = Decimal::add($byProduct[$id]['rework'], $qty, 4);
            }
            $byProduct[$id]['cost'] = Decimal::money(Decimal::add($byProduct[$id]['cost'], (string) $row['cost']));
            $byProduct[$id]['jobs'] = (string) max((int) $byProduct[$id]['jobs'], (int) $row['jobs']);
        }
        $quoted = [];
        foreach ($this->data->quotedMaterial($range['from'], $range['to']) as $row) {
            $quoted[(string) ($row['product_id'] ?? '')] = $row;
        }
        $rows = [];
        foreach ($byProduct as $id => $row) {
            $rate = ReportMath::wasteRate($row['waste'], $row['production']);
            $bill = $quoted[$id]['billable'] ?? null;
            $rows[] = [
                'product' => $row['product'],
                'href' => $row['href'],
                'billable' => $bill === null ? '—' : Decimal::round((string) $bill, 4),
                'production' => Decimal::round($row['production'], 4),
                'waste' => Decimal::round($row['waste'], 4),
                'rework' => Decimal::round($row['rework'], 4),
                'physical' => Decimal::round(Decimal::add($row['production'], $row['waste'], 4), 4),
                'rate' => $rate === null ? '—' : $rate . '%',
                'cost' => $showCost ? money($row['cost']) : 'Hidden',
                'jobs' => $row['jobs'],
            ];
        }
        $reasons = [];
        foreach ($this->data->materialUsage($range['from'], $range['to']) as $row) {
            if ((string) $row['usage_type'] !== 'WASTE') {
                continue;
            }
            $reasons[] = [
                'reason' => (string) ($row['reason'] ?? 'OTHER'),
                'product' => (string) ($row['product_name'] ?? ''),
                'quantity' => Decimal::round((string) $row['quantity'], 4),
                'cost' => $showCost ? money((string) $row['cost']) : 'Hidden',
            ];
        }
        $overall = ReportMath::wasteRate($wasteQty, $productionQty);
        $target = $this->data->target('TARGET_WASTE_RATE');

        return [
            'title' => 'Materials and waste',
            'intro' => 'Billable quantity is what the accepted quote charged. Production, waste, and rework are physical quantities recorded on jobs. Waste cost uses the unit cost stored when the waste was recorded.',
            'formulas' => [
                'Waste rate = waste quantity / (production quantity + waste quantity) × 100.',
                'Physical consumption = production + waste. Rework is shown separately.',
                'Do not treat billable quantity as physical consumption.',
            ],
            'kpis' => [
                $this->kpi('Waste rate', $overall === null ? '—' : $overall . '%', '/reports/waste', $this->targetHint($overall, $target, 'percentage points')),
                $this->kpi('Waste cost', $showCost ? money($wasteCost) : 'Hidden', '/reports/waste', 'Historical cost snapshot.'),
            ],
            'charts' => array_slice(array_map(static fn (array $row): array => [
                'label' => $row['product'],
                'value' => (string) $row['waste'],
            ], $rows), 0, 8),
            'tables' => [
                ['title' => 'Quoted, produced, and wasted', 'columns' => [
                    ['key' => 'product', 'label' => 'Material', 'href' => 'href'],
                    ['key' => 'billable', 'label' => 'Quoted billable'],
                    ['key' => 'production', 'label' => 'Production'],
                    ['key' => 'waste', 'label' => 'Waste'],
                    ['key' => 'rework', 'label' => 'Rework'],
                    ['key' => 'physical', 'label' => 'Production + waste'],
                    ['key' => 'rate', 'label' => 'Waste rate'],
                    ['key' => 'cost', 'label' => 'Recorded cost'],
                    ['key' => 'jobs', 'label' => 'Jobs'],
                ], 'rows' => $rows],
                ['title' => 'Waste by reason', 'columns' => [
                    ['key' => 'reason', 'label' => 'Reason'],
                    ['key' => 'product', 'label' => 'Material'],
                    ['key' => 'quantity', 'label' => 'Quantity'],
                    ['key' => 'cost', 'label' => 'Recorded cost'],
                ], 'rows' => $reasons],
            ],
        ];
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    private function inventory(array $range, bool $showCost): array
    {
        $consumption = [];
        foreach ($this->data->monthlyConsumption() as $row) {
            $consumption[(int) $row['product_id']] = Decimal::div((string) $row['qty'], '3', 4);
        }
        $stock = [];
        $value = '0.00';
        foreach ($this->data->stockByProduct() as $row) {
            $onHand = Decimal::round((string) $row['on_hand'], 4);
            $line = $showCost ? Decimal::money(Decimal::mul($onHand, (string) $row['cost_price'])) : '0.00';
            if ($showCost) {
                $value = Decimal::money(Decimal::add($value, $line));
            }
            $monthly = $consumption[(int) $row['id']] ?? '0';
            $months = Decimal::cmp($monthly, '0') === 0 ? null : Decimal::round(Decimal::div($onHand, $monthly), 1);
            $stock[] = [
                'product' => (string) $row['name'],
                'href' => '/products/' . $row['id'],
                'category' => (string) ($row['category'] ?? ''),
                'on_hand' => $onHand,
                'value' => $showCost ? money($line) : 'Hidden',
                'months' => $months === null ? 'No recent use' : $months,
            ];
        }
        $days = (int) (SettingsService::get('slow_stock_days', '180') ?? '180');
        $before = (new \DateTimeImmutable('today'))->modify('-' . max(1, $days) . ' days')->format('Y-m-d');
        $slow = [];
        foreach ($this->data->slowStock($before) as $row) {
            $slow[] = [
                'product' => (string) $row['name'],
                'href' => '/products/' . $row['id'],
                'last' => (string) $row['last_movement'],
                'note' => 'No consumption since this date. Stock is not written off.',
            ];
        }
        $offcutValue = '0.00';
        $offcutRows = [];
        foreach ($this->data->offcuts() as $row) {
            $offcutValue = Decimal::money(Decimal::add($offcutValue, (string) $row['value']));
            $offcutRows[] = [
                'status' => (string) $row['status'],
                'count' => (string) $row['n'],
                'value' => $showCost ? money((string) $row['value']) : 'Hidden',
            ];
        }
        $moves = [];
        foreach ($this->data->offcutMovement($range['from'], $range['to']) as $row) {
            $moves[] = [
                'type' => (string) $row['movement_type'],
                'count' => (string) $row['n'],
                'qty' => Decimal::round((string) $row['qty'], 4),
                'cost' => $showCost ? money((string) $row['cost']) : 'Hidden',
            ];
        }
        $adjustments = [];
        foreach ($this->data->adjustments($range['from'], $range['to']) as $row) {
            $adjustments[] = [
                'type' => (string) $row['movement_type'],
                'count' => (string) $row['n'],
                'cost' => $showCost ? money((string) $row['cost']) : 'Hidden',
            ];
        }
        $locations = [];
        foreach ($this->data->stockByLocation() as $row) {
            $locations[] = [
                'location' => (string) $row['location'],
                'value' => $showCost ? money((string) $row['value']) : 'Hidden',
            ];
        }

        return [
            'title' => 'Inventory',
            'intro' => 'Stock value uses the current catalogue cost times quantity on hand, excluding offcuts. Months remaining is on-hand divided by average monthly consumption over three months. It is not a formal accounting turnover ratio. Offcut value uses the stored unit cost.',
            'formulas' => [
                'Months remaining = on hand / (consumption over 3 months / 3). Blank consumption is labelled no recent use.',
                'Offcut reuse = stored cost of OFFCUT_CONSUMED movements in the period.',
                'Slow stock = no job consumption for the configured number of days. Nothing is written off.',
            ],
            'kpis' => [
                $this->kpi('Stock value', $showCost ? money($value) : 'Hidden', '/inventory', 'Current catalogue cost. Offcuts excluded.'),
                $this->kpi('Offcut stored value', $showCost ? money($offcutValue) : 'Hidden', '/inventory/offcuts', 'Stored unit cost × remaining quantity.'),
            ],
            'charts' => array_slice(array_map(static fn (array $row): array => [
                'label' => $row['location'],
                'value' => preg_replace('/[^0-9.\-]/', '', (string) $row['value']) ?: '0',
            ], $locations), 0, 8),
            'tables' => [
                ['title' => 'Stock and months of cover', 'columns' => [
                    ['key' => 'product', 'label' => 'Product', 'href' => 'href'],
                    ['key' => 'category', 'label' => 'Category'],
                    ['key' => 'on_hand', 'label' => 'On hand'],
                    ['key' => 'value', 'label' => 'Value at current cost'],
                    ['key' => 'months', 'label' => 'Months of cover'],
                ], 'rows' => $stock],
                ['title' => 'By location', 'columns' => [
                    ['key' => 'location', 'label' => 'Location'],
                    ['key' => 'value', 'label' => 'Movement cost on hand'],
                ], 'rows' => $locations],
                ['title' => 'Slow stock', 'columns' => [
                    ['key' => 'product', 'label' => 'Product', 'href' => 'href'],
                    ['key' => 'last', 'label' => 'Last consumption'],
                    ['key' => 'note', 'label' => 'Note'],
                ], 'rows' => $slow],
                ['title' => 'Offcuts', 'columns' => [
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'count', 'label' => 'Count'],
                    ['key' => 'value', 'label' => 'Stored value'],
                ], 'rows' => $offcutRows],
                ['title' => 'Offcut movements', 'columns' => [
                    ['key' => 'type', 'label' => 'Movement'],
                    ['key' => 'count', 'label' => 'Count'],
                    ['key' => 'qty', 'label' => 'Quantity'],
                    ['key' => 'cost', 'label' => 'Stored cost'],
                ], 'rows' => $moves],
                ['title' => 'Adjustments and waste movements', 'columns' => [
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'count', 'label' => 'Count'],
                    ['key' => 'cost', 'label' => 'Stored cost'],
                ], 'rows' => $adjustments],
            ],
        ];
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    private function purchasing(array $range): array
    {
        $returns = [];
        foreach ($this->data->supplierReturns($range['from'], $range['to']) as $row) {
            $returns[(int) $row['id']] = (int) $row['n'];
        }
        $suppliers = [];
        foreach ($this->data->supplierStats($range['from'], $range['to']) as $row) {
            $suppliers[] = [
                'supplier' => (string) $row['name'],
                'href' => '/suppliers/' . $row['id'],
                'orders' => (string) $row['orders'],
                'value' => money((string) $row['value']),
                'lead' => $row['lead_days'] === null ? '—' : Decimal::round((string) $row['lead_days'], 1),
                'late' => (string) $row['late'],
                'partial' => (string) $row['partials'],
                'returns' => (string) ($returns[(int) $row['id']] ?? 0),
            ];
        }
        $lines = [];
        foreach ($this->data->purchaseLines($range['from'], $range['to']) as $row) {
            $lines[] = [
                'product' => (string) $row['product_name'],
                'qty' => Decimal::round((string) $row['qty'], 4),
                'value' => money((string) $row['value']),
                'average' => money(Decimal::round((string) $row['avg_cost'], 4)),
            ];
        }
        $prices = [];
        foreach ($this->data->priceChanges() as $row) {
            $change = ReportMath::change((string) $row['new_price'], (string) $row['old_price']);
            $prices[] = [
                'product' => (string) $row['product_name'],
                'supplier' => (string) $row['supplier_name'],
                'date' => (string) $row['effective_date'],
                'old' => (string) $row['old_price'],
                'new' => (string) $row['new_price'],
                'change' => $change === null ? '—' : $change . '%',
            ];
        }
        $internal = [];
        foreach ($this->data->internalPriceChanges() as $row) {
            $change = ReportMath::change((string) $row['new_cost'], (string) $row['old_cost']);
            $internal[] = [
                'product' => (string) $row['product_name'],
                'date' => (string) $row['changed_at'],
                'old' => (string) $row['old_cost'],
                'new' => (string) $row['new_cost'],
                'change' => $change === null ? '—' : $change . '%',
            ];
        }
        $open = [];
        foreach ($this->data->openPurchaseOrders() as $row) {
            $open[] = [
                'number' => (string) $row['po_number'],
                'href' => '/purchasing/orders/' . $row['id'],
                'supplier' => (string) $row['supplier_name'],
                'status' => (string) $row['status'],
                'expected' => (string) ($row['expected_date'] ?? ''),
                'value' => money((string) $row['total']),
            ];
        }

        return [
            'title' => 'Purchasing',
            'intro' => 'Supplier rows are factual. There is no combined supplier score. Lead time is days from order date to goods receipt. Supplier returns are stock movements of type SUPPLIER_RETURN.',
            'formulas' => [
                'Average purchase price = average unit cost on purchase order lines in the period.',
                'Price change % = (new − old) / old × 100. Omitted when the old price is zero.',
            ],
            'kpis' => [
                $this->kpi('Open purchase orders', (string) count($open), '/purchasing/orders', ''),
            ],
            'charts' => [],
            'tables' => [
                ['title' => 'Suppliers', 'columns' => [
                    ['key' => 'supplier', 'label' => 'Supplier', 'href' => 'href'],
                    ['key' => 'orders', 'label' => 'Orders'],
                    ['key' => 'value', 'label' => 'Purchase value'],
                    ['key' => 'lead', 'label' => 'Average lead days'],
                    ['key' => 'late', 'label' => 'Late'],
                    ['key' => 'partial', 'label' => 'Partial'],
                    ['key' => 'returns', 'label' => 'Returns'],
                ], 'rows' => $suppliers],
                ['title' => 'Purchases by product', 'columns' => [
                    ['key' => 'product', 'label' => 'Product'],
                    ['key' => 'qty', 'label' => 'Quantity'],
                    ['key' => 'value', 'label' => 'Value'],
                    ['key' => 'average', 'label' => 'Average unit cost'],
                ], 'rows' => $lines],
                ['title' => 'Open orders', 'columns' => [
                    ['key' => 'number', 'label' => 'Order', 'href' => 'href'],
                    ['key' => 'supplier', 'label' => 'Supplier'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'expected', 'label' => 'Expected'],
                    ['key' => 'value', 'label' => 'Value'],
                ], 'rows' => $open],
                ['title' => 'Supplier price changes', 'columns' => [
                    ['key' => 'product', 'label' => 'Material'],
                    ['key' => 'supplier', 'label' => 'Supplier'],
                    ['key' => 'date', 'label' => 'Effective'],
                    ['key' => 'old', 'label' => 'Old'],
                    ['key' => 'new', 'label' => 'New'],
                    ['key' => 'change', 'label' => 'Change'],
                ], 'rows' => $prices],
                ['title' => 'Internal cost changes', 'columns' => [
                    ['key' => 'product', 'label' => 'Material'],
                    ['key' => 'date', 'label' => 'Changed'],
                    ['key' => 'old', 'label' => 'Old cost'],
                    ['key' => 'new', 'label' => 'New cost'],
                    ['key' => 'change', 'label' => 'Change'],
                ], 'rows' => $internal],
            ],
        ];
    }

    /**
     * @param array{from: string, to: string} $range
     * @return array<string, mixed>
     */
    private function finance(array $range): array
    {
        $finance = $this->data->finance($range['from'], $range['to']);
        $previous = $this->data->finance($range['compare_from'], $range['compare_to']);
        $collection = ReportMath::percent($finance['collected_on_due'], $finance['due_value']);
        $change = ReportMath::change($finance['payments'], $previous['payments']);
        $paid = [];
        foreach ($this->data->paidInvoices($range['from'], $range['to']) as $row) {
            $paid[] = [
                'number' => (string) $row['invoice_number'],
                'invoice_date' => (string) $row['invoice_date'],
                'paid' => (string) $row['paid_at'],
                'days' => (string) $row['days'],
                'total' => money((string) $row['total']),
            ];
        }

        return [
            'title' => 'Finance',
            'intro' => 'Cash collected is payments received in the period. It is not revenue. Collection rate uses invoices whose due date falls in the period.',
            'formulas' => [
                'Cash collected = sum of recorded payments with payment date in the period.',
                'Collection rate = payments in the period allocated to invoices due in the period / total of those invoices.',
                'Average days to payment = paid_at date − invoice_date, for invoices paid in the sense of status PAID whose invoice date is in the period.',
                'Comparison: ' . $range['compare_label'] . ' (' . $range['compare_from'] . ' to ' . $range['compare_to'] . ').',
            ],
            'kpis' => [
                $this->kpi('Invoices', $finance['invoices'], '/invoices', money($finance['invoice_value'])),
                $this->kpi('Cash collected', money($finance['payments']), '/payments', $change === null ? 'No earlier payments to compare.' : $change . '% vs ' . $range['compare_label']),
                $this->kpi('Credit notes', $finance['credits'], '/credit-notes', money($finance['credit_value'])),
                $this->kpi('Outstanding', money($finance['outstanding']), '/finance/debtors', ''),
                $this->kpi('Overdue', money($finance['overdue']), '/finance/debtors', ''),
                $this->kpi('Collection rate', $collection === null ? '—' : $collection . '%', '/reports/finance', 'Against invoices due in the period.'),
                $this->kpi('Average days to payment', $finance['average_days'] === null ? '—' : $finance['average_days'], '/reports/finance', 'Invoice date to paid date.'),
            ],
            'charts' => [
                ['label' => 'Invoiced', 'value' => $finance['invoice_value']],
                ['label' => 'Collected', 'value' => $finance['payments']],
                ['label' => 'Outstanding', 'value' => $finance['outstanding']],
            ],
            'tables' => [
                ['title' => 'Fully paid invoices (invoice date in period)', 'columns' => [
                    ['key' => 'number', 'label' => 'Invoice'],
                    ['key' => 'invoice_date', 'label' => 'Invoice date'],
                    ['key' => 'paid', 'label' => 'Paid at'],
                    ['key' => 'days', 'label' => 'Days'],
                    ['key' => 'total', 'label' => 'Total'],
                ], 'rows' => $paid],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function debtors(): array
    {
        $ageing = (new DebtorAgeingService())->report();
        $labels = [
            'CURRENT' => 'Current',
            'DAYS_1_30' => '1–30',
            'DAYS_31_60' => '31–60',
            'DAYS_61_90' => '61–90',
            'DAYS_90_PLUS' => '90+',
        ];
        $buckets = [];
        foreach ($labels as $code => $label) {
            $buckets[] = ['bucket' => $label, 'amount' => money($ageing['buckets'][$code])];
        }
        $customers = [];
        foreach (array_slice($ageing['customers'], 0, 20) as $row) {
            $customers[] = [
                'customer' => customer_label($row),
                'href' => '/customers/' . $row['customer_id'],
                'outstanding' => money((string) $row['total']),
            ];
        }
        $lines = [];
        foreach ($ageing['lines'] as $row) {
            $lines[] = [
                'invoice' => (string) $row['invoice_number'],
                'href' => '/invoices/' . $row['id'],
                'customer' => customer_label($row),
                'due' => (string) ($row['due_date'] ?? ''),
                'bucket' => $labels[$row['bucket']] ?? (string) $row['bucket'],
                'balance' => money((string) $row['balance_due']),
            ];
        }
        $total = self::debtorTotal($ageing['buckets']);

        return [
            'title' => 'Debtors',
            'intro' => 'Ageing uses the invoice due date. Current means not yet due. The total is the sum of the five buckets.',
            'formulas' => [
                'Bucket is based on due date versus today.',
                'Total outstanding = current + 1–30 + 31–60 + 61–90 + 90+.',
            ],
            'kpis' => [
                $this->kpi('Total outstanding', money($total), '/finance/debtors', 'Sum of the ageing buckets.'),
            ],
            'charts' => array_map(static fn (array $row): array => [
                'label' => $row['bucket'],
                'value' => preg_replace('/[^0-9.\-]/', '', (string) $row['amount']) ?: '0',
            ], $buckets),
            'tables' => [
                ['title' => 'Ageing', 'columns' => [
                    ['key' => 'bucket', 'label' => 'Bucket'],
                    ['key' => 'amount', 'label' => 'Outstanding'],
                ], 'rows' => $buckets],
                ['title' => 'Largest outstanding customers', 'columns' => [
                    ['key' => 'customer', 'label' => 'Customer', 'href' => 'href'],
                    ['key' => 'outstanding', 'label' => 'Outstanding'],
                ], 'rows' => $customers],
                ['title' => 'Open invoices', 'columns' => [
                    ['key' => 'invoice', 'label' => 'Invoice', 'href' => 'href'],
                    ['key' => 'customer', 'label' => 'Customer'],
                    ['key' => 'due', 'label' => 'Due'],
                    ['key' => 'bucket', 'label' => 'Bucket'],
                    ['key' => 'balance', 'label' => 'Balance'],
                ], 'rows' => $lines],
            ],
        ];
    }

    /**
     * @param array{from: string, to: string, compare_from: string, compare_to: string, compare_label: string} $range
     * @return array{kpis: list<array<string, string>>, charts: list<array<string, string>>}
     */
    private function salesBlock(array $range): array
    {
        $current = $this->data->quoteDecision($range['from'], $range['to']);
        $previous = $this->data->quoteDecision($range['compare_from'], $range['compare_to']);
        $count = ReportMath::countConversion($current['accepted_count'], $current['declined_count']);
        $value = ReportMath::valueConversion($current['accepted_value'], $current['declined_value']);
        $countChange = $count === null ? null : ReportMath::change(
            $count,
            (string) (ReportMath::countConversion($previous['accepted_count'], $previous['declined_count']) ?? '0')
        );
        $pipeline = $this->data->pipeline();
        $openCount = 0;
        $openValue = '0.00';
        foreach ($pipeline as $row) {
            if (in_array((string) $row['status'], ['NEW', 'CONTACTED', 'QUALIFIED', 'QUOTED'], true)) {
                $openCount += (int) $row['n'];
                $openValue = Decimal::money(Decimal::add($openValue, (string) $row['value']));
            }
        }
        $average = Decimal::cmp($current['created_count'], '0') === 0
            ? null
            : Decimal::money(Decimal::div($current['created_value'], $current['created_count']));
        $target = $this->data->target('TARGET_QUOTE_CONVERSION');
        $hint = $countChange === null ? $range['compare_label'] . ' has no decided quotes, so no percentage change is shown.' : $countChange . '% vs ' . $range['compare_label'];

        return [
            'kpis' => [
                $this->kpi('Quotes created', $current['created_count'], '/quotes', money($current['created_value'])),
                $this->kpi('Accepted value', money($current['accepted_value']), '/quotes?status=ACCEPTED', $current['accepted_count'] . ' accepted'),
                $this->kpi('Count conversion', $count === null ? '—' : $count . '%', '/reports/sales', $hint . ' ' . $this->targetHint($count, $target, 'percentage points')),
                $this->kpi('Value conversion', $value === null ? '—' : $value . '%', '/reports/sales', 'Accepted value / decided value.'),
                $this->kpi('Average quote', $average === null ? '—' : money($average), '/quotes', 'Created in the period.'),
                $this->kpi('New customers', (string) $this->data->newCustomers($range['from'], $range['to']), '/customers', ''),
                $this->kpi('Open opportunities', (string) $openCount, '/opportunities', money($openValue) . ' estimated, not guaranteed.'),
            ],
            'charts' => [
                ['label' => 'Accepted', 'value' => $current['accepted_count']],
                ['label' => 'Declined', 'value' => $current['declined_count']],
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function pipelineRows(): array
    {
        $indexed = [];
        foreach ($this->data->pipeline() as $row) {
            $indexed[(string) $row['status']] = $row;
        }
        $rows = [];
        foreach (['NEW', 'CONTACTED', 'QUALIFIED', 'QUOTED', 'WON', 'LOST'] as $status) {
            $row = $indexed[$status] ?? ['n' => 0, 'value' => '0'];
            $rows[] = [
                'stage' => OpportunityStatus::from($status)->label(),
                'count' => (string) $row['n'],
                'value' => money((string) $row['value']),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private function openOpportunityRows(): array
    {
        $rows = [];
        foreach ($this->data->openOpportunities() as $row) {
            $rows[] = [
                'number' => (string) $row['opportunity_number'],
                'href' => '/opportunities/' . $row['id'],
                'customer' => customer_label($row),
                'salesperson' => (string) ($row['salesperson'] ?? ''),
                'close' => (string) ($row['expected_close_date'] ?? ''),
                'value' => money((string) ($row['estimated_value'] ?? '0')),
            ];
        }

        return $rows;
    }

    /**
     * @param array{from: string, to: string} $range
     * @return list<array<string, string>>
     */
    private function peopleRows(array $range): array
    {
        $counts = [];
        foreach ($this->data->opportunityCounts($range['from'], $range['to']) as $row) {
            $counts[(int) $row['id']] = (int) $row['n'];
        }
        $rows = [];
        foreach ($this->data->salespeople($range['from'], $range['to']) as $row) {
            $conversion = ReportMath::countConversion((string) $row['accepted_count'], (string) $row['declined_count']);
            $average = Decimal::cmp((string) $row['quotes_created'], '0') === 0
                ? '—'
                : money(Decimal::div((string) $row['quote_value'], (string) $row['quotes_created']));
            $rows[] = [
                'name' => (string) $row['name'],
                'opportunities' => (string) ($counts[(int) $row['id']] ?? 0),
                'quotes' => (string) $row['quotes_created'],
                'quote_value' => money((string) $row['quote_value']),
                'accepted' => (string) $row['accepted_count'],
                'accepted_value' => money((string) $row['accepted_value']),
                'conversion' => $conversion === null ? '—' : $conversion . '%',
                'average' => $average,
            ];
        }

        return $rows;
    }

    /**
     * @param array{from: string, to: string} $range
     * @return list<array<string, string>>
     */
    private function lostRows(array $range): array
    {
        $indexed = [];
        $total = '0.00';
        foreach ($this->data->lostReasons($range['from'], $range['to']) as $row) {
            $indexed[(string) $row['reason']] = $row;
            $total = Decimal::money(Decimal::add($total, (string) $row['value']));
        }
        $rows = [];
        foreach (LostReason::cases() as $reason) {
            $row = $indexed[$reason->value] ?? ['n' => 0, 'value' => '0'];
            $share = ReportMath::percent((string) $row['value'], $total);
            $rows[] = [
                'reason' => $reason->label(),
                'count' => (string) $row['n'],
                'value' => money((string) $row['value']),
                'share' => $share === null ? '—' : $share . '%',
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, string>>
     */
    private function followRows(): array
    {
        $rows = [];
        foreach ($this->data->quotesToFollow(date('Y-m-d')) as $row) {
            $sent = $row['status_changed_at'] ?? null;
            $days = $sent ? (string) (new \DateTimeImmutable((string) $sent))->diff(new \DateTimeImmutable('today'))->days : '';
            $rows[] = [
                'number' => (string) $row['quote_number'],
                'href' => '/quotes/' . $row['id'],
                'customer' => customer_label($row),
                'value' => money((string) $row['total']),
                'salesperson' => (string) ($row['salesperson'] ?? ''),
                'follow' => (string) $row['next_follow_up_date'] . ($days !== '' ? ' · ' . $days . ' days since sent' : ''),
            ];
        }

        return $rows;
    }

    /**
     * @param array{from: string, to: string} $range
     * @return list<array<string, mixed>>
     */
    private function jobRows(array $range): array
    {
        $rows = [];
        foreach ($this->data->jobsForProfit($range['from'], $range['to']) as $row) {
            $figures = self::jobProfit(
                (string) $row['quote_total'],
                (string) $row['variation_total'],
                (string) $row['actual_material_cost'],
                (string) $row['actual_labour_cost'],
                (string) $row['actual_other_cost'],
                (string) $row['quoted_cost_snapshot']
            );
            $rows[] = [
                'id' => (int) $row['id'],
                'customer_id' => (int) $row['customer_id'],
                'label' => (string) $row['job_number'],
                'href' => '/jobs/' . $row['id'],
                'customer' => customer_label($row),
                'commercial' => money($figures['commercial']),
                'commercial_raw' => $figures['commercial'],
                'quoted_cost' => money((string) $row['quoted_cost_snapshot']),
                'quoted_raw' => Decimal::money((string) $row['quoted_cost_snapshot']),
                'material' => money((string) $row['actual_material_cost']),
                'labour' => money((string) $row['actual_labour_cost']),
                'other' => money((string) $row['actual_other_cost']),
                'actual' => money($figures['actual']),
                'actual_raw' => $figures['actual'],
                'profit' => money($figures['profit']),
                'margin' => $figures['margin'] === null ? '—' : $figures['margin'] . '%',
                'margin_raw' => $figures['margin'],
                'variance' => money($figures['variance']) . ($figures['variance_percent'] === null ? '' : ' (' . $figures['variance_percent'] . '%)'),
                'status' => (string) $row['status'],
                'completed' => (string) ($row['completed_at'] ?? ''),
                'month' => substr((string) $row['created_at'], 0, 7),
                'salesperson' => (string) ($row['salesperson_name'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $jobs
     * @return list<array<string, mixed>>
     */
    private function groupJobs(array $jobs, string $group): array
    {
        if ($group === 'job' || $group === '') {
            return $jobs;
        }
        $groups = [];
        foreach ($jobs as $job) {
            $key = match ($group) {
                'customer' => (string) $job['customer_id'],
                'month' => (string) $job['month'],
                'salesperson' => (string) $job['salesperson'],
                default => (string) $job['id'],
            };
            if (!isset($groups[$key])) {
                $groups[$key] = $job;
                $groups[$key]['label'] = match ($group) {
                    'customer' => (string) $job['customer'],
                    'month' => (string) $job['month'],
                    'salesperson' => $job['salesperson'] === '' ? 'Unassigned' : (string) $job['salesperson'],
                    default => (string) $job['label'],
                };
                $groups[$key]['href'] = $group === 'customer' ? '/customers/' . $job['customer_id'] : '';
                $groups[$key]['_commercial'] = '0.00';
                $groups[$key]['_actual'] = '0.00';
                $groups[$key]['_quoted'] = '0.00';
                $groups[$key]['_material'] = '0.00';
                $groups[$key]['_labour'] = '0.00';
                $groups[$key]['_other'] = '0.00';
            }
            $groups[$key]['_commercial'] = Decimal::money(Decimal::add($groups[$key]['_commercial'], $job['commercial_raw']));
            $groups[$key]['_actual'] = Decimal::money(Decimal::add($groups[$key]['_actual'], $job['actual_raw']));
            $groups[$key]['_quoted'] = Decimal::money(Decimal::add($groups[$key]['_quoted'], $job['quoted_raw']));
        }
        $rows = [];
        foreach ($groups as $row) {
            $figures = self::jobProfit($row['_commercial'], '0', $row['_actual'], '0', '0', $row['_quoted']);
            $row['commercial'] = money($figures['commercial']);
            $row['commercial_raw'] = $figures['commercial'];
            $row['actual'] = money($row['_actual']);
            $row['actual_raw'] = $row['_actual'];
            $row['quoted_cost'] = money($row['_quoted']);
            $row['profit'] = money(ReportMath::grossProfit($row['_commercial'], $row['_actual']));
            $margin = ReportMath::margin(ReportMath::grossProfit($row['_commercial'], $row['_actual']), $row['_commercial']);
            $row['margin'] = $margin === null ? '—' : $margin . '%';
            $row['margin_raw'] = $margin;
            $variance = Decimal::money(Decimal::sub($row['_actual'], $row['_quoted']));
            $row['variance'] = money($variance);
            $row['material'] = 'See jobs';
            $row['labour'] = 'See jobs';
            $row['other'] = 'See jobs';
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array{from: string, to: string} $range
     * @return list<array<string, mixed>>
     */
    private function customerRows(array $range, bool $showCost): array
    {
        $invoices = [];
        foreach ($this->data->customerInvoices() as $row) {
            $invoices[(int) $row['customer_id']] = $row;
        }
        $jobsByCustomer = [];
        foreach ($this->jobRows($range) as $job) {
            $jobsByCustomer[$job['customer_id']][] = [
                'commercial' => $job['commercial_raw'],
                'actual' => $job['actual_raw'],
            ];
        }
        $rows = [];
        foreach ($this->data->customerQuotes($range['from'], $range['to']) as $row) {
            $id = (int) $row['customer_id'];
            $profit = self::customerProfit($jobsByCustomer[$id] ?? []);
            $invoice = $invoices[$id] ?? ['invoiced' => '0', 'paid' => '0', 'outstanding' => '0'];
            $rows[] = [
                'customer' => customer_label($row),
                'href' => '/customers/' . $id,
                'quote_value' => money((string) $row['quote_value']),
                'accepted' => money((string) $row['accepted_value']),
                'invoiced' => money((string) $invoice['invoiced']),
                'paid' => money((string) $invoice['paid']),
                'outstanding' => money((string) $invoice['outstanding']),
                'profit' => $showCost ? money($profit['profit']) : 'Hidden',
                'margin' => !$showCost || $profit['margin'] === null ? '—' : $profit['margin'] . '%',
                'commercial_raw' => $profit['commercial'],
            ];
        }
        usort($rows, static fn (array $a, array $b): int => Decimal::cmp((string) $b['commercial_raw'], (string) $a['commercial_raw']));

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{top5: string, rows: list<array<string, string>>}
     */
    private function concentration(array $rows, bool $showCost): array
    {
        $total = '0.00';
        foreach ($rows as $row) {
            $total = Decimal::money(Decimal::add($total, (string) $row['commercial_raw']));
        }
        $top = '0.00';
        foreach (array_slice($rows, 0, 5) as $row) {
            $top = Decimal::money(Decimal::add($top, (string) $row['commercial_raw']));
        }
        $share = ReportMath::percent($top, $total);

        return [
            'top5' => $share === null ? '—' : $share . '%',
            'rows' => [
                ['label' => 'Top 5 customers by commercial value', 'value' => $share === null ? '—' : $share . '% of ' . money($total)],
                ['label' => 'Customers listed', 'value' => (string) count($rows)],
                ['label' => 'Profit columns', 'value' => $showCost ? 'Shown' : 'Hidden for this role'],
            ],
        ];
    }

    /**
     * @return array{label: string, value: string, href: string, hint: string}
     */
    private function kpi(string $label, string $value, string $href, string $hint): array
    {
        return ['label' => $label, 'value' => $value, 'href' => $href, 'hint' => $hint];
    }

    private function targetHint(?string $actual, ?string $target, string $unit): string
    {
        if ($actual === null || $target === null) {
            return '';
        }
        $diff = ReportMath::difference($actual, $target);

        return 'Target ' . $target . '. Difference ' . $diff . ' ' . $unit . '.';
    }
}

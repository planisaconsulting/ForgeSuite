<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\QuotePdf;
use App\Services\ReportService;

/**
 * Predefined reports. Each address checks its own permission.
 */
final class ReportController
{
    /** @var array<string, string> */
    private const ACCESS = [
        'executive' => 'reports.executive',
        'sales' => 'reports.sales',
        'customers' => 'reports.sales',
        'jobs' => 'reports.operations',
        'profitability' => 'reports.profitability',
        'production' => 'reports.operations',
        'waste' => 'reports.operations',
        'inventory' => 'reports.inventory',
        'purchasing' => 'reports.inventory',
        'finance' => 'reports.finance',
        'debtors' => 'reports.finance',
    ];

    public function show(string $code): void
    {
        $code = $this->allowed($code);
        $service = new ReportService();
        $report = $service->build($code, $service->range($_GET), $this->options());
        $this->render($code, $report);
    }

    public function csv(string $code): void
    {
        if (!can('reports.export')) {
            deny_access('You cannot export reports.');
        }
        $code = $this->allowed($code);
        $service = new ReportService();
        $report = $service->build($code, $service->range($_GET), $this->options());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="signforge-' . $code . '.csv"');
        echo $service->csv($report);
        exit;
    }

    public function pdf(string $code): void
    {
        if (!can('reports.export')) {
            deny_access('You cannot export reports.');
        }
        $code = $this->allowed($code);
        $service = new ReportService();
        $report = $service->build($code, $service->range($_GET), $this->options());
        $binary = (new QuotePdf())->render($service->pdfHtml($report));
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="signforge-' . $code . '.pdf"');
        echo $binary;
        exit;
    }

    private function allowed(string $code): string
    {
        if (!isset(self::ACCESS[$code])) {
            abort_not_found('That report does not exist.');
        }
        if (!can(self::ACCESS[$code])) {
            deny_access('Your role cannot open that report.');
        }

        return $code;
    }

    /**
     * @return array{show_cost: bool, group: string}
     */
    private function options(): array
    {
        $group = (string) ($_GET['group'] ?? 'job');
        if (!in_array($group, ['job', 'customer', 'month', 'salesperson'], true)) {
            $group = 'job';
        }

        return [
            'show_cost' => can('reports.profitability') || can('costing.view') || can('finance.costing.view'),
            'group' => $group,
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    private function render(string $code, array $report): void
    {
        View::render('reports/show', [
            'title' => (string) $report['title'],
            'activeNav' => 'report-' . $code,
            'report' => $report,
            'canExport' => can('reports.export'),
            'board' => isset($_GET['board']),
            'query' => $_GET,
        ]);
    }
}

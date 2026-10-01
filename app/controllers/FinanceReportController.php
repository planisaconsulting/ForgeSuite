<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Services\DebtorAgeingService;
use App\Services\QuotePdf;
use App\Services\SettingsService;
use App\Services\StatementService;

final class FinanceReportController
{
    public function debtors(): void
    {
        $report = (new DebtorAgeingService())->report();
        if (($_GET['export'] ?? '') === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="debtor-ageing.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['invoice', 'customer', 'due', 'balance', 'bucket']);
            foreach ($report['lines'] as $line) {
                fputcsv($out, [$line['invoice_number'], customer_label($line), $line['due_date'], $line['balance_due'], $line['bucket']]);
            }
            exit;
        }
        View::render('finance/debtors', [
            'title' => 'Debtors',
            'activeNav' => 'debtors',
            'report' => $report,
        ]);
    }

    public function vat(): void
    {
        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $summary = (new FinanceRepository())->vatSummary($from, $to);
        if (($_GET['export'] ?? '') === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="vat-summary.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['from', 'to', 'taxable', 'output_vat', 'credit_vat', 'net_vat']);
            fputcsv($out, [$from, $to, $summary['taxable'], $summary['output_vat'], $summary['credit_vat'], $summary['net_vat']]);
            exit;
        }
        View::render('finance/vat', [
            'title' => 'VAT summary',
            'activeNav' => 'vat',
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
        ]);
    }

    public function statementForm(): void
    {
        View::render('finance/statement', [
            'title' => 'Statements',
            'activeNav' => 'statements',
            'customers' => (new CustomerRepository())->search('', 'active', 300),
            'statement' => null,
            'from' => date('Y-m-01'),
            'to' => date('Y-m-d'),
            'customerId' => 0,
        ]);
    }

    public function statement(): void
    {
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
        $to = trim((string) ($_GET['to'] ?? date('Y-m-d')));
        $statement = $customerId > 0 ? (new StatementService())->build($customerId, $from, $to) : null;
        if ($statement !== null && (($_GET['export'] ?? '') === 'pdf')) {
            $html = View::capture('finance/statement_pdf', [
                'statement' => $statement,
                'company' => (string) SettingsService::get('company_name', 'Sign-Forge'),
                'bank' => [
                    'name' => SettingsService::get('bank_name', ''),
                    'account' => SettingsService::get('account_name', ''),
                    'number' => SettingsService::get('account_number', ''),
                    'branch' => SettingsService::get('branch_code', ''),
                ],
            ]);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="statement.pdf"');
            echo (new QuotePdf())->render($html);
            exit;
        }
        View::render('finance/statement', [
            'title' => 'Statements',
            'activeNav' => 'statements',
            'customers' => (new CustomerRepository())->search('', 'active', 300),
            'statement' => $statement,
            'from' => $from,
            'to' => $to,
            'customerId' => $customerId,
        ]);
    }

    public function hold(string $id): void
    {
        if (!can('invoices.issue')) {
            deny_access('You cannot change an account hold.');
        }
        $customerId = route_id($id);
        $hold = (int) ($_POST['account_on_hold'] ?? 0) === 1;
        $reason = trim((string) ($_POST['account_hold_reason'] ?? ''));
        if ($hold && $reason === '') {
            flash('error', 'An account hold needs a reason.');
            redirect('/customers/' . $customerId);
        }
        $finance = new FinanceRepository();
        $limit = trim((string) ($_POST['credit_limit'] ?? ''));
        $term = (int) ($_POST['payment_term_id'] ?? 0);
        $finance->setCredit($customerId, $limit === '' ? null : $limit, $term > 0 ? $term : null);
        $finance->setHold($customerId, $hold ? 1 : 0, $hold ? $reason : null, (int) auth_user()['id']);
        (new \App\Services\AuditService())->record('customer', $customerId, $hold ? 'CUSTOMER_ACCOUNT_HOLD' : 'CUSTOMER_ACCOUNT_RELEASED', null, [
            'reason' => $hold ? $reason : null,
        ], (int) auth_user()['id']);
        flash('success', $hold ? 'Account placed on hold.' : 'Account hold released.');
        redirect('/customers/' . $customerId);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\InvoiceStatus;
use App\Domain\InvoiceType;
use App\Helpers\View;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\JobRepository;
use App\Repositories\QuoteRepository;
use App\Services\InvoiceService;
use App\Services\QuotePdf;
use App\Services\SettingsService;

final class InvoiceController
{
    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            'type' => strtoupper(trim((string) ($_GET['type'] ?? ''))),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ];
        $rows = (new FinanceRepository())->invoices($filters);
        if (($_GET['export'] ?? '') === 'csv') {
            $this->csv($rows);
        }
        View::render('invoices/index', [
            'title' => 'Invoices',
            'activeNav' => 'invoices',
            'rows' => $rows,
            'filters' => $filters,
            'statuses' => InvoiceStatus::cases(),
            'types' => InvoiceType::cases(),
        ]);
    }

    public function createForm(): void
    {
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $quoteId = (int) ($_GET['quote_id'] ?? 0);
        $jobId = (int) ($_GET['job_id'] ?? 0);
        $quote = $quoteId > 0 ? (new QuoteRepository())->find($quoteId) : null;
        $job = $jobId > 0 ? (new JobRepository())->find($jobId) : null;
        if ($job !== null) {
            $customerId = (int) $job['customer_id'];
            $quoteId = (int) $job['quote_id'];
            $quote = (new QuoteRepository())->find($quoteId);
        } elseif ($quote !== null) {
            $customerId = (int) $quote['customer_id'];
        }
        $position = (new InvoiceService())->position($quoteId > 0 ? $quoteId : null, $jobId > 0 ? $jobId : null);
        View::render('invoices/create', [
            'title' => 'New invoice',
            'activeNav' => 'invoices',
            'customers' => (new CustomerRepository())->search('', 'active', 300),
            'customerId' => $customerId,
            'quote' => $quote,
            'job' => $job,
            'position' => $position,
            'types' => InvoiceType::cases(),
            'terms' => (new FinanceRepository())->terms(),
        ]);
    }

    public function store(): void
    {
        $result = (new InvoiceService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) ($result['errors']['_form'] ?? reset($result['errors'])));
            redirect('/invoices/new');
        }
        flash('success', 'Draft invoice saved. Review it before issuing.');
        redirect('/invoices/' . $result['id']);
    }

    public function show(string $id): void
    {
        $invoice = $this->invoice($id);
        $service = new InvoiceService();
        View::render('invoices/show', [
            'title' => (string) ($invoice['invoice_number'] ?: 'Draft invoice'),
            'activeNav' => 'invoices',
            'invoice' => $invoice,
            'items' => (new FinanceRepository())->items((int) $invoice['id']),
            'history' => (new FinanceRepository())->history((int) $invoice['id']),
            'position' => $service->position(
                (int) ($invoice['quote_id'] ?? 0) ?: null,
                (int) ($invoice['job_id'] ?? 0) ?: null
            ),
            'terms' => (new FinanceRepository())->terms(),
            'presented' => InvoiceStatus::present(
                (string) $invoice['status'],
                (string) $invoice['balance_due'],
                $invoice['due_date'] !== null ? (string) $invoice['due_date'] : null,
                date('Y-m-d')
            ),
        ]);
    }

    public function save(string $id): void
    {
        $invoice = $this->invoice($id);
        $errors = (new InvoiceService())->save((int) $invoice['id'], $_POST, (int) $invoice['version_number'], (int) auth_user()['id']);
        $this->back($invoice, $errors, 'Draft saved.');
    }

    public function addLine(string $id): void
    {
        $invoice = $this->invoice($id);
        $errors = (new InvoiceService())->addLine((int) $invoice['id'], $_POST, (int) $invoice['version_number'], (int) auth_user()['id']);
        $this->back($invoice, $errors, 'Line added.');
    }

    public function issue(string $id): void
    {
        $invoice = $this->invoice($id);
        $errors = (new InvoiceService())->issue(
            (int) $invoice['id'],
            (int) $invoice['version_number'],
            (int) auth_user()['id'],
            trim((string) ($_POST['override_reason'] ?? ''))
        );
        $this->back($invoice, $errors, 'Invoice issued.');
    }

    public function cancel(string $id): void
    {
        $invoice = $this->invoice($id);
        $errors = (new InvoiceService())->cancel(
            (int) $invoice['id'],
            (int) $invoice['version_number'],
            (int) auth_user()['id'],
            trim((string) ($_POST['cancellation_reason'] ?? ''))
        );
        $this->back($invoice, $errors, 'Invoice cancelled.');
    }

    public function flag(string $id): void
    {
        $invoice = $this->invoice($id);
        $errors = (new InvoiceService())->setCollection((int) $invoice['id'], $_POST['collection_flag'] ?? '', (int) auth_user()['id']);
        $this->back($invoice, $errors, 'Collection note saved.');
    }

    public function pdf(string $id): void
    {
        $invoice = $this->invoice($id);
        if ((string) $invoice['status'] === 'DRAFT') {
            flash('error', 'Issue the invoice before downloading the customer PDF.');
            redirect('/invoices/' . $invoice['id']);
        }
        $items = (new FinanceRepository())->items((int) $invoice['id']);
        $html = View::capture('invoices/pdf', [
            'invoice' => $invoice,
            'items' => $items,
            'company' => (string) ($invoice['company_name_snapshot'] ?: SettingsService::get('company_name', '')),
        ]);
        $binary = (new QuotePdf())->render($html);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $invoice['invoice_number']) ?: 'invoice';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $name . '.pdf"');
        echo $binary;
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoice(string $id): array
    {
        $row = (new FinanceRepository())->invoice(route_id($id));
        if ($row === null) {
            abort_not_found('That invoice was not found.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, string> $errors
     */
    private function back(array $invoice, array $errors, string $message): void
    {
        if ($errors !== []) {
            flash('error', (string) ($errors['_form'] ?? reset($errors)));
        } else {
            flash('success', $message);
        }
        redirect('/invoices/' . $invoice['id']);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function csv(array $rows): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="invoices.csv"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }
        fputcsv($out, ['invoice_number', 'date', 'customer', 'job', 'type', 'status', 'due', 'total', 'paid', 'balance']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['invoice_number'], $row['invoice_date'], customer_label($row), $row['job_number'],
                $row['invoice_type'], $row['status'], $row['due_date'], $row['total'], $row['amount_paid'], $row['balance_due'],
            ]);
        }
        exit;
    }
}

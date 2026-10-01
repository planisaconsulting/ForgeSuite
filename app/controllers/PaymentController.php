<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\PaymentMethod;
use App\Helpers\Decimal;
use App\Helpers\View;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Services\PaymentService;
use App\Services\QuotePdf;

final class PaymentController
{
    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
        ];
        $rows = (new FinanceRepository())->payments($filters);
        if (($_GET['export'] ?? '') === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="payments.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'date', 'customer', 'amount', 'method', 'status']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['payment_reference'], $row['payment_date'], customer_label($row), $row['amount'], $row['payment_method'], $row['status']]);
            }
            exit;
        }
        View::render('payments/index', [
            'title' => 'Payments',
            'activeNav' => 'payments',
            'rows' => $rows,
            'filters' => $filters,
            'customers' => (new CustomerRepository())->search('', 'active', 300),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function store(): void
    {
        $result = (new PaymentService())->record($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) ($result['errors']['_form'] ?? reset($result['errors'])));
            redirect('/payments');
        }
        flash('success', 'Payment recorded.');
        redirect('/payments/' . $result['id']);
    }

    public function show(string $id): void
    {
        $payment = $this->payment($id);
        $finance = new FinanceRepository();
        $allocated = $finance->allocated((int) $payment['id']);
        View::render('payments/show', [
            'title' => (string) $payment['payment_reference'],
            'activeNav' => 'payments',
            'payment' => $payment,
            'allocations' => $finance->allocations((int) $payment['id']),
            'open' => $finance->openInvoices((int) $payment['customer_id']),
            'available' => Decimal::money(Decimal::sub((string) $payment['amount'], $allocated)),
        ]);
    }

    public function allocate(string $id): void
    {
        $payment = $this->payment($id);
        $errors = (new PaymentService())->allocate((int) $payment['id'], $_POST['allocate'] ?? [], (int) auth_user()['id']);
        if ($errors !== []) {
            flash('error', (string) ($errors['_form'] ?? reset($errors)));
        } else {
            flash('success', 'Payment allocated.');
        }
        redirect('/payments/' . $payment['id']);
    }

    public function reverse(string $id): void
    {
        $payment = $this->payment($id);
        $errors = (new PaymentService())->reverse((int) $payment['id'], (int) auth_user()['id'], (string) ($_POST['reason'] ?? ''));
        if ($errors !== []) {
            flash('error', (string) ($errors['_form'] ?? $errors['reason'] ?? reset($errors)));
        } else {
            flash('success', 'Payment reversed.');
        }
        redirect('/payments/' . $payment['id']);
    }

    public function receipt(string $id): void
    {
        $payment = $this->payment($id);
        $html = View::capture('payments/receipt', [
            'payment' => $payment,
            'allocations' => (new FinanceRepository())->allocations((int) $payment['id']),
            'company' => (string) \App\Services\SettingsService::get('company_name', 'Sign-Forge'),
        ]);
        $binary = (new QuotePdf())->render($html);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $payment['payment_reference'] . '-receipt.pdf"');
        echo $binary;
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(string $id): array
    {
        $row = (new FinanceRepository())->payment(route_id($id));
        if ($row === null) {
            abort_not_found('That payment was not found.');
        }

        return $row;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Services\CreditNoteService;
use App\Services\QuotePdf;
use App\Services\SettingsService;

final class CreditNoteController
{
    public function index(): void
    {
        $rows = (new FinanceRepository())->credits([
            'q' => trim((string) ($_GET['q'] ?? '')),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
        ]);
        if (($_GET['export'] ?? '') === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="credit-notes.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['number', 'date', 'customer', 'invoice', 'status', 'total', 'reason']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['credit_note_number'], $row['credit_date'], customer_label($row), $row['invoice_number'], $row['status'], $row['total'], $row['reason']]);
            }
            exit;
        }
        View::render('credit_notes/index', [
            'title' => 'Credit notes',
            'activeNav' => 'credits',
            'rows' => $rows,
            'customers' => (new CustomerRepository())->search('', 'active', 300),
        ]);
    }

    public function store(): void
    {
        $result = (new CreditNoteService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            flash('error', (string) ($result['errors']['_form'] ?? reset($result['errors'])));
            $back = (int) ($_POST['invoice_id'] ?? 0);
            redirect($back > 0 ? '/invoices/' . $back : '/credit-notes');
        }
        flash('success', 'Credit note drafted.');
        redirect('/credit-notes/' . $result['id']);
    }

    public function show(string $id): void
    {
        $note = $this->note($id);
        View::render('credit_notes/show', [
            'title' => (string) ($note['credit_note_number'] ?: 'Draft credit note'),
            'activeNav' => 'credits',
            'note' => $note,
            'items' => (new FinanceRepository())->creditItems((int) $note['id']),
        ]);
    }

    public function issue(string $id): void
    {
        $note = $this->note($id);
        $errors = (new CreditNoteService())->issue((int) $note['id'], (int) auth_user()['id']);
        if ($errors !== []) {
            flash('error', (string) ($errors['_form'] ?? reset($errors)));
        } else {
            flash('success', 'Credit note issued.');
        }
        redirect('/credit-notes/' . $note['id']);
    }

    public function pdf(string $id): void
    {
        $note = $this->note($id);
        if ((string) $note['status'] === 'DRAFT') {
            flash('error', 'Issue the credit note before downloading it.');
            redirect('/credit-notes/' . $note['id']);
        }
        $html = View::capture('credit_notes/pdf', [
            'note' => $note,
            'items' => (new FinanceRepository())->creditItems((int) $note['id']),
            'company' => (string) SettingsService::get('company_name', 'Sign-Forge'),
        ]);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . ($note['credit_note_number'] ?: 'credit-note') . '.pdf"');
        echo (new QuotePdf())->render($html);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function note(string $id): array
    {
        $row = (new FinanceRepository())->credit(route_id($id));
        if ($row === null) {
            abort_not_found('That credit note was not found.');
        }

        return $row;
    }
}

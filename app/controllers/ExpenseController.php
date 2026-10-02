<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\ExpenseService;

/**
 * Expense desk. Approval records a cost. It does not pay the person.
 */
final class ExpenseController
{
    public function index(): void
    {
        $service = new ExpenseService();
        $userId = (int) auth_user()['id'];
        View::render('expenses/index', [
            'title' => 'Expenses',
            'activeNav' => 'expenses',
            'cards' => $service->dashboard(),
            'rows' => $service->page(40, 0, $userId),
            'categories' => (new \App\Repositories\ExpenseRepository())->categories(),
        ]);
    }

    public function show(string $id): void
    {
        $row = (new ExpenseService())->open((int) $id, (int) auth_user()['id']);
        if ($row === null) {
            flash('error', 'That expense was not found.');
            redirect('/expenses');
        }
        View::render('expenses/show', [
            'title' => (string) $row['expense_number'],
            'activeNav' => 'expenses',
            'expense' => $row,
        ]);
    }

    public function create(): void
    {
        $result = (new ExpenseService())->create($_POST, (int) auth_user()['id']);
        if ($result['id'] === null) {
            flash('error', (string) (reset($result['errors']) ?: 'The expense was not saved.'));
            redirect('/expenses');
        }
        $message = 'Expense ' . $result['number'] . ' captured.';
        if ($result['warning'] !== null) {
            $message .= ' ' . $result['warning'];
        }
        flash('success', $message);
        redirect('/expenses/' . $result['id']);
    }

    public function submit(string $id): void
    {
        $result = (new ExpenseService())->submit((int) $id, (int) auth_user()['id']);
        $row = (new ExpenseService())->open((int) $id, (int) auth_user()['id']);
        $number = (string) ($row['expense_number'] ?? 'expense');
        flash($result['errors'] === [] ? 'success' : 'error', $result['errors'] === [] ? 'Expense ' . $number . ' submitted for approval.' : (string) reset($result['errors']));
        redirect('/expenses/' . (int) $id);
    }

    public function approve(string $id): void
    {
        $result = (new ExpenseService())->approve((int) $id, (int) auth_user()['id']);
        $text = $result['warning'] ?? ($result['errors'] === [] ? 'Expense approved. The job cost was posted once.' : (string) reset($result['errors']));
        flash($result['errors'] === [] ? 'success' : 'error', $text);
        redirect('/expenses/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $result = (new ExpenseService())->reject((int) $id, (string) ($_POST['reason'] ?? ''), (int) auth_user()['id']);
        flash($result['errors'] === [] ? 'success' : 'error', $result['errors'] === [] ? 'Expense rejected. No job cost was posted.' : (string) reset($result['errors']));
        redirect('/expenses/' . (int) $id);
    }
}

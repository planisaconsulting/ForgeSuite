<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Services\SalesIntakeService;

/**
 * Sales intake desk. Prices are not entered on this screen.
 */
final class SalesIntakeController
{
    public function index(): void
    {
        $service = new SalesIntakeService();
        View::render('sales_intake/inbox', [
            'title' => 'Sales intake',
            'activeNav' => 'sales-intake',
            'cards' => $service->dashboard(),
            'rows' => $service->page(30, 0),
            'report' => $service->report(),
        ]);
    }

    public function show(string $id): void
    {
        $service = new SalesIntakeService();
        $opened = $service->workspace((int) $id, (int) auth_user()['id']);
        if ($opened['intake'] === null) {
            redirect('/sales/intake');
        }
        View::render('sales_intake/show', [
            'title' => (string) $opened['intake']['intake_number'],
            'activeNav' => 'sales-intake',
            'intake' => $opened['intake'],
        ]);
    }

    public function create(): void
    {
        $result = (new SalesIntakeService())->create([
            'source_type' => (string) ($_POST['source_type'] ?? 'MANUAL'),
            'message' => (string) ($_POST['message'] ?? ''),
            'sender_name' => (string) ($_POST['sender_name'] ?? ''),
            'sender_email' => (string) ($_POST['sender_email'] ?? ''),
            'subject' => (string) ($_POST['subject'] ?? ''),
            'received_at' => (string) ($_POST['received_at'] ?? ''),
        ], (int) auth_user()['id']);
        if ($result['id'] === null) {
            redirect('/sales/intake');
        }
        redirect('/sales/intake/' . $result['id']);
    }

    public function analyse(string $id): void
    {
        (new SalesIntakeService())->analyse((int) $id, (int) auth_user()['id']);
        redirect('/sales/intake/' . (int) $id);
    }

    public function confirmCustomer(string $id): void
    {
        (new SalesIntakeService())->confirmCustomer((int) $id, (int) ($_POST['customer_id'] ?? 0), (int) auth_user()['id']);
        redirect('/sales/intake/' . (int) $id);
    }

    public function confirmField(string $id): void
    {
        (new SalesIntakeService())->confirmField((int) $id, (string) ($_POST['field_key'] ?? ''), (string) ($_POST['value'] ?? ''), (int) auth_user()['id']);
        redirect('/sales/intake/' . (int) $id);
    }

    public function confirmRequirements(string $id): void
    {
        (new SalesIntakeService())->confirmRequirements((int) $id, (int) auth_user()['id']);
        redirect('/sales/intake/' . (int) $id);
    }

    public function match(string $id): void
    {
        (new SalesIntakeService())->matchProducts((int) $id, (int) auth_user()['id']);
        redirect('/sales/intake/' . (int) $id);
    }

    public function confirmProduct(string $id): void
    {
        (new SalesIntakeService())->confirmProduct((int) $id, (int) ($_POST['match_id'] ?? 0), (int) auth_user()['id']);
        redirect('/sales/intake/' . (int) $id);
    }

    public function estimate(string $id): void
    {
        (new SalesIntakeService())->createEstimate((int) $id, (int) auth_user()['id']);
        redirect('/sales/intake/' . (int) $id);
    }

    public function quote(string $id): void
    {
        (new SalesIntakeService())->createQuote((int) $id, (int) auth_user()['id']);
        redirect('/sales/intake/' . (int) $id);
    }
}

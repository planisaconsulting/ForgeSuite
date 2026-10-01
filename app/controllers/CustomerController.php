<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\ActivityRepository;
use App\Repositories\AuditRepository;
use App\Repositories\ContactRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\JobRepository;
use App\Repositories\OpportunityRepository;
use App\Repositories\QuoteRepository;
use App\Services\CustomerService;

final class CustomerController
{
    public function index(): void
    {
        $term = trim((string) ($_GET['q'] ?? ''));
        $status = list_status();
        View::render('customers/index', [
            'title' => 'Customers',
            'activeNav' => 'customers',
            'rows' => (new CustomerRepository())->search($term, $status),
            'term' => $term,
            'status' => $status,
            'canManage' => can('customers.manage'),
        ]);
    }

    public function create(): void
    {
        $this->form(null, [], []);
    }

    public function store(): void
    {
        $result = (new CustomerService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            $this->form(null, $result['errors'], $_POST);

            return;
        }
        flash('success', 'Customer created.');
        redirect('/customers/' . $result['id']);
    }

    public function show(string $id): void
    {
        $customerId = route_id($id);
        $customer = (new CustomerRepository())->find($customerId);
        if ($customer === null) {
            abort_not_found('That customer was not found.');
        }
        View::render('customers/show', [
            'title' => customer_label($customer),
            'activeNav' => 'customers',
            'customer' => $customer,
            'contacts' => (new ContactRepository())->forCustomer($customerId),
            'activities' => (new ActivityRepository())->forCustomer($customerId),
            'audit' => (new AuditRepository())->forEntity('customer', $customerId),
            'canManage' => can('customers.manage'),
            'canActivity' => can('activities.manage'),
            ...$this->salesLists($customerId),
            'contactErrors' => [],
            'contactOld' => ['active' => '1'],
            'activityErrors' => [],
            'activityOld' => ['activity_date' => date('Y-m-d'), 'activity_type' => 'NOTE'],
            ...$this->financeContext($customerId),
        ]);
    }

    public function edit(string $id): void
    {
        $customer = $this->requireCustomer($id);
        $this->form($customer, [], $customer);
    }

    public function update(string $id): void
    {
        $customerId = route_id($id);
        $errors = (new CustomerService())->update($customerId, $_POST);
        if ($errors !== []) {
            $customer = (new CustomerRepository())->find($customerId) ?? abort_not_found();
            $this->form($customer, $errors, $_POST);

            return;
        }
        flash('success', 'Customer updated.');
        redirect('/customers/' . $customerId);
    }

    public function deactivate(string $id): void
    {
        $customerId = route_id($id);
        $active = posted_flag($_POST, 'active', 0) === 1;
        if (!(new CustomerService())->setActive($customerId, $active)) {
            abort_not_found('That customer was not found.');
        }
        flash('success', $active ? 'Customer activated.' : 'Customer deactivated. The record is kept.');
        redirect('/customers/' . $customerId);
    }

    public function storeContact(string $id): void
    {
        $customerId = route_id($id);
        $result = (new CustomerService())->saveContact($customerId, null, $_POST);
        if ($result['errors'] !== []) {
            $this->showWithErrors($customerId, $result['errors'], $_POST, [], []);

            return;
        }
        flash('success', 'Contact added.');
        redirect('/customers/' . $customerId);
    }

    public function updateContact(string $id, string $contactId): void
    {
        $customerId = route_id($id);
        $result = (new CustomerService())->saveContact($customerId, route_id($contactId), $_POST);
        if ($result['errors'] !== []) {
            flash('error', $result['errors']['_form'] ?? 'The contact could not be saved. Check the form and try again.');
            redirect('/customers/' . $customerId);
        }
        flash('success', 'Contact updated.');
        redirect('/customers/' . $customerId);
    }

    public function storeActivity(string $id): void
    {
        $customerId = route_id($id);
        $errors = (new CustomerService())->addActivity($customerId, (int) auth_user()['id'], $_POST);
        if ($errors !== []) {
            $this->showWithErrors($customerId, [], [], $errors, $_POST);

            return;
        }
        flash('success', 'Activity recorded.');
        redirect('/customers/' . $customerId);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     * @param array<string, mixed>|null $customer
     */
    private function form(?array $customer, array $errors, array $old): void
    {
        View::render('customers/form', [
            'title' => $customer === null ? 'New customer' : 'Edit customer',
            'activeNav' => 'customers',
            'customer' => $customer,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireCustomer(string $id): array
    {
        $customer = (new CustomerRepository())->find(route_id($id));
        if ($customer === null) {
            abort_not_found('That customer was not found.');
        }

        return $customer;
    }

    /**
     * @return array{opportunities: list<array<string, mixed>>, quotes: list<array<string, mixed>>, jobs: list<array<string, mixed>>, canQuote: bool}
     */
    private function salesLists(int $customerId): array
    {
        return [
            'opportunities' => can('opportunities.view') ? (new OpportunityRepository())->forCustomer($customerId) : [],
            'quotes' => can('quotes.view') ? (new QuoteRepository())->forCustomer($customerId) : [],
            'jobs' => can('jobs.view') ? (new JobRepository())->forCustomer($customerId) : [],
            'canQuote' => can('quotes.manage'),
        ];
    }

    /**
     * @param array<string, string> $contactErrors
     * @param array<string, mixed> $contactOld
     * @param array<string, string> $activityErrors
     * @param array<string, mixed> $activityOld
     */
    /**
     * @return array<string, mixed>
     */
    private function financeContext(int $customerId): array
    {
        if (!can('invoices.view') && !can('payments.view')) {
            return ['account' => null, 'financeInvoices' => [], 'financePayments' => [], 'financeCredits' => [], 'paymentTerms' => []];
        }
        $finance = new \App\Repositories\FinanceRepository();

        return [
            'account' => $finance->account($customerId),
            'financeInvoices' => can('invoices.view') ? $finance->invoices(['customer_id' => $customerId], 20) : [],
            'financePayments' => can('payments.view') ? $finance->payments(['customer_id' => $customerId], 20) : [],
            'financeCredits' => can('credit_notes.view') ? $finance->credits(['customer_id' => $customerId], 20) : [],
            'paymentTerms' => $finance->terms(),
        ];
    }

    private function showWithErrors(
        int $customerId,
        array $contactErrors,
        array $contactOld,
        array $activityErrors,
        array $activityOld
    ): void {
        $customer = (new CustomerRepository())->find($customerId);
        if ($customer === null) {
            abort_not_found('That customer was not found.');
        }
        View::render('customers/show', [
            'title' => customer_label($customer),
            'activeNav' => 'customers',
            'customer' => $customer,
            'contacts' => (new ContactRepository())->forCustomer($customerId),
            'activities' => (new ActivityRepository())->forCustomer($customerId),
            'audit' => (new AuditRepository())->forEntity('customer', $customerId),
            'canManage' => can('customers.manage'),
            'canActivity' => can('activities.manage'),
            ...$this->salesLists($customerId),
            'contactErrors' => $contactErrors,
            'contactOld' => $contactOld,
            'activityErrors' => $activityErrors,
            'activityOld' => $activityOld === [] ? ['activity_date' => date('Y-m-d'), 'activity_type' => 'NOTE'] : $activityOld,
            ...$this->financeContext($customerId),
        ]);
    }
}

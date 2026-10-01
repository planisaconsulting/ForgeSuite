<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\LostReason;
use App\Domain\OpportunitySource;
use App\Domain\OpportunityStatus;
use App\Helpers\View;
use App\Repositories\ActivityRepository;
use App\Repositories\AttachmentRepository;
use App\Repositories\ContactRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OpportunityRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\UserRepository;
use App\Services\CustomerService;
use App\Services\OpportunityService;

final class OpportunityController
{
    public function index(): void
    {
        View::render('opportunities/index', [
            'title' => 'Opportunities',
            'activeNav' => 'opportunities',
            'rows' => (new OpportunityRepository())->search(trim((string) ($_GET['q'] ?? '')), (string) ($_GET['status'] ?? 'all')),
            'term' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? 'all'),
            'canManage' => can('opportunities.manage'),
        ]);
    }

    public function create(): void
    {
        $this->form([
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'status' => 'NEW',
            'source' => 'PHONE',
            'assigned_to' => (int) (auth_user()['id'] ?? 0),
        ], []);
    }

    public function store(): void
    {
        $result = (new OpportunityService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            $this->form($_POST, $result['errors']);

            return;
        }
        flash('success', 'Opportunity created.');
        redirect('/opportunities/' . $result['id']);
    }

    public function show(string $id): void
    {
        $row = $this->row($id);
        View::render('opportunities/show', [
            'title' => (string) $row['opportunity_number'],
            'activeNav' => 'opportunities',
            'opportunity' => $row,
            'quotes' => (new QuoteRepository())->forOpportunity((int) $row['id']),
            'activities' => (new ActivityRepository())->forCustomer((int) $row['customer_id']),
            'attachments' => (new AttachmentRepository())->forEntity('opportunity', (int) $row['id']),
            'canManage' => can('opportunities.manage'),
            'canQuote' => can('quotes.manage'),
            'canActivity' => can('activities.manage'),
            'reasons' => LostReason::cases(),
        ]);
    }

    public function edit(string $id): void
    {
        $row = $this->row($id);
        $this->form($row, []);
    }

    public function update(string $id): void
    {
        $errors = (new OpportunityService())->update(route_id($id), $_POST);
        if ($errors !== []) {
            $this->form(array_merge($this->row($id), $_POST), $errors);

            return;
        }
        flash('success', 'Opportunity updated.');
        redirect('/opportunities/' . route_id($id));
    }

    public function win(string $id): void
    {
        $errors = (new OpportunityService())->markWon(route_id($id));
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Marked as won.' : (string) reset($errors));
        redirect('/opportunities/' . route_id($id));
    }

    public function lose(string $id): void
    {
        $errors = (new OpportunityService())->markLost(route_id($id), (string) ($_POST['lost_reason'] ?? ''), (string) ($_POST['lost_notes'] ?? ''));
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Marked as lost.' : (string) reset($errors));
        redirect('/opportunities/' . route_id($id));
    }

    public function activity(string $id): void
    {
        $row = $this->row($id);
        $_POST['opportunity_id'] = $row['id'];
        $errors = (new CustomerService())->addActivity((int) $row['customer_id'], (int) auth_user()['id'], $_POST);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Activity saved.' : (string) reset($errors));
        redirect('/opportunities/' . $row['id']);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     */
    private function form(array $old, array $errors): void
    {
        $customerId = (int) ($old['customer_id'] ?? 0);
        View::render('opportunities/form', [
            'title' => isset($old['id']) ? 'Edit opportunity' : 'New opportunity',
            'activeNav' => 'opportunities',
            'old' => $old,
            'errors' => $errors,
            'customers' => (new CustomerRepository())->search('', 'active', 300),
            'contacts' => $customerId > 0 ? (new ContactRepository())->forCustomer($customerId) : [],
            'users' => (new UserRepository())->listAll(),
            'sources' => OpportunitySource::cases(),
            'statuses' => OpportunityStatus::cases(),
            'reasons' => LostReason::cases(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $id): array
    {
        $row = (new OpportunityRepository())->find(route_id($id));
        if ($row === null) {
            abort_not_found('That opportunity was not found.');
        }

        return $row;
    }
}

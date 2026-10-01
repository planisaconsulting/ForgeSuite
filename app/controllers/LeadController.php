<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CommunicationRepository;
use App\Repositories\LeadRepository;
use App\Repositories\MarketingRepository;
use App\Repositories\UserRepository;
use App\Services\BusinessTimeService;
use App\Services\FollowUpService;
use App\Services\LeadService;
use App\Services\SettingsService;
use App\Services\TemplateEngine;

final class LeadController
{
    public function index(): void
    {
        $user = auth_user();
        $status = strtoupper(trim((string) ($_GET['status'] ?? '')));
        $mine = in_array((string) ($user['role_code'] ?? ''), ['ADMIN', 'MANAGEMENT', 'MARKETING'], true) ? 0 : (int) $user['id'];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $rows = (new LeadRepository())->inbox([
            'status' => $status,
            'q' => (string) ($_GET['q'] ?? ''),
            'mine' => $mine,
        ], 40, ($page - 1) * 40);
        View::render('leads/index', [
            'title' => 'Lead inbox',
            'activeNav' => 'leads',
            'rows' => $rows,
            'status' => $status,
            'q' => (string) ($_GET['q'] ?? ''),
            'page' => $page,
            'hours' => (new BusinessTimeService()),
        ]);
    }

    public function create(): void
    {
        View::render('leads/form', [
            'title' => 'New lead',
            'activeNav' => 'leads',
            'sources' => (new LeadRepository())->sources(),
            'users' => (new UserRepository())->listAll(),
            'campaigns' => (new MarketingRepository())->campaigns(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public function store(): void
    {
        $result = (new LeadService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            View::render('leads/form', [
                'title' => 'New lead',
                'activeNav' => 'leads',
                'sources' => (new LeadRepository())->sources(),
                'users' => (new UserRepository())->listAll(),
                'campaigns' => (new MarketingRepository())->campaigns(),
                'errors' => $result['errors'],
                'old' => $_POST,
            ]);

            return;
        }
        redirect('/leads/' . $result['id']);
    }

    public function show(string $id): void
    {
        $lead = $this->lead($id);
        $templates = (new CommunicationRepository())->templates('WHATSAPP');
        $engine = new TemplateEngine();
        $rendered = [];
        foreach ($templates as $template) {
            $rendered[] = [
                'id' => $template['id'],
                'name' => $template['name'],
                'body' => $engine->render((string) $template['body_template'], [
                    'contact_name' => (string) $lead['name'],
                    'customer_name' => (string) ($lead['company_name'] ?? ''),
                    'company_name' => (string) SettingsService::get('company_name', ''),
                    'quote_number' => '',
                    'quote_total' => (string) ($lead['estimated_value'] ?? ''),
                    'quote_expiry' => '',
                    'job_number' => '',
                    'invoice_number' => '',
                    'invoice_balance' => '',
                    'invoice_due_date' => '',
                    'portal_link' => (string) SettingsService::get('review_request_url', ''),
                ]),
            ];
        }
        View::render('leads/show', [
            'title' => (string) $lead['lead_number'],
            'activeNav' => 'leads',
            'lead' => $lead,
            'matches' => (new LeadService())->matches($lead),
            'users' => (new UserRepository())->listAll(),
            'outcomes' => (new LeadRepository())->outcomes(),
            'templates' => $rendered,
            'timeline' => (new CommunicationRepository())->search(['lead_id' => (int) $lead['id']], 30, 0),
        ]);
    }

    public function assign(string $id): void
    {
        $lead = $this->lead($id);
        $errors = (new LeadService())->assign((int) $lead['id'], (int) ($_POST['assigned_to'] ?? 0), (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Lead assigned.' : ($errors['_form'] ?? 'Could not assign.'));
        redirect('/leads/' . $lead['id']);
    }

    public function call(string $id): void
    {
        $lead = $this->lead($id);
        (new LeadService())->logCall((int) $lead['id'], $_POST, (int) auth_user()['id']);
        flash('success', 'Call logged.');
        redirect('/leads/' . $lead['id']);
    }

    public function whatsapp(string $id): void
    {
        $lead = $this->lead($id);
        $result = (new LeadService())->prepareWhatsapp((int) $lead['id'], trim((string) ($_POST['message'] ?? '')), (int) auth_user()['id']);
        if (str_starts_with($result['url'], 'https://wa.me/')) {
            header('Location: ' . $result['url']);
            exit;
        }
        flash('error', $result['errors']['_form'] ?? 'WhatsApp could not be prepared.');
        redirect('/leads/' . $lead['id']);
    }

    public function note(string $id): void
    {
        $lead = $this->lead($id);
        (new LeadService())->addNote((int) $lead['id'], (string) ($_POST['note'] ?? ''), (int) auth_user()['id']);
        flash('success', 'Note added.');
        redirect('/leads/' . $lead['id']);
    }

    public function followUp(string $id): void
    {
        $lead = $this->lead($id);
        $errors = (new LeadService())->setFollowUp((int) $lead['id'], (string) ($_POST['follow_up'] ?? ''), (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Follow-up saved. Earlier activity stays on the timeline.' : 'Choose a date.');
        redirect('/leads/' . $lead['id']);
    }

    public function lost(string $id): void
    {
        $lead = $this->lead($id);
        $errors = (new LeadService())->markLost((int) $lead['id'], (string) ($_POST['lost_reason'] ?? ''), (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Lead marked lost.' : 'A reason is required.');
        redirect('/leads/' . $lead['id']);
    }

    public function spam(string $id): void
    {
        $lead = $this->lead($id);
        (new LeadService())->markSpam((int) $lead['id'], (int) auth_user()['id']);
        flash('success', 'Lead marked as spam.');
        redirect('/leads/' . $lead['id']);
    }

    public function convert(string $id): void
    {
        $lead = $this->lead($id);
        $errors = (new LeadService())->convert((int) $lead['id'], $_POST, (int) auth_user()['id']);
        if ($errors !== []) {
            flash('error', $errors['_form'] ?? 'The lead was not converted.');
            redirect('/leads/' . $lead['id']);
        }
        flash('success', 'Lead converted. The original lead is still here.');
        redirect('/leads/' . $lead['id']);
    }

    public function followUps(): void
    {
        $user = auth_user();
        $mine = !in_array((string) ($user['role_code'] ?? ''), ['ADMIN', 'MANAGEMENT'], true);
        View::render('leads/followups', [
            'title' => 'Sales follow-ups',
            'activeNav' => 'follow-ups',
            'queue' => (new FollowUpService())->queue((int) $user['id'], $mine),
        ]);
    }

    public function desk(): void
    {
        $user = auth_user();
        $mine = (int) $user['id'];
        $repo = new LeadRepository();
        View::render('leads/desk', [
            'title' => 'My sales',
            'activeNav' => 'my-sales',
            'newToday' => $repo->inbox(['status' => 'NEW'], 20, 0),
            'mine' => $repo->inbox(['assigned_only' => $mine], 20, 0),
            'uncontacted' => $repo->inbox(['status' => 'UNCONTACTED', 'mine' => $mine], 20, 0),
            'response' => (new MarketingRepository())->responseMinutes(date('Y-m-01'), date('Y-m-d')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lead(string $id): array
    {
        $lead = (new LeadRepository())->find(route_id($id));
        $user = auth_user();
        if ($lead === null || $user === null || !(new LeadService())->canView($lead, $user)) {
            abort_not_found('That lead was not found.');
        }

        return $lead;
    }
}

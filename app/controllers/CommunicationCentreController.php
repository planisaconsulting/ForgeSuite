<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\CommunicationRepository;
use App\Repositories\QuoteRepository;
use App\Services\CommunicationService;
use App\Services\ConsentService;

final class CommunicationCentreController
{
    public function index(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        View::render('communications/index', [
            'title' => 'Communication centre',
            'activeNav' => 'communications',
            'rows' => (new CommunicationRepository())->search([
                'q' => (string) ($_GET['q'] ?? ''),
                'channel' => strtoupper(trim((string) ($_GET['channel'] ?? ''))),
                'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            ], 40, ($page - 1) * 40),
            'q' => (string) ($_GET['q'] ?? ''),
            'channel' => strtoupper(trim((string) ($_GET['channel'] ?? ''))),
            'page' => $page,
        ]);
    }

    public function templates(): void
    {
        View::render('communications/templates', [
            'title' => 'Templates',
            'activeNav' => 'message-templates',
            'rows' => (new CommunicationRepository())->templates(),
            'canManage' => can('communication_templates.manage'),
        ]);
    }

    public function saveTemplate(): void
    {
        $channel = strtoupper(trim((string) ($_POST['channel'] ?? 'EMAIL')));
        $category = strtoupper(trim((string) ($_POST['category'] ?? 'CUSTOM')));
        (new CommunicationRepository())->saveTemplate([
            'name' => mb_substr(trim((string) ($_POST['name'] ?? 'Template')), 0, 120),
            'channel' => in_array($channel, ['EMAIL', 'WHATSAPP', 'SMS'], true) ? $channel : 'EMAIL',
            'category' => $category === '' ? 'CUSTOM' : mb_substr($category, 0, 40),
            'subject_template' => blank_to_null($_POST['subject_template'] ?? null),
            'body_template' => (string) ($_POST['body_template'] ?? ''),
            'active' => isset($_POST['active']) ? 1 : 0,
            'created_by' => (int) auth_user()['id'],
        ]);
        flash('success', 'Template saved. Only listed placeholders are replaced.');
        redirect('/communications/templates');
    }

    public function quotePreview(string $id): void
    {
        $quote = $this->quote($id);
        $result = (new CommunicationService())->sendQuote(
            (int) $quote['id'],
            trim((string) ($_POST['to'] ?? $quote['contact_email'] ?? $quote['customer_email'] ?? '')),
            (int) ($_POST['template_id'] ?? 0),
            (int) auth_user()['id'],
            false
        );
        View::render('communications/preview', [
            'title' => 'Preview quote email',
            'activeNav' => 'quotes',
            'quote' => $quote,
            'preview' => $result['preview'],
            'templates' => (new CommunicationRepository())->templates('EMAIL'),
            'templateId' => (int) ($_POST['template_id'] ?? 0),
        ]);
    }

    public function quoteSend(string $id): void
    {
        $quote = $this->quote($id);
        $result = (new CommunicationService())->sendQuote(
            (int) $quote['id'],
            trim((string) ($_POST['to'] ?? '')),
            (int) ($_POST['template_id'] ?? 0),
            (int) auth_user()['id'],
            isset($_POST['confirm'])
        );
        flash($result['sent'] ? 'success' : 'error', $result['sent'] ? 'Email handed to the configured delivery method.' : ($result['reason'] !== '' ? $result['reason'] : 'The email was not sent.'));
        redirect('/quotes/' . $quote['id']);
    }

    public function preferences(string $id, string $contactId): void
    {
        $contact = (new \App\Repositories\ContactRepository())->findForCustomer(route_id($id), route_id($contactId));
        if ($contact === null) {
            abort_not_found('That contact was not found.');
        }
        View::render('communications/preferences', [
            'title' => 'Communication preferences',
            'activeNav' => 'customers',
            'customerId' => route_id($id),
            'contact' => $contact,
            'prefs' => (new CommunicationRepository())->preferences((int) $contact['id']),
        ]);
    }

    public function savePreferences(string $id, string $contactId): void
    {
        $errors = (new ConsentService())->save(route_id($id), route_id($contactId), $_POST, (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Preferences saved.' : ($errors['_form'] ?? 'Could not save.'));
        redirect('/customers/' . route_id($id) . '/contacts/' . route_id($contactId) . '/preferences');
    }

    public function unsubscribe(string $id, string $contactId): void
    {
        (new ConsentService())->unsubscribe(route_id($contactId), 'EMAIL', blank_to_null($_POST['reason'] ?? null), (int) auth_user()['id']);
        flash('success', 'Marketing email is suppressed for that contact. Transactional messages stay on their own setting.');
        redirect('/customers/' . route_id($id) . '/contacts/' . route_id($contactId) . '/preferences');
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(string $id): array
    {
        $quote = (new QuoteRepository())->find(route_id($id));
        if ($quote === null) {
            abort_not_found('That quotation was not found.');
        }

        return $quote;
    }
}

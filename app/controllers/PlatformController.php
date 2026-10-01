<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\PlatformRepository;
use App\Repositories\WorkflowRepository;
use App\Services\ApprovalService;
use App\Services\AssistedIntelligenceService;
use App\Services\BusinessRuleService;
use App\Services\ConfigTransferService;
use App\Services\CustomFieldService;
use App\Services\CustomFormService;
use App\Services\DashboardLayoutService;
use App\Services\DataDictionary;
use App\Services\FeatureFlagService;
use App\Services\IntegrationHealthService;
use App\Services\IntegrationIssueService;
use App\Services\PaymentLinkService;
use App\Services\PaymentWebhookService;
use App\Services\QuickActionService;
use App\Services\ReviewQueueService;
use App\Services\SavedViewService;
use App\Services\StatusLabelService;
use App\Services\WorkflowActionRunner;
use App\Services\WorkflowConditionEvaluator;
use App\Services\WorkflowEngine;
use App\Services\WorkflowFactReader;
use App\Services\WorkflowService;

/**
 * Configuration, workflows, approvals, and assistance screens.
 */
final class PlatformController
{
    public function workflows(): void
    {
        $rows = [];
        foreach ((new WorkflowRepository())->all() as $row) {
            $rows[] = [$row['name'], $row['trigger_event'], (int) $row['active'] === 1 ? 'Active' : 'Inactive', '/workflows/' . $row['id']];
        }
        $this->page('Workflows', 'workflows', [
            $this->table(['Name', 'When', 'State', ''], $rows),
        ], [['label' => 'New workflow', 'href' => '/workflows/new']]);
    }

    public function workflowForm(?string $id = null): void
    {
        $workflow = $id === null ? null : (new WorkflowRepository())->find((int) $id);
        View::render('platform/workflow', [
            'title' => $workflow === null ? 'New workflow' : 'Edit workflow',
            'activeNav' => 'workflows',
            'workflow' => $workflow,
            'triggers' => WorkflowEngine::TRIGGERS,
            'entities' => WorkflowFactReader::ENTITIES,
            'operators' => WorkflowConditionEvaluator::OPERATORS,
            'actions' => WorkflowActionRunner::TYPES,
            'conditions' => $workflow === null ? [] : (new WorkflowRepository())->conditions((int) $workflow['id']),
            'storedActions' => $workflow === null ? [] : (new WorkflowRepository())->actions((int) $workflow['id']),
        ]);
    }

    public function saveWorkflow(?string $id = null): void
    {
        $conditions = [];
        $fields = $_POST['field_key'] ?? [];
        if (is_array($fields)) {
            foreach ($fields as $index => $field) {
                $conditions[] = [
                    'field_key' => $field,
                    'operator' => $_POST['operator'][$index] ?? '',
                    'comparison_value' => $_POST['comparison_value'][$index] ?? '',
                    'condition_group' => $_POST['condition_group'][$index] ?? 1,
                ];
            }
        }
        $actions = [];
        $types = $_POST['action_type'] ?? [];
        if (is_array($types)) {
            foreach ($types as $index => $type) {
                if (trim((string) $type) === '') {
                    continue;
                }
                $decoded = json_decode((string) ($_POST['action_config'][$index] ?? '{}'), true);
                $actions[] = ['action_type' => $type, 'configuration' => is_array($decoded) ? $decoded : []];
            }
        }
        $result = (new WorkflowService())->save([
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'entity_type' => $_POST['entity_type'] ?? '',
            'trigger_event' => $_POST['trigger_event'] ?? '',
            'active' => $_POST['active'] ?? '0',
            'priority' => $_POST['priority'] ?? 100,
            'stop_on_match' => $_POST['stop_on_match'] ?? '0',
            'reason' => $_POST['reason'] ?? '',
            'conditions' => $conditions,
            'actions' => $actions,
        ], (int) auth_user()['id'], $id === null ? null : (int) $id);
        if ($result['errors'] !== []) {
            flash('error', (string) reset($result['errors']));
            redirect($id === null ? '/workflows/new' : '/workflows/' . $id);
        }
        flash('success', 'Workflow saved. The previous version is kept.');
        redirect('/workflows/' . $result['id']);
    }

    public function testWorkflow(string $id): void
    {
        if (!can('workflows.test')) {
            deny_access('You cannot test workflows.');
        }
        $report = (new WorkflowEngine())->test((int) $id, (int) ($_POST['entity_id'] ?? 0));
        $rows = [];
        foreach ($report['results'] as $result) {
            $rows[] = [$result['field'], $result['operator'], $result['matched'] ? 'Matched' : 'Failed'];
        }
        $this->page('Workflow test', 'workflows', [
            $this->figure('Result', $report['matched'] ? 'Conditions matched' : 'Conditions did not match', 'Actions were not executed.'),
            $this->table(['Field', 'Comparison', 'Result'], $rows),
            $this->table(['Would run'], $report['actions'] === [] ? [] : array_map(static fn (string $action): array => [$action], $report['actions'])),
        ]);
    }

    public function history(): void
    {
        $rows = [];
        foreach ((new WorkflowRepository())->recentExecutions() as $row) {
            $rows[] = [$row['name'], $row['trigger_event'], $row['status'], (string) $row['entity_id'], (string) ($row['error_message'] ?? '')];
        }
        $this->page('Workflow history', 'workflow-history', [
            $this->table(['Workflow', 'Event', 'Status', 'Record', 'Note'], $rows),
        ]);
    }

    public function approvals(): void
    {
        $rows = [];
        foreach ((new PlatformRepository())->approvalQueue() as $row) {
            $rows[] = [$row['action_key'], $row['entity_type'], (string) $row['entity_id'], (string) ($row['reason'] ?? ''), (string) $row['requested_at'], '/approvals/' . $row['id']];
        }
        $this->page('My approvals', 'approvals', [
            $this->table(['Action', 'Record', 'Id', 'Reason', 'Requested', ''], $rows),
        ]);
    }

    public function showApproval(string $id): void
    {
        $request = (new PlatformRepository())->approval((int) $id);
        if ($request === null) {
            abort_not_found('That approval was not found.');
        }
        $steps = [];
        foreach ((new PlatformRepository())->approvalSteps((int) $id) as $step) {
            $steps[] = [(string) $step['sequence'], $step['approver_type'], $step['status'], (string) ($step['comment'] ?? '')];
        }
        View::render('platform/approval', [
            'title' => 'Approval',
            'activeNav' => 'approvals',
            'request' => $request,
            'steps' => $steps,
        ]);
    }

    public function decide(string $id): void
    {
        $errors = (new ApprovalService())->decide((int) $id, (string) ($_POST['decision'] ?? ''), (int) auth_user()['id'], (string) ($_POST['comment'] ?? ''));
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Decision recorded.' : (string) reset($errors));
        redirect('/approvals/' . $id);
    }

    public function reviews(): void
    {
        $rows = [];
        foreach ((new PlatformRepository())->reviews('PENDING') as $row) {
            $rows[] = [$row['item_type'], (string) ($row['entity_type'] ?? ''), (string) $row['proposed_action'], (string) $row['source'], (string) ($row['reason'] ?? ''), $row['status']];
        }
        $this->page('Review queue', 'reviews', [
            $this->table(['Type', 'Record', 'Proposed action', 'Source', 'Reason', 'Status'], $rows),
        ]);
    }

    public function resolveReview(string $id): void
    {
        $errors = (new ReviewQueueService())->resolve((int) $id, (string) ($_POST['status'] ?? ''), (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Review updated.' : (string) reset($errors));
        redirect('/reviews');
    }

    public function configuration(): void
    {
        $sections = [
            ['Company', '/settings'],
            ['Numbering', '/settings'],
            ['Pricing', '/pricing-levels'],
            ['Sales', '/settings'],
            ['Jobs', '/jobs/setup'],
            ['Production', '/jobs/setup'],
            ['Inventory', '/settings'],
            ['Purchasing', '/settings'],
            ['Finance', '/settings'],
            ['Communications', '/communications/templates'],
            ['Approvals', '/admin/approval-policies'],
            ['Workflows', '/workflows'],
            ['Integrations', '/integrations'],
            ['Documents', '/admin/document-templates'],
            ['AI Assistance', '/admin/ai'],
            ['Mobile/PWA', '/settings'],
        ];
        $rows = [];
        foreach ($sections as [$label, $href]) {
            $rows[] = [$label, $href];
        }
        $rules = [];
        foreach ((new PlatformRepository())->allRules() as $rule) {
            $rules[] = [$rule['rule_key'], $rule['scope_type'], (string) $rule['value_text']];
        }
        $this->page('Configuration', 'configuration', [
            $this->table(['Section', ''], $rows),
            $this->table(['Rule', 'Scope', 'Value'], $rules),
        ], [], 'Company, numbering, and pricing stay on their existing screens. This page is the index. A customer override beats a company value, which beats the system default. A value typed on the transaction wins for that decision only.');
    }

    public function policies(): void
    {
        $rows = [];
        foreach ((new PlatformRepository())->policies() as $policy) {
            $rows[] = [$policy['name'], $policy['entity_type'], $policy['action_key'], (int) $policy['active'] === 1 ? 'Active' : 'Off'];
        }
        $this->page('Approval policies', 'approval-policies', [
            $this->table(['Policy', 'Record', 'Action', 'State'], $rows),
        ]);
    }

    public function fields(): void
    {
        $rows = [];
        foreach (WorkflowFactReader::ENTITIES as $entity) {
            foreach ((new PlatformRepository())->fields($entity, false) as $field) {
                $rows[] = [$entity, $field['label'], $field['field_type'], (int) $field['required'] === 1 ? 'Required' : 'Optional'];
            }
        }
        $this->page('Custom fields', 'custom-fields', [
            $this->table(['Record', 'Label', 'Type', 'Required'], $rows),
        ]);
    }

    public function saveField(): void
    {
        $result = (new CustomFieldService())->define($_POST, (int) auth_user()['id']);
        flash($result['errors'] === [] ? 'success' : 'error', $result['errors'] === [] ? 'Field saved.' : (string) reset($result['errors']));
        redirect('/admin/custom-fields');
    }

    public function forms(): void
    {
        $rows = [];
        foreach ((new PlatformRepository())->forms() as $form) {
            $rows[] = [$form['name'], $form['purpose'], 'Version ' . $form['current_version']];
        }
        $this->page('Custom forms', 'custom-forms', [
            $this->figure('Existing checklists', 'Kept', 'QC, installation, vehicle, and dispatch records already stored are not rewritten.'),
            $this->table(['Form', 'Purpose', 'Version'], $rows),
        ]);
    }

    public function flags(): void
    {
        $rows = [];
        foreach ((new PlatformRepository())->flags() as $flag) {
            $rows[] = [$flag['feature_key'], (int) $flag['enabled'] === 1 ? 'On' : 'Off'];
        }
        $this->page('Feature flags', 'feature-flags', [
            $this->table(['Feature', 'State'], $rows),
        ]);
    }

    public function saveFlag(): void
    {
        $message = (new FeatureFlagService())->set((string) ($_POST['feature_key'] ?? ''), ($_POST['enabled'] ?? '') === '1', (int) auth_user()['id']);
        flash($message === '' ? 'success' : 'error', $message === '' ? 'Flag updated.' : $message);
        redirect('/admin/feature-flags');
    }

    public function ai(): void
    {
        $this->page('AI assistance', 'ai', [
            $this->figure('Provider', (string) (\App\Services\SettingsService::get('ai_provider', '') ?: 'Not configured'), 'The ERP keeps working when assistance is off.'),
        ]);
    }

    public function dictionary(): void
    {
        $rows = [];
        foreach (array_merge(DataDictionary::financial(), DataDictionary::operations()) as $row) {
            $rows[] = [$row['term'], $row['meaning']];
        }
        $this->page('Data dictionary', 'dictionary', [
            $this->table(['Term', 'Meaning'], $rows),
        ]);
    }

    public function command(): void
    {
        $actions = (new QuickActionService())->actions((string) ($_GET['q'] ?? ''));
        $rows = [];
        foreach ($actions as $action) {
            $rows[] = [$action['label'], $action['href']];
        }
        $this->page('Quick actions', 'command', [
            $this->table(['Action', ''], $rows),
        ]);
    }

    public function integrations(): void
    {
        $rows = [];
        foreach ((new IntegrationHealthService())->dashboard() as $card) {
            $rows[] = [$card['name'], $card['status'], $card['detail']];
        }
        $this->page('Integrations', 'integrations-home', [
            $this->table(['Connector', 'Status', 'Detail'], $rows),
        ]);
    }

    public function issues(): void
    {
        $rows = [];
        foreach ((new PlatformRepository())->issues() as $issue) {
            $rows[] = [$issue['provider'], $issue['entity_type'], (string) $issue['entity_id'], $issue['failure'], (string) $issue['attempts'], $issue['status']];
        }
        $this->page('Integration issues', 'integration-issues', [
            $this->table(['Provider', 'Record', 'Id', 'Failure', 'Attempts', 'Status'], $rows),
        ]);
    }

    public function retryIssue(string $id): void
    {
        $errors = (new IntegrationIssueService())->act((int) $id, (string) ($_POST['action'] ?? ''), (int) auth_user()['id']);
        flash($errors === [] ? 'success' : 'error', $errors === [] ? 'Issue updated.' : (string) reset($errors));
        redirect('/integrations/issues');
    }

    public function payments(): void
    {
        $this->page('Payment links', 'payment-links', [
            $this->figure('Confirmation', 'Verified webhook', 'A browser return does not record a payment.'),
        ]);
    }

    public function payReturn(string $token): void
    {
        $request = (new PaymentLinkService())->acknowledge($token);
        View::render('platform/pay_return', [
            'title' => 'Payment',
            'request' => $request,
        ], 'layouts/auth');
    }

    public function webhook(string $provider): void
    {
        $body = file_get_contents('php://input');
        $body = $body === false ? '' : $body;
        $signature = (string) ($_SERVER['HTTP_X_SIGNFORGE_SIGNATURE'] ?? '');
        $result = (new PaymentWebhookService())->handle($provider, $body, $signature);
        json_response($result, $result['ok'] ? 200 : 422);
    }

    public function dashboard(): void
    {
        $user = auth_user();
        $widgets = (new DashboardLayoutService())->forUser((int) ($user['role_id'] ?? 0), (int) $user['id']);
        $rows = [];
        foreach ($widgets as $widget) {
            $rows[] = [str_replace('_', ' ', $widget)];
        }
        $this->page('My dashboard', 'my-dashboard', [
            $this->table(['Widget'], $rows),
        ]);
    }

    public function performance(): void
    {
        $repo = new WorkflowRepository();
        $this->page('Workflow performance', 'workflow-performance', [
            $this->figure('Completed', (string) $repo->countStatus('COMPLETED')),
            $this->figure('Prevented loops', (string) $repo->countStatus('LOOP_PREVENTED')),
            $this->figure('Failed', (string) $repo->countStatus('FAILED')),
        ]);
    }

    public function exportConfig(): void
    {
        if (!can('configuration.manage')) {
            deny_access('You cannot export configuration.');
        }
        json_response((new ConfigTransferService())->export());
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @param list<array{label: string, href: string}> $links
     */
    private function page(string $title, string $nav, array $sections, array $links = [], string $intro = ''): void
    {
        View::render('platform/page', [
            'title' => $title,
            'activeNav' => $nav,
            'sections' => $sections,
            'links' => $links,
            'intro' => $intro,
        ]);
    }

    /**
     * @param list<string> $head
     * @param list<list<string>> $rows
     * @return array<string, mixed>
     */
    private function table(array $head, array $rows): array
    {
        return ['kind' => 'table', 'head' => $head, 'rows' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function figure(string $label, string $value, string $note = ''): array
    {
        return ['kind' => 'figure', 'label' => $label, 'value' => $value, 'note' => $note];
    }
}

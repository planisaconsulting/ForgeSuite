<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Decimal;
use App\Helpers\View;
use App\Repositories\AutomationRepository;
use App\Repositories\SystemRepository;
use App\Services\AutomationService;
use App\Services\BackupService;
use App\Services\ReportMath;
use App\Services\SystemHealthService;
use App\Version;

/**
 * Administration screens. Each action checks the permission for that screen.
 */
final class AdminController
{
    public function audit(): void
    {
        $this->guard('audit.view');
        $filters = [
            'user_id' => trim((string) ($_GET['user'] ?? '')),
            'action' => trim((string) ($_GET['action'] ?? '')),
            'entity' => trim((string) ($_GET['entity'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
            'ip' => trim((string) ($_GET['ip'] ?? '')),
        ];
        View::render('admin/audit', [
            'title' => 'Audit log',
            'activeNav' => 'audit',
            'rows' => (new SystemRepository())->audit($filters),
            'filters' => $filters,
        ]);
    }

    public function health(): void
    {
        $this->guard('system.health');
        View::render('admin/health', [
            'title' => 'System health',
            'activeNav' => 'health',
            'health' => (new SystemHealthService())->snapshot(),
            'version' => Version::NUMBER,
        ]);
    }

    public function logs(): void
    {
        $this->guard('system.logs');
        View::render('admin/logs', [
            'title' => 'Application log',
            'activeNav' => 'logs',
            'rows' => (new SystemHealthService())->recentErrors(),
        ]);
    }

    public function logins(): void
    {
        $this->guard('audit.view');
        View::render('admin/logins', [
            'title' => 'Sign-in history',
            'activeNav' => 'audit',
            'rows' => (new SystemRepository())->logins(),
        ]);
    }

    public function backups(): void
    {
        $this->guard('system.backup');
        View::render('admin/backups', [
            'title' => 'Backups',
            'activeNav' => 'backups',
            'rows' => (new SystemRepository())->backups(),
        ]);
    }

    public function createBackup(): void
    {
        $this->guard('system.backup');
        $result = (new BackupService())->create((int) auth_user()['id']);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/admin/backups');
    }

    public function downloadBackup(string $id): void
    {
        $this->guard('system.backup');
        $row = (new SystemRepository())->backup(route_id($id));
        if ($row === null || (string) $row['status'] !== 'SUCCESS') {
            abort_not_found('That backup is not available.');
        }
        $path = (new BackupService())->pathFor($row);
        if ($path === null || !is_file($path)) {
            abort_not_found('That backup file is missing.');
        }
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    public function automations(): void
    {
        $this->guard('automations.view');
        $repo = new AutomationRepository();
        View::render('admin/automations', [
            'title' => 'Automations',
            'activeNav' => 'automations',
            'rules' => $repo->all(),
            'log' => $repo->recentLog(),
            'reports' => $repo->reports(),
            'canManage' => can('automations.manage'),
        ]);
    }

    public function saveAutomation(): void
    {
        $this->guard('automations.manage');
        $repo = new AutomationRepository();
        if (($_POST['mode'] ?? '') === 'toggle') {
            $repo->setActive(route_id((string) ($_POST['id'] ?? '0')), ($_POST['active'] ?? '') === '1');
            flash('success', 'Automation updated.');
            redirect('/admin/automations');
        }
        if (($_POST['mode'] ?? '') === 'schedule') {
            $type = (string) ($_POST['report_type'] ?? 'executive');
            $allowed = ['executive', 'sales', 'finance', 'debtors', 'production', 'waste', 'inventory'];
            if (!in_array($type, $allowed, true)) {
                flash('error', 'Choose a known report.');
                redirect('/admin/automations');
            }
            $frequency = strtoupper((string) ($_POST['frequency'] ?? 'WEEKLY'));
            if (!in_array($frequency, ['DAILY', 'WEEKLY', 'MONTHLY'], true)) {
                $frequency = 'WEEKLY';
            }
            $service = new AutomationService();
            $repo->insertReport([
                'report_type' => $type,
                'recipient_user_id' => (int) auth_user()['id'],
                'frequency' => $frequency,
                'filters_json' => '{}',
                'next_run_at' => $service->nextRun($frequency),
            ]);
            flash('success', 'The report will appear in the notification centre. Email is not sent.');
            redirect('/admin/automations');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $days = (int) ($_POST['days'] ?? 3);
        if ($name === '') {
            flash('error', 'Name the rule.');
            redirect('/admin/automations');
        }
        $repo->insert([
            'name' => mb_substr($name, 0, 120),
            'trigger_type' => 'QUOTE_SENT',
            'conditions_json' => json_encode(['days' => max(1, min(60, $days))], JSON_THROW_ON_ERROR),
            'action_type' => 'CREATE_REMINDER',
            'action_config_json' => json_encode(['days' => max(1, min(60, $days))], JSON_THROW_ON_ERROR),
            'created_by' => (int) auth_user()['id'],
        ]);
        flash('success', 'Rule saved. It creates one follow-up reminder when a quote is marked sent.');
        redirect('/admin/automations');
    }

    public function roles(): void
    {
        $this->guard('users.manage');
        $system = new SystemRepository();
        $selected = (int) ($_GET['role'] ?? 0);
        View::render('admin/roles', [
            'title' => 'Roles',
            'activeNav' => 'roles',
            'roles' => $system->roles(),
            'permissions' => $selected > 0 ? $system->rolePermissions($selected) : [],
            'selected' => $selected,
        ]);
    }

    public function targets(): void
    {
        $this->guard('settings.manage');
        View::render('admin/targets', [
            'title' => 'KPI targets',
            'activeNav' => 'targets',
            'rows' => (new SystemRepository())->targets(),
        ]);
    }

    public function saveTargets(): void
    {
        $this->guard('settings.manage');
        $system = new SystemRepository();
        foreach ((array) ($_POST['target'] ?? []) as $id => $value) {
            $clean = str_replace(',', '.', trim((string) $value));
            if (!is_numeric($clean)) {
                continue;
            }
            $active = !empty($_POST['active'][$id]);
            $system->updateTarget((int) $id, Decimal::round($clean, 4), $active);
        }
        flash('success', 'Targets saved.');
        redirect('/admin/targets');
    }

    public function exportForm(): void
    {
        $this->guard('reports.export');
        View::render('admin/export', [
            'title' => 'Data export',
            'activeNav' => 'export',
            'datasets' => $this->datasets(),
        ]);
    }

    public function exportCsv(): void
    {
        $this->guard('reports.export');
        $key = (string) ($_GET['dataset'] ?? '');
        $datasets = $this->datasets();
        if (!isset($datasets[$key]) || !can($datasets[$key]['permission'])) {
            deny_access('You cannot export that dataset.');
        }
        $rows = $datasets[$key]['rows']();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="signforge-' . $key . '.csv"');
        $out = fopen('php://output', 'wb');
        if ($out === false || $rows === []) {
            fputcsv($out ?: fopen('php://output', 'wb'), ['empty']);
            exit;
        }
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            $line = [];
            foreach ($row as $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $line[] = ReportMath::csvCell((string) $value);
            }
            fputcsv($out, $line);
        }
        exit;
    }

    public function documents(): void
    {
        if (!can('jobs.view') && !can('quotes.view') && !can('customers.view')) {
            deny_access('You cannot search documents.');
        }
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'entity_type' => trim((string) ($_GET['type'] ?? '')),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ];
        View::render('documents/index', [
            'title' => 'Documents',
            'activeNav' => 'documents',
            'rows' => (new SystemRepository())->documents($filters),
            'filters' => $filters,
        ]);
    }

    private function guard(string $permission): void
    {
        if (!can($permission)) {
            deny_access('Your role cannot open that page.');
        }
    }

    /**
     * @return array<string, array{label: string, permission: string, rows: callable(): list<array<string, mixed>>}>
     */
    private function datasets(): array
    {
        $pdo = static function (string $sql): array {
            $statement = \App\Helpers\Database::connection()->query($sql);

            return $statement === false ? [] : $statement->fetchAll();
        };

        return [
            'customers' => ['label' => 'Customers', 'permission' => 'customers.view', 'rows' => static fn (): array => $pdo('SELECT id, customer_type, company_name, first_name, last_name, email, phone, active FROM customers ORDER BY id')],
            'products' => ['label' => 'Products', 'permission' => 'products.view', 'rows' => static fn (): array => $pdo('SELECT id, sku, name, product_type, cost_price, active FROM products ORDER BY id')],
            'suppliers' => ['label' => 'Suppliers', 'permission' => 'suppliers.view', 'rows' => static fn (): array => $pdo('SELECT id, name, email, phone, active FROM suppliers ORDER BY id')],
            'quotes' => ['label' => 'Quotes', 'permission' => 'quotes.view', 'rows' => static fn (): array => $pdo('SELECT id, quote_number, customer_id, status, quote_date, total FROM quotes ORDER BY id')],
            'jobs' => ['label' => 'Jobs', 'permission' => 'jobs.view', 'rows' => static fn (): array => $pdo('SELECT id, job_number, customer_id, status, quoted_cost_snapshot, actual_total_cost FROM jobs ORDER BY id')],
            'invoices' => ['label' => 'Invoices', 'permission' => 'invoices.view', 'rows' => static fn (): array => $pdo('SELECT id, invoice_number, customer_id, status, invoice_date, total, balance_due FROM invoices ORDER BY id')],
            'payments' => ['label' => 'Payments', 'permission' => 'payments.view', 'rows' => static fn (): array => $pdo('SELECT id, payment_reference, customer_id, status, payment_date, amount FROM payments ORDER BY id')],
            'movements' => ['label' => 'Stock movements', 'permission' => 'inventory.view', 'rows' => static fn (): array => $pdo('SELECT id, product_id, movement_type, quantity, movement_date, total_cost FROM stock_movements ORDER BY id DESC LIMIT 5000')],
        ];
    }
}

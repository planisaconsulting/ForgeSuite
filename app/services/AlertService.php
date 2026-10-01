<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ReportingRepository;
use App\Repositories\SystemRepository;

/**
 * Evaluates operational alerts from live records.
 * Each alert uses a stable dedupe key, so running this every hour
 * does not create another copy of the same overdue invoice notice.
 */
final class AlertService
{
    public function __construct(
        private readonly ReportingRepository $reports = new ReportingRepository(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly SystemRepository $system = new SystemRepository()
    ) {
    }

    public function evaluate(): int
    {
        $created = 0;
        $created += $this->quotes();
        $created += $this->jobs();
        $created += $this->stock();
        $created += $this->purchasing();
        $created += $this->finance();

        return $created;
    }

    private function quotes(): int
    {
        $days = (int) (SettingsService::get('quote_expiry_warning_days', '3') ?? '3');
        $until = (new \DateTimeImmutable('today'))->modify('+' . max(1, $days) . ' days')->format('Y-m-d');
        $n = 0;
        foreach ($this->reports->expiringQuotes($until) as $quote) {
            $n += $this->notify(
                isset($quote['assigned_to']) ? (int) $quote['assigned_to'] : null,
                null,
                'QUOTE_EXPIRING',
                'Quote ' . $quote['quote_number'] . ' expires ' . $quote['expiry_date'],
                'Open quotation still awaiting a decision.',
                'quote',
                (int) $quote['id'],
                'HIGH',
                'QUOTE_EXPIRING:quote:' . $quote['id']
            ) ? 1 : 0;
        }
        foreach ($this->reports->quotesToFollow(date('Y-m-d')) as $quote) {
            $n += $this->notify(
                isset($quote['assigned_to']) ? (int) $quote['assigned_to'] : null,
                null,
                'QUOTE_FOLLOW_UP',
                'Follow up ' . $quote['quote_number'],
                'Follow-up date is due.',
                'quote',
                (int) $quote['id'],
                'NORMAL',
                'QUOTE_FOLLOW_UP:quote:' . $quote['id'] . ':' . $quote['next_follow_up_date']
            ) ? 1 : 0;
        }

        return $n;
    }

    private function jobs(): int
    {
        $n = 0;
        foreach ($this->reports->overdueJobs() as $job) {
            $n += $this->notify(
                isset($job['assigned_to']) ? (int) $job['assigned_to'] : null,
                $this->system->roleId('PRODUCTION'),
                'JOB_OVERDUE',
                'Job ' . $job['job_number'] . ' is past its target',
                'Target date ' . $job['target_date'] . '.',
                'job',
                (int) $job['id'],
                'HIGH',
                'JOB_OVERDUE:job:' . $job['id']
            ) ? 1 : 0;
        }
        foreach ($this->reports->overdueTasks() as $task) {
            $n += $this->notify(
                isset($task['assigned_to']) ? (int) $task['assigned_to'] : null,
                $this->system->roleId('PRODUCTION'),
                'TASK_OVERDUE',
                'Task overdue: ' . $task['title'],
                'Due ' . $task['due_date'] . '.',
                'job',
                (int) $task['job_id'],
                'NORMAL',
                'TASK_OVERDUE:task:' . $task['id']
            ) ? 1 : 0;
        }
        $today = date('Y-m-d');
        foreach ($this->reports->installationsOn($today) as $row) {
            $n += $this->notify(
                isset($row['assigned_user_id']) ? (int) $row['assigned_user_id'] : null,
                $this->system->roleId('INSTALLER'),
                'INSTALLATION_TODAY',
                'Installation today on ' . $row['job_number'],
                'Scheduled for today.',
                'job',
                (int) $row['job_id'],
                'HIGH',
                'INSTALLATION_TODAY:installation:' . $row['id'] . ':' . $today
            ) ? 1 : 0;
        }
        foreach ($this->reports->returnInstallations() as $row) {
            $n += $this->notify(
                isset($row['assigned_user_id']) ? (int) $row['assigned_user_id'] : null,
                $this->system->roleId('INSTALLER'),
                'INSTALLATION_TODAY',
                'Return visit on ' . $row['job_number'],
                'Installation is marked return required.',
                'job',
                (int) $row['job_id'],
                'HIGH',
                'INSTALL_RETURN:installation:' . $row['id']
            ) ? 1 : 0;
        }

        return $n;
    }

    private function stock(): int
    {
        $n = 0;
        $role = $this->system->roleId('PRODUCTION');
        foreach ($this->reports->stockByProduct() as $product) {
            $level = $product['reorder_level'] ?? $product['minimum_stock_level'];
            if ($level === null || $level === '') {
                continue;
            }
            $onHand = Decimal::round((string) $product['on_hand'], 4);
            if (Decimal::cmp($onHand, (string) $level) > 0) {
                continue;
            }
            $type = Decimal::cmp($onHand, '0') <= 0 ? 'MATERIAL_SHORTAGE' : 'LOW_STOCK';
            $n += $this->notify(
                null,
                $role,
                $type,
                $product['name'] . ' is ' . ($type === 'MATERIAL_SHORTAGE' ? 'out of stock' : 'at or below reorder'),
                'On hand ' . $onHand . '. Reorder level ' . $level . '.',
                'product',
                (int) $product['id'],
                'HIGH',
                $type . ':product:' . $product['id']
            ) ? 1 : 0;
        }

        return $n;
    }

    private function purchasing(): int
    {
        $n = 0;
        $today = date('Y-m-d');
        foreach ($this->reports->openPurchaseOrders() as $order) {
            if ($order['expected_date'] === null || (string) $order['expected_date'] >= $today) {
                continue;
            }
            $n += $this->notify(
                null,
                $this->system->roleId('ACCOUNTS'),
                'PO_OVERDUE',
                $order['po_number'] . ' is past its expected date',
                'Expected ' . $order['expected_date'] . ' from ' . $order['supplier_name'] . '.',
                'purchase_order',
                (int) $order['id'],
                'NORMAL',
                'PO_OVERDUE:po:' . $order['id']
            ) ? 1 : 0;
        }

        return $n;
    }

    private function finance(): int
    {
        $n = 0;
        $accounts = $this->system->roleId('ACCOUNTS');
        $soon = (int) (SettingsService::get('invoice_due_soon_days', '3') ?? '3');
        $until = (new \DateTimeImmutable('today'))->modify('+' . max(1, $soon) . ' days')->format('Y-m-d');
        foreach ($this->reports->dueInvoices(date('Y-m-d'), $until, false) as $invoice) {
            $n += $this->notify(
                null,
                $accounts,
                'INVOICE_DUE',
                $invoice['invoice_number'] . ' is due ' . $invoice['due_date'],
                'Balance ' . $invoice['balance_due'] . '.',
                'invoice',
                (int) $invoice['id'],
                'NORMAL',
                'INVOICE_DUE:invoice:' . $invoice['id']
            ) ? 1 : 0;
        }
        foreach ($this->reports->dueInvoices(date('Y-m-d'), date('Y-m-d'), true) as $invoice) {
            $n += $this->notify(
                null,
                $accounts,
                'INVOICE_OVERDUE',
                $invoice['invoice_number'] . ' is overdue',
                'Due ' . $invoice['due_date'] . '. Balance ' . $invoice['balance_due'] . '.',
                'invoice',
                (int) $invoice['id'],
                'HIGH',
                'INVOICE_OVERDUE:invoice:' . $invoice['id']
            ) ? 1 : 0;
        }
        $large = SettingsService::get('large_balance_amount', '50000') ?? '50000';
        foreach ($this->reports->largeBalances($large) as $row) {
            $n += $this->notify(
                null,
                $accounts,
                'LARGE_BALANCE',
                'Large outstanding balance',
                'Customer balance ' . $row['balance'] . ' is at or above the configured amount.',
                'customer',
                (int) $row['customer_id'],
                'HIGH',
                'LARGE_BALANCE:customer:' . $row['customer_id']
            ) ? 1 : 0;
        }
        foreach ($this->reports->heldCustomers() as $customer) {
            $n += $this->notify(
                null,
                $accounts,
                'CUSTOMER_ACCOUNT_HOLD',
                customer_label($customer) . ' is on hold',
                'The account hold is still active.',
                'customer',
                (int) $customer['id'],
                'HIGH',
                'ACCOUNT_HOLD:customer:' . $customer['id']
            ) ? 1 : 0;
        }

        return $n;
    }

    private function notify(
        ?int $userId,
        ?int $roleId,
        string $type,
        string $title,
        string $message,
        string $entityType,
        int $entityId,
        string $priority,
        string $dedupe
    ): bool {
        if ($userId !== null && $userId < 1) {
            $userId = null;
        }

        return $this->notifications->send(
            $userId,
            $userId === null ? $roleId : null,
            $type,
            $title,
            $message,
            $entityType,
            $entityId,
            $priority,
            $dedupe
        );
    }
}

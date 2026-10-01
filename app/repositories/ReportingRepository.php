<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Decimal;

/**
 * Aggregated reads for management reports.
 * The rows come from quotations, jobs, invoices, stock, and purchases.
 */
final class ReportingRepository extends Repository
{
    /**
     * @return array<string, string>
     */
    public function quoteDecision(string $from, string $to): array
    {
        $row = $this->one(
            "SELECT
                SUM(quote_date BETWEEN ? AND ?) AS created_count,
                COALESCE(SUM(CASE WHEN quote_date BETWEEN ? AND ? THEN total ELSE 0 END), 0) AS created_value,
                SUM(status = 'ACCEPTED' AND DATE(accepted_at) BETWEEN ? AND ?) AS accepted_count,
                COALESCE(SUM(CASE WHEN status = 'ACCEPTED' AND DATE(accepted_at) BETWEEN ? AND ? THEN total ELSE 0 END), 0) AS accepted_value,
                SUM(status = 'DECLINED' AND DATE(status_changed_at) BETWEEN ? AND ?) AS declined_count,
                COALESCE(SUM(CASE WHEN status = 'DECLINED' AND DATE(status_changed_at) BETWEEN ? AND ? THEN total ELSE 0 END), 0) AS declined_value
             FROM quotes WHERE archived = 0",
            [$from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to]
        ) ?? [];

        return [
            'created_count' => (string) ($row['created_count'] ?? 0),
            'created_value' => Decimal::money((string) ($row['created_value'] ?? '0')),
            'accepted_count' => (string) ($row['accepted_count'] ?? 0),
            'accepted_value' => Decimal::money((string) ($row['accepted_value'] ?? '0')),
            'declined_count' => (string) ($row['declined_count'] ?? 0),
            'declined_value' => Decimal::money((string) ($row['declined_value'] ?? '0')),
        ];
    }

    public function newCustomers(string $from, string $to): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM customers WHERE DATE(created_at) BETWEEN ? AND ?', [$from, $to]);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pipeline(): array
    {
        return $this->rows(
            "SELECT status, COUNT(*) AS n, COALESCE(SUM(estimated_value), 0) AS value
             FROM sales_opportunities
             WHERE status IN ('NEW','CONTACTED','QUALIFIED','QUOTED','WON','LOST')
             GROUP BY status"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openOpportunities(): array
    {
        return $this->rows(
            "SELECT o.*, u.name AS salesperson, c.company_name, c.first_name, c.last_name, c.customer_type
             FROM sales_opportunities o
             INNER JOIN customers c ON c.id = o.customer_id
             LEFT JOIN users u ON u.id = o.assigned_to
             WHERE o.status IN ('NEW','CONTACTED','QUALIFIED','QUOTED')
             ORDER BY o.expected_close_date, o.id
             LIMIT 200"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function salespeople(string $from, string $to): array
    {
        return $this->rows(
            "SELECT u.id, u.name,
                SUM(q.quote_date BETWEEN ? AND ?) AS quotes_created,
                COALESCE(SUM(CASE WHEN q.quote_date BETWEEN ? AND ? THEN q.total ELSE 0 END), 0) AS quote_value,
                SUM(q.status = 'ACCEPTED' AND DATE(q.accepted_at) BETWEEN ? AND ?) AS accepted_count,
                COALESCE(SUM(CASE WHEN q.status = 'ACCEPTED' AND DATE(q.accepted_at) BETWEEN ? AND ? THEN q.total ELSE 0 END), 0) AS accepted_value,
                SUM(q.status = 'DECLINED' AND DATE(q.status_changed_at) BETWEEN ? AND ?) AS declined_count
             FROM users u
             LEFT JOIN quotes q ON q.assigned_to = u.id AND q.archived = 0
             GROUP BY u.id, u.name
             HAVING quotes_created > 0 OR accepted_count > 0
             ORDER BY accepted_value DESC",
            [$from, $to, $from, $to, $from, $to, $from, $to, $from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lostReasons(string $from, string $to): array
    {
        return $this->rows(
            "SELECT COALESCE(lost_reason, 'OTHER') AS reason, COUNT(*) AS n, COALESCE(SUM(estimated_value), 0) AS value
             FROM sales_opportunities
             WHERE status = 'LOST' AND DATE(updated_at) BETWEEN ? AND ?
             GROUP BY reason",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function jobsForProfit(string $from, string $to): array
    {
        return $this->rows(
            "SELECT j.id, j.job_number, j.customer_id, j.status, j.completed_at, j.created_at,
                    j.quoted_cost_snapshot, j.actual_material_cost, j.actual_labour_cost, j.actual_other_cost, j.actual_total_cost,
                    q.total AS quote_total, q.assigned_to, u.name AS salesperson_name,
                    c.company_name, c.first_name, c.last_name, c.customer_type,
                    COALESCE((SELECT SUM(v.total) FROM job_variations v
                              WHERE v.job_id = j.id AND v.status IN ('APPROVED','INVOICED')), 0) AS variation_total
             FROM jobs j
             INNER JOIN quotes q ON q.id = j.quote_id
             INNER JOIN customers c ON c.id = j.customer_id
             LEFT JOIN users u ON u.id = q.assigned_to
             WHERE j.archived = 0 AND DATE(j.created_at) BETWEEN ? AND ?
             ORDER BY j.id DESC
             LIMIT 500",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function materialUsage(string $from, string $to): array
    {
        return $this->rows(
            "SELECT u.product_id, p.name AS product_name, u.usage_type, u.reason,
                    SUM(u.quantity) AS quantity, SUM(u.total_cost) AS cost, COUNT(DISTINCT u.job_id) AS jobs
             FROM job_material_usage u
             LEFT JOIN products p ON p.id = u.product_id
             WHERE DATE(u.recorded_at) BETWEEN ? AND ?
             GROUP BY u.product_id, p.name, u.usage_type, u.reason
             ORDER BY cost DESC",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quotedMaterial(string $from, string $to): array
    {
        return $this->rows(
            "SELECT i.product_id, p.name AS product_name,
                    SUM(i.billable_quantity) AS billable, SUM(i.actual_quantity) AS actual_qty
             FROM quote_items i
             INNER JOIN quotes q ON q.id = i.quote_id
             LEFT JOIN products p ON p.id = i.product_id
             WHERE q.status = 'ACCEPTED' AND q.archived = 0 AND DATE(q.accepted_at) BETWEEN ? AND ?
               AND (i.is_optional = 0 OR i.include_optional = 1)
             GROUP BY i.product_id, p.name",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function labour(string $from, string $to): array
    {
        return $this->rows(
            "SELECT t.user_id, u.name, t.work_type, SUM(t.minutes) AS minutes, SUM(t.total_cost) AS cost
             FROM job_time_entries t
             INNER JOIN users u ON u.id = t.user_id
             WHERE DATE(COALESCE(t.ended_at, t.created_at)) BETWEEN ? AND ?
             GROUP BY t.user_id, u.name, t.work_type
             ORDER BY minutes DESC",
            [$from, $to]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function production(string $from, string $to): array
    {
        $row = $this->one(
            "SELECT
                SUM(status = 'COMPLETED' AND DATE(completed_at) BETWEEN ? AND ?) AS completed,
                SUM(target_date < CURDATE() AND status NOT IN ('COMPLETED','CANCELLED','ARCHIVED')) AS overdue,
                SUM(status = 'COMPLETED' AND completed_at IS NOT NULL AND target_date IS NOT NULL AND DATE(completed_at) > target_date AND DATE(completed_at) BETWEEN ? AND ?) AS late
             FROM jobs WHERE archived = 0",
            [$from, $to, $from, $to]
        ) ?? [];
        $turn = $this->one(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) AS hours
             FROM jobs
             WHERE status = 'COMPLETED' AND completed_at IS NOT NULL AND DATE(completed_at) BETWEEN ? AND ?",
            [$from, $to]
        );
        $tasks = $this->one(
            "SELECT COUNT(*) AS n, AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) AS hours
             FROM job_tasks
             WHERE status = 'COMPLETE' AND completed_at IS NOT NULL AND DATE(completed_at) BETWEEN ? AND ?",
            [$from, $to]
        );
        $rework = $this->one(
            "SELECT COUNT(DISTINCT rework_task_id) AS n FROM job_quality_checks
             WHERE rework_task_id IS NOT NULL AND DATE(created_at) BETWEEN ? AND ?",
            [$from, $to]
        );
        $qc = $this->one(
            "SELECT SUM(status = 'FAIL') AS n FROM job_quality_checks WHERE DATE(created_at) BETWEEN ? AND ?",
            [$from, $to]
        );

        return [
            'completed' => (int) ($row['completed'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'late' => (int) ($row['late'] ?? 0),
            'turnaround_hours' => $turn['hours'] !== null ? Decimal::round((string) $turn['hours'], 1) : null,
            'tasks_completed' => (int) ($tasks['n'] ?? 0),
            'task_hours' => $tasks['hours'] !== null ? Decimal::round((string) $tasks['hours'], 1) : null,
            'rework' => (int) ($rework['n'] ?? 0),
            'qc_fail' => (int) ($qc['n'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function bottlenecks(): array
    {
        return $this->rows(
            "SELECT ps.name,
                    AVG(CASE WHEN jps.completed_at IS NOT NULL AND jps.started_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, jps.started_at, jps.completed_at) END) AS avg_hours,
                    SUM(jps.status <> 'COMPLETE') AS waiting,
                    MIN(CASE WHEN jps.status <> 'COMPLETE' THEN jps.created_at END) AS oldest
             FROM job_production_stages jps
             INNER JOIN production_stages ps ON ps.id = jps.production_stage_id
             GROUP BY ps.id, ps.name
             ORDER BY avg_hours DESC"
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function installations(string $from, string $to): array
    {
        $row = $this->one(
            "SELECT
                SUM(scheduled_date BETWEEN ? AND ?) AS scheduled,
                SUM(status = 'COMPLETE' AND DATE(completed_at) BETWEEN ? AND ?) AS completed,
                SUM(status = 'RETURN_REQUIRED') AS return_required,
                AVG(CASE WHEN started_at IS NOT NULL AND completed_at IS NOT NULL
                    THEN TIMESTAMPDIFF(MINUTE, started_at, completed_at) END) AS minutes
             FROM job_installations",
            [$from, $to, $from, $to]
        ) ?? [];

        return [
            'scheduled' => (int) ($row['scheduled'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'return_required' => (int) ($row['return_required'] ?? 0),
            'minutes' => $row['minutes'] !== null ? Decimal::round((string) $row['minutes'], 0) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stockByProduct(): array
    {
        return $this->rows(
            "SELECT p.id, p.name, p.cost_price, p.minimum_stock_level, p.reorder_level, c.name AS category,
                    COALESCE(SUM(CASE WHEN i.inventory_type = 'OFFCUT' THEN 0 ELSE m.quantity END), 0) AS on_hand
             FROM products p
             LEFT JOIN product_categories c ON c.id = p.category_id
             LEFT JOIN stock_movements m ON m.product_id = p.id
             LEFT JOIN inventory_items i ON i.id = m.inventory_item_id
             WHERE p.inventory_method <> 'NONE'
             GROUP BY p.id, p.name, p.cost_price, p.minimum_stock_level, p.reorder_level, c.name
             ORDER BY p.name
             LIMIT 400"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function offcuts(): array
    {
        return $this->rows(
            "SELECT status, COUNT(*) AS n, COALESCE(SUM(remaining_quantity * unit_cost), 0) AS value
             FROM inventory_items WHERE inventory_type = 'OFFCUT' GROUP BY status"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function purchases(string $from, string $to): array
    {
        return $this->rows(
            "SELECT s.id, s.name, COUNT(po.id) AS orders, COALESCE(SUM(po.total), 0) AS value,
                    SUM(po.expected_date < CURDATE() AND po.status NOT IN ('RECEIVED','CANCELLED','CLOSED')) AS late
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.order_date BETWEEN ? AND ?
             GROUP BY s.id, s.name
             ORDER BY value DESC",
            [$from, $to]
        );
    }

    /**
     * @return array<string, string>
     */
    public function finance(string $from, string $to): array
    {
        $invoices = $this->one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS value
             FROM invoices
             WHERE status NOT IN ('DRAFT','CANCELLED') AND invoice_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $payments = $this->one(
            "SELECT COALESCE(SUM(amount), 0) AS value FROM payments
             WHERE status = 'RECORDED' AND payment_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $credits = $this->one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS value FROM credit_notes
             WHERE status IN ('ISSUED','APPLIED') AND credit_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $outstanding = $this->one(
            "SELECT COALESCE(SUM(balance_due), 0) AS value FROM invoices
             WHERE status NOT IN ('DRAFT','CANCELLED')"
        );
        $overdue = $this->one(
            "SELECT COALESCE(SUM(balance_due), 0) AS value FROM invoices
             WHERE status IN ('ISSUED','PARTIALLY_PAID') AND balance_due > 0 AND due_date < CURDATE()"
        );
        $days = $this->one(
            "SELECT AVG(DATEDIFF(DATE(paid_at), invoice_date)) AS days
             FROM invoices WHERE status = 'PAID' AND paid_at IS NOT NULL AND invoice_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $due = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS invoiced FROM invoices
             WHERE status NOT IN ('DRAFT','CANCELLED') AND due_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $collectedOnDue = $this->one(
            "SELECT COALESCE(SUM(a.amount), 0) AS paid
             FROM payment_allocations a
             INNER JOIN payments p ON p.id = a.payment_id
             INNER JOIN invoices i ON i.id = a.invoice_id
             WHERE a.reversed_at IS NULL AND p.status = 'RECORDED'
               AND p.payment_date BETWEEN ? AND ?
               AND i.due_date BETWEEN ? AND ?",
            [$from, $to, $from, $to]
        );

        return [
            'invoices' => (string) ($invoices['n'] ?? 0),
            'invoice_value' => Decimal::money((string) ($invoices['value'] ?? '0')),
            'payments' => Decimal::money((string) ($payments['value'] ?? '0')),
            'credits' => (string) ($credits['n'] ?? 0),
            'credit_value' => Decimal::money((string) ($credits['value'] ?? '0')),
            'outstanding' => Decimal::money((string) ($outstanding['value'] ?? '0')),
            'overdue' => Decimal::money((string) ($overdue['value'] ?? '0')),
            'average_days' => $days['days'] !== null ? Decimal::round((string) $days['days'], 1) : null,
            'due_value' => Decimal::money((string) ($due['invoiced'] ?? '0')),
            'collected_on_due' => Decimal::money((string) ($collectedOnDue['paid'] ?? '0')),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paidInvoices(string $from, string $to): array
    {
        return $this->rows(
            "SELECT invoice_number, invoice_date, paid_at, total, DATEDIFF(DATE(paid_at), invoice_date) AS days
             FROM invoices
             WHERE status = 'PAID' AND paid_at IS NOT NULL AND invoice_date BETWEEN ? AND ?
             ORDER BY paid_at DESC LIMIT 200",
            [$from, $to]
        );
    }

    public function target(string $code): ?string
    {
        $row = $this->one('SELECT target_value FROM kpi_targets WHERE kpi_code = ? AND active = 1', [$code]);

        return $row === null ? null : Decimal::round((string) $row['target_value'], 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function followUps(string $date): array
    {
        return $this->rows(
            "SELECT a.id, a.subject, a.follow_up_date, c.id AS customer_id, c.company_name, c.first_name, c.last_name, c.customer_type
             FROM crm_activities a
             INNER JOIN customers c ON c.id = a.customer_id
             WHERE a.completed = 0 AND a.follow_up_date IS NOT NULL AND a.follow_up_date <= ?
             ORDER BY a.follow_up_date
             LIMIT 30",
            [$date]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quotesToFollow(string $date): array
    {
        return $this->rows(
            "SELECT q.id, q.quote_number, q.total, q.status_changed_at, q.next_follow_up_date, q.assigned_to,
                    c.company_name, c.first_name, c.last_name, c.customer_type, u.name AS salesperson
             FROM quotes q
             INNER JOIN customers c ON c.id = q.customer_id
             LEFT JOIN users u ON u.id = q.assigned_to
             WHERE q.status = 'SENT' AND q.archived = 0
               AND q.next_follow_up_date IS NOT NULL AND q.next_follow_up_date <= ?
             ORDER BY q.next_follow_up_date, q.id
             LIMIT 30",
            [$date]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function opportunityCounts(string $from, string $to): array
    {
        return $this->rows(
            "SELECT assigned_to AS id, COUNT(*) AS n
             FROM sales_opportunities
             WHERE DATE(created_at) BETWEEN ? AND ?
             GROUP BY assigned_to",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerQuotes(string $from, string $to): array
    {
        return $this->rows(
            "SELECT q.customer_id, c.company_name, c.first_name, c.last_name, c.customer_type,
                    COALESCE(SUM(CASE WHEN q.quote_date BETWEEN ? AND ? THEN q.total ELSE 0 END), 0) AS quote_value,
                    COALESCE(SUM(CASE WHEN q.status = 'ACCEPTED' AND DATE(q.accepted_at) BETWEEN ? AND ? THEN q.total ELSE 0 END), 0) AS accepted_value
             FROM quotes q
             INNER JOIN customers c ON c.id = q.customer_id
             WHERE q.archived = 0
             GROUP BY q.customer_id, c.company_name, c.first_name, c.last_name, c.customer_type
             HAVING quote_value > 0 OR accepted_value > 0",
            [$from, $to, $from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customerInvoices(): array
    {
        return $this->rows(
            "SELECT i.customer_id,
                    COALESCE(SUM(CASE WHEN i.status NOT IN ('DRAFT','CANCELLED') THEN i.total ELSE 0 END), 0) AS invoiced,
                    COALESCE(SUM(CASE WHEN i.status NOT IN ('DRAFT','CANCELLED') THEN i.amount_paid ELSE 0 END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN i.status NOT IN ('DRAFT','CANCELLED') THEN i.balance_due ELSE 0 END), 0) AS outstanding,
                    AVG(CASE WHEN i.status = 'PAID' AND i.paid_at IS NOT NULL THEN DATEDIFF(DATE(i.paid_at), i.invoice_date) END) AS pay_days
             FROM invoices i
             GROUP BY i.customer_id"
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function customerHistory(int $customerId): ?array
    {
        return $this->one(
            "SELECT
                (SELECT MIN(created_at) FROM quotes WHERE customer_id = ? AND archived = 0) AS first_quote,
                (SELECT MAX(updated_at) FROM quotes WHERE customer_id = ? AND archived = 0) AS last_quote,
                (SELECT COUNT(*) FROM quotes WHERE customer_id = ? AND archived = 0) AS quotes,
                (SELECT COUNT(*) FROM jobs WHERE customer_id = ? AND archived = 0) AS jobs,
                (SELECT MIN(invoice_date) FROM invoices WHERE customer_id = ? AND status NOT IN ('DRAFT','CANCELLED')) AS first_invoice,
                (SELECT MAX(invoice_date) FROM invoices WHERE customer_id = ? AND status NOT IN ('DRAFT','CANCELLED')) AS last_invoice
            ",
            [$customerId, $customerId, $customerId, $customerId, $customerId, $customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function labourByJob(string $from, string $to): array
    {
        return $this->rows(
            "SELECT j.id, j.job_number, j.customer_id, c.company_name, c.first_name, c.last_name, c.customer_type,
                    COALESCE(SUM(t.minutes), 0) AS minutes,
                    COALESCE(SUM(t.total_cost), 0) AS cost,
                    COALESCE((SELECT SUM(k.estimated_minutes) FROM job_tasks k WHERE k.job_id = j.id), 0) AS estimated
             FROM job_time_entries t
             INNER JOIN jobs j ON j.id = t.job_id
             INNER JOIN customers c ON c.id = j.customer_id
             WHERE DATE(COALESCE(t.ended_at, t.created_at)) BETWEEN ? AND ?
             GROUP BY j.id, j.job_number, j.customer_id, c.company_name, c.first_name, c.last_name, c.customer_type
             ORDER BY minutes DESC
             LIMIT 200",
            [$from, $to]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rework(string $from, string $to): array
    {
        $jobs = $this->one(
            "SELECT COUNT(DISTINCT job_id) AS n FROM job_quality_checks
             WHERE (rework_task_id IS NOT NULL OR status = 'REWORK_REQUIRED')
               AND DATE(created_at) BETWEEN ? AND ?",
            [$from, $to]
        );
        $material = $this->one(
            "SELECT COALESCE(SUM(total_cost), 0) AS cost, COALESCE(SUM(quantity), 0) AS qty
             FROM job_material_usage
             WHERE usage_type = 'REWORK' AND DATE(recorded_at) BETWEEN ? AND ?",
            [$from, $to]
        );
        $labour = $this->one(
            "SELECT COALESCE(SUM(t.minutes), 0) AS minutes, COALESCE(SUM(t.total_cost), 0) AS cost
             FROM job_time_entries t
             INNER JOIN job_quality_checks q ON q.rework_task_id = t.task_id
             WHERE DATE(COALESCE(t.ended_at, t.created_at)) BETWEEN ? AND ?",
            [$from, $to]
        );
        $stages = $this->rows(
            "SELECT check_type AS name, COUNT(*) AS n
             FROM job_quality_checks
             WHERE (rework_task_id IS NOT NULL OR status = 'REWORK_REQUIRED')
               AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY check_type",
            [$from, $to]
        );

        return [
            'jobs' => (int) ($jobs['n'] ?? 0),
            'material_cost' => Decimal::money((string) ($material['cost'] ?? '0')),
            'labour_minutes' => (string) ($labour['minutes'] ?? 0),
            'labour_cost' => Decimal::money((string) ($labour['cost'] ?? '0')),
            'stages' => $stages,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function priceChanges(): array
    {
        return $this->rows(
            "SELECT p.name AS product_name, s.name AS supplier_name, h.old_price, h.new_price, h.effective_date
             FROM supplier_price_history h
             INNER JOIN supplier_products sp ON sp.id = h.supplier_product_id
             INNER JOIN products p ON p.id = sp.product_id
             INNER JOIN suppliers s ON s.id = sp.supplier_id
             ORDER BY h.effective_date DESC, h.id DESC
             LIMIT 80"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function internalPriceChanges(): array
    {
        return $this->rows(
            "SELECT p.name AS product_name, h.old_cost, h.new_cost, h.changed_at
             FROM product_price_history h
             INNER JOIN products p ON p.id = h.product_id
             ORDER BY h.changed_at DESC
             LIMIT 40"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function purchaseLines(string $from, string $to): array
    {
        return $this->rows(
            "SELECT p.name AS product_name, SUM(i.ordered_quantity) AS qty, SUM(i.line_total) AS value,
                    AVG(i.unit_cost) AS avg_cost
             FROM purchase_order_items i
             INNER JOIN purchase_orders po ON po.id = i.purchase_order_id
             INNER JOIN products p ON p.id = i.product_id
             WHERE po.order_date BETWEEN ? AND ? AND po.status <> 'CANCELLED'
             GROUP BY p.id, p.name
             ORDER BY value DESC
             LIMIT 80",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function supplierStats(string $from, string $to): array
    {
        return $this->rows(
            "SELECT s.id, s.name,
                    COUNT(po.id) AS orders,
                    COALESCE(SUM(po.total), 0) AS value,
                    SUM(po.status = 'PARTIALLY_RECEIVED') AS partials,
                    SUM(po.expected_date IS NOT NULL AND po.expected_date < CURDATE() AND po.status NOT IN ('RECEIVED','CANCELLED')) AS late,
                    (SELECT AVG(DATEDIFF(gr.received_date, po2.order_date))
                     FROM goods_receipts gr
                     INNER JOIN purchase_orders po2 ON po2.id = gr.purchase_order_id
                     WHERE po2.supplier_id = s.id AND po2.order_date BETWEEN ? AND ?) AS lead_days
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.order_date BETWEEN ? AND ?
             GROUP BY s.id, s.name
             ORDER BY value DESC",
            [$from, $to, $from, $to]
        );
    }

    public function supplierReturns(string $from, string $to): array
    {
        return $this->rows(
            "SELECT po.supplier_id AS id, COUNT(*) AS n
             FROM stock_movements m
             INNER JOIN purchase_orders po ON po.id = m.purchase_order_id
             WHERE m.movement_type = 'SUPPLIER_RETURN' AND m.movement_date BETWEEN ? AND ?
             GROUP BY po.supplier_id",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openPurchaseOrders(): array
    {
        return $this->rows(
            "SELECT po.id, po.po_number, po.status, po.expected_date, po.total, s.name AS supplier_name
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.status IN ('APPROVED','ORDERED','PARTIALLY_RECEIVED')
             ORDER BY po.expected_date, po.id
             LIMIT 100"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function adjustments(string $from, string $to): array
    {
        return $this->rows(
            "SELECT movement_type, COUNT(*) AS n, COALESCE(SUM(total_cost), 0) AS cost
             FROM stock_movements
             WHERE movement_type IN ('ADJUSTMENT_IN','ADJUSTMENT_OUT','STOCK_COUNT_CORRECTION','WASTE','DAMAGE')
               AND movement_date BETWEEN ? AND ?
             GROUP BY movement_type",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function offcutMovement(string $from, string $to): array
    {
        return $this->rows(
            "SELECT movement_type, COUNT(*) AS n, COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total_cost), 0) AS cost
             FROM stock_movements
             WHERE movement_type IN ('OFFCUT_CREATED','OFFCUT_CONSUMED') AND movement_date BETWEEN ? AND ?
             GROUP BY movement_type",
            [$from, $to]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function slowStock(string $before): array
    {
        return $this->rows(
            "SELECT p.id, p.name, p.cost_price,
                    COALESCE(MAX(u.recorded_at), p.created_at) AS last_movement
             FROM products p
             LEFT JOIN job_material_usage u ON u.product_id = p.id
             WHERE p.inventory_method <> 'NONE' AND p.active = 1
               AND NOT EXISTS (
                    SELECT 1 FROM job_material_requirements r
                    INNER JOIN jobs j ON j.id = r.job_id
                    WHERE r.product_id = p.id
                      AND j.status NOT IN ('COMPLETED', 'CANCELLED')
                      AND r.final_required_quantity > 0
               )
             GROUP BY p.id, p.name, p.cost_price, p.created_at
             HAVING last_movement < ?
             ORDER BY last_movement
             LIMIT 100",
            [$before]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function monthlyConsumption(): array
    {
        return $this->rows(
            "SELECT product_id, SUM(quantity) AS qty
             FROM job_material_usage
             WHERE usage_type IN ('PRODUCTION','WASTE','REWORK','INSTALLATION')
               AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
             GROUP BY product_id"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stockByLocation(): array
    {
        return $this->rows(
            "SELECT l.name AS location, COALESCE(SUM(CASE WHEN i.inventory_type = 'OFFCUT' THEN 0 ELSE m.total_cost END), 0) AS value
             FROM stock_locations l
             LEFT JOIN stock_movements m ON m.stock_location_id = l.id
             LEFT JOIN inventory_items i ON i.id = m.inventory_item_id
             GROUP BY l.id, l.name
             ORDER BY l.name"
        );
    }

    /**
     * Quotes whose expiry falls inside the warning window and are still open.
     *
     * @return list<array<string, mixed>>
     */
    public function expiringQuotes(string $until): array
    {
        return $this->rows(
            "SELECT id, quote_number, assigned_to, expiry_date, total
             FROM quotes
             WHERE archived = 0 AND status IN ('SENT','VIEWED')
               AND expiry_date IS NOT NULL AND expiry_date >= CURDATE() AND expiry_date <= ?",
            [$until]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overdueJobs(): array
    {
        return $this->rows(
            "SELECT id, job_number, assigned_to, target_date
             FROM jobs
             WHERE archived = 0 AND target_date IS NOT NULL AND target_date < CURDATE()
               AND status NOT IN ('COMPLETED','CANCELLED')
             LIMIT 200"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function overdueTasks(): array
    {
        return $this->rows(
            "SELECT id, job_id, title, assigned_to, due_date
             FROM job_tasks
             WHERE due_date IS NOT NULL AND due_date < CURDATE() AND status NOT IN ('COMPLETE','CANCELLED')
             LIMIT 200"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function installationsOn(string $date): array
    {
        return $this->rows(
            "SELECT i.id, i.job_id, i.assigned_user_id, i.status, j.job_number
             FROM job_installations i
             INNER JOIN jobs j ON j.id = i.job_id
             WHERE i.scheduled_date = ?",
            [$date]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function returnInstallations(): array
    {
        return $this->rows(
            "SELECT i.id, i.job_id, i.assigned_user_id, j.job_number
             FROM job_installations i
             INNER JOIN jobs j ON j.id = i.job_id
             WHERE i.status = 'RETURN_REQUIRED'
             LIMIT 100"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueInvoices(string $from, string $until, bool $overdueOnly): array
    {
        $sql = "SELECT id, invoice_number, customer_id, due_date, balance_due
                FROM invoices
                WHERE status IN ('ISSUED','PARTIALLY_PAID') AND balance_due > 0 AND due_date IS NOT NULL";
        $sql .= $overdueOnly ? ' AND due_date < ?' : ' AND due_date >= ? AND due_date <= ?';
        $params = $overdueOnly ? [$from] : [$from, $until];

        return $this->rows($sql . ' LIMIT 300', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function largeBalances(string $amount): array
    {
        return $this->rows(
            "SELECT customer_id, SUM(balance_due) AS balance
             FROM invoices
             WHERE status NOT IN ('DRAFT','CANCELLED') AND balance_due > 0
             GROUP BY customer_id
             HAVING balance >= ?",
            [$amount]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function heldCustomers(): array
    {
        return $this->rows(
            "SELECT id, company_name, first_name, last_name, customer_type
             FROM customers WHERE account_on_hold = 1 AND active = 1 LIMIT 50"
        );
    }
}

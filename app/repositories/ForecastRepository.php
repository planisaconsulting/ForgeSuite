<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Read models for planning. Forecast rows are stored only in forecast_snapshots.
 */
final class ForecastRepository extends Repository
{
    public function openOpportunities(): array
    {
        return $this->rows(
            "SELECT o.id, o.opportunity_number, o.title, o.estimated_value, o.probability_percent,
                    o.status, o.expected_close_date, o.assigned_to, u.name AS salesperson,
                    c.company_name, c.first_name, c.last_name
             FROM sales_opportunities o
             INNER JOIN customers c ON c.id = o.customer_id
             LEFT JOIN users u ON u.id = o.assigned_to
             WHERE o.status NOT IN ('WON', 'LOST')
             ORDER BY o.expected_close_date, o.id"
        );
    }

    public function openQuotes(): array
    {
        return $this->rows(
            "SELECT q.id, q.quote_number, q.total, q.status, q.quote_date, q.expiry_date,
                    q.expected_decision_date, q.assigned_to, c.company_name
             FROM quotes q
             INNER JOIN customers c ON c.id = q.customer_id
             WHERE q.status IN ('READY', 'SENT', 'VIEWED') AND q.archived = 0
             ORDER BY q.expiry_date, q.id"
        );
    }

    public function conversionContext(): array
    {
        $row = $this->one(
            "SELECT
                SUM(CASE WHEN status IN ('ACCEPTED', 'CONVERTED') THEN 1 ELSE 0 END) AS won,
                SUM(CASE WHEN status IN ('DECLINED', 'EXPIRED', 'ACCEPTED', 'CONVERTED') THEN 1 ELSE 0 END) AS decided
             FROM quotes WHERE archived = 0"
        );

        return [
            'won' => (int) ($row['won'] ?? 0),
            'decided' => (int) ($row['decided'] ?? 0),
        ];
    }

    public function openJobs(): array
    {
        return $this->rows(
            "SELECT j.id, j.job_number, j.quote_id, j.status, j.target_date, j.quoted_revenue_snapshot,
                    c.company_name,
                    COALESCE(SUM(i.quantity), 0) AS item_quantity,
                    COALESCE(SUM(CASE WHEN i.production_status = 'COMPLETE' THEN i.quantity ELSE 0 END), 0) AS completed_quantity,
                    COALESCE((
                        SELECT SUM(inv.total) FROM invoices inv
                        WHERE inv.job_id = j.id AND inv.status NOT IN ('DRAFT', 'CANCELLED')
                    ), 0) AS invoiced
             FROM jobs j
             INNER JOIN customers c ON c.id = j.customer_id
             LEFT JOIN job_items i ON i.job_id = j.id
             WHERE j.status NOT IN ('COMPLETED', 'CANCELLED') AND j.archived = 0
             GROUP BY j.id, j.job_number, j.quote_id, j.status, j.target_date, j.quoted_revenue_snapshot, c.company_name
             ORDER BY j.target_date, j.id"
        );
    }

    public function issuedReceivables(): array
    {
        return $this->rows(
            "SELECT id, invoice_number, customer_id, due_date, balance_due, total, status
             FROM invoices
             WHERE status NOT IN ('DRAFT', 'CANCELLED') AND balance_due > 0
             ORDER BY due_date, id"
        );
    }

    public function paymentDays(int $customerId): array
    {
        return $this->rows(
            "SELECT DATEDIFF(paid_at, invoice_date) AS days
             FROM invoices
             WHERE customer_id = ? AND paid_at IS NOT NULL AND status = 'PAID'
             ORDER BY days",
            [$customerId]
        );
    }

    public function openPurchaseOrders(): array
    {
        return $this->rows(
            "SELECT po.id, po.po_number, po.total, po.expected_date, po.payment_terms, po.status,
                    s.name AS supplier_name, s.payment_terms AS supplier_terms
             FROM purchase_orders po
             INNER JOIN suppliers s ON s.id = po.supplier_id
             WHERE po.status IN ('APPROVED', 'ORDERED', 'PARTIALLY_RECEIVED')
             ORDER BY po.expected_date, po.id"
        );
    }

    public function firmDemand(): array
    {
        return $this->rows(
            "SELECT r.id, r.job_id, r.product_id, r.final_required_quantity AS quantity, r.pack_size,
                    COALESCE(j.target_date, CURDATE()) AS required_by, j.job_number, j.status,
                    p.name AS product_name, p.minimum_stock_level, p.cost_price
             FROM job_material_requirements r
             INNER JOIN jobs j ON j.id = r.job_id
             INNER JOIN products p ON p.id = r.product_id
             WHERE j.status NOT IN ('COMPLETED', 'CANCELLED') AND j.archived = 0
               AND j.preparation_status IN ('RELEASED', 'IN_PRODUCTION', 'PRODUCTION_COMPLETE')
               AND r.final_required_quantity > 0
             ORDER BY required_by, r.id"
        );
    }

    /**
     * Unreleased work stays planned. It is not also counted as firm demand.
     *
     * @return list<array<string, mixed>>
     */
    public function plannedDemand(): array
    {
        return $this->rows(
            "SELECT r.id, r.job_id, r.product_id, r.final_required_quantity AS quantity, r.pack_size,
                    COALESCE(j.target_date, CURDATE()) AS required_by, j.job_number, j.status,
                    p.name AS product_name, p.minimum_stock_level, p.cost_price
             FROM job_material_requirements r
             INNER JOIN jobs j ON j.id = r.job_id
             INNER JOIN products p ON p.id = r.product_id
             WHERE j.status NOT IN ('COMPLETED', 'CANCELLED') AND j.archived = 0
               AND j.preparation_status NOT IN ('RELEASED', 'IN_PRODUCTION', 'PRODUCTION_COMPLETE')
               AND r.final_required_quantity > 0
             ORDER BY required_by, r.id"
        );
    }

    public function incomingPurchaseLines(): array
    {
        return $this->rows(
            "SELECT i.product_id, (i.ordered_quantity - i.received_quantity) AS quantity,
                    COALESCE(i.expected_date, po.expected_date, po.order_date) AS arrives, po.status
             FROM purchase_order_items i
             INNER JOIN purchase_orders po ON po.id = i.purchase_order_id
             WHERE po.status IN ('APPROVED', 'ORDERED', 'PARTIALLY_RECEIVED')
               AND i.ordered_quantity > i.received_quantity"
        );
    }

    public function issuedQuantity(int $productId, int $days): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(-quantity), 0) AS issued
             FROM stock_movements
             WHERE product_id = ? AND quantity < 0 AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)',
            [$productId, $days]
        );

        return (string) ($row['issued'] ?? '0');
    }

    public function supplierOptions(int $productId): array
    {
        return $this->rows(
            'SELECT sp.*, s.name AS supplier_name
             FROM supplier_products sp
             INNER JOIN suppliers s ON s.id = sp.supplier_id
             WHERE sp.product_id = ? AND sp.active = 1
             ORDER BY sp.preferred_supplier DESC, sp.cost_price',
            [$productId]
        );
    }

    public function product(int $id): ?array
    {
        return $this->one('SELECT id, name, cost_price, minimum_stock_level FROM products WHERE id = ?', [$id]);
    }

    public function quoteTotal(int $id): ?string
    {
        $row = $this->one('SELECT total FROM quotes WHERE id = ?', [$id]);

        return $row === null ? null : (string) $row['total'];
    }

    public function insertSnapshot(array $data): int
    {
        $this->run(
            'INSERT INTO forecast_snapshots
                (forecast_type, as_of_date, horizon_start, horizon_end, parameters_json, result_summary_json, generated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['forecast_type'], $data['as_of_date'], $data['horizon_start'], $data['horizon_end'],
                $data['parameters_json'], $data['result_summary_json'], $data['generated_by'],
            ]
        );

        return $this->insertId();
    }

    public function snapshots(string $type): array
    {
        return $this->rows(
            'SELECT * FROM forecast_snapshots WHERE forecast_type = ? ORDER BY generated_at DESC, id DESC LIMIT 20',
            [$type]
        );
    }

    public function insertRecommendation(array $data): int
    {
        $this->run(
            'INSERT INTO purchase_recommendations
                (product_id, supplier_id, required_quantity, order_quantity, required_by_date, recommended_order_date, source_type, source_summary, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['product_id'], $data['supplier_id'], $data['required_quantity'], $data['order_quantity'],
                $data['required_by_date'], $data['recommended_order_date'], $data['source_type'], $data['source_summary'],
                $data['status'],
            ]
        );

        return $this->insertId();
    }

    public function insertRecommendationSource(array $data): void
    {
        $this->run(
            'INSERT INTO purchase_recommendation_sources
                (recommendation_id, job_id, demand_category, quantity, required_by_date, source_label)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['recommendation_id'], $data['job_id'], $data['demand_category'], $data['quantity'],
                $data['required_by_date'], $data['source_label'],
            ]
        );
    }

    public function recommendations(string $status = ''): array
    {
        $sql = 'SELECT r.*, p.name AS product_name FROM purchase_recommendations r INNER JOIN products p ON p.id = r.product_id';
        $params = [];
        if ($status !== '') {
            $sql .= ' WHERE r.status = ?';
            $params[] = $status;
        }

        return $this->rows($sql . ' ORDER BY r.required_by_date, r.id', $params);
    }

    public function recommendation(int $id): ?array
    {
        return $this->one('SELECT * FROM purchase_recommendations WHERE id = ?', [$id]);
    }

    public function recommendationSources(int $id): array
    {
        return $this->rows('SELECT * FROM purchase_recommendation_sources WHERE recommendation_id = ?', [$id]);
    }

    public function setRecommendationStatus(int $id, string $status, int $userId): void
    {
        $this->run(
            'UPDATE purchase_recommendations SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
            [$status, $userId, $id]
        );
    }

    public function insertBudget(array $data): int
    {
        $this->run(
            'INSERT INTO budgets (name, financial_year, status, created_by) VALUES (?, ?, ?, ?)',
            [$data['name'], $data['financial_year'], $data['status'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function insertBudgetLine(array $data): int
    {
        $this->run(
            'INSERT INTO budget_lines (budget_id, period, metric_code, target_amount, notes) VALUES (?, ?, ?, ?, ?)',
            [$data['budget_id'], $data['period'], $data['metric_code'], $data['target_amount'], $data['notes']]
        );

        return $this->insertId();
    }

    public function budgets(): array
    {
        return $this->rows('SELECT * FROM budgets ORDER BY financial_year DESC, id DESC');
    }

    public function budgetLines(int $budgetId): array
    {
        return $this->rows('SELECT * FROM budget_lines WHERE budget_id = ? ORDER BY period, metric_code', [$budgetId]);
    }

    public function invoicedBetween(string $from, string $to): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS amount FROM invoices
             WHERE status NOT IN ('DRAFT', 'CANCELLED') AND invoice_date BETWEEN ? AND ?",
            [$from, $to]
        );

        return (string) ($row['amount'] ?? '0');
    }

    public function insertTarget(array $data): int
    {
        $this->run(
            'INSERT INTO planning_targets
                (name, metric_code, scope_type, scope_id, period_start, period_end, target_amount, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['metric_code'], $data['scope_type'], $data['scope_id'],
                $data['period_start'], $data['period_end'], $data['target_amount'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function targets(): array
    {
        return $this->rows('SELECT * FROM planning_targets ORDER BY period_start DESC, id DESC');
    }

    public function insertScenario(array $data): int
    {
        $this->run(
            'INSERT INTO scenarios (name, description, scenario_type, parameters_json, created_by) VALUES (?, ?, ?, ?, ?)',
            [$data['name'], $data['description'], $data['scenario_type'], $data['parameters_json'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function scenarios(): array
    {
        return $this->rows('SELECT * FROM scenarios ORDER BY id DESC');
    }

    public function scenario(int $id): ?array
    {
        return $this->one('SELECT * FROM scenarios WHERE id = ?', [$id]);
    }

    public function insertImport(array $data): int
    {
        $this->run(
            'INSERT INTO data_imports
                (import_type, filename, status, rows_total, rows_success, rows_failed, created_by, completed_at, error_file_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['import_type'], $data['filename'], $data['status'], $data['rows_total'],
                $data['rows_success'], $data['rows_failed'], $data['created_by'], $data['completed_at'], $data['error_file_path'],
            ]
        );

        return $this->insertId();
    }

    public function customerByEmail(string $email): ?array
    {
        return $this->one('SELECT id, company_name, email FROM customers WHERE email = ? LIMIT 1', [$email]);
    }

    public function customersByName(string $name): array
    {
        return $this->rows(
            'SELECT id, company_name FROM customers WHERE company_name = ? LIMIT 5',
            [$name]
        );
    }

    public function insertApiClient(array $data): int
    {
        $this->run(
            'INSERT INTO api_clients (name, client_identifier, secret_hash, scopes_json, active, expires_at, created_by)
             VALUES (?, ?, ?, ?, 1, ?, ?)',
            [$data['name'], $data['client_identifier'], $data['secret_hash'], $data['scopes_json'], $data['expires_at'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function apiClientByIdentifier(string $identifier): ?array
    {
        return $this->one('SELECT * FROM api_clients WHERE client_identifier = ?', [$identifier]);
    }

    public function touchApiClient(int $id): void
    {
        $this->run('UPDATE api_clients SET last_used_at = NOW() WHERE id = ?', [$id]);
    }

    public function apiClients(): array
    {
        return $this->rows('SELECT id, name, client_identifier, scopes_json, active, expires_at, last_used_at, revoked_at, created_at FROM api_clients ORDER BY id DESC');
    }

    public function revokeApiClient(int $id): void
    {
        $this->run('UPDATE api_clients SET active = 0, revoked_at = NOW() WHERE id = ?', [$id]);
    }

    public function logApi(array $data): void
    {
        $this->run(
            'INSERT INTO api_request_log (api_client_id, endpoint, action, status_code, entity_type, entity_id) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['api_client_id'], $data['endpoint'], $data['action'], $data['status_code'], $data['entity_type'], $data['entity_id']]
        );
    }

    public function insertWebhook(array $data): int
    {
        $this->run(
            'INSERT INTO webhook_subscriptions (name, endpoint_url, secret, event_types_json, active) VALUES (?, ?, ?, ?, 1)',
            [$data['name'], $data['endpoint_url'], $data['secret'], $data['event_types_json']]
        );

        return $this->insertId();
    }

    public function activeWebhooks(): array
    {
        return $this->rows('SELECT * FROM webhook_subscriptions WHERE active = 1');
    }

    public function webhooks(): array
    {
        return $this->rows('SELECT id, name, endpoint_url, event_types_json, active, created_at FROM webhook_subscriptions ORDER BY id DESC');
    }

    public function insertDelivery(array $data): int
    {
        $this->run(
            'INSERT INTO webhook_deliveries
                (subscription_id, event_id, event_type, attempt, status, response_code, error_message, payload_json, next_retry_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['subscription_id'], $data['event_id'], $data['event_type'], $data['attempt'], $data['status'],
                $data['response_code'], $data['error_message'], $data['payload_json'], $data['next_retry_at'],
            ]
        );

        return $this->insertId();
    }

    public function dueDeliveries(): array
    {
        return $this->rows(
            "SELECT d.*, s.endpoint_url, s.secret, s.active AS subscription_active
             FROM webhook_deliveries d
             INNER JOIN webhook_subscriptions s ON s.id = d.subscription_id
             WHERE d.status = 'RETRY' AND d.next_retry_at IS NOT NULL AND d.next_retry_at <= NOW()
             ORDER BY d.id
             LIMIT 50"
        );
    }

    public function delivery(int $id): ?array
    {
        return $this->one('SELECT * FROM webhook_deliveries WHERE id = ?', [$id]);
    }

    public function updateDelivery(int $id, array $data): void
    {
        $this->run(
            'UPDATE webhook_deliveries SET attempt = ?, status = ?, response_code = ?, error_message = ?, next_retry_at = ?, sent_at = NOW() WHERE id = ?',
            [$data['attempt'], $data['status'], $data['response_code'], $data['error_message'], $data['next_retry_at'], $id]
        );
    }

    public function upsertMapping(array $data): void
    {
        $this->run(
            'INSERT INTO integration_mappings (provider, entity_type, entity_id, external_id, sync_status, last_synced_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE sync_status = VALUES(sync_status), external_id = VALUES(external_id), last_synced_at = VALUES(last_synced_at)',
            [$data['provider'], $data['entity_type'], $data['entity_id'], $data['external_id'], $data['sync_status'], $data['last_synced_at']]
        );
    }

    public function insertSyncLog(array $data): int
    {
        $this->run(
            'INSERT INTO integration_sync_log (provider, entity_type, entity_id, direction, status, message, completed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$data['provider'], $data['entity_type'], $data['entity_id'], $data['direction'], $data['status'], $data['message'], $data['completed_at']]
        );

        return $this->insertId();
    }

    public function syncLogs(): array
    {
        return $this->rows('SELECT * FROM integration_sync_log ORDER BY id DESC LIMIT 100');
    }

    public function invoiceStatus(int $id): ?string
    {
        $row = $this->one('SELECT status FROM invoices WHERE id = ?', [$id]);

        return $row === null ? null : (string) $row['status'];
    }

    public function insertCashPosition(array $data): int
    {
        $this->run(
            'INSERT INTO operational_cash_positions (as_of_date, opening_amount, notes, created_by) VALUES (?, ?, ?, ?)',
            [$data['as_of_date'], $data['opening_amount'], $data['notes'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function latestCashPosition(): ?array
    {
        return $this->one('SELECT * FROM operational_cash_positions ORDER BY as_of_date DESC, id DESC LIMIT 1');
    }

    public function commitments(string $from, string $to): array
    {
        return $this->rows(
            'SELECT * FROM operational_commitments WHERE due_date BETWEEN ? AND ? ORDER BY due_date',
            [$from, $to]
        );
    }

    public function insertCommitment(array $data): int
    {
        $this->run(
            'INSERT INTO operational_commitments (name, amount, due_date, category, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['name'], $data['amount'], $data['due_date'], $data['category'], $data['notes'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function insertLocation(array $data): int
    {
        $this->run(
            'INSERT INTO business_locations (code, name, address, active, default_stock_location_id, timezone) VALUES (?, ?, ?, 1, ?, ?)',
            [$data['code'], $data['name'], $data['address'], $data['default_stock_location_id'], $data['timezone']]
        );

        return $this->insertId();
    }

    public function locations(): array
    {
        return $this->rows('SELECT * FROM business_locations ORDER BY name');
    }

    public function setPurchaseTerms(int $orderId, string $terms): void
    {
        $this->run('UPDATE purchase_orders SET payment_terms = ? WHERE id = ?', [$terms, $orderId]);
    }

    public function customer(int $id): ?array
    {
        return $this->one('SELECT id, company_name, first_name, last_name, email FROM customers WHERE id = ?', [$id]);
    }

    public function quoteStatus(int $id): ?array
    {
        return $this->one('SELECT id, quote_number, status, total FROM quotes WHERE id = ?', [$id]);
    }

    public function jobStatus(int $id): ?array
    {
        return $this->one('SELECT id, job_number, status, target_date FROM jobs WHERE id = ?', [$id]);
    }

    public function dataQualityCustomers(): array
    {
        return $this->rows(
            "SELECT id, company_name FROM customers
             WHERE active = 1 AND (email IS NULL OR email = '') AND (phone IS NULL OR phone = '') AND (mobile IS NULL OR mobile = '')
             LIMIT 50"
        );
    }

    public function dataQualityProducts(): array
    {
        return $this->rows(
            "SELECT id, name FROM products WHERE active = 1 AND (cost_price IS NULL OR cost_price <= 0) LIMIT 50"
        );
    }

    public function dataQualityJobs(): array
    {
        return $this->rows(
            "SELECT id, job_number FROM jobs WHERE archived = 0 AND status NOT IN ('COMPLETED', 'CANCELLED') AND target_date IS NULL LIMIT 50"
        );
    }

    public function dataQualityInvoices(): array
    {
        return $this->rows(
            "SELECT id, invoice_number FROM invoices WHERE status = 'ISSUED' AND due_date IS NULL LIMIT 50"
        );
    }

    public function dataQualityOpportunities(): array
    {
        return $this->rows(
            "SELECT id, opportunity_number FROM sales_opportunities
             WHERE status NOT IN ('WON', 'LOST') AND next_follow_up_date IS NULL LIMIT 50"
        );
    }

    public function negativeStock(): array
    {
        return $this->rows(
            'SELECT id, inventory_code, remaining_quantity FROM inventory_items WHERE remaining_quantity < 0 LIMIT 50'
        );
    }

    public function calendarQuotes(string $from, string $to): array
    {
        return $this->rows(
            "SELECT id, quote_number, total, COALESCE(expected_decision_date, expiry_date) AS on_date
             FROM quotes
             WHERE status IN ('READY', 'SENT', 'VIEWED')
               AND COALESCE(expected_decision_date, expiry_date) BETWEEN ? AND ?",
            [$from, $to]
        );
    }

    public function calendarJobs(string $from, string $to): array
    {
        return $this->rows(
            "SELECT id, job_number, target_date FROM jobs
             WHERE archived = 0 AND status NOT IN ('COMPLETED', 'CANCELLED') AND target_date BETWEEN ? AND ?",
            [$from, $to]
        );
    }

    public function calendarInvoices(string $from, string $to): array
    {
        return $this->rows(
            "SELECT id, invoice_number, due_date, balance_due FROM invoices
             WHERE status NOT IN ('DRAFT', 'CANCELLED') AND due_date BETWEEN ? AND ? AND balance_due > 0",
            [$from, $to]
        );
    }
}

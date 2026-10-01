-- Phase 12 business planning, forecasting, MRP, and the integration platform.
-- Import once on a database that already has Phase 11. Do not re-import schema.sql.

SET NAMES utf8mb4;

ALTER TABLE sales_opportunities
    ADD COLUMN business_location_id INT UNSIGNED NULL AFTER assigned_to,
    ADD KEY idx_opportunities_close (expected_close_date),
    ADD KEY idx_opportunities_location (business_location_id);

ALTER TABLE quotes
    ADD COLUMN expected_decision_date DATE NULL AFTER expiry_date,
    ADD KEY idx_quotes_decision (expected_decision_date);

ALTER TABLE jobs
    ADD COLUMN business_location_id INT UNSIGNED NULL AFTER assigned_to,
    ADD KEY idx_jobs_location (business_location_id);

ALTER TABLE users
    ADD COLUMN business_location_id INT UNSIGNED NULL AFTER role_id,
    ADD KEY idx_users_location (business_location_id);

ALTER TABLE resources
    ADD COLUMN business_location_id INT UNSIGNED NULL AFTER linked_supplier_id,
    ADD KEY idx_resources_location (business_location_id);

ALTER TABLE suppliers
    ADD COLUMN payment_terms VARCHAR(20) NULL AFTER account_number;

ALTER TABLE purchase_orders
    ADD COLUMN payment_terms VARCHAR(20) NULL AFTER expected_date,
    ADD KEY idx_purchase_orders_expected (expected_date);

CREATE TABLE business_locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(180) NOT NULL,
    address TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    default_stock_location_id INT UNSIGNED NULL,
    timezone VARCHAR(60) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_locations_code (code),
    KEY idx_business_locations_stock (default_stock_location_id),
    CONSTRAINT fk_business_locations_stock FOREIGN KEY (default_stock_location_id) REFERENCES stock_locations (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sales_opportunities
    ADD CONSTRAINT fk_opportunities_location FOREIGN KEY (business_location_id) REFERENCES business_locations (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE jobs
    ADD CONSTRAINT fk_jobs_location FOREIGN KEY (business_location_id) REFERENCES business_locations (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE users
    ADD CONSTRAINT fk_users_location FOREIGN KEY (business_location_id) REFERENCES business_locations (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE resources
    ADD CONSTRAINT fk_resources_location FOREIGN KEY (business_location_id) REFERENCES business_locations (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE forecast_snapshots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    forecast_type VARCHAR(40) NOT NULL,
    as_of_date DATE NOT NULL,
    horizon_start DATE NOT NULL,
    horizon_end DATE NOT NULL,
    parameters_json JSON NULL,
    result_summary_json JSON NULL,
    generated_by INT UNSIGNED NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_forecast_snapshots_type_date (forecast_type, as_of_date),
    KEY idx_forecast_snapshots_generated (generated_at),
    CONSTRAINT fk_forecast_snapshots_user FOREIGN KEY (generated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_recommendations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NULL,
    required_quantity DECIMAL(14,4) NOT NULL,
    order_quantity DECIMAL(14,4) NOT NULL,
    required_by_date DATE NULL,
    recommended_order_date DATE NULL,
    source_type VARCHAR(40) NOT NULL,
    source_summary VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'NEW',
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_purchase_recommendations_product (product_id),
    KEY idx_purchase_recommendations_status (status),
    KEY idx_purchase_recommendations_required (required_by_date),
    CONSTRAINT fk_purchase_recommendations_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_recommendations_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_recommendations_user FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_recommendation_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recommendation_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    demand_category VARCHAR(20) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    required_by_date DATE NULL,
    source_label VARCHAR(180) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_recommendation_sources_rec (recommendation_id),
    KEY idx_recommendation_sources_job (job_id),
    CONSTRAINT fk_recommendation_sources_rec FOREIGN KEY (recommendation_id) REFERENCES purchase_recommendations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_recommendation_sources_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE budgets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    financial_year VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_budgets_year (financial_year, status),
    CONSTRAINT fk_budgets_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE budget_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    budget_id INT UNSIGNED NOT NULL,
    period DATE NOT NULL,
    metric_code VARCHAR(40) NOT NULL,
    category_id INT UNSIGNED NULL,
    target_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_budget_lines_period (budget_id, period, metric_code),
    CONSTRAINT fk_budget_lines_budget FOREIGN KEY (budget_id) REFERENCES budgets (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_budget_lines_category FOREIGN KEY (category_id) REFERENCES product_categories (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planning_targets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    metric_code VARCHAR(40) NOT NULL,
    scope_type VARCHAR(20) NOT NULL DEFAULT 'COMPANY',
    scope_id INT UNSIGNED NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    target_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_planning_targets_period (metric_code, period_start, period_end),
    CONSTRAINT fk_planning_targets_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scenarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    scenario_type VARCHAR(40) NOT NULL,
    parameters_json JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_scenarios_type (scenario_type),
    CONSTRAINT fk_scenarios_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_imports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    import_type VARCHAR(40) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PREVIEW',
    rows_total INT UNSIGNED NOT NULL DEFAULT 0,
    rows_success INT UNSIGNED NOT NULL DEFAULT 0,
    rows_failed INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    error_file_path VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_data_imports_type (import_type, started_at),
    CONSTRAINT fk_data_imports_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    client_identifier VARCHAR(80) NOT NULL,
    secret_hash CHAR(64) NOT NULL,
    scopes_json JSON NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_clients_identifier (client_identifier),
    KEY idx_api_clients_active (active),
    CONSTRAINT fk_api_clients_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_request_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_client_id INT UNSIGNED NULL,
    endpoint VARCHAR(180) NOT NULL,
    action VARCHAR(20) NOT NULL,
    status_code INT NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_api_request_log_client (api_client_id, created_at),
    CONSTRAINT fk_api_request_log_client FOREIGN KEY (api_client_id) REFERENCES api_clients (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_subscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    endpoint_url VARCHAR(255) NOT NULL,
    secret VARCHAR(128) NOT NULL,
    event_types_json JSON NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_webhook_subscriptions_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_deliveries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    subscription_id INT UNSIGNED NOT NULL,
    event_id VARCHAR(64) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    attempt INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    response_code INT NULL,
    error_message VARCHAR(255) NULL,
    payload_json JSON NULL,
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    next_retry_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_webhook_deliveries_event (event_id),
    KEY idx_webhook_deliveries_retry (status, next_retry_at),
    CONSTRAINT fk_webhook_deliveries_sub FOREIGN KEY (subscription_id) REFERENCES webhook_subscriptions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_mappings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    external_id VARCHAR(80) NULL,
    sync_status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    last_synced_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_mappings_entity (provider, entity_type, entity_id),
    KEY idx_integration_mappings_external (provider, external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_sync_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    direction VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    message VARCHAR(255) NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_integration_sync_entity (provider, entity_type, entity_id),
    KEY idx_integration_sync_status (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operational_cash_positions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    as_of_date DATE NOT NULL,
    opening_amount DECIMAL(14,2) NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cash_positions_date (as_of_date),
    CONSTRAINT fk_cash_positions_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE operational_commitments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    due_date DATE NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_operational_commitments_due (due_date),
    CONSTRAINT fk_operational_commitments_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, name, module) VALUES
('planning.view', 'View the planning overview', 'planning'),
('forecast.sales', 'View the sales forecast', 'planning'),
('forecast.cash', 'View cash visibility', 'planning'),
('forecast.materials', 'View material demand', 'planning'),
('forecast.capacity', 'View the capacity forecast', 'planning'),
('mrp.view', 'View material requirements planning', 'planning'),
('mrp.manage', 'Refresh material planning', 'planning'),
('purchase_recommendations.review', 'Review purchase recommendations', 'planning'),
('budgets.view', 'View operational budgets', 'planning'),
('budgets.manage', 'Edit operational budgets', 'planning'),
('targets.view', 'View planning targets', 'planning'),
('targets.manage', 'Edit planning targets', 'planning'),
('scenarios.view', 'View scenarios', 'planning'),
('scenarios.manage', 'Run scenarios', 'planning'),
('imports.perform', 'Import business data', 'planning'),
('exports.perform', 'Export planning data', 'planning'),
('api.manage', 'Manage API clients', 'integrations'),
('webhooks.manage', 'Manage outbound webhooks', 'integrations'),
('integrations.manage', 'Manage integrations', 'integrations'),
('integration_logs.view', 'View integration logs', 'integrations'),
('data_quality.view', 'View data quality', 'planning');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.code IN (
    'planning.view', 'forecast.sales', 'forecast.cash', 'forecast.materials', 'forecast.capacity',
    'mrp.view', 'budgets.view', 'targets.view', 'scenarios.view', 'data_quality.view', 'exports.perform'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('planning.view', 'forecast.sales', 'targets.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN ('planning.view', 'forecast.cash', 'budgets.view', 'targets.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN ('forecast.materials', 'forecast.capacity', 'mrp.view');

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('planning_order_buffer_days', '2'),
('planning_skip_weekends', '1'),
('planning_default_horizon', '30'),
('accounting_provider', ''),
('api_rate_per_minute', '60'),
('forecast_stale_days', '7'),
('budget_variance_alert_percent', '10'),
('cash_pressure_amount', '0');

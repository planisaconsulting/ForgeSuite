-- Sign-Forge Management System
-- Phase 6: reporting, notifications, automation, and administration.
--
-- Apply this once to a database that already has Phases 1 to 5.
-- It does not copy invoices, jobs, or stock into a second ledger.
-- Reports read those tables. This file only adds the supporting records.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS kpi_targets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kpi_code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    target_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    comparison_type VARCHAR(20) NOT NULL DEFAULT 'MINIMUM',
    period_type VARCHAR(20) NOT NULL DEFAULT 'MONTH',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kpi_targets_code (kpi_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    role_id INT UNSIGNED NULL,
    type VARCHAR(40) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message VARCHAR(500) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    dedupe_key VARCHAR(190) NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notifications_dedupe (dedupe_key),
    KEY idx_notifications_user (user_id, read_at, created_at),
    KEY idx_notifications_role (role_id, read_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notifications_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    notification_type VARCHAR(40) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_pref (user_id, notification_type),
    CONSTRAINT fk_notification_pref_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reminders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    remind_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    dedupe_key VARCHAR(190) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reminders_dedupe (dedupe_key),
    KEY idx_reminders_user (user_id, status, remind_at),
    CONSTRAINT fk_reminders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduled_reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_type VARCHAR(40) NOT NULL,
    recipient_user_id INT UNSIGNED NOT NULL,
    frequency VARCHAR(20) NOT NULL DEFAULT 'WEEKLY',
    filters_json JSON NULL,
    last_run_at TIMESTAMP NULL DEFAULT NULL,
    next_run_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_scheduled_reports_next (active, next_run_at),
    CONSTRAINT fk_scheduled_reports_user FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    channel VARCHAR(20) NOT NULL,
    direction VARCHAR(20) NOT NULL DEFAULT 'OUTBOUND',
    subject VARCHAR(180) NOT NULL,
    message_summary VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'LOGGED',
    sent_by INT UNSIGNED NULL,
    sent_at DATETIME NULL,
    external_reference VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communications_customer (customer_id, created_at),
    KEY idx_communications_entity (entity_type, entity_id),
    CONSTRAINT fk_communications_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_communications_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_communications_user FOREIGN KEY (sent_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    trigger_type VARCHAR(40) NOT NULL,
    conditions_json JSON NULL,
    action_type VARCHAR(40) NOT NULL,
    action_config_json JSON NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_automation_rules_trigger (trigger_type, active),
    CONSTRAINT fk_automation_rules_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS automation_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    automation_rule_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL,
    message VARCHAR(255) NULL,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_automation_log_rule (automation_rule_id, executed_at),
    CONSTRAINT fk_automation_log_rule FOREIGN KEY (automation_rule_id) REFERENCES automation_rules (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_backups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    backup_type VARCHAR(20) NOT NULL DEFAULT 'DATABASE',
    filename VARCHAR(180) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'STARTED',
    created_by INT UNSIGNED NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_system_backups_started (started_at),
    CONSTRAINT fk_system_backups_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_events_user (user_id, created_at),
    KEY idx_login_events_created (created_at),
    CONSTRAINT fk_login_events_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sf_quote_follow = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quotes' AND COLUMN_NAME = 'next_follow_up_date'
);
SET @sf_quote_follow_sql = IF(
    @sf_quote_follow = 0,
    'ALTER TABLE quotes ADD COLUMN next_follow_up_date DATE NULL AFTER expiry_date, ADD KEY idx_quotes_follow_up (next_follow_up_date)',
    'SELECT 1'
);
PREPARE sf_quote_follow_stmt FROM @sf_quote_follow_sql;
EXECUTE sf_quote_follow_stmt;
DEALLOCATE PREPARE sf_quote_follow_stmt;

INSERT INTO roles (code, name, description)
SELECT 'MANAGEMENT', 'Management', 'Dashboards, reports, and audit visibility. Not every operational edit.'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE code = 'MANAGEMENT');

INSERT INTO kpi_targets (kpi_code, name, target_value, comparison_type, period_type, active)
SELECT v.kpi_code, v.name, v.target_value, v.comparison_type, 'MONTH', 1 FROM (
    SELECT 'TARGET_GROSS_MARGIN' AS kpi_code, 'Target gross margin %' AS name, 35.0000 AS target_value, 'MINIMUM' AS comparison_type
    UNION ALL SELECT 'TARGET_QUOTE_CONVERSION', 'Target quote count conversion %', 50.0000, 'MINIMUM'
    UNION ALL SELECT 'TARGET_WASTE_RATE', 'Target waste rate %', 10.0000, 'MAXIMUM'
    UNION ALL SELECT 'TARGET_DEBTOR_DAYS', 'Target days to payment', 30.0000, 'MAXIMUM'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM kpi_targets t WHERE t.kpi_code = v.kpi_code);

INSERT INTO automation_rules (name, trigger_type, conditions_json, action_type, action_config_json, active)
SELECT 'Follow up a sent quotation', 'QUOTE_SENT', JSON_OBJECT('days', 3), 'CREATE_REMINDER', JSON_OBJECT('days', 3, 'title', 'Follow up sent quotation'), 1
WHERE NOT EXISTS (
    SELECT 1 FROM automation_rules WHERE trigger_type = 'QUOTE_SENT' AND action_type = 'CREATE_REMINDER'
);

INSERT INTO permissions (code, name, module)
SELECT * FROM (
    SELECT 'reports.executive' AS code, 'View the executive dashboard' AS name, 'reports' AS module
    UNION ALL SELECT 'reports.sales', 'View sales reports', 'reports'
    UNION ALL SELECT 'reports.operations', 'View operations reports', 'reports'
    UNION ALL SELECT 'reports.finance', 'View finance reports', 'reports'
    UNION ALL SELECT 'reports.inventory', 'View inventory reports', 'reports'
    UNION ALL SELECT 'reports.profitability', 'View profitability and labour cost', 'reports'
    UNION ALL SELECT 'reports.export', 'Export reports', 'reports'
    UNION ALL SELECT 'notifications.manage', 'Manage notification preferences', 'admin'
    UNION ALL SELECT 'automations.view', 'View automation rules', 'admin'
    UNION ALL SELECT 'automations.manage', 'Change automation rules', 'admin'
    UNION ALL SELECT 'system.health', 'View system health', 'admin'
    UNION ALL SELECT 'system.backup', 'Create and download backups', 'admin'
    UNION ALL SELECT 'system.logs', 'View application errors', 'admin'
) AS incoming
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.code = incoming.code);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.code IN (
    'dashboard.view', 'customers.view', 'activities.view', 'opportunities.view', 'quotes.view',
    'jobs.view', 'production.view', 'installations.view', 'inventory.view', 'purchasing.view',
    'invoices.view', 'payments.view', 'credit_notes.view', 'statements.view', 'debtors.view',
    'costing.view', 'finance.costing.view', 'finance.vat_report.view',
    'reports.executive', 'reports.sales', 'reports.operations', 'reports.finance',
    'reports.inventory', 'reports.profitability', 'reports.export',
    'audit.view', 'automations.view', 'notifications.manage'
)
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('reports.sales', 'reports.export')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN ('reports.operations')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN ('reports.finance', 'reports.export', 'reports.profitability')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO settings (setting_key, setting_value)
SELECT v.setting_key, v.setting_value FROM (
    SELECT 'quote_expiry_warning_days' AS setting_key, '3' AS setting_value
    UNION ALL SELECT 'invoice_due_soon_days', '3'
    UNION ALL SELECT 'slow_stock_days', '180'
    UNION ALL SELECT 'large_balance_amount', '50000'
    UNION ALL SELECT 'backup_keep_daily', '7'
    UNION ALL SELECT 'backup_keep_weekly', '4'
    UNION ALL SELECT 'backup_keep_monthly', '6'
    UNION ALL SELECT 'app_version', '6.0.0'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = v.setting_key);

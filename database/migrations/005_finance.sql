-- Sign-Forge Management System
-- Phase 5: invoices, payments, credit notes, and debtor balances.
--
-- Apply this once to a database that already has Phases 1 to 4.
-- It does not drop customers, quotations, jobs, stock, or purchase orders.
-- A fresh install uses schema.sql, which already includes these tables.
-- Issued invoices store snapshots. There is no customer.balance column.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS payment_terms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    days_due INT UNSIGNED NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_terms_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sf_pay_term = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'payment_term_id'
);
SET @sf_pay_term_sql = IF(
    @sf_pay_term = 0,
    'ALTER TABLE customers
        ADD COLUMN payment_term_id INT UNSIGNED NULL AFTER notes,
        ADD COLUMN credit_limit DECIMAL(14,2) NULL AFTER payment_term_id,
        ADD COLUMN account_on_hold TINYINT(1) NOT NULL DEFAULT 0 AFTER credit_limit,
        ADD COLUMN account_hold_reason VARCHAR(255) NULL AFTER account_on_hold,
        ADD COLUMN account_hold_by INT UNSIGNED NULL AFTER account_hold_reason,
        ADD COLUMN account_hold_at TIMESTAMP NULL DEFAULT NULL AFTER account_hold_by,
        ADD KEY idx_customers_hold (account_on_hold),
        ADD CONSTRAINT fk_customers_payment_term FOREIGN KEY (payment_term_id) REFERENCES payment_terms (id) ON DELETE SET NULL ON UPDATE CASCADE,
        ADD CONSTRAINT fk_customers_hold_user FOREIGN KEY (account_hold_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE sf_pay_term_stmt FROM @sf_pay_term_sql;
EXECUTE sf_pay_term_stmt;
DEALLOCATE PREPARE sf_pay_term_stmt;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number VARCHAR(40) NULL,
    invoice_type VARCHAR(20) NOT NULL DEFAULT 'STANDARD',
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    quote_id INT UNSIGNED NULL,
    quote_revision_number INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    collection_flag VARCHAR(20) NULL,
    customer_name_snapshot VARCHAR(180) NULL,
    customer_vat_number_snapshot VARCHAR(40) NULL,
    customer_address_snapshot TEXT NULL,
    customer_email_snapshot VARCHAR(190) NULL,
    company_name_snapshot VARCHAR(180) NULL,
    company_vat_number_snapshot VARCHAR(40) NULL,
    company_address_snapshot TEXT NULL,
    bank_name_snapshot VARCHAR(120) NULL,
    account_name_snapshot VARCHAR(120) NULL,
    account_number_snapshot VARCHAR(40) NULL,
    branch_code_snapshot VARCHAR(20) NULL,
    account_type_snapshot VARCHAR(40) NULL,
    payment_term_name_snapshot VARCHAR(80) NULL,
    payment_term_days_snapshot INT UNSIGNED NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_type VARCHAR(20) NOT NULL DEFAULT 'NONE',
    discount_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subtotal_after_discount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_mode VARCHAR(20) NOT NULL DEFAULT 'EXCLUSIVE',
    vat_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    credit_applied DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    balance_due DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customer_po_number VARCHAR(80) NULL,
    customer_notes TEXT NULL,
    internal_notes TEXT NULL,
    terms TEXT NULL,
    over_invoice_reason VARCHAR(255) NULL,
    over_invoice_by INT UNSIGNED NULL,
    over_invoice_at TIMESTAMP NULL DEFAULT NULL,
    issued_at TIMESTAMP NULL DEFAULT NULL,
    issued_by INT UNSIGNED NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_by INT UNSIGNED NULL,
    cancellation_reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_number (invoice_number),
    KEY idx_invoices_customer (customer_id),
    KEY idx_invoices_job (job_id),
    KEY idx_invoices_quote (quote_id),
    KEY idx_invoices_status (status),
    KEY idx_invoices_date (invoice_date),
    KEY idx_invoices_due (due_date),
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_issued_by FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_invoices_override_by FOREIGN KEY (over_invoice_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    source_type VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    source_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_price DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_rate_snapshot DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_invoice_items_invoice (invoice_id, sort_order),
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_status_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NOT NULL,
    changed_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_invoice_history_invoice (invoice_id, created_at),
    CONSTRAINT fk_invoice_history_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_invoice_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_reference VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'EFT',
    external_reference VARCHAR(80) NULL,
    bank_reference VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'RECORDED',
    reversal_reason VARCHAR(255) NULL,
    reversed_by INT UNSIGNED NULL,
    reversed_at TIMESTAMP NULL DEFAULT NULL,
    recorded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payments_reference (payment_reference),
    KEY idx_payments_customer (customer_id),
    KEY idx_payments_date (payment_date),
    CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_payments_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_payments_reversed_by FOREIGN KEY (reversed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payment_allocations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    allocated_by INT UNSIGNED NULL,
    allocated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reversed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_allocations_payment (payment_id),
    KEY idx_allocations_invoice (invoice_id),
    CONSTRAINT fk_allocations_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_allocations_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_allocations_user FOREIGN KEY (allocated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS credit_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    credit_note_number VARCHAR(40) NULL,
    customer_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    credit_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    reason VARCHAR(255) NOT NULL,
    customer_name_snapshot VARCHAR(180) NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_mode VARCHAR(20) NOT NULL DEFAULT 'EXCLUSIVE',
    vat_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    issued_at TIMESTAMP NULL DEFAULT NULL,
    issued_by INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_credit_notes_number (credit_note_number),
    KEY idx_credit_notes_customer (customer_id),
    KEY idx_credit_notes_invoice (invoice_id),
    KEY idx_credit_notes_date (credit_date),
    CONSTRAINT fk_credit_notes_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_credit_notes_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_credit_notes_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_credit_notes_user FOREIGN KEY (issued_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_credit_notes_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS credit_note_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    credit_note_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    unit_price DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_rate_snapshot DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_credit_note_items_note (credit_note_id),
    CONSTRAINT fk_credit_note_items_note FOREIGN KEY (credit_note_id) REFERENCES credit_notes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_variations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    variation_number VARCHAR(40) NOT NULL,
    description VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_mode VARCHAR(20) NOT NULL DEFAULT 'EXCLUSIVE',
    vat_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customer_approved TINYINT(1) NOT NULL DEFAULT 0,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    approved_by_name VARCHAR(120) NULL,
    approval_method VARCHAR(20) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_variations_number (job_id, variation_number),
    KEY idx_job_variations_job (job_id),
    KEY idx_job_variations_status (status),
    CONSTRAINT fk_job_variations_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_variations_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_variation_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_variation_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    unit_price DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_variation_items_variation (job_variation_id),
    CONSTRAINT fk_variation_items_variation FOREIGN KEY (job_variation_id) REFERENCES job_variations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_variation_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO payment_terms (name, days_due, description, active)
SELECT v.name, v.days_due, v.description, 1 FROM (
    SELECT 'COD' AS name, 0 AS days_due, 'Due on presentation' AS description
    UNION ALL SELECT '7 DAYS', 7, 'Due 7 days from the invoice date'
    UNION ALL SELECT '14 DAYS', 14, 'Due 14 days from the invoice date'
    UNION ALL SELECT '30 DAYS', 30, 'Due 30 days from the invoice date'
    UNION ALL SELECT '50% DEPOSIT / BALANCE ON COMPLETION', 0, 'Half before work, the balance when the job is complete'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM payment_terms t WHERE t.name = v.name);

INSERT INTO permissions (code, name, module)
SELECT * FROM (
    SELECT 'invoices.view' AS code, 'View invoices' AS name, 'finance' AS module
    UNION ALL SELECT 'invoices.create', 'Create draft invoices', 'finance'
    UNION ALL SELECT 'invoices.edit_draft', 'Edit draft invoices', 'finance'
    UNION ALL SELECT 'invoices.issue', 'Issue invoices', 'finance'
    UNION ALL SELECT 'invoices.cancel', 'Cancel invoices', 'finance'
    UNION ALL SELECT 'payments.view', 'View payments', 'finance'
    UNION ALL SELECT 'payments.record', 'Record payments', 'finance'
    UNION ALL SELECT 'payments.allocate', 'Allocate payments', 'finance'
    UNION ALL SELECT 'payments.reverse', 'Reverse payments', 'finance'
    UNION ALL SELECT 'credit_notes.view', 'View credit notes', 'finance'
    UNION ALL SELECT 'credit_notes.create', 'Create credit notes', 'finance'
    UNION ALL SELECT 'credit_notes.issue', 'Issue credit notes', 'finance'
    UNION ALL SELECT 'statements.view', 'View statements', 'finance'
    UNION ALL SELECT 'statements.generate', 'Generate statements', 'finance'
    UNION ALL SELECT 'debtors.view', 'View debtor ageing', 'finance'
    UNION ALL SELECT 'finance.costing.view', 'See finance costing beside invoices', 'finance'
    UNION ALL SELECT 'finance.vat_report.view', 'View the operational VAT summary', 'finance'
) AS incoming
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.code = incoming.code);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN (
    'invoices.view', 'invoices.create', 'invoices.edit_draft', 'invoices.issue', 'invoices.cancel',
    'payments.view', 'payments.record', 'payments.allocate', 'payments.reverse',
    'credit_notes.view', 'credit_notes.create', 'credit_notes.issue',
    'statements.view', 'statements.generate', 'debtors.view', 'finance.costing.view', 'finance.vat_report.view'
)
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('invoices.view', 'payments.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO settings (setting_key, setting_value)
SELECT v.setting_key, v.setting_value FROM (
    SELECT 'payment_prefix' AS setting_key, 'SFPAY' AS setting_value
    UNION ALL SELECT 'credit_note_prefix', 'SFCN'
    UNION ALL SELECT 'bank_name', ''
    UNION ALL SELECT 'account_name', ''
    UNION ALL SELECT 'account_number', ''
    UNION ALL SELECT 'branch_code', ''
    UNION ALL SELECT 'account_type', ''
) AS v
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = v.setting_key);

-- Sign-Forge Management System
-- Phase 2: opportunities, quotations, revisions, and the job hand-off.
--
-- Apply this once to a database that already has the Phase 1 tables.
-- It does not drop customers, products, or any other Phase 1 data.
-- A fresh install uses schema.sql, which already includes these tables.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS number_sequences (
    sequence_key VARCHAR(40) NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sequence_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_opportunities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    opportunity_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    estimated_value DECIMAL(14,2) NULL,
    probability_percent DECIMAL(5,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'NEW',
    lost_reason VARCHAR(40) NULL,
    lost_notes TEXT NULL,
    assigned_to INT UNSIGNED NULL,
    expected_close_date DATE NULL,
    next_follow_up_date DATE NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_opportunities_number (opportunity_number),
    KEY idx_opportunities_customer (customer_id),
    KEY idx_opportunities_status (status),
    KEY idx_opportunities_assigned (assigned_to),
    KEY idx_opportunities_follow (next_follow_up_date),
    CONSTRAINT fk_opportunities_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_opportunities_contact
        FOREIGN KEY (contact_id) REFERENCES customer_contacts (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_opportunities_assigned
        FOREIGN KEY (assigned_to) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_opportunities_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_number VARCHAR(40) NOT NULL,
    revision_number INT UNSIGNED NOT NULL DEFAULT 1,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    opportunity_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    quote_date DATE NOT NULL,
    expiry_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    pricing_level_id INT UNSIGNED NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_type VARCHAR(20) NOT NULL DEFAULT 'NONE',
    discount_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subtotal_after_discount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_mode VARCHAR(20) NOT NULL DEFAULT 'EXCLUSIVE',
    vat_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
    vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    deposit_type VARCHAR(20) NOT NULL DEFAULT 'NONE',
    deposit_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    deposit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customer_notes TEXT NULL,
    internal_notes TEXT NULL,
    terms TEXT NULL,
    assigned_to INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    status_changed_at TIMESTAMP NULL DEFAULT NULL,
    status_changed_by INT UNSIGNED NULL,
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    accepted_by_name VARCHAR(120) NULL,
    accepted_by_user_id INT UNSIGNED NULL,
    acceptance_method VARCHAR(20) NULL,
    acceptance_reference VARCHAR(120) NULL,
    acceptance_notes TEXT NULL,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quotes_number (quote_number),
    KEY idx_quotes_customer (customer_id),
    KEY idx_quotes_opportunity (opportunity_id),
    KEY idx_quotes_status (status, expiry_date),
    KEY idx_quotes_assigned (assigned_to),
    KEY idx_quotes_dates (quote_date),
    CONSTRAINT fk_quotes_opportunity
        FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_contact
        FOREIGN KEY (contact_id) REFERENCES customer_contacts (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_level
        FOREIGN KEY (pricing_level_id) REFERENCES pricing_levels (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_assigned
        FOREIGN KEY (assigned_to) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_updated_by
        FOREIGN KEY (updated_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_status_user
        FOREIGN KEY (status_changed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_accepted_user
        FOREIGN KEY (accepted_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_sections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quote_sections_quote (quote_id, sort_order),
    CONSTRAINT fk_quote_sections_quote
        FOREIGN KEY (quote_id) REFERENCES quotes (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    product_id INT UNSIGNED NULL,
    is_custom_item TINYINT(1) NOT NULL DEFAULT 0,
    is_optional TINYINT(1) NOT NULL DEFAULT 0,
    include_optional TINYINT(1) NOT NULL DEFAULT 0,
    product_name_snapshot VARCHAR(180) NOT NULL,
    product_description_snapshot TEXT NULL,
    sku_snapshot VARCHAR(40) NULL,
    product_type_snapshot VARCHAR(20) NULL,
    pricing_method_snapshot VARCHAR(20) NOT NULL,
    width_mm DECIMAL(14,2) NULL,
    height_mm DECIMAL(14,2) NULL,
    length_mm DECIMAL(14,2) NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    actual_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    billable_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    actual_area DECIMAL(14,4) NULL,
    billable_area DECIMAL(14,4) NULL,
    waste_area DECIMAL(14,4) NULL,
    waste_mode VARCHAR(20) NULL,
    standard_waste_percent_snapshot DECIMAL(7,2) NOT NULL DEFAULT 0.00,
    cost_unit_snapshot VARCHAR(20) NULL,
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    base_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    waste_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    pricing_level_id INT UNSIGNED NULL,
    markup_percent_snapshot DECIMAL(7,2) NOT NULL DEFAULT 0.00,
    calculated_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    final_sell_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    unit_sell_price DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    price_overridden TINYINT(1) NOT NULL DEFAULT 0,
    override_reason VARCHAR(255) NULL,
    overridden_by INT UNSIGNED NULL,
    overridden_at TIMESTAMP NULL DEFAULT NULL,
    line_discount_type VARCHAR(20) NOT NULL DEFAULT 'NONE',
    line_discount_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customer_description TEXT NULL,
    internal_description TEXT NULL,
    measure_snapshot JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quote_items_quote (quote_id, sort_order),
    KEY idx_quote_items_product (product_id),
    CONSTRAINT fk_quote_items_quote
        FOREIGN KEY (quote_id) REFERENCES quotes (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_section
        FOREIGN KEY (section_id) REFERENCES quote_sections (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_level
        FOREIGN KEY (pricing_level_id) REFERENCES pricing_levels (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_override_user
        FOREIGN KEY (overridden_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_revisions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    change_summary VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quote_revisions (quote_id, revision_number),
    CONSTRAINT fk_quote_revisions_quote
        FOREIGN KEY (quote_id) REFERENCES quotes (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quote_revisions_user
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_status_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NOT NULL,
    changed_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quote_status_quote (quote_id, created_at),
    CONSTRAINT fk_quote_status_quote
        FOREIGN KEY (quote_id) REFERENCES quotes (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quote_status_user
        FOREIGN KEY (changed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    quote_id INT UNSIGNED NOT NULL,
    quote_revision_number INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'NEW',
    priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    assigned_to INT UNSIGNED NULL,
    target_date DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jobs_number (job_number),
    UNIQUE KEY uq_jobs_quote (quote_id),
    KEY idx_jobs_customer (customer_id),
    KEY idx_jobs_status (status),
    CONSTRAINT fk_jobs_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_quote
        FOREIGN KEY (quote_id) REFERENCES quotes (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_assigned
        FOREIGN KEY (assigned_to) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attachments_entity (entity_type, entity_id),
    CONSTRAINT fk_attachments_user
        FOREIGN KEY (uploaded_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Link a CRM note to the opportunity it was written from. Existing notes stay unlinked.
-- Safe to run again: the column and key are added only when they are missing.
SET @sf_activity_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'crm_activities'
      AND COLUMN_NAME = 'opportunity_id'
);
SET @sf_activity_sql = IF(
    @sf_activity_col = 0,
    'ALTER TABLE crm_activities ADD COLUMN opportunity_id INT UNSIGNED NULL AFTER customer_id, ADD KEY idx_activities_opportunity (opportunity_id), ADD CONSTRAINT fk_activities_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE sf_activity_stmt FROM @sf_activity_sql;
EXECUTE sf_activity_stmt;
DEALLOCATE PREPARE sf_activity_stmt;

INSERT INTO permissions (code, name, module)
SELECT * FROM (
    SELECT 'opportunities.view' AS code, 'View opportunities' AS name, 'sales' AS module
    UNION ALL SELECT 'opportunities.manage', 'Manage opportunities', 'sales'
    UNION ALL SELECT 'quotes.view', 'View quotations', 'sales'
    UNION ALL SELECT 'quotes.manage', 'Build quotations', 'sales'
    UNION ALL SELECT 'quotes.discount', 'Apply quote discounts', 'sales'
    UNION ALL SELECT 'quotes.discount_below_cost', 'Discount a quote below cost', 'sales'
    UNION ALL SELECT 'quotes.price_override', 'Override a calculated selling price', 'sales'
    UNION ALL SELECT 'quotes.accept', 'Record quote acceptance', 'sales'
    UNION ALL SELECT 'quotes.convert', 'Convert an accepted quote to a job', 'sales'
    UNION ALL SELECT 'costing.view', 'See internal quote costing', 'sales'
    UNION ALL SELECT 'jobs.view', 'View job hand-off records', 'operations'
    UNION ALL SELECT 'attachments.manage', 'Upload customer and quote files', 'crm'
) AS incoming
WHERE NOT EXISTS (
    SELECT 1 FROM permissions p WHERE p.code = incoming.code
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'ADMIN'
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'SALES'
  AND p.code IN (
      'opportunities.view', 'opportunities.manage',
      'quotes.view', 'quotes.manage', 'quotes.discount', 'quotes.price_override',
      'quotes.accept', 'quotes.convert', 'costing.view', 'jobs.view', 'attachments.manage'
  )
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'ACCOUNTS'
  AND p.code IN ('quotes.view', 'jobs.view', 'costing.view')
  AND NOT EXISTS (
      SELECT 1 FROM role_permissions rp
      WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO settings (setting_key, setting_value)
SELECT 'opportunity_prefix', 'SFO'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'opportunity_prefix');

INSERT INTO settings (setting_key, setting_value)
SELECT 'default_quote_terms',
       'This quotation is valid until the expiry date. Prices are in the currency shown and exclude VAT unless the quotation says otherwise. Work starts after written acceptance and any deposit shown above. A site measure that differs from the sizes in this quotation may change the price. Artwork supplied by the customer is their responsibility. Goods remain the property of the company until paid in full.'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'default_quote_terms');

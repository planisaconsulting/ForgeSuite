-- Sign-Forge Management System
-- Current schema (Phase 1 foundation plus Phase 2 sales)
--
-- Import this into an EMPTY database, or run database/install.php --force
-- on a development copy. It drops the Sign-Forge tables first.
-- Do not import it over a live database that already holds customers
-- unless you have a backup and a plan to keep the admin password.
--
-- ---------------------------------------------------------------------------
-- What this phase stores
-- ---------------------------------------------------------------------------
-- Staff, roles, and a permission list the application checks before an action.
-- Company settings (currency, VAT, prefixes, timezone). Never hard-code those.
-- Customers, their contacts, and a simple activity log.
-- Suppliers, product categories (one optional parent), and products.
-- A preferred supplier on the product. A later product_suppliers table can
-- add more suppliers without replacing products.supplier_id.
-- Pricing levels. Markup lives here. Selling price = cost x (1 + markup/100).
-- Gross margin is a different number and is calculated, not stored as the rule.
-- Product cost history. A cost change inserts a row. It does not erase the old cost.
-- An audit log of important edits. Passwords are never written here.
--
-- ---------------------------------------------------------------------------
-- What this schema deliberately does not store
-- ---------------------------------------------------------------------------
-- Phase 2 stores opportunities, quotations, revisions, a basic job hand-off,
-- and attachments. It does not store stock movements, recipes, purchase
-- orders, invoices, or payments.
-- products.track_stock is only a flag. There is no stock-on-hand column.
-- Future stock must be a ledger of movements, not a single quantity.
-- A future recipe table can point at products.id. Product types already
-- separate materials, components, services, labour, and consumables.
--
-- ---------------------------------------------------------------------------
-- How material math is defined (the PHP services are the implementation)
-- ---------------------------------------------------------------------------
-- Dimensions are millimetres.
--   area m2  = (width_mm x height_mm / 1000000) x quantity
--   linear m = (length_mm / 1000) x quantity
--
-- ACTUAL measure is what the job needs.
-- BILLABLE measure is what the operator decided to cost. For a roll that may
-- be the printed area (ACTUAL), the full consumed roll width (CONSUMED_WIDTH),
-- or a width/area they typed (MANUAL). For a sheet it may be the piece area,
-- the whole sheet (FULL_SHEET), or a manual figure.
--
-- standard_waste_percent is manufacturing waste. It is applied AFTER the
-- billable measure is chosen. It is not unused roll width.
--   costed quantity = billable x (1 + standard_waste_percent / 100)
--   total cost      = costed quantity x unit cost
--
-- waste_threshold_percent only raises a warning. It never picks a waste mode.
--
-- Money is DECIMAL. Quantities keep four decimal places in storage where a
-- size or a rate needs them. Do not use FLOAT.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS attachments;
DROP TABLE IF EXISTS jobs;
DROP TABLE IF EXISTS quote_status_history;
DROP TABLE IF EXISTS quote_revisions;
DROP TABLE IF EXISTS quote_items;
DROP TABLE IF EXISTS quote_sections;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS sales_opportunities;
DROP TABLE IF EXISTS number_sequences;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS crm_activities;
DROP TABLE IF EXISTS customer_contacts;
DROP TABLE IF EXISTS product_price_history;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS pricing_levels;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS quote_items;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS quote_sequences;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permission codes are checked in PHP (AuthorizationService).
-- ADMIN is also allowed everything in code, so a missing row cannot lock
-- an administrator out. The rows still document what each role may do.
CREATE TABLE permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    module VARCHAR(40) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    KEY idx_users_active (active),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_type ENUM('BUSINESS', 'INDIVIDUAL') NOT NULL DEFAULT 'BUSINESS',
    company_name VARCHAR(180) NULL,
    first_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NULL,
    vat_number VARCHAR(40) NULL,
    registration_number VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    mobile VARCHAR(40) NULL,
    website VARCHAR(190) NULL,
    billing_address TEXT NULL,
    physical_address TEXT NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customers_company (company_name),
    KEY idx_customers_email (email),
    KEY idx_customers_person (last_name, first_name),
    KEY idx_customers_active (active),
    CONSTRAINT fk_customers_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_contacts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    position VARCHAR(80) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    mobile VARCHAR(40) NULL,
    primary_contact TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contacts_customer (customer_id, active),
    CONSTRAINT fk_contacts_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE crm_activities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    opportunity_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    activity_type ENUM(
        'CALL',
        'EMAIL',
        'WHATSAPP',
        'MEETING',
        'SITE_VISIT',
        'NOTE',
        'FOLLOW_UP',
        'OTHER'
    ) NOT NULL DEFAULT 'NOTE',
    subject VARCHAR(180) NOT NULL,
    description TEXT NULL,
    activity_date DATE NOT NULL,
    follow_up_date DATE NULL,
    completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activities_customer (customer_id, activity_date),
    KEY idx_activities_opportunity (opportunity_id),
    KEY idx_activities_follow (follow_up_date, completed),
    CONSTRAINT fk_activities_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_activities_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    contact_name VARCHAR(120) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    website VARCHAR(190) NULL,
    account_number VARCHAR(80) NULL,
    address TEXT NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_suppliers_name (name),
    KEY idx_suppliers_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    KEY idx_categories_parent (parent_id),
    KEY idx_categories_active_sort (active, sort_order),
    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_id) REFERENCES product_categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One catalogue row is a material, a component, a service, labour, or a consumable.
-- cost_price is the buy (or internal) cost for one pricing unit.
-- cost_unit is the matching unit: m2, m, unit, sheet, litre, hour.
-- A later recipe does not need a stock quantity on this row.
CREATE TABLE products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NULL,
    sku VARCHAR(64) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    product_type ENUM('MATERIAL', 'COMPONENT', 'SERVICE', 'LABOUR', 'CONSUMABLE') NOT NULL DEFAULT 'MATERIAL',
    pricing_method ENUM(
        'AREA',
        'LINEAR_METRE',
        'UNIT',
        'SHEET',
        'LITRE',
        'HOUR',
        'CUSTOM'
    ) NOT NULL,
    cost_price DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    cost_unit VARCHAR(16) NOT NULL,
    roll_width_mm DECIMAL(10,2) NULL,
    sheet_width_mm DECIMAL(10,2) NULL,
    sheet_height_mm DECIMAL(10,2) NULL,
    standard_waste_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    waste_threshold_percent DECIMAL(6,2) NULL,
    default_waste_policy ENUM('ACTUAL', 'CONSUMED_WIDTH', 'FULL_SHEET', 'MANUAL') NOT NULL DEFAULT 'ACTUAL',
    allow_rotation TINYINT(1) NOT NULL DEFAULT 0,
    allow_nesting TINYINT(1) NOT NULL DEFAULT 0,
    track_stock TINYINT(1) NOT NULL DEFAULT 0,
    minimum_stock_level DECIMAL(14,4) NULL,
    supplier_code VARCHAR(80) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_name (name),
    KEY idx_products_category (category_id),
    KEY idx_products_supplier (supplier_id),
    KEY idx_products_active (active),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES product_categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_products_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_products_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Written in the same transaction as a cost change. RESTRICT so a product
-- with price history cannot be physically removed by accident.
CREATE TABLE product_price_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    old_cost DECIMAL(14,4) NOT NULL,
    new_cost DECIMAL(14,4) NOT NULL,
    changed_by INT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_price_history_product (product_id, changed_at),
    CONSTRAINT fk_price_history_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_price_history_user
        FOREIGN KEY (changed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pricing_levels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(80) NOT NULL,
    markup_percent DECIMAL(7,2) NOT NULL DEFAULT 0.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pricing_levels_code (code),
    KEY idx_pricing_levels_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 2 sales tables. See database/migrations/002_sales_and_quotes.sql.

CREATE TABLE number_sequences (
    sequence_key VARCHAR(40) NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (sequence_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sales_opportunities (
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

CREATE TABLE quotes (
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

CREATE TABLE quote_sections (
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

CREATE TABLE quote_items (
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

CREATE TABLE quote_revisions (
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

CREATE TABLE quote_status_history (
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

CREATE TABLE jobs (
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

CREATE TABLE attachments (
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


ALTER TABLE crm_activities
    ADD CONSTRAINT fk_activities_opportunity
        FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id)
        ON DELETE SET NULL ON UPDATE CASCADE;


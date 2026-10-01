-- Sign-Forge Management System
-- Current schema (Phases 1 to 5)
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
-- Phases 2 and 3 store opportunities, quotations, jobs, artwork, production,
-- and job costing. Phase 4 stores stock as a ledger of movements. There is
-- no products.stock_quantity column. On hand is the sum of signed movements.
-- Phase 5 stores invoices, payments, credit notes, and job variations.
-- Customer balances are calculated from those documents. There is no
-- editable customer.balance column.
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

DROP TABLE IF EXISTS supplier_price_history;
DROP TABLE IF EXISTS goods_receipt_items;
DROP TABLE IF EXISTS goods_receipts;
DROP TABLE IF EXISTS purchase_requests;
DROP TABLE IF EXISTS stock_count_items;
DROP TABLE IF EXISTS stock_counts;
DROP TABLE IF EXISTS stock_transfers;
DROP TABLE IF EXISTS stock_reservations;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS job_variation_items;
DROP TABLE IF EXISTS job_variations;
DROP TABLE IF EXISTS credit_note_items;
DROP TABLE IF EXISTS credit_notes;
DROP TABLE IF EXISTS payment_allocations;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoice_status_history;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS payment_terms;
DROP TABLE IF EXISTS purchase_order_items;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS supplier_products;
DROP TABLE IF EXISTS stock_locations;
DROP TABLE IF EXISTS installation_checklist_items;
DROP TABLE IF EXISTS installation_checklist_template_items;
DROP TABLE IF EXISTS installation_checklist_templates;
DROP TABLE IF EXISTS job_quality_checks;
DROP TABLE IF EXISTS qc_check_definitions;
DROP TABLE IF EXISTS job_status_history;
DROP TABLE IF EXISTS job_installations;
DROP TABLE IF EXISTS job_other_costs;
DROP TABLE IF EXISTS job_time_entries;
DROP TABLE IF EXISTS job_material_usage;
DROP TABLE IF EXISTS job_material_requirements;
DROP TABLE IF EXISTS artwork_approvals;
DROP TABLE IF EXISTS job_artworks;
DROP TABLE IF EXISTS job_production_stages;
DROP TABLE IF EXISTS job_tasks;
DROP TABLE IF EXISTS job_items;
DROP TABLE IF EXISTS production_route_template_stages;
DROP TABLE IF EXISTS production_route_templates;
DROP TABLE IF EXISTS production_stages;
DROP TABLE IF EXISTS team_members;
DROP TABLE IF EXISTS teams;
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
    hourly_cost DECIMAL(14,2) NULL,
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
    payment_term_id INT UNSIGNED NULL,
    credit_limit DECIMAL(14,2) NULL,
    account_on_hold TINYINT(1) NOT NULL DEFAULT 0,
    account_hold_reason VARCHAR(255) NULL,
    account_hold_by INT UNSIGNED NULL,
    account_hold_at TIMESTAMP NULL DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customers_company (company_name),
    KEY idx_customers_email (email),
    KEY idx_customers_person (last_name, first_name),
    KEY idx_customers_active (active),
    KEY idx_customers_hold (account_on_hold),
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
    is_subcontractor TINYINT(1) NOT NULL DEFAULT 0,
    capabilities VARCHAR(255) NULL,
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
    product_type ENUM('MATERIAL', 'COMPONENT', 'SERVICE', 'LABOUR', 'CONSUMABLE', 'FINISHED_PRODUCT') NOT NULL DEFAULT 'MATERIAL',
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
    inventory_method VARCHAR(20) NOT NULL DEFAULT 'NONE',
    minimum_stock_level DECIMAL(14,4) NULL,
    reorder_level DECIMAL(14,4) NULL,
    preferred_order_quantity DECIMAL(14,4) NULL,
    costing_method VARCHAR(30) NOT NULL DEFAULT 'LAST_COST',
    average_cost DECIMAL(14,4) NULL,
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
    next_follow_up_date DATE NULL,
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
    KEY idx_quotes_follow_up (next_follow_up_date),
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
    opportunity_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'NEW',
    priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    assigned_to INT UNSIGNED NULL,
    project_manager_id INT UNSIGNED NULL,
    target_date DATE NULL,
    original_target_date DATE NULL,
    customer_promised_date DATE NULL,
    production_due_date DATE NULL,
    installation_date DATE NULL,
    delivery_method VARCHAR(20) NOT NULL DEFAULT 'INSTALLATION',
    site_address TEXT NULL,
    site_contact_name VARCHAR(120) NULL,
    site_contact_phone VARCHAR(40) NULL,
    customer_po_number VARCHAR(80) NULL,
    quoted_revenue_snapshot DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    quoted_cost_snapshot DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_material_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_labour_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_other_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customer_notes TEXT NULL,
    production_notes TEXT NULL,
    installation_notes TEXT NULL,
    internal_notes TEXT NULL,
    artwork_override_by INT UNSIGNED NULL,
    artwork_override_reason VARCHAR(255) NULL,
    artwork_override_at TIMESTAMP NULL DEFAULT NULL,
    completion_override_by INT UNSIGNED NULL,
    completion_override_reason VARCHAR(255) NULL,
    completion_override_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    completed_by INT UNSIGNED NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jobs_number (job_number),
    UNIQUE KEY uq_jobs_quote (quote_id),
    KEY idx_jobs_customer (customer_id),
    KEY idx_jobs_status (status),
    KEY idx_jobs_target (target_date),
    KEY idx_jobs_assigned (assigned_to),
    KEY idx_jobs_install (installation_date),
    KEY idx_jobs_opportunity (opportunity_id),
    CONSTRAINT fk_jobs_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_quote
        FOREIGN KEY (quote_id) REFERENCES quotes (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_opportunity
        FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_assigned
        FOREIGN KEY (assigned_to) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_manager
        FOREIGN KEY (project_manager_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_artwork_override
        FOREIGN KEY (artwork_override_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_completion_override
        FOREIGN KEY (completion_override_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_jobs_completed_by
        FOREIGN KEY (completed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    purpose VARCHAR(40) NOT NULL DEFAULT 'GENERAL',
    visibility VARCHAR(30) NOT NULL DEFAULT 'INTERNAL',
    photo_tag VARCHAR(40) NULL,
    measurement_id INT UNSIGNED NULL,
    portal_user_id INT UNSIGNED NULL,
    annotation_json JSON NULL,
    notes VARCHAR(255) NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attachments_entity (entity_type, entity_id),
    CONSTRAINT fk_attachments_user
        FOREIGN KEY (uploaded_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE teams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    team_type VARCHAR(30) NOT NULL DEFAULT 'GENERAL',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teams_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE team_members (
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (team_id, user_id),
    KEY idx_team_members_user (user_id),
    CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_team_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_stages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_stages_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_route_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_route_templates_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_route_template_stages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    production_stage_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_template_stage (template_id, production_stage_id),
    KEY idx_template_stages_sort (template_id, sort_order),
    CONSTRAINT fk_template_stages_template FOREIGN KEY (template_id) REFERENCES production_route_templates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_template_stages_stage FOREIGN KEY (production_stage_id) REFERENCES production_stages (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    quote_item_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    product_id INT UNSIGNED NULL,
    description TEXT NULL,
    internal_description TEXT NULL,
    width_mm DECIMAL(14,2) NULL,
    height_mm DECIMAL(14,2) NULL,
    length_mm DECIMAL(14,2) NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    production_status VARCHAR(20) NOT NULL DEFAULT 'NOT_STARTED',
    artwork_required TINYINT(1) NOT NULL DEFAULT 1,
    installation_required TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_items_job (job_id, sort_order),
    KEY idx_job_items_quote_item (quote_item_id),
    CONSTRAINT fk_job_items_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_items_quote_item FOREIGN KEY (quote_item_id) REFERENCES quote_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    task_type VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    assigned_to INT UNSIGNED NULL,
    assigned_team_id INT UNSIGNED NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    status VARCHAR(20) NOT NULL DEFAULT 'TODO',
    due_date DATE NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    estimated_minutes INT UNSIGNED NULL,
    actual_minutes INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_tasks_job (job_id, sort_order),
    KEY idx_job_tasks_assigned (assigned_to),
    KEY idx_job_tasks_status (status),
    KEY idx_job_tasks_due (due_date),
    CONSTRAINT fk_job_tasks_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_tasks_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_tasks_user FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_tasks_team FOREIGN KEY (assigned_team_id) REFERENCES teams (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_tasks_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_production_stages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    production_stage_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'NOT_STARTED',
    assigned_to INT UNSIGNED NULL,
    required_resource_type VARCHAR(30) NULL,
    estimated_minutes INT UNSIGNED NULL,
    actual_minutes INT UNSIGNED NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_stages_job (job_id, sort_order),
    KEY idx_job_stages_status (status),
    CONSTRAINT fk_job_stages_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_stages_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_stages_stage FOREIGN KEY (production_stage_id) REFERENCES production_stages (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_job_stages_user FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_artworks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    revision_number INT UNSIGNED NOT NULL DEFAULT 1,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'DRAFT',
    uploaded_by INT UNSIGNED NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    customer_approved TINYINT(1) NOT NULL DEFAULT 0,
    customer_approved_at TIMESTAMP NULL DEFAULT NULL,
    customer_approved_by VARCHAR(120) NULL,
    notes TEXT NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_artworks_job (job_id, title, revision_number),
    CONSTRAINT fk_job_artworks_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_artworks_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_artworks_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_approvals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_artwork_id INT UNSIGNED NOT NULL,
    approval_status VARCHAR(40) NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    approval_method VARCHAR(20) NOT NULL,
    reference VARCHAR(120) NULL,
    notes TEXT NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    recorded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_artwork_approvals_art (job_artwork_id),
    CONSTRAINT fk_artwork_approvals_art FOREIGN KEY (job_artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_approvals_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_material_requirements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    required_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    calculated_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    manual_adjustment DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    final_required_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    purchase_quantity DECIMAL(14,4) NULL,
    pack_size DECIMAL(14,4) NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_requirements_job (job_id),
    CONSTRAINT fk_job_requirements_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_requirements_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_requirements_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_requirements_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_material_usage (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    usage_type VARCHAR(20) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reason VARCHAR(40) NULL,
    notes VARCHAR(255) NULL,
    recorded_by INT UNSIGNED NULL,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_usage_job (job_id),
    KEY idx_job_usage_product (product_id),
    CONSTRAINT fk_job_usage_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_usage_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_usage_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_usage_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_time_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    task_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    work_type VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    started_at TIMESTAMP NULL DEFAULT NULL,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    minutes INT UNSIGNED NOT NULL DEFAULT 0,
    hourly_cost_snapshot DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_time_job (job_id),
    KEY idx_job_time_user (user_id),
    KEY idx_job_time_open (user_id, ended_at),
    CONSTRAINT fk_job_time_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_time_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_time_task FOREIGN KEY (task_id) REFERENCES job_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_time_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_other_costs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    cost_type VARCHAR(40) NOT NULL,
    description VARCHAR(180) NOT NULL,
    supplier_id INT UNSIGNED NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reference VARCHAR(80) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_other_job (job_id),
    CONSTRAINT fk_job_other_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_other_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_other_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_installations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    scheduled_date DATE NULL,
    scheduled_start_time TIME NULL,
    estimated_duration_minutes INT UNSIGNED NULL,
    site_address TEXT NULL,
    site_contact_name VARCHAR(120) NULL,
    site_contact_phone VARCHAR(40) NULL,
    assigned_team_id INT UNSIGNED NULL,
    assigned_user_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'NOT_SCHEDULED',
    arrival_time TIMESTAMP NULL DEFAULT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    installation_notes TEXT NULL,
    completion_notes TEXT NULL,
    customer_signoff_name VARCHAR(120) NULL,
    signoff_date DATE NULL,
    signoff_notes VARCHAR(255) NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_installations_job (job_id),
    KEY idx_job_installations_date (scheduled_date),
    KEY idx_job_installations_status (status),
    CONSTRAINT fk_job_installations_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_installations_team FOREIGN KEY (assigned_team_id) REFERENCES teams (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_installations_user FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_installations_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installation_checklist_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installation_checklist_template_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    label VARCHAR(180) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_install_template_items (template_id, sort_order),
    CONSTRAINT fk_install_template_items FOREIGN KEY (template_id) REFERENCES installation_checklist_templates (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installation_checklist_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    installation_id INT UNSIGNED NOT NULL,
    label VARCHAR(180) NOT NULL,
    checked TINYINT(1) NOT NULL DEFAULT 0,
    checked_by INT UNSIGNED NULL,
    checked_at TIMESTAMP NULL DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_install_checks (installation_id, sort_order),
    CONSTRAINT fk_install_checks_installation FOREIGN KEY (installation_id) REFERENCES job_installations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_install_checks_user FOREIGN KEY (checked_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qc_check_definitions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_qc_definitions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_quality_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    check_type VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL,
    notes VARCHAR(255) NULL,
    rework_task_id INT UNSIGNED NULL,
    checked_by INT UNSIGNED NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_qc_job (job_id),
    CONSTRAINT fk_job_qc_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_qc_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_qc_task FOREIGN KEY (rework_task_id) REFERENCES job_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_qc_user FOREIGN KEY (checked_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_status_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    changed_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_status_job (job_id, created_at),
    CONSTRAINT fk_job_status_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_status_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE crm_activities
    ADD CONSTRAINT fk_activities_opportunity
        FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Phase 4 inventory and purchasing. Movements are the stock balance.
-- purchase_order foreign keys are added after both sides exist.

CREATE TABLE stock_locations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stock_locations_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    inventory_type VARCHAR(20) NOT NULL,
    inventory_code VARCHAR(40) NOT NULL,
    supplier_id INT UNSIGNED NULL,
    purchase_order_item_id INT UNSIGNED NULL,
    source_inventory_item_id INT UNSIGNED NULL,
    source_job_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'AVAILABLE',
    received_date DATE NULL,
    expiry_date DATE NULL,
    original_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    remaining_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    acquisition_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    valuation_treatment VARCHAR(20) NULL,
    width_mm DECIMAL(10,2) NULL,
    length_mm DECIMAL(12,2) NULL,
    height_mm DECIMAL(10,2) NULL,
    batch_number VARCHAR(80) NULL,
    supplier_reference VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inventory_items_code (inventory_code),
    KEY idx_inventory_items_product (product_id),
    KEY idx_inventory_items_location (stock_location_id),
    KEY idx_inventory_items_status (status),
    KEY idx_inventory_items_type (inventory_type, status),
    CONSTRAINT fk_inventory_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_items_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_items_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_items_source_item FOREIGN KEY (source_inventory_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_inventory_items_source_job FOREIGN KEY (source_job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    movement_type VARCHAR(40) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    reference_type VARCHAR(40) NULL,
    reference_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    purchase_order_id INT UNSIGNED NULL,
    job_material_usage_id INT UNSIGNED NULL,
    reason VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    movement_date DATE NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_movements_product (product_id),
    KEY idx_stock_movements_location (stock_location_id),
    KEY idx_stock_movements_job (job_id),
    KEY idx_stock_movements_date (movement_date),
    KEY idx_stock_movements_item (inventory_item_id),
    KEY idx_stock_movements_po (purchase_order_id),
    KEY idx_stock_movements_usage (job_material_usage_id),
    CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_usage FOREIGN KEY (job_material_usage_id) REFERENCES job_material_usage (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_movements_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_reservations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_material_requirement_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'RESERVED',
    reserved_by INT UNSIGNED NULL,
    reserved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at TIMESTAMP NULL DEFAULT NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_stock_reservations_job (job_id),
    KEY idx_stock_reservations_product (product_id),
    KEY idx_stock_reservations_status (status),
    KEY idx_stock_reservations_item (inventory_item_id),
    CONSTRAINT fk_stock_reservations_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_requirement FOREIGN KEY (job_material_requirement_id) REFERENCES job_material_requirements (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_reservations_user FOREIGN KEY (reserved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_transfers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    from_location_id INT UNSIGNED NOT NULL,
    to_location_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    reason VARCHAR(80) NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_transfers_product (product_id),
    CONSTRAINT fk_stock_transfers_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_transfers_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_transfers_from FOREIGN KEY (from_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_transfers_to FOREIGN KEY (to_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_transfers_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_counts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference_code VARCHAR(40) NOT NULL,
    stock_location_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stock_counts_code (reference_code),
    KEY idx_stock_counts_status (status),
    CONSTRAINT fk_stock_counts_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_stock_counts_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_stock_counts_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_count_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stock_count_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NULL,
    stock_location_id INT UNSIGNED NOT NULL,
    system_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    physical_quantity DECIMAL(14,4) NULL,
    unit VARCHAR(20) NOT NULL,
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_stock_count_items_count (stock_count_id),
    KEY idx_stock_count_items_product (product_id),
    CONSTRAINT fk_stock_count_items_count FOREIGN KEY (stock_count_id) REFERENCES stock_counts (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stock_count_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_count_items_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_count_items_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    supplier_sku VARCHAR(80) NULL,
    supplier_description VARCHAR(180) NULL,
    cost_price DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    minimum_order_quantity DECIMAL(14,4) NULL,
    lead_time_days INT UNSIGNED NULL,
    preferred_supplier TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_price_update TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supplier_products (supplier_id, product_id),
    KEY idx_supplier_products_product (product_id),
    KEY idx_supplier_products_supplier (supplier_id),
    CONSTRAINT fk_supplier_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_supplier_products_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_price_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_product_id INT UNSIGNED NOT NULL,
    old_price DECIMAL(14,4) NOT NULL,
    new_price DECIMAL(14,4) NOT NULL,
    effective_date DATE NOT NULL,
    changed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_supplier_price_history_product (supplier_product_id, created_at),
    CONSTRAINT fk_supplier_price_history_row FOREIGN KEY (supplier_product_id) REFERENCES supplier_products (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_supplier_price_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    po_number VARCHAR(40) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    expected_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    supplier_reference VARCHAR(80) NULL,
    notes TEXT NULL,
    internal_notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_purchase_orders_number (po_number),
    KEY idx_purchase_orders_supplier (supplier_id),
    KEY idx_purchase_orders_status (status),
    KEY idx_purchase_orders_date (order_date),
    CONSTRAINT fk_purchase_orders_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_orders_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_orders_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    supplier_product_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    ordered_quantity DECIMAL(14,4) NOT NULL,
    received_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(20) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_date DATE NULL,
    job_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_po_items_po (purchase_order_id),
    KEY idx_po_items_product (product_id),
    KEY idx_po_items_job (job_id),
    CONSTRAINT fk_po_items_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_po_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_po_items_supplier_product FOREIGN KEY (supplier_product_id) REFERENCES supplier_products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_po_items_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE goods_receipts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    grn_number VARCHAR(40) NOT NULL,
    purchase_order_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    received_date DATE NOT NULL,
    supplier_delivery_note VARCHAR(80) NULL,
    supplier_invoice_number VARCHAR(80) NULL,
    received_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_goods_receipts_number (grn_number),
    KEY idx_goods_receipts_po (purchase_order_id),
    CONSTRAINT fk_goods_receipts_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_goods_receipts_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_goods_receipts_user FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE goods_receipt_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    goods_receipt_id INT UNSIGNED NOT NULL,
    purchase_order_item_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity_received DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    stock_location_id INT UNSIGNED NOT NULL,
    width_mm DECIMAL(10,2) NULL,
    length_mm DECIMAL(12,2) NULL,
    track_each TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_grn_items_receipt (goods_receipt_id),
    KEY idx_grn_items_po_item (purchase_order_item_id),
    CONSTRAINT fk_grn_items_receipt FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_grn_items_po_item FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_grn_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_grn_items_location FOREIGN KEY (stock_location_id) REFERENCES stock_locations (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NULL,
    requested_by INT UNSIGNED NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL,
    required_by DATE NULL,
    reason VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'REQUESTED',
    approved_by INT UNSIGNED NULL,
    purchase_order_id INT UNSIGNED NULL,
    purchase_order_item_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_purchase_requests_status (status),
    KEY idx_purchase_requests_job (job_id),
    KEY idx_purchase_requests_product (product_id),
    CONSTRAINT fk_purchase_requests_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_approver FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_purchase_requests_po_item FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE stock_movements
    ADD CONSTRAINT fk_stock_movements_po
        FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id)
        ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE inventory_items
    ADD CONSTRAINT fk_inventory_items_po_item
        FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Phase 5 finance documents.
CREATE TABLE payment_terms (
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

CREATE TABLE invoices (
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

CREATE TABLE invoice_items (
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

CREATE TABLE invoice_status_history (
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

CREATE TABLE payments (
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

CREATE TABLE payment_allocations (
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

CREATE TABLE credit_notes (
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

CREATE TABLE credit_note_items (
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

CREATE TABLE job_variations (
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

CREATE TABLE job_variation_items (
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


ALTER TABLE customers
    ADD CONSTRAINT fk_customers_payment_term FOREIGN KEY (payment_term_id) REFERENCES payment_terms (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_customers_hold_user FOREIGN KEY (account_hold_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Phase 6 supporting tables. Reports still read the operational tables above.

CREATE TABLE kpi_targets (
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

CREATE TABLE notifications (
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

CREATE TABLE notification_preferences (
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

CREATE TABLE reminders (
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

CREATE TABLE scheduled_reports (
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

CREATE TABLE communications (
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

CREATE TABLE automation_rules (
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

CREATE TABLE automation_log (
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

CREATE TABLE system_backups (
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

CREATE TABLE login_events (
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

-- Phase 7: recipes, site surveys, and the customer portal.

CREATE TABLE recipe_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipe_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    category_id INT UNSIGNED NULL,
    finished_product_id INT UNSIGNED NULL,
    recipe_type VARCHAR(20) NOT NULL DEFAULT 'SIGNAGE',
    pricing_method VARCHAR(20) NOT NULL DEFAULT 'MARKUP',
    production_route_template_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipes_code (code),
    KEY idx_recipes_active (active, name),
    CONSTRAINT fk_recipes_category FOREIGN KEY (category_id) REFERENCES recipe_categories (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recipes_product FOREIGN KEY (finished_product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recipes_route FOREIGN KEY (production_route_template_id) REFERENCES production_route_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recipes_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipe_inputs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    label VARCHAR(120) NOT NULL,
    input_type VARCHAR(20) NOT NULL DEFAULT 'NUMBER',
    unit VARCHAR(20) NULL,
    required TINYINT(1) NOT NULL DEFAULT 1,
    default_value VARCHAR(120) NULL,
    min_value VARCHAR(40) NULL,
    max_value VARCHAR(40) NULL,
    options_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipe_inputs_code (recipe_id, code),
    CONSTRAINT fk_recipe_inputs_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipe_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    component_type VARCHAR(20) NOT NULL DEFAULT 'MATERIAL',
    product_id INT UNSIGNED NULL,
    nested_recipe_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    quantity_formula VARCHAR(255) NOT NULL,
    waste_percent_override DECIMAL(7,2) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    cost_calculation_method VARCHAR(20) NOT NULL DEFAULT 'PRODUCT',
    rounding_rule VARCHAR(10) NOT NULL DEFAULT 'NONE',
    pack_size DECIMAL(14,4) NULL,
    yield_mode VARCHAR(10) NOT NULL DEFAULT 'NONE',
    condition_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    optional TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recipe_items_recipe (recipe_id, sort_order),
    CONSTRAINT fk_recipe_items_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_recipe_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_recipe_items_nested FOREIGN KEY (nested_recipe_id) REFERENCES recipes (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recipe_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipe_versions (recipe_id, version_number),
    CONSTRAINT fk_recipe_versions_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_recipe_versions_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE signage_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(180) NOT NULL,
    category VARCHAR(80) NULL,
    finished_product_id INT UNSIGNED NULL,
    recipe_id INT UNSIGNED NULL,
    default_inputs_json JSON NULL,
    customer_description TEXT NULL,
    internal_description TEXT NULL,
    notes TEXT NULL,
    production_route_template_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_signage_templates_code (code),
    CONSTRAINT fk_signage_templates_product FOREIGN KEY (finished_product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_signage_templates_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_signage_templates_route FOREIGN KEY (production_route_template_id) REFERENCES production_route_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_signage_templates_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_recipe_snapshots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_item_id INT UNSIGNED NOT NULL,
    recipe_id INT UNSIGNED NULL,
    recipe_version INT UNSIGNED NOT NULL,
    input_snapshot_json JSON NOT NULL,
    component_snapshot_json JSON NOT NULL,
    cost_snapshot_json JSON NOT NULL,
    production_route_snapshot_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quote_recipe_item (quote_item_id),
    CONSTRAINT fk_quote_recipe_item FOREIGN KEY (quote_item_id) REFERENCES quote_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quote_recipe_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_expected_labour (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    expected_minutes DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    hourly_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    source VARCHAR(20) NOT NULL DEFAULT 'RECIPE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expected_labour_job (job_id),
    CONSTRAINT fk_expected_labour_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_expected_labour_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_surveys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    quote_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    site_name VARCHAR(180) NOT NULL,
    address_line_1 VARCHAR(180) NULL,
    address_line_2 VARCHAR(180) NULL,
    city VARCHAR(80) NULL,
    province VARCHAR(80) NULL,
    postal_code VARCHAR(20) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    site_contact_name VARCHAR(120) NULL,
    site_contact_phone VARCHAR(40) NULL,
    survey_date DATE NULL,
    surveyed_by INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    environment VARCHAR(20) NULL,
    surface_type VARCHAR(80) NULL,
    mounting_height_mm DECIMAL(10,2) NULL,
    access_difficulty VARCHAR(40) NULL,
    ladder_required TINYINT(1) NOT NULL DEFAULT 0,
    scaffolding_required TINYINT(1) NOT NULL DEFAULT 0,
    cherry_picker_required TINYINT(1) NOT NULL DEFAULT 0,
    electrical_supply TINYINT(1) NOT NULL DEFAULT 0,
    power_location VARCHAR(180) NULL,
    height_notes TEXT NULL,
    traffic_notes TEXT NULL,
    special_access TEXT NULL,
    access_notes TEXT NULL,
    installation_notes TEXT NULL,
    electrical_notes TEXT NULL,
    general_notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_surveys_number (survey_number),
    KEY idx_site_surveys_customer (customer_id),
    KEY idx_site_surveys_status (status),
    CONSTRAINT fk_site_surveys_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_user FOREIGN KEY (surveyed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_survey_measurements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_survey_id INT UNSIGNED NOT NULL,
    reference VARCHAR(80) NOT NULL,
    measurement_type VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    width_mm DECIMAL(12,2) NULL,
    height_mm DECIMAL(12,2) NULL,
    depth_mm DECIMAL(12,2) NULL,
    length_mm DECIMAL(12,2) NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    description VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_survey_measurements_survey (site_survey_id),
    CONSTRAINT fk_survey_measurements_survey FOREIGN KEY (site_survey_id) REFERENCES site_surveys (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    customer_contact_id INT UNSIGNED NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_portal_users_email (email),
    KEY idx_portal_users_customer (customer_id),
    CONSTRAINT fk_portal_users_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_users_contact FOREIGN KEY (customer_contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_access_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NOT NULL,
    purpose VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_portal_tokens_hash (token_hash),
    KEY idx_portal_tokens_customer (customer_id, purpose),
    CONSTRAINT fk_portal_tokens_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_tokens_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_actions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NULL,
    statement_version VARCHAR(20) NULL,
    statement_text TEXT NULL,
    ip_address VARCHAR(45) NULL,
    payload_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_portal_actions_entity (entity_type, entity_id),
    CONSTRAINT fk_portal_actions_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_actions_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    portal_user_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    event VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_portal_audit_customer (customer_id, created_at),
    CONSTRAINT fk_portal_audit_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_rate_limits (
    bucket VARCHAR(190) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    PRIMARY KEY (bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_portal_messages_entity (entity_type, entity_id),
    CONSTRAINT fk_portal_messages_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_messages_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_status_map (
    internal_status VARCHAR(40) NOT NULL,
    customer_label VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (internal_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_templates_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE attachments
    ADD CONSTRAINT fk_attachments_measurement FOREIGN KEY (measurement_id) REFERENCES site_survey_measurements (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_attachments_portal_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE;

-- Phase 8 resource planning and scheduling.

CREATE TABLE resources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_type VARCHAR(30) NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    capacity_type VARCHAR(20) NOT NULL DEFAULT 'MINUTES',
    default_daily_capacity DECIMAL(12,2) NOT NULL DEFAULT 480.00,
    concurrent_capacity INT UNSIGNED NOT NULL DEFAULT 1,
    internal_hourly_cost DECIMAL(14,4) NULL,
    internal_cost_per_km DECIMAL(14,4) NULL,
    linked_user_id INT UNSIGNED NULL,
    linked_team_id INT UNSIGNED NULL,
    linked_supplier_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'AVAILABLE',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resources_code (code),
    KEY idx_resources_type (resource_type, active),
    KEY idx_resources_user (linked_user_id),
    KEY idx_resources_team (linked_team_id),
    KEY idx_resources_supplier (linked_supplier_id),
    CONSTRAINT fk_resources_user FOREIGN KEY (linked_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_resources_team FOREIGN KEY (linked_team_id) REFERENCES teams (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_resources_supplier FOREIGN KEY (linked_supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE work_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    monday_start TIME NULL,
    monday_end TIME NULL,
    tuesday_start TIME NULL,
    tuesday_end TIME NULL,
    wednesday_start TIME NULL,
    wednesday_end TIME NULL,
    thursday_start TIME NULL,
    thursday_end TIME NULL,
    friday_start TIME NULL,
    friday_end TIME NULL,
    saturday_start TIME NULL,
    saturday_end TIME NULL,
    sunday_start TIME NULL,
    sunday_end TIME NULL,
    break_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_work_schedules_default (is_default, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resource_work_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    work_schedule_id INT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resource_schedules_resource (resource_id, effective_from),
    CONSTRAINT fk_resource_schedules_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_resource_schedules_schedule FOREIGN KEY (work_schedule_id) REFERENCES work_schedules (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resource_unavailability (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    reason_type VARCHAR(30) NOT NULL,
    description VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_unavailability_resource (resource_id),
    KEY idx_unavailability_start (start_datetime),
    KEY idx_unavailability_end (end_datetime),
    CONSTRAINT fk_unavailability_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_unavailability_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_exceptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exception_date DATE NOT NULL,
    name VARCHAR(180) NOT NULL,
    exception_type VARCHAR(30) NOT NULL DEFAULT 'PUBLIC_HOLIDAY',
    working_day_override TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calendar_exceptions_date (exception_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedule_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(30) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    resource_id INT UNSIGNED NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    estimated_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
    locked TINYINT(1) NOT NULL DEFAULT 0,
    customer_visible TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    override_reason VARCHAR(255) NULL,
    override_by INT UNSIGNED NULL,
    override_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_schedule_resource (resource_id),
    KEY idx_schedule_start (start_datetime),
    KEY idx_schedule_end (end_datetime),
    KEY idx_schedule_job (job_id),
    KEY idx_schedule_status (status),
    KEY idx_schedule_entity (entity_type, entity_id),
    CONSTRAINT fk_schedule_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_override_by FOREIGN KEY (override_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedule_entry_resources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_entry_id INT UNSIGNED NOT NULL,
    resource_id INT UNSIGNED NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'ASSIGNED',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_entry_resource (schedule_entry_id, resource_id),
    KEY idx_schedule_entry_resources_resource (resource_id),
    CONSTRAINT fk_schedule_entry_resources_entry FOREIGN KEY (schedule_entry_id) REFERENCES schedule_entries (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_entry_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE schedule_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_entry_id INT UNSIGNED NOT NULL,
    old_start DATETIME NULL,
    old_end DATETIME NULL,
    new_start DATETIME NULL,
    new_end DATETIME NULL,
    changed_by INT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_schedule_history_entry (schedule_entry_id, created_at),
    CONSTRAINT fk_schedule_history_entry FOREIGN KEY (schedule_entry_id) REFERENCES schedule_entries (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_dependencies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    predecessor_task_id INT UNSIGNED NULL,
    successor_task_id INT UNSIGNED NULL,
    predecessor_stage_id INT UNSIGNED NULL,
    successor_stage_id INT UNSIGNED NULL,
    dependency_type VARCHAR(30) NOT NULL DEFAULT 'FINISH_TO_START',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dependencies_pred_task (predecessor_task_id),
    KEY idx_dependencies_succ_task (successor_task_id),
    KEY idx_dependencies_pred_stage (predecessor_stage_id),
    KEY idx_dependencies_succ_stage (successor_stage_id),
    CONSTRAINT fk_dependencies_pred_task FOREIGN KEY (predecessor_task_id) REFERENCES job_tasks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dependencies_succ_task FOREIGN KEY (successor_task_id) REFERENCES job_tasks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dependencies_pred_stage FOREIGN KEY (predecessor_stage_id) REFERENCES job_production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dependencies_succ_stage FOREIGN KEY (successor_stage_id) REFERENCES job_production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stage_resource_requirements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_production_stage_id INT UNSIGNED NULL,
    production_stage_id INT UNSIGNED NULL,
    resource_type VARCHAR(30) NOT NULL,
    resource_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stage_requirements_job_stage (job_production_stage_id),
    CONSTRAINT fk_stage_requirements_job_stage FOREIGN KEY (job_production_stage_id) REFERENCES job_production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stage_requirements_stage FOREIGN KEY (production_stage_id) REFERENCES production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stage_requirements_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE machine_details (
    resource_id INT UNSIGNED NOT NULL,
    manufacturer VARCHAR(120) NULL,
    model VARCHAR(120) NULL,
    serial_number VARCHAR(120) NULL,
    purchase_date DATE NULL,
    service_interval_days INT UNSIGNED NULL,
    service_interval_hours DECIMAL(12,2) NULL,
    last_service_date DATE NULL,
    next_service_date DATE NULL,
    meter_hours DECIMAL(12,2) NULL,
    notes TEXT NULL,
    PRIMARY KEY (resource_id),
    CONSTRAINT fk_machine_details_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance_records (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    maintenance_type VARCHAR(30) NOT NULL,
    scheduled_date DATE NULL,
    completed_date DATE NULL,
    downtime_start DATETIME NULL,
    downtime_end DATETIME NULL,
    description VARCHAR(255) NOT NULL,
    supplier_id INT UNSIGNED NULL,
    cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    meter_reading DECIMAL(14,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_maintenance_resource (resource_id),
    KEY idx_maintenance_scheduled (scheduled_date),
    CONSTRAINT fk_maintenance_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_maintenance_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_maintenance_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE resource_downtime (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    maintenance_record_id INT UNSIGNED NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_downtime_resource (resource_id, started_at),
    CONSTRAINT fk_downtime_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_downtime_maintenance FOREIGN KEY (maintenance_record_id) REFERENCES maintenance_records (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_downtime_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_details (
    resource_id INT UNSIGNED NOT NULL,
    registration_number VARCHAR(40) NOT NULL,
    make VARCHAR(80) NULL,
    model VARCHAR(80) NULL,
    year SMALLINT UNSIGNED NULL,
    vin VARCHAR(40) NULL,
    odometer DECIMAL(12,1) NOT NULL DEFAULT 0.0,
    service_interval_km DECIMAL(12,1) NULL,
    service_interval_days INT UNSIGNED NULL,
    last_service_date DATE NULL,
    last_service_odometer DECIMAL(12,1) NULL,
    next_service_date DATE NULL,
    next_service_odometer DECIMAL(12,1) NULL,
    licence_expiry DATE NULL,
    notes TEXT NULL,
    PRIMARY KEY (resource_id),
    UNIQUE KEY uq_vehicle_registration (registration_number),
    CONSTRAINT fk_vehicle_details_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_usage (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_resource_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    start_odometer DECIMAL(12,1) NOT NULL,
    end_odometer DECIMAL(12,1) NOT NULL,
    distance_km DECIMAL(12,1) NOT NULL,
    rate_per_km_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    travel_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    other_cost_id INT UNSIGNED NULL,
    usage_date DATE NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vehicle_usage_vehicle (vehicle_resource_id),
    KEY idx_vehicle_usage_date (usage_date),
    KEY idx_vehicle_usage_job (job_id),
    CONSTRAINT fk_vehicle_usage_vehicle FOREIGN KEY (vehicle_resource_id) REFERENCES resources (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vehicle_usage_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_vehicle_usage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vehicle_usage_other FOREIGN KEY (other_cost_id) REFERENCES job_other_costs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recurring_job_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    frequency_type VARCHAR(30) NOT NULL,
    interval_value INT UNSIGNED NOT NULL DEFAULT 1,
    next_run_date DATE NOT NULL,
    generation_mode VARCHAR(20) NOT NULL DEFAULT 'FOLLOW_UP',
    default_recipe_id INT UNSIGNED NULL,
    anchor_job_id INT UNSIGNED NULL,
    assigned_to INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recurring_next (active, next_run_date),
    KEY idx_recurring_customer (customer_id),
    CONSTRAINT fk_recurring_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_recipe FOREIGN KEY (default_recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_job FOREIGN KEY (anchor_job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_user FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recurring_followups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    job_task_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_followups_template (template_id),
    CONSTRAINT fk_followups_template FOREIGN KEY (template_id) REFERENCES recurring_job_templates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_followups_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_followups_task FOREIGN KEY (job_task_id) REFERENCES job_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recurring_job_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    period_key VARCHAR(40) NOT NULL,
    followup_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recurring_period (template_id, period_key),
    CONSTRAINT fk_recurring_runs_template FOREIGN KEY (template_id) REFERENCES recurring_job_templates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_runs_followup FOREIGN KEY (followup_id) REFERENCES recurring_followups (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subcontract_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(40) NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    required_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_cost DECIMAL(14,2) NULL,
    supplier_reference VARCHAR(80) NULL,
    other_cost_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subcontract_number (order_number),
    KEY idx_subcontract_job (job_id),
    KEY idx_subcontract_supplier (supplier_id),
    KEY idx_subcontract_status (status),
    CONSTRAINT fk_subcontract_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_subcontract_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_subcontract_other FOREIGN KEY (other_cost_id) REFERENCES job_other_costs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_subcontract_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE block_reasons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_block_reasons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE work_blocks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NULL,
    stage_id INT UNSIGNED NULL,
    reason_id INT UNSIGNED NOT NULL,
    reason_text VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cleared_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_work_blocks_job (job_id, status),
    CONSTRAINT fk_work_blocks_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_work_blocks_task FOREIGN KEY (task_id) REFERENCES job_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_work_blocks_stage FOREIGN KEY (stage_id) REFERENCES job_production_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_work_blocks_reason FOREIGN KEY (reason_id) REFERENCES block_reasons (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_work_blocks_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sign-Forge Management System
-- Current schema (Phases 1 to 15)
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

DROP TABLE IF EXISTS user_devices;
DROP TABLE IF EXISTS sync_operations;
DROP TABLE IF EXISTS sync_conflicts;
DROP TABLE IF EXISTS field_packs;
DROP TABLE IF EXISTS field_photos;
DROP TABLE IF EXISTS field_notes;
DROP TABLE IF EXISTS field_checklist_answers;
DROP TABLE IF EXISTS field_time_entries;
DROP TABLE IF EXISTS field_travel;
DROP TABLE IF EXISTS field_scans;
DROP TABLE IF EXISTS push_subscriptions;
DROP TABLE IF EXISTS push_messages;
DROP TABLE IF EXISTS mobile_problem_reports;
DROP TABLE IF EXISTS message_drafts;
DROP TABLE IF EXISTS entity_tags;
DROP TABLE IF EXISTS tags;
DROP TABLE IF EXISTS saved_views;
DROP TABLE IF EXISTS dashboard_layouts;
DROP TABLE IF EXISTS integration_issues;
DROP TABLE IF EXISTS review_items;
DROP TABLE IF EXISTS ai_suggestions;
DROP TABLE IF EXISTS ai_interactions;
DROP TABLE IF EXISTS ai_prompt_templates;
DROP TABLE IF EXISTS document_extractions;
DROP TABLE IF EXISTS payment_requests;
DROP TABLE IF EXISTS accounting_code_maps;
DROP TABLE IF EXISTS connector_secrets;
DROP TABLE IF EXISTS integration_connectors;
DROP TABLE IF EXISTS status_labels;
DROP TABLE IF EXISTS feature_flags;
DROP TABLE IF EXISTS business_rules;
DROP TABLE IF EXISTS checklist_bindings;
DROP TABLE IF EXISTS custom_form_submissions;
DROP TABLE IF EXISTS custom_form_fields;
DROP TABLE IF EXISTS custom_forms;
DROP TABLE IF EXISTS custom_field_values;
DROP TABLE IF EXISTS custom_field_definitions;
DROP TABLE IF EXISTS approval_delegations;
DROP TABLE IF EXISTS approval_steps;
DROP TABLE IF EXISTS approval_requests;
DROP TABLE IF EXISTS approval_policy_rules;
DROP TABLE IF EXISTS approval_policies;
DROP TABLE IF EXISTS workflow_action_executions;
DROP TABLE IF EXISTS workflow_executions;
DROP TABLE IF EXISTS workflow_actions;
DROP TABLE IF EXISTS workflow_conditions;
DROP TABLE IF EXISTS workflow_definition_versions;
DROP TABLE IF EXISTS workflow_definitions;
DROP TABLE IF EXISTS business_event_consumers;
DROP TABLE IF EXISTS business_events;
DROP TABLE IF EXISTS purchase_recommendation_sources;
DROP TABLE IF EXISTS purchase_recommendations;
DROP TABLE IF EXISTS webhook_deliveries;
DROP TABLE IF EXISTS webhook_subscriptions;
DROP TABLE IF EXISTS api_request_log;
DROP TABLE IF EXISTS api_clients;
DROP TABLE IF EXISTS integration_sync_log;
DROP TABLE IF EXISTS integration_mappings;
DROP TABLE IF EXISTS budget_lines;
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS planning_targets;
DROP TABLE IF EXISTS scenarios;
DROP TABLE IF EXISTS data_imports;
DROP TABLE IF EXISTS forecast_snapshots;
DROP TABLE IF EXISTS operational_commitments;
DROP TABLE IF EXISTS operational_cash_positions;
DROP TABLE IF EXISTS business_locations;
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
    business_location_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    hourly_cost DECIMAL(14,2) NULL,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    KEY idx_users_location (business_location_id),
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
    payment_terms VARCHAR(20) NULL,
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
    direction_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    kerf_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    sheet_edge_margin_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    horizontal_spacing_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    vertical_spacing_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    print_edge_margin_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
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
    business_location_id INT UNSIGNED NULL,
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
    KEY idx_opportunities_close (expected_close_date),
    KEY idx_opportunities_location (business_location_id),
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
    expected_decision_date DATE NULL,
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
    KEY idx_quotes_decision (expected_decision_date),
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
    business_location_id INT UNSIGNED NULL,
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
    KEY idx_jobs_location (business_location_id),
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
    payment_terms VARCHAR(20) NULL,
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
    KEY idx_purchase_orders_expected (expected_date),
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
    KEY idx_login_events_ip_created (ip_address, created_at),
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
    quantity_basis VARCHAR(20) NOT NULL DEFAULT 'PER_UNIT',
    batch_size INT UNSIGNED NULL,
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
    business_location_id INT UNSIGNED NULL,
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
    KEY idx_resources_location (business_location_id),
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

-- Phase 9 estimating and yield.
CREATE TABLE estimates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_number VARCHAR(40) NOT NULL,
    revision_number INT UNSIGNED NOT NULL DEFAULT 1,
    customer_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    quote_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    recipe_id INT UNSIGNED NULL,
    recipe_version INT UNSIGNED NULL,
    estimate_type VARCHAR(30) NOT NULL DEFAULT 'GENERAL',
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    subtotal_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    material_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    labour_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    machine_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    installation_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    travel_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subcontract_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    other_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    recommended_sell_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_gross_profit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_margin DECIMAL(8,2) NULL,
    confidence_basis VARCHAR(30) NOT NULL DEFAULT 'NO_HISTORY',
    snapshot_json JSON NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estimates_number (estimate_number),
    KEY idx_estimates_customer (customer_id),
    KEY idx_estimates_quote (quote_id),
    KEY idx_estimates_job (job_id),
    KEY idx_estimates_status (status),
    KEY idx_estimates_recipe (recipe_id),
    CONSTRAINT fk_estimates_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estimate_revisions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estimate_revisions (estimate_id, revision_number),
    CONSTRAINT fk_estimate_revisions_estimate FOREIGN KEY (estimate_id) REFERENCES estimates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_estimate_revisions_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estimate_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_id INT UNSIGNED NOT NULL,
    component_type VARCHAR(20) NOT NULL,
    product_id INT UNSIGNED NULL,
    recipe_item_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    billable_quantity DECIMAL(14,4) NULL,
    estimated_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    actual_quantity DECIMAL(14,4) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    calculation_method VARCHAR(30) NOT NULL DEFAULT 'QUANTITY',
    calculation_details_json JSON NULL,
    manual_override TINYINT(1) NOT NULL DEFAULT 0,
    override_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estimate_components_estimate (estimate_id),
    CONSTRAINT fk_estimate_components_estimate FOREIGN KEY (estimate_id) REFERENCES estimates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_estimate_components_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pricing_recommendations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recommendation_type VARCHAR(40) NOT NULL,
    recipe_id INT UNSIGNED NULL,
    recipe_item_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    current_value VARCHAR(40) NOT NULL,
    suggested_value VARCHAR(40) NOT NULL,
    sample_size INT UNSIGNED NOT NULL DEFAULT 0,
    confidence_basis VARCHAR(30) NOT NULL DEFAULT 'LIMITED_HISTORY',
    reason TEXT NOT NULL,
    statistic_json JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'NEW',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pricing_recommendations_status (status),
    KEY idx_pricing_recommendations_recipe (recipe_id),
    CONSTRAINT fk_pricing_recommendations_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pricing_recommendations_user FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_risk_reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id INT UNSIGNED NULL,
    estimate_id INT UNSIGNED NULL,
    level VARCHAR(20) NOT NULL,
    warnings_json JSON NOT NULL,
    override_reason VARCHAR(255) NULL,
    override_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quote_risk_quote (quote_id),
    KEY idx_quote_risk_estimate (estimate_id),
    CONSTRAINT fk_quote_risk_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_risk_estimate FOREIGN KEY (estimate_id) REFERENCES estimates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_risk_user FOREIGN KEY (override_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installation_access_levels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(80) NOT NULL,
    multiplier DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_installation_access_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installation_height_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(80) NOT NULL,
    equipment_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_installation_height_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Phase 10 communications, leads, campaigns, and consent.
-- Reference rows live in seed.sql. Upgrades use migration 010.

-- Phase 10: leads, communications, campaigns, consent, and webhooks.
-- Import once on a database that already has Phase 9.
-- Do not import schema.sql on that database.

SET NAMES utf8mb4;

ALTER TABLE communications
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER contact_id,
    ADD COLUMN opportunity_id INT UNSIGNED NULL AFTER lead_id,
    ADD COLUMN quote_id INT UNSIGNED NULL AFTER opportunity_id,
    ADD COLUMN job_id INT UNSIGNED NULL AFTER quote_id,
    ADD COLUMN invoice_id INT UNSIGNED NULL AFTER job_id,
    ADD COLUMN message_body MEDIUMTEXT NULL AFTER message_summary,
    ADD COLUMN external_message_id VARCHAR(120) NULL AFTER external_reference,
    ADD COLUMN thread_id VARCHAR(120) NULL AFTER external_message_id,
    ADD COLUMN template_id INT UNSIGNED NULL AFTER thread_id,
    ADD COLUMN failure_reason VARCHAR(255) NULL AFTER status,
    ADD COLUMN received_at DATETIME NULL AFTER sent_at,
    ADD KEY idx_communications_lead (lead_id),
    ADD KEY idx_communications_contact (contact_id),
    ADD KEY idx_communications_channel (channel),
    ADD KEY idx_communications_sent (sent_at);

ALTER TABLE sales_opportunities
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER contact_id,
    ADD COLUMN campaign_id INT UNSIGNED NULL AFTER source,
    ADD COLUMN attribution_model VARCHAR(40) NULL AFTER campaign_id;

ALTER TABLE quotes
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER opportunity_id,
    ADD COLUMN campaign_id INT UNSIGNED NULL AFTER lead_id,
    ADD COLUMN attribution_source VARCHAR(40) NULL AFTER campaign_id,
    ADD COLUMN attribution_model VARCHAR(40) NULL AFTER attribution_source;

ALTER TABLE jobs
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER opportunity_id,
    ADD COLUMN campaign_id INT UNSIGNED NULL AFTER lead_id,
    ADD COLUMN attribution_source VARCHAR(40) NULL AFTER campaign_id,
    ADD COLUMN attribution_model VARCHAR(40) NULL AFTER attribution_source;

ALTER TABLE invoices
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER job_id,
    ADD COLUMN campaign_id INT UNSIGNED NULL AFTER lead_id,
    ADD COLUMN attribution_source VARCHAR(40) NULL AFTER campaign_id,
    ADD COLUMN attribution_model VARCHAR(40) NULL AFTER attribution_source;

CREATE TABLE marketing_campaigns (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    campaign_code VARCHAR(60) NOT NULL,
    channel VARCHAR(30) NOT NULL DEFAULT 'OTHER',
    start_date DATE NULL,
    end_date DATE NULL,
    budget DECIMAL(14,2) NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketing_campaigns_code (campaign_code),
    KEY idx_marketing_campaigns_status (status),
    CONSTRAINT fk_marketing_campaigns_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    response_hours INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lead_sources_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_number VARCHAR(40) NOT NULL,
    source VARCHAR(40) NOT NULL,
    source_detail VARCHAR(180) NULL,
    campaign_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    company_name VARCHAR(180) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    phone_normalised VARCHAR(20) NULL,
    message TEXT NOT NULL,
    message_hash CHAR(64) NULL,
    service_interest VARCHAR(180) NULL,
    estimated_value DECIMAL(14,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'NEW',
    assigned_to INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    attribution_json JSON NULL,
    attribution_model VARCHAR(40) NOT NULL DEFAULT 'FIRST_TOUCH',
    first_contact_at DATETIME NULL,
    last_contact_at DATETIME NULL,
    next_followup_at DATETIME NULL,
    converted_at DATETIME NULL,
    converted_by INT UNSIGNED NULL,
    lost_reason VARCHAR(180) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_leads_number (lead_number),
    KEY idx_leads_status (status),
    KEY idx_leads_source (source),
    KEY idx_leads_assigned (assigned_to),
    KEY idx_leads_created (created_at),
    KEY idx_leads_followup (next_followup_at),
    KEY idx_leads_email (email),
    KEY idx_leads_phone (phone_normalised),
    KEY idx_leads_message_hash (message_hash),
    KEY idx_leads_campaign (campaign_id),
    CONSTRAINT fk_leads_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_converted_by FOREIGN KEY (converted_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sales_opportunities
    ADD KEY idx_opportunities_lead (lead_id),
    ADD KEY idx_opportunities_campaign (campaign_id),
    ADD CONSTRAINT fk_opportunities_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_opportunities_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE quotes
    ADD KEY idx_quotes_lead (lead_id),
    ADD KEY idx_quotes_campaign (campaign_id),
    ADD CONSTRAINT fk_quotes_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_quotes_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE jobs
    ADD KEY idx_jobs_lead (lead_id),
    ADD KEY idx_jobs_campaign (campaign_id),
    ADD CONSTRAINT fk_jobs_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_jobs_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE invoices
    ADD KEY idx_invoices_lead (lead_id),
    ADD KEY idx_invoices_campaign (campaign_id),
    ADD CONSTRAINT fk_invoices_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_invoices_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE communications
    ADD CONSTRAINT fk_communications_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE communication_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'CUSTOM',
    subject_template VARCHAR(180) NULL,
    body_template MEDIUMTEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communication_templates_channel (channel, active),
    CONSTRAINT fk_communication_templates_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE communications
    ADD CONSTRAINT fk_communications_template FOREIGN KEY (template_id) REFERENCES communication_templates (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE contact_communication_preferences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    email_allowed TINYINT(1) NOT NULL DEFAULT 1,
    whatsapp_allowed TINYINT(1) NOT NULL DEFAULT 1,
    sms_allowed TINYINT(1) NOT NULL DEFAULT 0,
    marketing_allowed TINYINT(1) NOT NULL DEFAULT 0,
    transactional_allowed TINYINT(1) NOT NULL DEFAULT 1,
    preferred_channel VARCHAR(20) NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'STAFF',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contact_preferences (contact_id),
    CONSTRAINT fk_contact_preferences_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE communication_suppressions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    channel VARCHAR(20) NOT NULL,
    reason VARCHAR(180) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_suppressions_email (email, channel),
    KEY idx_suppressions_phone (phone, channel),
    KEY idx_suppressions_contact (contact_id, channel),
    CONSTRAINT fk_suppressions_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    rating TINYINT UNSIGNED NULL,
    feedback_text TEXT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'STAFF',
    submitted_at DATETIME NOT NULL,
    followup_required TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_customer (customer_id, submitted_at),
    KEY idx_feedback_job (job_id),
    CONSTRAINT fk_feedback_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE review_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    channel VARCHAR(20) NOT NULL,
    destination VARCHAR(255) NULL,
    requested_at DATETIME NOT NULL,
    requested_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_review_requests_customer (customer_id, requested_at),
    CONSTRAINT fk_review_requests_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_review_requests_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_review_requests_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_review_requests_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_referrals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    referred_by_customer_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_referrals_lead (lead_id),
    KEY idx_referrals_by (referred_by_customer_id),
    CONSTRAINT fk_referrals_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_referrals_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_referrals_referrer FOREIGN KEY (referred_by_customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL,
    external_event_id VARCHAR(120) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL,
    received_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    error_message VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_events_external (provider, external_event_id),
    KEY idx_integration_events_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_secrets (
    secret_key VARCHAR(80) NOT NULL,
    secret_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (secret_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE public_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(40) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_public_rate_limits_bucket (bucket, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE call_outcomes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_call_outcomes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 11 workshop documents and tracking.

SET NAMES utf8mb4;

ALTER TABLE job_items
    ADD COLUMN tracking_mode VARCHAR(20) NOT NULL DEFAULT 'NONE' AFTER production_status;

ALTER TABLE inventory_items
    ADD COLUMN customer_supplied TINYINT(1) NOT NULL DEFAULT 0 AFTER notes;

CREATE TABLE tracking_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    tracking_code VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tracking_codes_code (tracking_code),
    UNIQUE KEY uq_tracking_codes_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tracking_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tracking_tokens_hash (token_hash),
    KEY idx_tracking_tokens_entity (entity_type, entity_id),
    CONSTRAINT fk_tracking_tokens_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE label_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    width_mm DECIMAL(8,2) NOT NULL,
    height_mm DECIMAL(8,2) NOT NULL,
    orientation VARCHAR(20) NOT NULL DEFAULT 'PORTRAIT',
    layout_definition TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_label_templates_type (entity_type, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE label_reprints (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    template_id INT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    reason VARCHAR(255) NULL,
    printed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_label_reprints_entity (entity_type, entity_id),
    CONSTRAINT fk_label_reprints_template FOREIGN KEY (template_id) REFERENCES label_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_label_reprints_user FOREIGN KEY (printed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NOT NULL,
    tracking_code VARCHAR(40) NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    quantity_completed DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    sequence_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'WAITING',
    current_stage_id INT UNSIGNED NULL,
    assigned_resource_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_items_code (tracking_code),
    KEY idx_production_items_job (job_id),
    KEY idx_production_items_status (status),
    KEY idx_production_items_item (job_item_id),
    CONSTRAINT fk_production_items_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_production_items_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_production_items_stage FOREIGN KEY (current_stage_id) REFERENCES job_production_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_production_items_resource FOREIGN KEY (assigned_resource_id) REFERENCES resources (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_item_id INT UNSIGNED NOT NULL,
    job_production_stage_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    reason VARCHAR(40) NULL,
    quantity DECIMAL(14,4) NULL,
    notes VARCHAR(255) NULL,
    idempotency_key VARCHAR(80) NULL,
    user_id INT UNSIGNED NULL,
    client_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_events_key (idempotency_key),
    KEY idx_production_events_item (production_item_id, created_at),
    CONSTRAINT fk_production_events_item FOREIGN KEY (production_item_id) REFERENCES production_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_production_events_stage FOREIGN KEY (job_production_stage_id) REFERENCES job_production_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_production_events_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE material_issues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idempotency_key VARCHAR(80) NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    production_item_id INT UNSIGNED NULL,
    inventory_item_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    production_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    waste_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    override_reason VARCHAR(255) NULL,
    usage_id INT UNSIGNED NULL,
    waste_usage_id INT UNSIGNED NULL,
    offcut_inventory_item_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_material_issues_key (idempotency_key),
    KEY idx_material_issues_job (job_id),
    KEY idx_material_issues_item (inventory_item_id),
    CONSTRAINT fk_material_issues_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_material_issues_job_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_material_issues_production FOREIGN KEY (production_item_id) REFERENCES production_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_material_issues_inventory FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_material_issues_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_material_issues_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quality_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    production_item_id INT UNSIGNED NULL,
    check_type VARCHAR(120) NOT NULL,
    status VARCHAR(20) NOT NULL,
    fail_reason VARCHAR(255) NULL,
    fail_action VARCHAR(20) NULL,
    notes VARCHAR(255) NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    override_reason VARCHAR(255) NULL,
    checked_by INT UNSIGNED NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quality_checks_job (job_id),
    KEY idx_quality_checks_item (production_item_id),
    CONSTRAINT fk_quality_checks_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quality_checks_job_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quality_checks_production FOREIGN KEY (production_item_id) REFERENCES production_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quality_checks_user FOREIGN KEY (checked_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qc_checklist_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NULL,
    label VARCHAR(180) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_qc_checklist_product (product_id, sort_order),
    CONSTRAINT fk_qc_checklist_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reprint_reasons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reprint_reasons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reprints (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    production_item_id INT UNSIGNED NULL,
    stage_id INT UNSIGNED NULL,
    quantity DECIMAL(14,4) NOT NULL,
    reason_code VARCHAR(40) NOT NULL,
    material_quantity DECIMAL(14,4) NULL,
    labour_minutes INT UNSIGNED NULL,
    chargeable TINYINT(1) NOT NULL DEFAULT 0,
    variation_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reprints_job (job_id),
    CONSTRAINT fk_reprints_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reprints_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reprints_production FOREIGN KEY (production_item_id) REFERENCES production_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reprints_stage FOREIGN KEY (stage_id) REFERENCES job_production_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reprints_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dispatches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dispatch_number VARCHAR(40) NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    dispatch_type VARCHAR(20) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    scheduled_at DATETIME NULL,
    dispatched_at DATETIME NULL,
    vehicle_resource_id INT UNSIGNED NULL,
    driver_user_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dispatches_number (dispatch_number),
    KEY idx_dispatches_job (job_id),
    KEY idx_dispatches_status (status),
    CONSTRAINT fk_dispatches_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dispatches_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_dispatches_vehicle FOREIGN KEY (vehicle_resource_id) REFERENCES resources (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_dispatches_driver FOREIGN KEY (driver_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_dispatches_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dispatch_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dispatch_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    production_item_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    status VARCHAR(20) NOT NULL DEFAULT 'EXPECTED',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dispatch_items_production (dispatch_id, production_item_id),
    KEY idx_dispatch_items_dispatch (dispatch_id),
    CONSTRAINT fk_dispatch_items_dispatch FOREIGN KEY (dispatch_id) REFERENCES dispatches (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dispatch_items_job_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_dispatch_items_production FOREIGN KEY (production_item_id) REFERENCES production_items (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE digital_signatures (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    signer_name VARCHAR(120) NOT NULL,
    signer_contact VARCHAR(80) NULL,
    statement_text TEXT NOT NULL,
    statement_version VARCHAR(20) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    captured_by INT UNSIGNED NULL,
    signed_at DATETIME NOT NULL,
    client_signed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_signatures_entity (entity_type, entity_id),
    CONSTRAINT fk_signatures_user FOREIGN KEY (captured_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE proof_of_delivery (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dispatch_id INT UNSIGNED NOT NULL,
    recipient_name VARCHAR(120) NOT NULL,
    recipient_contact VARCHAR(80) NULL,
    delivery_datetime DATETIME NOT NULL,
    signature_file_id INT UNSIGNED NULL,
    photo_file_id INT UNSIGNED NULL,
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proof_of_delivery_dispatch (dispatch_id),
    CONSTRAINT fk_pod_dispatch FOREIGN KEY (dispatch_id) REFERENCES dispatches (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pod_signature FOREIGN KEY (signature_file_id) REFERENCES digital_signatures (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pod_photo FOREIGN KEY (photo_file_id) REFERENCES attachments (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pod_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_snags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    installation_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    assigned_to INT UNSIGNED NULL,
    target_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_snags_job (job_id),
    KEY idx_job_snags_status (status),
    CONSTRAINT fk_job_snags_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_job_snags_installation FOREIGN KEY (installation_id) REFERENCES job_installations (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_snags_user FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_job_snags_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_type VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    template_version INT UNSIGNED NOT NULL DEFAULT 1,
    layout_config TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_document_templates_type (document_type, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE generated_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_type VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    document_number VARCHAR(40) NULL,
    template_id INT UNSIGNED NULL,
    template_version INT UNSIGNED NOT NULL DEFAULT 1,
    file_path VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'CURRENT',
    immutable TINYINT(1) NOT NULL DEFAULT 0,
    outdated_reason VARCHAR(255) NULL,
    generated_by INT UNSIGNED NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    superseded_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_generated_documents_entity (entity_type, entity_id),
    KEY idx_generated_documents_number (document_number),
    KEY idx_generated_documents_type (document_type, status),
    CONSTRAINT fk_generated_documents_template FOREIGN KEY (template_id) REFERENCES document_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_generated_documents_user FOREIGN KEY (generated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scan_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracking_token_id INT UNSIGNED NULL,
    tracking_code VARCHAR(40) NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    user_id INT UNSIGNED NULL,
    device_identifier VARCHAR(80) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    metadata_json JSON NULL,
    PRIMARY KEY (id),
    KEY idx_scan_events_entity (entity_type, entity_id),
    KEY idx_scan_events_user (user_id, created_at),
    CONSTRAINT fk_scan_events_token FOREIGN KEY (tracking_token_id) REFERENCES tracking_tokens (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_scan_events_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE packages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_code VARCHAR(40) NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    description VARCHAR(180) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_packages_code (package_code),
    KEY idx_packages_job (job_id),
    CONSTRAINT fk_packages_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_packages_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE package_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    package_id INT UNSIGNED NOT NULL,
    production_item_id INT UNSIGNED NULL,
    job_item_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_package_items_production (package_id, production_item_id),
    KEY idx_package_items_package (package_id),
    CONSTRAINT fk_package_items_package FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_package_items_production FOREIGN KEY (production_item_id) REFERENCES production_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_package_items_job_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE kiosk_pins (
    user_id INT UNSIGNED NOT NULL,
    pin_hash VARCHAR(255) NOT NULL,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_kiosk_pins_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 12 planning, forecasting, and integrations.
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

-- Phase 13 workflows, approvals, configuration, and assistance.

CREATE TABLE business_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uuid CHAR(36) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payload_summary_json JSON NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_events_uuid (event_uuid),
    KEY idx_business_events_type (event_type, entity_type, occurred_at),
    KEY idx_business_events_entity (entity_type, entity_id),
    CONSTRAINT fk_business_events_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_event_consumers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id INT UNSIGNED NOT NULL,
    consumer_key VARCHAR(80) NOT NULL,
    processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_event_consumers (event_id, consumer_key),
    CONSTRAINT fk_business_event_consumers_event FOREIGN KEY (event_id) REFERENCES business_events (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_definitions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    description VARCHAR(255) NULL,
    entity_type VARCHAR(40) NOT NULL,
    trigger_event VARCHAR(60) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    priority INT NOT NULL DEFAULT 100,
    stop_on_match TINYINT(1) NOT NULL DEFAULT 0,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workflow_definitions_trigger (active, trigger_event, entity_type),
    KEY idx_workflow_definitions_priority (priority),
    CONSTRAINT fk_workflow_definitions_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_definition_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id INT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    changed_by INT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_workflow_versions_workflow (workflow_id, version_number),
    CONSTRAINT fk_workflow_versions_workflow FOREIGN KEY (workflow_id) REFERENCES workflow_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_workflow_versions_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_conditions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(64) NOT NULL,
    operator VARCHAR(32) NOT NULL,
    comparison_value VARCHAR(255) NULL,
    condition_group INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workflow_conditions_workflow (workflow_id, condition_group, sort_order),
    CONSTRAINT fk_workflow_conditions_workflow FOREIGN KEY (workflow_id) REFERENCES workflow_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_actions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id INT UNSIGNED NOT NULL,
    action_type VARCHAR(40) NOT NULL,
    configuration_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workflow_actions_workflow (workflow_id, sort_order),
    CONSTRAINT fk_workflow_actions_workflow FOREIGN KEY (workflow_id) REFERENCES workflow_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_executions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_id INT UNSIGNED NOT NULL,
    trigger_event VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    event_uuid CHAR(36) NULL,
    origin_workflow_id INT UNSIGNED NULL,
    execution_depth INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    error_message VARCHAR(255) NULL,
    context_snapshot_json JSON NULL,
    idempotency_key VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_workflow_executions_idempotency (idempotency_key),
    KEY idx_workflow_executions_event (workflow_id, event_uuid),
    KEY idx_workflow_executions_entity (entity_type, entity_id),
    KEY idx_workflow_executions_status (status, started_at),
    CONSTRAINT fk_workflow_executions_workflow FOREIGN KEY (workflow_id) REFERENCES workflow_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_action_executions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    workflow_execution_id INT UNSIGNED NOT NULL,
    workflow_action_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL,
    result_summary VARCHAR(255) NULL,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_workflow_action_executions_run (workflow_execution_id),
    CONSTRAINT fk_workflow_action_executions_run FOREIGN KEY (workflow_execution_id) REFERENCES workflow_executions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approval_policies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    action_key VARCHAR(60) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    priority INT NOT NULL DEFAULT 100,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_approval_policies_lookup (active, entity_type, action_key, priority),
    CONSTRAINT fk_approval_policies_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approval_policy_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    match_mode VARCHAR(8) NOT NULL DEFAULT 'ALL',
    conditions_json JSON NOT NULL,
    steps_json JSON NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_approval_rules_policy (policy_id, sort_order),
    CONSTRAINT fk_approval_rules_policy FOREIGN KEY (policy_id) REFERENCES approval_policies (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approval_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_id INT UNSIGNED NULL,
    policy_version INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    action_key VARCHAR(60) NOT NULL,
    requested_by INT UNSIGNED NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    current_step INT UNSIGNED NOT NULL DEFAULT 1,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    reason VARCHAR(255) NULL,
    context_json JSON NULL,
    open_key VARCHAR(40) NOT NULL DEFAULT 'OPEN',
    PRIMARY KEY (id),
    UNIQUE KEY uq_approval_requests_open (entity_type, entity_id, action_key, open_key),
    KEY idx_approval_requests_status (status, requested_at),
    KEY idx_approval_requests_entity (entity_type, entity_id),
    CONSTRAINT fk_approval_requests_policy FOREIGN KEY (policy_id) REFERENCES approval_policies (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_approval_requests_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approval_steps (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    approval_request_id INT UNSIGNED NOT NULL,
    sequence INT UNSIGNED NOT NULL,
    approver_type VARCHAR(20) NOT NULL,
    approver_id INT UNSIGNED NULL,
    role_code VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    decision_by INT UNSIGNED NULL,
    delegated_from_user_id INT UNSIGNED NULL,
    decision_at TIMESTAMP NULL DEFAULT NULL,
    comment VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_approval_steps_request (approval_request_id, sequence),
    CONSTRAINT fk_approval_steps_request FOREIGN KEY (approval_request_id) REFERENCES approval_requests (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_approval_steps_user FOREIGN KEY (decision_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approval_delegations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    original_user_id INT UNSIGNED NOT NULL,
    delegate_user_id INT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_approval_delegations_window (original_user_id, starts_at, ends_at),
    CONSTRAINT fk_approval_delegations_original FOREIGN KEY (original_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_approval_delegations_delegate FOREIGN KEY (delegate_user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_field_definitions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    field_key VARCHAR(64) NOT NULL,
    label VARCHAR(120) NOT NULL,
    field_type VARCHAR(20) NOT NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    options_json JSON NULL,
    validation_json JSON NULL,
    default_value VARCHAR(255) NULL,
    expose_documents TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_custom_fields_entity_key (entity_type, field_key),
    KEY idx_custom_fields_entity (entity_type, active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_field_values (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    field_definition_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    value_text VARCHAR(255) NULL,
    value_number DECIMAL(14,4) NULL,
    value_date DATETIME NULL,
    value_json JSON NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_custom_field_values_entity (field_definition_id, entity_type, entity_id),
    KEY idx_custom_field_values_entity (entity_type, entity_id),
    CONSTRAINT fk_custom_field_values_field FOREIGN KEY (field_definition_id) REFERENCES custom_field_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_custom_field_values_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_forms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    purpose VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    current_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_custom_forms_purpose (purpose, active),
    CONSTRAINT fk_custom_forms_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_form_fields (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    form_version INT UNSIGNED NOT NULL,
    field_source VARCHAR(20) NOT NULL,
    field_key VARCHAR(64) NOT NULL,
    label VARCHAR(180) NOT NULL,
    field_type VARCHAR(20) NOT NULL,
    required TINYINT(1) NOT NULL DEFAULT 0,
    options_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_custom_form_fields_version (form_id, form_version, sort_order),
    CONSTRAINT fk_custom_form_fields_form FOREIGN KEY (form_id) REFERENCES custom_forms (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE custom_form_submissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    form_version INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    submitted_by INT UNSIGNED NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    values_json JSON NOT NULL,
    signature_name VARCHAR(120) NULL,
    signature_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_custom_form_submissions_form (form_id, form_version),
    KEY idx_custom_form_submissions_entity (entity_type, entity_id),
    CONSTRAINT fk_custom_form_submissions_form FOREIGN KEY (form_id) REFERENCES custom_forms (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_custom_form_submissions_user FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE checklist_bindings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    area VARCHAR(40) NOT NULL,
    form_id INT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_checklist_bindings_area (area, active),
    CONSTRAINT fk_checklist_bindings_form FOREIGN KEY (form_id) REFERENCES custom_forms (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE business_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_key VARCHAR(60) NOT NULL,
    scope_type VARCHAR(20) NOT NULL,
    scope_id INT UNSIGNED NOT NULL DEFAULT 0,
    value_text VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_business_rules_scope (rule_key, scope_type, scope_id),
    KEY idx_business_rules_key (rule_key, active),
    CONSTRAINT fk_business_rules_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feature_flags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feature_key VARCHAR(60) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    configuration_json JSON NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feature_flags_key (feature_key),
    CONSTRAINT fk_feature_flags_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE status_labels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    status_code VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    locked TINYINT(1) NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_status_labels (entity_type, status_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_connectors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    connector_key VARCHAR(60) NOT NULL,
    connector_type VARCHAR(20) NOT NULL,
    provider VARCHAR(40) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    configuration_json JSON NULL,
    tested_at TIMESTAMP NULL DEFAULT NULL,
    health_status VARCHAR(20) NOT NULL DEFAULT 'UNCONFIGURED',
    last_success_at TIMESTAMP NULL DEFAULT NULL,
    last_error VARCHAR(255) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_connectors_key (connector_key),
    KEY idx_integration_connectors_type (connector_type, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE connector_secrets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    connector_id INT UNSIGNED NOT NULL,
    secret_key VARCHAR(40) NOT NULL,
    secret_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_connector_secrets (connector_id, secret_key),
    CONSTRAINT fk_connector_secrets_connector FOREIGN KEY (connector_id) REFERENCES integration_connectors (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE accounting_code_maps (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    map_type VARCHAR(20) NOT NULL,
    local_code VARCHAR(40) NOT NULL,
    external_code VARCHAR(40) NOT NULL,
    description VARCHAR(180) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_accounting_code_maps (map_type, local_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id INT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    public_token CHAR(32) NOT NULL,
    external_reference VARCHAR(80) NULL,
    provider_event_id VARCHAR(80) NULL,
    amount DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'ZAR',
    amount_basis VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'CREATED',
    payment_url VARCHAR(255) NULL,
    payment_id INT UNSIGNED NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_requests_token (public_token),
    UNIQUE KEY uq_payment_requests_external (external_reference),
    UNIQUE KEY uq_payment_requests_event (provider_event_id),
    KEY idx_payment_requests_invoice (invoice_id, status),
    CONSTRAINT fk_payment_requests_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_extractions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_type VARCHAR(40) NOT NULL,
    source_label VARCHAR(180) NOT NULL,
    original_text MEDIUMTEXT NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PROPOSED',
    proposed_json JSON NULL,
    confirmed_json JSON NULL,
    confidence DECIMAL(5,2) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_document_extractions_status (status, created_at),
    CONSTRAINT fk_document_extractions_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_prompt_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feature VARCHAR(60) NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    body VARCHAR(500) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ai_prompt_templates (feature, version_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_interactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    feature VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    provider VARCHAR(40) NULL,
    model VARCHAR(60) NULL,
    prompt_version INT UNSIGNED NULL,
    input_summary VARCHAR(500) NOT NULL,
    output_summary VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL,
    tokens_input INT UNSIGNED NULL,
    tokens_output INT UNSIGNED NULL,
    estimated_cost DECIMAL(12,4) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ai_interactions_user (user_id, created_at),
    KEY idx_ai_interactions_feature (feature, created_at),
    CONSTRAINT fk_ai_interactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ai_suggestions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    interaction_id INT UNSIGNED NULL,
    feature VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    suggestion_type VARCHAR(40) NOT NULL,
    content VARCHAR(2000) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ai_suggestions_entity (entity_type, entity_id),
    CONSTRAINT fk_ai_suggestions_interaction FOREIGN KEY (interaction_id) REFERENCES ai_interactions (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE review_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    item_type VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    proposed_action VARCHAR(180) NOT NULL,
    source VARCHAR(60) NOT NULL,
    reason VARCHAR(255) NULL,
    assigned_to INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT UNSIGNED NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_review_items_status (status, created_at),
    KEY idx_review_items_entity (entity_type, entity_id),
    CONSTRAINT fk_review_items_assignee FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_review_items_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_issues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    failure VARCHAR(255) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 1,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    safe_retry TINYINT(1) NOT NULL DEFAULT 0,
    idempotency_key VARCHAR(80) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    last_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    next_retry_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_integration_issues_status (status, next_retry_at),
    KEY idx_integration_issues_entity (provider, entity_type, entity_id),
    UNIQUE KEY uq_integration_issues_idempotency (idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dashboard_layouts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    layout_json JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dashboard_layouts_role (role_id),
    UNIQUE KEY uq_dashboard_layouts_user (user_id),
    CONSTRAINT fk_dashboard_layouts_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dashboard_layouts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_views (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    filter_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_saved_views_user (user_id, entity_type),
    CONSTRAINT fk_saved_views_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tags_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entity_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tag_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entity_tags (tag_id, entity_type, entity_id),
    KEY idx_entity_tags_entity (entity_type, entity_id),
    CONSTRAINT fk_entity_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_entity_tags_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE message_drafts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel VARCHAR(20) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    recipient VARCHAR(180) NULL,
    subject VARCHAR(180) NULL,
    body VARCHAR(2000) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_message_drafts_entity (entity_type, entity_id),
    CONSTRAINT fk_message_drafts_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    device_uuid CHAR(36) NOT NULL,
    device_name VARCHAR(80) NOT NULL,
    platform VARCHAR(40) NULL,
    app_version VARCHAR(20) NULL,
    sw_version VARCHAR(40) NULL,
    last_seen_at TIMESTAMP NULL DEFAULT NULL,
    last_sync_at TIMESTAMP NULL DEFAULT NULL,
    pending_count INT UNSIGNED NULL,
    trusted TINYINT(1) NOT NULL DEFAULT 0,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_devices_uuid (device_uuid),
    KEY idx_user_devices_user (user_id, revoked_at),
    CONSTRAINT fk_user_devices_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sync_operations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_uuid CHAR(36) NOT NULL,
    local_uuid CHAR(36) NULL,
    user_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL,
    operation_type VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    server_entity_id INT UNSIGNED NULL,
    error_code VARCHAR(40) NULL,
    result_json JSON NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sync_operations_uuid (operation_uuid),
    KEY idx_sync_operations_status (status, user_id),
    KEY idx_sync_operations_entity (entity_type, server_entity_id),
    CONSTRAINT fk_sync_operations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sync_operations_device FOREIGN KEY (device_id) REFERENCES user_devices (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sync_conflicts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    conflict_type VARCHAR(30) NOT NULL,
    server_text VARCHAR(2000) NOT NULL,
    client_text VARCHAR(2000) NOT NULL,
    field_label VARCHAR(80) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    resolution VARCHAR(20) NULL,
    resolved_by INT UNSIGNED NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sync_conflicts_operation (operation_uuid),
    KEY idx_sync_conflicts_status (status, created_at),
    CONSTRAINT fk_sync_conflicts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sync_conflicts_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_packs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pack_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NULL,
    pack_type VARCHAR(20) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    artwork_revision INT UNSIGNED NOT NULL DEFAULT 0,
    critical_hash CHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_packs_uuid (pack_uuid),
    KEY idx_field_packs_entity (entity_type, entity_id, status),
    KEY idx_field_packs_user (user_id, status),
    CONSTRAINT fk_field_packs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_packs_device FOREIGN KEY (device_id) REFERENCES user_devices (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL,
    caption VARCHAR(180) NULL,
    original_path VARCHAR(255) NULL,
    display_path VARCHAR(255) NOT NULL,
    thumb_path VARCHAR(255) NOT NULL,
    annotated_path VARCHAR(255) NULL,
    file_size INT UNSIGNED NOT NULL,
    captured_at DATETIME NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_photos_local (local_uuid),
    KEY idx_field_photos_entity (entity_type, entity_id, sort_order),
    CONSTRAINT fk_field_photos_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    note_type VARCHAR(40) NOT NULL DEFAULT 'NOTE',
    body VARCHAR(2000) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_notes_local (local_uuid),
    KEY idx_field_notes_entity (entity_type, entity_id),
    CONSTRAINT fk_field_notes_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_checklist_answers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    installation_id INT UNSIGNED NOT NULL,
    checklist_item_id INT UNSIGNED NOT NULL,
    answer VARCHAR(10) NOT NULL,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_checks_local (local_uuid),
    KEY idx_field_checks_installation (installation_id),
    CONSTRAINT fk_field_checks_installation FOREIGN KEY (installation_id) REFERENCES job_installations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_checks_item FOREIGN KEY (checklist_item_id) REFERENCES installation_checklist_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_checks_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_time_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    started_at_local DATETIME NOT NULL,
    ended_at_local DATETIME NULL,
    timezone_name VARCHAR(64) NOT NULL,
    server_received_at DATETIME NOT NULL,
    clock_flag TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_time_local (local_uuid),
    KEY idx_field_time_entity (entity_type, entity_id),
    CONSTRAINT fk_field_time_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_travel (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    installation_id INT UNSIGNED NULL,
    vehicle_resource_id INT UNSIGNED NULL,
    event_type VARCHAR(20) NOT NULL,
    start_odometer DECIMAL(12,1) NULL,
    end_odometer DECIMAL(12,1) NULL,
    manual_km DECIMAL(12,1) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    occurred_at_local DATETIME NOT NULL,
    timezone_name VARCHAR(64) NOT NULL,
    vehicle_usage_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_travel_local (local_uuid),
    KEY idx_field_travel_job (job_id),
    CONSTRAINT fk_field_travel_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_travel_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_travel_vehicle FOREIGN KEY (vehicle_resource_id) REFERENCES resources (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_scans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    pack_id INT UNSIGNED NULL,
    code VARCHAR(80) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING_VALIDATION',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_scans_local (local_uuid),
    KEY idx_field_scans_user (user_id, status),
    CONSTRAINT fk_field_scans_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_scans_pack FOREIGN KEY (pack_id) REFERENCES field_packs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_subscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NULL,
    endpoint VARCHAR(500) NOT NULL,
    public_key VARCHAR(255) NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_subscriptions_endpoint (endpoint),
    KEY idx_push_subscriptions_user (user_id, active),
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_push_subscriptions_device FOREIGN KEY (device_id) REFERENCES user_devices (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(80) NOT NULL,
    body VARCHAR(180) NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_push_messages_user (user_id, created_at),
    CONSTRAINT fk_push_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mobile_problem_reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    app_version VARCHAR(20) NOT NULL,
    sw_version VARCHAR(40) NULL,
    platform VARCHAR(40) NULL,
    module_name VARCHAR(40) NOT NULL,
    online_flag TINYINT(1) NOT NULL DEFAULT 1,
    error_code VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mobile_reports_user (user_id, created_at),
    CONSTRAINT fk_mobile_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v1.1 Phase 1 projects and rollouts.
-- v1.1 Phase 1: projects and multi-site rollouts.
-- Jobs, stock, invoices, and payments stay on their existing tables.
-- A job without a project remains valid.

CREATE TABLE project_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE milestone_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    default_weight DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_milestone_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE delay_reasons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_delay_reasons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE risk_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_risk_categories_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE issue_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_issue_categories_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    project_type_id INT UNSIGNED NULL,
    description TEXT NULL,
    template_version INT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_templates_type (project_type_id),
    CONSTRAINT fk_project_templates_type FOREIGN KEY (project_type_id) REFERENCES project_types (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_template_milestones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    milestone_type VARCHAR(40) NOT NULL,
    weight DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    sequence INT UNSIGNED NOT NULL DEFAULT 1,
    blocking TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_template_milestones_template (template_id, sequence),
    CONSTRAINT fk_template_milestones_template FOREIGN KEY (template_id) REFERENCES project_templates (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE projects (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    project_type_id INT UNSIGNED NULL,
    template_id INT UNSIGNED NULL,
    template_version_copied INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    project_health VARCHAR(20) NOT NULL DEFAULT 'ON_TRACK',
    health_reasons TEXT NULL,
    account_manager_user_id INT UNSIGNED NULL,
    project_manager_user_id INT UNSIGNED NULL,
    salesperson_user_id INT UNSIGNED NULL,
    primary_contact_id INT UNSIGNED NULL,
    start_date DATE NULL,
    original_target_date DATE NULL,
    current_target_date DATE NULL,
    actual_completion_date DATE NULL,
    currency_code VARCHAR(3) NOT NULL DEFAULT 'ZAR',
    commercial_mode VARCHAR(20) NOT NULL DEFAULT 'PROJECT',
    commercial_value_cached DECIMAL(14,2) NULL,
    estimated_cost_cached DECIMAL(14,2) NULL,
    actual_cost_cached DECIMAL(14,2) NULL,
    progress_percent_cached DECIMAL(6,2) NULL,
    source_opportunity_id INT UNSIGNED NULL,
    source_quote_id INT UNSIGNED NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    completed_by INT UNSIGNED NULL,
    completion_notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_number (project_number),
    KEY idx_projects_customer (customer_id),
    KEY idx_projects_status (status),
    KEY idx_projects_health (project_health),
    KEY idx_projects_manager (project_manager_user_id),
    KEY idx_projects_salesperson (salesperson_user_id),
    KEY idx_projects_target (current_target_date),
    KEY idx_projects_type (project_type_id),
    CONSTRAINT fk_projects_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_projects_type FOREIGN KEY (project_type_id) REFERENCES project_types (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_template FOREIGN KEY (template_id) REFERENCES project_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_account_manager FOREIGN KEY (account_manager_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_manager FOREIGN KEY (project_manager_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_salesperson FOREIGN KEY (salesperson_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_contact FOREIGN KEY (primary_contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_opportunity FOREIGN KEY (source_opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_quote FOREIGN KEY (source_quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_completed_by FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_waves (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    sequence INT UNSIGNED NOT NULL DEFAULT 1,
    planned_start DATE NULL,
    planned_end DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_waves_project (project_id, sequence),
    CONSTRAINT fk_project_waves_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_sites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    wave_id INT UNSIGNED NULL,
    site_code VARCHAR(40) NOT NULL,
    site_name VARCHAR(180) NOT NULL,
    customer_site_reference VARCHAR(80) NULL,
    address_line_1 VARCHAR(180) NULL,
    address_line_2 VARCHAR(180) NULL,
    city VARCHAR(80) NULL,
    province VARCHAR(80) NULL,
    postal_code VARCHAR(20) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    primary_contact_id INT UNSIGNED NULL,
    site_manager_contact_id INT UNSIGNED NULL,
    group_kind VARCHAR(40) NULL,
    group_label VARCHAR(80) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'NOT_STARTED',
    sequence INT UNSIGNED NOT NULL DEFAULT 1,
    survey_required TINYINT(1) NOT NULL DEFAULT 0,
    survey_status VARCHAR(20) NULL,
    survey_id INT UNSIGNED NULL,
    original_target_date DATE NULL,
    current_target_date DATE NULL,
    actual_completion_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_sites_code (project_id, site_code),
    KEY idx_project_sites_project (project_id, status),
    KEY idx_project_sites_wave (wave_id),
    KEY idx_project_sites_target (current_target_date),
    KEY idx_project_sites_reference (project_id, customer_site_reference),
    CONSTRAINT fk_project_sites_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_sites_wave FOREIGN KEY (wave_id) REFERENCES project_waves (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_project_sites_contact FOREIGN KEY (primary_contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_project_sites_manager FOREIGN KEY (site_manager_contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_project_sites_survey FOREIGN KEY (survey_id) REFERENCES site_surveys (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_milestones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    milestone_type VARCHAR(40) NOT NULL DEFAULT 'CUSTOM',
    status VARCHAR(20) NOT NULL DEFAULT 'NOT_STARTED',
    weight DECIMAL(6,2) NULL,
    original_due_date DATE NULL,
    current_due_date DATE NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    depends_on_milestone_id INT UNSIGNED NULL,
    responsible_user_id INT UNSIGNED NULL,
    blocking TINYINT(1) NOT NULL DEFAULT 0,
    sequence INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_project_milestones_project (project_id, status),
    KEY idx_project_milestones_site (project_site_id),
    KEY idx_project_milestones_due (current_due_date),
    KEY idx_project_milestones_depends (depends_on_milestone_id),
    CONSTRAINT fk_project_milestones_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_milestones_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_milestones_user FOREIGN KEY (responsible_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE project_milestones
    ADD CONSTRAINT fk_project_milestones_depends FOREIGN KEY (depends_on_milestone_id) REFERENCES project_milestones (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE project_risks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    probability TINYINT UNSIGNED NOT NULL DEFAULT 1,
    impact TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    owner_user_id INT UNSIGNED NULL,
    mitigation TEXT NULL,
    due_date DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_project_risks_project (project_id, status),
    KEY idx_project_risks_site (project_site_id),
    CONSTRAINT fk_project_risks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_risks_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_project_risks_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_issues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    milestone_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    owner_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_project_issues_project (project_id, status),
    KEY idx_project_issues_site (project_site_id),
    KEY idx_project_issues_job (job_id),
    CONSTRAINT fk_project_issues_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_issues_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_project_issues_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_project_issues_milestone FOREIGN KEY (milestone_id) REFERENCES project_milestones (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_project_issues_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'INTERNAL',
    body TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_notes_project (project_id, created_at),
    CONSTRAINT fk_project_notes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_notes_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_notes_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_contacts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NOT NULL,
    role_code VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_contacts (project_id, contact_id, role_code),
    KEY idx_project_contacts_site (project_site_id),
    CONSTRAINT fk_project_contacts_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_contacts_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_contacts_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_team (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role_code VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_team (project_id, user_id, role_code),
    KEY idx_project_team_user (user_id),
    CONSTRAINT fk_project_team_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_team_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_direct_costs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    description VARCHAR(180) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    cost_date DATE NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_direct_costs_project (project_id),
    CONSTRAINT fk_project_direct_costs_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_direct_costs_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_budget_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_budget (project_id, category),
    CONSTRAINT fk_project_budget_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_changes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    change_number VARCHAR(40) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    commercial_impact DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    cost_impact DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    schedule_impact_days INT NOT NULL DEFAULT 0,
    sites_affected INT UNSIGNED NOT NULL DEFAULT 0,
    approval_request_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_project_changes_number (project_id, change_number),
    KEY idx_project_changes_project (project_id, status),
    CONSTRAINT fk_project_changes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_changes_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_commercial_lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_id INT UNSIGNED NULL,
    project_site_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    label VARCHAR(180) NOT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    counts_as_value TINYINT(1) NOT NULL DEFAULT 0,
    superseded TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_commercial_project (project_id, counts_as_value, superseded),
    KEY idx_project_commercial_site (project_site_id),
    KEY idx_project_commercial_job (job_id),
    CONSTRAINT fk_project_commercial_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_commercial_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_project_commercial_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_date_changes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(20) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    original_date DATE NULL,
    previous_date DATE NULL,
    new_date DATE NULL,
    reason_code VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    notes VARCHAR(255) NULL,
    changed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_date_changes_project (project_id, created_at),
    CONSTRAINT fk_project_date_changes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_date_changes_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE project_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NULL,
    attachment_id INT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    share_with_jobs TINYINT(1) NOT NULL DEFAULT 0,
    customer_visible TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_project_documents_project (project_id, category),
    KEY idx_project_documents_site (project_site_id),
    CONSTRAINT fk_project_documents_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_documents_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_project_documents_attachment FOREIGN KEY (attachment_id) REFERENCES attachments (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE jobs
    ADD COLUMN project_id INT UNSIGNED NULL AFTER opportunity_id,
    ADD COLUMN project_site_id INT UNSIGNED NULL AFTER project_id,
    ADD COLUMN value_treatment VARCHAR(32) NOT NULL DEFAULT 'STANDALONE' AFTER project_site_id,
    ADD COLUMN rollout_state VARCHAR(20) NOT NULL DEFAULT 'STANDARD' AFTER value_treatment,
    ADD KEY idx_jobs_project (project_id),
    ADD KEY idx_jobs_project_site (project_site_id),
    ADD CONSTRAINT fk_jobs_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_jobs_project_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE quotes
    ADD COLUMN project_id INT UNSIGNED NULL AFTER opportunity_id,
    ADD COLUMN project_site_id INT UNSIGNED NULL AFTER project_id,
    ADD KEY idx_quotes_project (project_id),
    ADD KEY idx_quotes_project_site (project_site_id),
    ADD CONSTRAINT fk_quotes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_quotes_project_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE invoices
    ADD COLUMN project_id INT UNSIGNED NULL AFTER job_id,
    ADD COLUMN project_site_id INT UNSIGNED NULL AFTER project_id,
    ADD KEY idx_invoices_project (project_id),
    ADD KEY idx_invoices_project_site (project_site_id),
    ADD CONSTRAINT fk_invoices_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_invoices_project_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE site_surveys
    ADD COLUMN project_site_id INT UNSIGNED NULL AFTER job_id,
    ADD KEY idx_site_surveys_project_site (project_site_id),
    ADD CONSTRAINT fk_site_surveys_project_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE sales_opportunities
    ADD COLUMN project_id INT UNSIGNED NULL AFTER customer_id,
    ADD KEY idx_opportunities_project (project_id),
    ADD CONSTRAINT fk_opportunities_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE;

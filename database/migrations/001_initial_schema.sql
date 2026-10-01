-- Sign-Forge Management System
-- Phase 1 schema
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
-- What this phase deliberately does not store
-- ---------------------------------------------------------------------------
-- Quotes, jobs, stock movements, recipes, purchase orders, invoices, payments.
-- Those get their own numbered migrations (002_quotes.sql and so on).
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

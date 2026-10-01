-- Sign-Forge Pricing & Quotation System
-- Phase 1 schema
--
-- Import this file into an EMPTY database. It drops the Sign-Forge tables
-- if they already exist. Do not import it over a live quoting database.
--
-- ---------------------------------------------------------------------------
-- How material math is stored
-- ---------------------------------------------------------------------------
-- ACTUAL measure
--   The size the customer asked for.
--   For area: (width_mm * height_mm / 1000000) * quantity
--
-- BILLABLE / CONSUMED measure
--   What the customer is charged for. This can be larger than the actual
--   measure when unused roll width is billed, or when a whole sheet is billed.
--
-- MANUFACTURING WASTE (standard_waste_percent)
--   A separate percentage applied after the billable measure is known.
--   Example: 2.00 m2 billable, 10% manufacturing waste, R50.00 per m2
--     cost = 2 * 1.10 * 50
--   This is offcut and handling waste. It is NOT unused roll width.
--
-- ROLL-WIDTH WASTE (waste_mode on the quote line)
--   ACTUAL          charge the printed area only
--   CONSUMED_WIDTH  charge roll width x print length
--   MANUAL          charge the width or area the operator typed
--
-- waste_threshold_percent only raises a warning. It never chooses a waste mode.
-- The operator decides with "charge wastage" or "don't charge wastage".
--
-- Selling price (every later service must use this same rule)
--   extended_cost    = billable_measure * unit_cost
--   cost_with_waste  = extended_cost * (1 + standard_waste_percent / 100)
--   sell_ex_vat      = cost_with_waste * (1 + markup_percent / 100)
--
-- Line sell prices are stored EXCLUDING VAT.
-- quotes.vat_mode says whether the printed quote adds VAT on top (EXCLUSIVE)
-- or presents a VAT-inclusive total (INCLUSIVE).
-- Discount is a currency amount taken off the ex-VAT subtotal before VAT.
--
-- Money totals use DECIMAL(12,2). Unit rates use DECIMAL(12,4) so a supplier
-- rate such as 12.3450 is not rounded away. Do not use FLOAT for either.
--
-- Historical quotes copy cost, waste, and markup onto the line.
-- Changing a product later must not change an old quote.
--
-- Not built yet, but the columns are here so we do not have to break this
-- schema later:
--   products.product_type = ASSEMBLY for a future recipe (vinyl + board + labour)
--   quote_items.parent_item_id for the component lines of that recipe
--   quote_items.nest_group so several cuts can share one length of roll
-- A future jobs table should point at quotes.id. Quote status CONVERTED is
-- the hand-off from quotation to production.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS quote_items;
DROP TABLE IF EXISTS quotes;
DROP TABLE IF EXISTS quote_sequences;
DROP TABLE IF EXISTS product_price_history;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS pricing_levels;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- Staff who can sign in. Deactivate a person; do not delete them,
-- because quotes remember who created them.
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    -- admin, sales, or production today. Stored as text so a new role
    -- does not need an ALTER TABLE. Allowed values are checked in PHP.
    role VARCHAR(32) NOT NULL DEFAULT 'sales',
    active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name),
    KEY idx_categories_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A product is one sellable thing: a roll, a sheet, a unit, an hour, a litre.
-- An assembly (a finished sign made of several products) is reserved for later.
-- Phase 1 only creates product_type = SIMPLE.
CREATE TABLE products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED NOT NULL,
    product_type ENUM('SIMPLE', 'ASSEMBLY') NOT NULL DEFAULT 'SIMPLE',
    sku VARCHAR(64) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    supplier VARCHAR(160) NULL,
    supplier_code VARCHAR(80) NULL,
    -- Cost per pricing unit: per m2, per metre, per sheet, per unit,
    -- per litre, or per hour. Four decimal places, not a float.
    cost_price DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    pricing_method ENUM(
        'AREA',
        'LINEAR_METRE',
        'UNIT',
        'SHEET',
        'LITRE',
        'HOUR',
        'CUSTOM'
    ) NOT NULL,
    roll_width_mm DECIMAL(10,2) NULL,
    sheet_width_mm DECIMAL(10,2) NULL,
    sheet_height_mm DECIMAL(10,2) NULL,
    -- Warning only. If the print is wider than this percent of the roll,
    -- the calculator asks the operator what to do. It does not auto-charge.
    waste_threshold_percent DECIMAL(5,2) NULL,
    default_waste_policy ENUM('ACTUAL', 'CONSUMED_WIDTH', 'MANUAL') NOT NULL DEFAULT 'ACTUAL',
    -- Manufacturing waste. Separate from unused roll width.
    standard_waste_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    allow_rotation TINYINT(1) NOT NULL DEFAULT 0,
    allow_nesting TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_category (category_id),
    KEY idx_products_supplier (supplier),
    KEY idx_products_active (active),
    KEY idx_products_name (name),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Written by the product screen when cost_price changes. Not a database
-- trigger, so the behaviour stays visible in the PHP that saves a product.
CREATE TABLE product_price_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    old_cost DECIMAL(12,4) NOT NULL,
    new_cost DECIMAL(12,4) NOT NULL,
    changed_by INT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_price_history_product (product_id, changed_at),
    CONSTRAINT fk_price_history_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_price_history_user
        FOREIGN KEY (changed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Q1-Q4 to start. Rename them later (Retail, Trade, Wholesale) in the
-- admin screen. Markup lives here, never as a hard-coded percentage.
CREATE TABLE pricing_levels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    code VARCHAR(20) NOT NULL,
    markup_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pricing_levels_code (code),
    KEY idx_pricing_levels_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(180) NOT NULL,
    contact_name VARCHAR(120) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    vat_number VARCHAR(40) NULL,
    address TEXT NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customers_company (company_name),
    KEY idx_customers_email (email),
    KEY idx_customers_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per year. The quote-number service will lock the row
-- (SELECT ... FOR UPDATE) and add 1, so two staff cannot get SFQ-2026-0007.
CREATE TABLE quote_sequences (
    year_num SMALLINT UNSIGNED NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (year_num)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_number VARCHAR(32) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    quote_date DATE NOT NULL,
    expiry_date DATE NULL,
    status ENUM(
        'DRAFT',
        'SENT',
        'ACCEPTED',
        'DECLINED',
        'EXPIRED',
        'CONVERTED'
    ) NOT NULL DEFAULT 'DRAFT',
    pricing_level_id INT UNSIGNED NULL,
    -- Copied at save time so renaming a level does not rewrite old quotes.
    pricing_level_code VARCHAR(20) NULL,
    pricing_level_name VARCHAR(80) NULL,
    markup_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    -- Currency amount taken off the ex-VAT subtotal before VAT is calculated.
    discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vat DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    -- Snapshots. The settings screen can change the company VAT rate later
    -- without changing a quote that was already issued.
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    vat_mode ENUM('EXCLUSIVE', 'INCLUSIVE') NOT NULL DEFAULT 'EXCLUSIVE',
    currency_code CHAR(3) NOT NULL DEFAULT 'ZAR',
    notes TEXT NULL,
    internal_notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quotes_number (quote_number),
    KEY idx_quotes_customer (customer_id),
    KEY idx_quotes_status (status),
    KEY idx_quotes_date (quote_date),
    KEY idx_quotes_created_by (created_by),
    CONSTRAINT fk_quotes_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_level
        FOREIGN KEY (pricing_level_id) REFERENCES pricing_levels (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_quotes_user
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One priced line. The numbers on this row are a snapshot.
-- Do not recalculate an old line from the live product cost.
--
-- How to read quantity columns:
--   AREA and area-priced boards
--     actual_area, billable_area, and waste_area are square metres.
--     actual_quantity, billable_quantity, and waste_quantity repeat those
--     same square-metre values. The calculator must keep them in step.
--   LINEAR_METRE   quantities are metres
--   UNIT           quantities are units
--   SHEET          quantities are sheets (billable may be whole sheets)
--   LITRE          quantities are litres
--   HOUR           quantities are hours
--   CUSTOM         quantities use whatever the description says
-- Area columns stay NULL when the method is not based on area.
CREATE TABLE quote_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id INT UNSIGNED NOT NULL,
    -- Null only for a future assembly component that is typed freehand.
    -- Products are deactivated, not deleted, so normal lines keep the link.
    product_id INT UNSIGNED NULL,
    parent_item_id INT UNSIGNED NULL,
    nest_group VARCHAR(40) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    sku_snapshot VARCHAR(64) NULL,
    product_name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    pricing_method ENUM(
        'AREA',
        'LINEAR_METRE',
        'UNIT',
        'SHEET',
        'LITRE',
        'HOUR',
        'CUSTOM'
    ) NOT NULL,
    width_mm DECIMAL(10,2) NULL,
    height_mm DECIMAL(10,2) NULL,
    length_mm DECIMAL(10,2) NULL,
    quantity DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
    roll_width_mm DECIMAL(10,2) NULL,
    sheet_width_mm DECIMAL(10,2) NULL,
    sheet_height_mm DECIMAL(10,2) NULL,
    waste_mode ENUM('ACTUAL', 'CONSUMED_WIDTH', 'MANUAL') NULL,
    -- What the operator typed when waste_mode is MANUAL.
    manual_billable_width_mm DECIMAL(10,2) NULL,
    manual_billable_area DECIMAL(12,4) NULL,
    actual_area DECIMAL(12,4) NULL,
    billable_area DECIMAL(12,4) NULL,
    waste_area DECIMAL(12,4) NULL,
    actual_quantity DECIMAL(12,4) NULL,
    billable_quantity DECIMAL(12,4) NULL,
    waste_quantity DECIMAL(12,4) NULL,
    waste_threshold_percent_snapshot DECIMAL(5,2) NULL,
    allow_rotation_snapshot TINYINT(1) NOT NULL DEFAULT 0,
    allow_nesting_snapshot TINYINT(1) NOT NULL DEFAULT 0,
    unit_cost_snapshot DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    standard_waste_percent_snapshot DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    markup_percent_snapshot DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    pricing_level_id INT UNSIGNED NULL,
    -- Ex-VAT sell price for one billable unit, then the ex-VAT line total.
    unit_sell_price DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quote_items_quote (quote_id, sort_order),
    KEY idx_quote_items_product (product_id),
    KEY idx_quote_items_parent (parent_item_id),
    KEY idx_quote_items_nest (nest_group),
    CONSTRAINT fk_quote_items_quote
        FOREIGN KEY (quote_id) REFERENCES quotes (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_parent
        FOREIGN KEY (parent_item_id) REFERENCES quote_items (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quote_items_level
        FOREIGN KEY (pricing_level_id) REFERENCES pricing_levels (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company profile, VAT, currency, and quote numbering.
-- One row per setting so a new option does not need a new column.
CREATE TABLE settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

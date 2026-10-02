-- v1.1 Phase 6 customer hub. Extends the existing portal user. Does not create a second customer.

ALTER TABLE portal_users
    ADD COLUMN hub_role VARCHAR(40) NOT NULL DEFAULT 'ADMIN' AFTER active,
    ADD COLUMN display_name VARCHAR(120) NULL AFTER hub_role,
    ADD COLUMN phone VARCHAR(40) NULL AFTER display_name,
    ADD COLUMN site_scope VARCHAR(20) NOT NULL DEFAULT 'ALL' AFTER phone,
    ADD COLUMN custom_permissions VARCHAR(500) NULL AFTER site_scope;

ALTER TABLE portal_messages
    ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'CUSTOMER' AFTER body;

CREATE TABLE portal_user_sites (
    portal_user_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (portal_user_id, project_site_id),
    KEY idx_portal_user_sites_site (project_site_id),
    CONSTRAINT fk_portal_user_sites_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_user_sites_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_user_projects (
    portal_user_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (portal_user_id, project_id),
    KEY idx_portal_user_projects_project (project_id),
    CONSTRAINT fk_portal_user_projects_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_user_projects_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_catalogues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    valid_from DATE NULL,
    valid_until DATE NULL,
    pricing_mode VARCHAR(40) NOT NULL DEFAULT 'QUOTE_REQUIRED',
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer_catalogues_customer (customer_id, status),
    CONSTRAINT fk_customer_catalogues_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_catalogue_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    catalogue_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    specification_id INT UNSIGNED NULL,
    customer_code VARCHAR(80) NULL,
    internal_code VARCHAR(80) NULL,
    name VARCHAR(180) NOT NULL,
    description VARCHAR(500) NULL,
    locked_config_json JSON NULL,
    allowed_variables_json JSON NULL,
    price_visibility VARCHAR(30) NOT NULL DEFAULT 'SHOW_PRICE',
    availability_label VARCHAR(40) NOT NULL DEFAULT 'MADE_TO_ORDER',
    status VARCHAR(30) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_catalogue_items_catalogue (catalogue_id, status),
    KEY idx_catalogue_items_product (product_id),
    KEY idx_catalogue_items_code (customer_code),
    CONSTRAINT fk_catalogue_items_catalogue FOREIGN KEY (catalogue_id) REFERENCES customer_catalogues (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_catalogue_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_catalogue_prices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    catalogue_item_id INT UNSIGNED NOT NULL,
    price_ex_vat DECIMAL(14,4) NOT NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'each',
    currency_code VARCHAR(3) NOT NULL DEFAULT 'ZAR',
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    min_quantity DECIMAL(14,4) NULL,
    max_quantity DECIMAL(14,4) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_catalogue_prices_item (catalogue_item_id, effective_from, effective_to),
    CONSTRAINT fk_catalogue_prices_item FOREIGN KEY (catalogue_item_id) REFERENCES customer_catalogue_items (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_catalogue_bundles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    catalogue_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    pricing_mode VARCHAR(40) NOT NULL DEFAULT 'SUM',
    fixed_price_ex_vat DECIMAL(14,4) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    PRIMARY KEY (id),
    KEY idx_catalogue_bundles_catalogue (catalogue_id),
    CONSTRAINT fk_catalogue_bundles_catalogue FOREIGN KEY (catalogue_id) REFERENCES customer_catalogues (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_catalogue_bundle_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bundle_id INT UNSIGNED NOT NULL,
    catalogue_item_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    PRIMARY KEY (id),
    KEY idx_bundle_items_bundle (bundle_id),
    CONSTRAINT fk_bundle_items_bundle FOREIGN KEY (bundle_id) REFERENCES customer_catalogue_bundles (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bundle_items_item FOREIGN KEY (catalogue_item_id) REFERENCES customer_catalogue_items (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    order_kind VARCHAR(40) NOT NULL DEFAULT 'CATALOGUE',
    status VARCHAR(40) NOT NULL DEFAULT 'DRAFT',
    customer_po VARCHAR(80) NULL,
    cost_centre VARCHAR(80) NULL,
    department VARCHAR(80) NULL,
    branch_reference VARCHAR(80) NULL,
    campaign_code VARCHAR(80) NULL,
    idempotency_key VARCHAR(80) NULL,
    requested_date DATE NULL,
    confirmed_date DATE NULL,
    notes VARCHAR(1000) NULL,
    submitted_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    first_response_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_orders_number (order_number),
    UNIQUE KEY uq_customer_orders_idem (idempotency_key),
    KEY idx_customer_orders_customer (customer_id, status, created_at),
    KEY idx_customer_orders_user (portal_user_id),
    KEY idx_customer_orders_po (customer_po),
    CONSTRAINT fk_customer_orders_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_customer_orders_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_order_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id INT UNSIGNED NOT NULL,
    catalogue_item_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    artwork_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    unit_price_ex_vat DECIMAL(14,4) NULL,
    vat_amount DECIMAL(14,2) NULL,
    line_total DECIMAL(14,2) NULL,
    pricing_source VARCHAR(40) NOT NULL DEFAULT 'QUOTE_REQUIRED',
    configuration_json JSON NULL,
    customer_reference VARCHAR(80) NULL,
    notes VARCHAR(500) NULL,
    PRIMARY KEY (id),
    KEY idx_customer_order_items_order (order_id),
    KEY idx_customer_order_items_catalogue (catalogue_item_id),
    CONSTRAINT fk_customer_order_items_order FOREIGN KEY (order_id) REFERENCES customer_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_order_sites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_item_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    required_date DATE NULL,
    contact_name VARCHAR(120) NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_customer_order_sites_item (order_item_id),
    KEY idx_customer_order_sites_site (project_site_id),
    CONSTRAINT fk_customer_order_sites_item FOREIGN KEY (order_item_id) REFERENCES customer_order_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_customer_order_sites_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_quote_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    request_type VARCHAR(40) NOT NULL,
    payload_json JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'SUBMITTED',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quote_requests_customer (customer_id, created_at),
    CONSTRAINT fk_quote_requests_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_order_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    items_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_templates_customer (customer_id),
    CONSTRAINT fk_order_templates_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_favourites (
    portal_user_id INT UNSIGNED NOT NULL,
    catalogue_item_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (portal_user_id, catalogue_item_id),
    CONSTRAINT fk_favourites_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_favourites_item FOREIGN KEY (catalogue_item_id) REFERENCES customer_catalogue_items (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_brand_colours (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    pantone VARCHAR(40) NULL,
    cmyk VARCHAR(40) NULL,
    rgb VARCHAR(40) NULL,
    ral VARCHAR(40) NULL,
    vinyl_code VARCHAR(40) NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_brand_colours_customer (customer_id),
    CONSTRAINT fk_brand_colours_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_artwork_library (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'APPROVED',
    artwork_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_artwork_library_customer (customer_id, status),
    CONSTRAINT fk_artwork_library_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_cancellation_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    customer_order_id INT UNSIGNED NULL,
    reason VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'REQUESTED',
    impact VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cancel_requests_customer (customer_id),
    KEY idx_cancel_requests_job (job_id),
    CONSTRAINT fk_cancel_requests_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE portal_site_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    site_name VARCHAR(180) NOT NULL,
    address VARCHAR(255) NULL,
    branch_code VARCHAR(40) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    duplicate_warning VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_site_requests_customer (customer_id, status),
    CONSTRAINT fk_site_requests_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (code, name, module) VALUES
('customer_hub.view', 'View portal orders and requests', 'sales'),
('customer_catalogues.manage', 'Manage customer catalogues', 'sales'),
('customer_orders.review', 'Review a customer portal order', 'sales');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.code IN (
    'customer_hub.view', 'customer_catalogues.manage', 'customer_orders.review'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('customer_hub.view', 'customer_orders.review');

INSERT INTO settings (setting_key, setting_value) VALUES
('customer_order_prefix', 'SFCO'),
('artwork_approval_responsibility', 'Check spelling, contact details, colours, and any dimensions you supplied before you approve.'),
('catalogue_margin_alert_percent', '15'),
('expired_price_policy', 'QUOTE_REQUIRED')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

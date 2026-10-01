-- v1.1 Phase 2: customer assets, warranties, service, and maintenance.
-- An asset is the physical item at a site. It is not a product and not a job item.
-- Jobs, stock, and invoices stay on their existing tables.

CREATE TABLE asset_types (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_problem_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_problem_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    asset_type_id INT UNSIGNED NULL,
    interval_days INT UNSIGNED NOT NULL DEFAULT 0,
    interval_months INT UNSIGNED NOT NULL DEFAULT 0,
    checklist_name VARCHAR(160) NULL,
    recipe_id INT UNSIGNED NULL,
    auto_request TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_maintenance_plans_type (asset_type_id),
    KEY idx_maintenance_plans_recipe (recipe_id),
    CONSTRAINT fk_maintenance_plans_type FOREIGN KEY (asset_type_id) REFERENCES asset_types (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_maintenance_plans_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NULL,
    project_site_id INT UNSIGNED NULL,
    original_job_id INT UNSIGNED NULL,
    original_job_item_id INT UNSIGNED NULL,
    asset_type_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    manufacturer VARCHAR(120) NULL,
    model VARCHAR(120) NULL,
    serial_number VARCHAR(80) NULL,
    customer_asset_reference VARCHAR(80) NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'SIGN_FORGE',
    status VARCHAR(30) NOT NULL DEFAULT 'PLANNED',
    health VARCHAR(30) NOT NULL DEFAULT 'UNKNOWN',
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    track_mode VARCHAR(20) NOT NULL DEFAULT 'INDIVIDUAL',
    installation_date DATE NULL,
    commissioned_date DATE NULL,
    warranty_start_date DATE NULL,
    warranty_end_date DATE NULL,
    expected_service_interval_days INT UNSIGNED NULL,
    next_service_date DATE NULL,
    last_service_date DATE NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    gps_accuracy_m DECIMAL(8,2) NULL,
    gps_captured_at TIMESTAMP NULL DEFAULT NULL,
    location_description VARCHAR(255) NULL,
    fleet_number VARCHAR(40) NULL,
    registration VARCHAR(20) NULL,
    vehicle_make VARCHAR(40) NULL,
    vehicle_model VARCHAR(40) NULL,
    vehicle_year SMALLINT UNSIGNED NULL,
    original_commercial_value DECIMAL(14,2) NULL,
    original_internal_cost DECIMAL(14,2) NULL,
    replaces_asset_id INT UNSIGNED NULL,
    replacement_asset_id INT UNSIGNED NULL,
    removed_at DATE NULL,
    removal_reason VARCHAR(255) NULL,
    disposition VARCHAR(80) NULL,
    tracking_token VARCHAR(64) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_assets_number (asset_number),
    UNIQUE KEY uq_customer_assets_token (tracking_token),
    KEY idx_customer_assets_customer (customer_id, status),
    KEY idx_customer_assets_project (project_id),
    KEY idx_customer_assets_site (project_site_id),
    KEY idx_customer_assets_job (original_job_id),
    KEY idx_customer_assets_item (original_job_item_id),
    KEY idx_customer_assets_status (status),
    KEY idx_customer_assets_service (next_service_date),
    KEY idx_customer_assets_serial (serial_number),
    KEY idx_customer_assets_registration (registration),
    CONSTRAINT fk_customer_assets_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_customer_assets_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_customer_assets_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_customer_assets_job FOREIGN KEY (original_job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_customer_assets_item FOREIGN KEY (original_job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_customer_assets_type FOREIGN KEY (asset_type_id) REFERENCES asset_types (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_customer_assets_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE customer_assets
    ADD CONSTRAINT fk_customer_assets_replaces FOREIGN KEY (replaces_asset_id) REFERENCES customer_assets (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_customer_assets_replacement FOREIGN KEY (replacement_asset_id) REFERENCES customer_assets (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE asset_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    component_type VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    product_id INT UNSIGNED NULL,
    inventory_item_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    manufacturer VARCHAR(120) NULL,
    model VARCHAR(120) NULL,
    serial_number VARCHAR(80) NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    installed_date DATE NULL,
    warranty_start_date DATE NULL,
    warranty_end_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    replaces_component_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_components_asset (asset_id, status),
    KEY idx_asset_components_serial (serial_number),
    KEY idx_asset_components_product (product_id),
    CONSTRAINT fk_asset_components_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asset_components_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_asset_components_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE asset_components
    ADD CONSTRAINT fk_asset_components_replaces FOREIGN KEY (replaces_component_id) REFERENCES asset_components (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE asset_warranties (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    asset_component_id INT UNSIGNED NULL,
    warranty_type VARCHAR(40) NOT NULL,
    provider_type VARCHAR(40) NOT NULL DEFAULT 'SIGN_FORGE',
    supplier_id INT UNSIGNED NULL,
    provider_name VARCHAR(160) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    terms TEXT NULL,
    exclusions TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_warranties_asset (asset_id, end_date),
    KEY idx_asset_warranties_end (end_date, status),
    CONSTRAINT fk_asset_warranties_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asset_warranties_component FOREIGN KEY (asset_component_id) REFERENCES asset_components (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_asset_warranties_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    project_site_id INT UNSIGNED NULL,
    asset_id INT UNSIGNED NULL,
    asset_component_id INT UNSIGNED NULL,
    reported_by VARCHAR(120) NULL,
    contact_detail VARCHAR(160) NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'PHONE',
    problem_category VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    description TEXT NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
    status VARCHAR(30) NOT NULL DEFAULT 'NEW',
    classification VARCHAR(30) NULL,
    warranty_candidate TINYINT(1) NOT NULL DEFAULT 0,
    assigned_user_id INT UNSIGNED NULL,
    reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    first_response_at TIMESTAMP NULL DEFAULT NULL,
    assessed_at TIMESTAMP NULL DEFAULT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    work_performed TEXT NULL,
    outstanding_issue TEXT NULL,
    recommendations TEXT NULL,
    customer_signoff_name VARCHAR(120) NULL,
    signed_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_requests_number (request_number),
    KEY idx_service_requests_customer (customer_id, status),
    KEY idx_service_requests_asset (asset_id),
    KEY idx_service_requests_status (status, reported_at),
    KEY idx_service_requests_reported (reported_at),
    CONSTRAINT fk_service_requests_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_service_requests_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_service_requests_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_service_requests_component FOREIGN KEY (asset_component_id) REFERENCES asset_components (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_service_requests_user FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warranty_claims (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    claim_number VARCHAR(40) NOT NULL,
    asset_id INT UNSIGNED NOT NULL,
    asset_component_id INT UNSIGNED NULL,
    warranty_id INT UNSIGNED NULL,
    service_request_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'NEW',
    reported_date DATE NOT NULL,
    failure_description TEXT NOT NULL,
    assessment TEXT NULL,
    claim_outcome VARCHAR(40) NULL,
    supplier_reference VARCHAR(80) NULL,
    cost_recovery_amount DECIMAL(14,2) NULL,
    recovered_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_warranty_claims_number (claim_number),
    KEY idx_warranty_claims_asset (asset_id, status),
    KEY idx_warranty_claims_status (status),
    CONSTRAINT fk_warranty_claims_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_warranty_claims_component FOREIGN KEY (asset_component_id) REFERENCES asset_components (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_warranty_claims_warranty FOREIGN KEY (warranty_id) REFERENCES asset_warranties (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_warranty_claims_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_warranty_claims_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_inspections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    service_request_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    inspection_type VARCHAR(30) NOT NULL DEFAULT 'ROUTINE',
    result VARCHAR(30) NOT NULL DEFAULT 'PASS',
    findings TEXT NULL,
    recommendations TEXT NULL,
    inspected_on DATE NOT NULL,
    technician_user_id INT UNSIGNED NULL,
    customer_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_inspections_asset (asset_id, inspected_on),
    CONSTRAINT fk_asset_inspections_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asset_inspections_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_asset_inspections_user FOREIGN KEY (technician_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_inspection_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inspection_id INT UNSIGNED NOT NULL,
    label VARCHAR(180) NOT NULL,
    result VARCHAR(30) NOT NULL DEFAULT 'PASS',
    note VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_inspection_items_inspection (inspection_id),
    CONSTRAINT fk_inspection_items_inspection FOREIGN KEY (inspection_id) REFERENCES asset_inspections (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_plan_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    last_completed_on DATE NULL,
    next_due_on DATE NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_plan (asset_id, plan_id),
    KEY idx_asset_plan_due (next_due_on, active),
    CONSTRAINT fk_asset_plan_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asset_plan_plan FOREIGN KEY (plan_id) REFERENCES maintenance_plans (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_agreements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    agreement_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    description TEXT NULL,
    included_services TEXT NULL,
    response_target_hours INT UNSIGNED NULL,
    billing_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_agreements_number (agreement_number),
    KEY idx_service_agreements_customer (customer_id, status),
    CONSTRAINT fk_service_agreements_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_service_agreements_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_location_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    from_site_id INT UNSIGNED NULL,
    to_site_id INT UNSIGNED NULL,
    from_location VARCHAR(255) NULL,
    to_location VARCHAR(255) NULL,
    reason VARCHAR(180) NULL,
    job_id INT UNSIGNED NULL,
    changed_by INT UNSIGNED NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_asset_location_asset (asset_id, changed_at),
    CONSTRAINT fk_asset_location_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asset_location_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    asset_id INT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    summary VARCHAR(255) NOT NULL,
    related_type VARCHAR(40) NULL,
    related_id INT UNSIGNED NULL,
    happened_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_asset_events_asset (asset_id, happened_at),
    CONSTRAINT fk_asset_events_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_part_usages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_uuid VARCHAR(64) NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    asset_id INT UNSIGNED NOT NULL,
    old_component_id INT UNSIGNED NULL,
    new_component_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    quantity DECIMAL(14,4) NOT NULL,
    stock_movement_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_service_part_uuid (operation_uuid),
    KEY idx_service_part_job (job_id),
    CONSTRAINT fk_service_part_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_service_part_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE asset_sync_operations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_uuid VARCHAR(64) NOT NULL,
    asset_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    kind VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asset_sync_uuid (operation_uuid),
    KEY idx_asset_sync_asset (asset_id),
    CONSTRAINT fk_asset_sync_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asset_sync_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE products
    ADD COLUMN creates_customer_asset TINYINT(1) NOT NULL DEFAULT 0 AFTER track_stock;

ALTER TABLE jobs
    ADD COLUMN job_type VARCHAR(30) NOT NULL DEFAULT 'STANDARD' AFTER rollout_state,
    ADD COLUMN service_request_id INT UNSIGNED NULL AFTER job_type,
    ADD COLUMN customer_asset_id INT UNSIGNED NULL AFTER service_request_id,
    ADD COLUMN asset_component_id INT UNSIGNED NULL AFTER customer_asset_id,
    ADD COLUMN warranty_claim_id INT UNSIGNED NULL AFTER asset_component_id,
    ADD COLUMN service_classification VARCHAR(30) NULL AFTER warranty_claim_id,
    ADD COLUMN service_reason VARCHAR(255) NULL AFTER service_classification,
    ADD KEY idx_jobs_type (job_type),
    ADD KEY idx_jobs_asset (customer_asset_id),
    ADD KEY idx_jobs_service_request (service_request_id),
    ADD CONSTRAINT fk_jobs_service_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_jobs_asset FOREIGN KEY (customer_asset_id) REFERENCES customer_assets (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_jobs_asset_component FOREIGN KEY (asset_component_id) REFERENCES asset_components (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_jobs_warranty_claim FOREIGN KEY (warranty_claim_id) REFERENCES warranty_claims (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE quotes
    ADD COLUMN service_request_id INT UNSIGNED NULL AFTER project_site_id,
    ADD COLUMN customer_asset_id INT UNSIGNED NULL AFTER service_request_id,
    ADD KEY idx_quotes_service_request (service_request_id),
    ADD KEY idx_quotes_asset (customer_asset_id),
    ADD CONSTRAINT fk_quotes_service_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_quotes_asset FOREIGN KEY (customer_asset_id) REFERENCES customer_assets (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE asset_inspections
    ADD CONSTRAINT fk_asset_inspections_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO asset_types (code, name) VALUES
('PYLON', 'Pylon'),
('LIGHTBOX', 'Lightbox'),
('CHANNEL_LETTERS', 'Channel letters'),
('FASCIA_SIGN', 'Fascia sign'),
('ACM_SIGN', 'ACM sign'),
('CHROMADEK_SIGN', 'Chromadek sign'),
('WAYFINDING', 'Wayfinding'),
('WINDOW_GRAPHICS', 'Window graphics'),
('VEHICLE_BRANDING', 'Vehicle branding'),
('BILLBOARD', 'Billboard'),
('LED_DISPLAY', 'LED display'),
('NEON_SIGN', 'Neon sign'),
('SAFETY_SIGNAGE', 'Safety signage'),
('DIRECTORY_BOARD', 'Directory board'),
('CUSTOM', 'Custom');

INSERT INTO service_problem_categories (code, name) VALUES
('LIGHTING_FAILURE', 'Lighting failure'),
('POWER_SUPPLY', 'Power supply'),
('STRUCTURAL', 'Structural'),
('FACE_DAMAGE', 'Face damage'),
('VINYL_FAILURE', 'Vinyl failure'),
('PEELING', 'Peeling'),
('FADING', 'Fading'),
('WATER_INGRESS', 'Water ingress'),
('ELECTRICAL', 'Electrical'),
('MOUNTING', 'Mounting'),
('STORM_DAMAGE', 'Storm damage'),
('VANDALISM', 'Vandalism'),
('VEHICLE_DAMAGE', 'Vehicle damage'),
('ARTWORK', 'Artwork'),
('OTHER', 'Other');

INSERT INTO maintenance_plans (name, interval_months, checklist_name, auto_request) VALUES
('Annual inspection', 12, 'Structure, face, lighting, electrical enclosure', 0),
('Six-month clean', 6, 'Clean face and check fasteners', 0);

INSERT INTO permissions (code, name, module) VALUES
('assets.view', 'View customer assets', 'assets'),
('assets.create', 'Create customer assets', 'assets'),
('assets.edit', 'Edit customer assets', 'assets'),
('assets.archive', 'Archive customer assets', 'assets'),
('assets.view_costs', 'View asset and service costs', 'assets'),
('assets.manage_components', 'Manage asset components', 'assets'),
('assets.manage_warranties', 'Manage warranties', 'assets'),
('assets.generate_labels', 'Print asset labels', 'assets'),
('assets.import', 'Import assets', 'assets'),
('service_requests.view', 'View service requests', 'service'),
('service_requests.create', 'Create service requests', 'service'),
('service_requests.assign', 'Assign service requests', 'service'),
('service_requests.manage', 'Manage service requests', 'service'),
('service_jobs.manage', 'Create service jobs', 'service'),
('service.view_costs', 'View service costs', 'service'),
('warranty_claims.view', 'View warranty claims', 'service'),
('warranty_claims.manage', 'Manage warranty claims', 'service'),
('inspections.perform', 'Record inspections', 'service'),
('inspections.manage', 'Manage inspections', 'service'),
('service_reports.generate', 'Generate service reports', 'service');
-- maintenance.manage already exists from resource planning and is reused for asset plans.

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.module IN ('assets', 'service');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
    'assets.view', 'assets.create', 'service_requests.view', 'service_requests.create', 'warranty_claims.view'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN (
    'assets.view', 'assets.view_costs', 'service.view_costs', 'warranty_claims.view', 'service_requests.view'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('DESIGN', 'PRODUCTION') AND p.code = 'assets.view';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN (
    'assets.view', 'service_requests.view', 'inspections.perform'
);

INSERT INTO settings (setting_key, setting_value) VALUES
('asset_prefix', 'SFA'),
('service_request_prefix', 'SFSR'),
('warranty_claim_prefix', 'SFWC'),
('service_agreement_prefix', 'SFM'),
('warranty_alert_days', '30'),
('asset_label_contact', 'Sign-Forge service'),
('service_manager_user_id', '');

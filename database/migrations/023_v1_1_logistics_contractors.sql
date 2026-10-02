-- v1.1 Phase 8 logistics, couriers, contractors, and field fulfilment.
-- Extends packages, fulfilment, installations, and snags. Does not replace them.

ALTER TABLE fulfilment_requirements
    ADD COLUMN dispatched_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER fulfilled_quantity,
    ADD COLUMN delivered_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER dispatched_quantity,
    ADD COLUMN installed_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER delivered_quantity;

ALTER TABLE packages
    ADD COLUMN project_site_id INT UNSIGNED NULL AFTER job_id,
    ADD COLUMN package_type VARCHAR(30) NOT NULL DEFAULT 'CARTON' AFTER description,
    ADD COLUMN length_mm DECIMAL(10,2) NULL AFTER package_type,
    ADD COLUMN width_mm DECIMAL(10,2) NULL AFTER length_mm,
    ADD COLUMN height_mm DECIMAL(10,2) NULL AFTER width_mm,
    ADD COLUMN weight_kg DECIMAL(10,3) NULL AFTER height_mm,
    ADD COLUMN fragile TINYINT(1) NOT NULL DEFAULT 0 AFTER weight_kg,
    ADD COLUMN special_handling VARCHAR(255) NULL AFTER fragile,
    ADD COLUMN photo_path VARCHAR(255) NULL AFTER special_handling,
    ADD COLUMN sequence_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER photo_path,
    ADD COLUMN sequence_total INT UNSIGNED NOT NULL DEFAULT 1 AFTER sequence_no,
    ADD COLUMN verified_at DATETIME NULL AFTER sequence_total,
    ADD COLUMN verified_by INT UNSIGNED NULL AFTER verified_at,
    ADD KEY idx_packages_site (project_site_id);

ALTER TABLE job_snags
    ADD COLUMN snag_type VARCHAR(30) NOT NULL DEFAULT 'OTHER' AFTER description,
    ADD COLUMN severity VARCHAR(20) NOT NULL DEFAULT 'MINOR' AFTER snag_type,
    ADD COLUMN source_category VARCHAR(40) NULL AFTER severity,
    ADD COLUMN project_site_id INT UNSIGNED NULL AFTER installation_id,
    ADD KEY idx_job_snags_severity (severity, status);

ALTER TABLE installation_checklist_templates
    ADD COLUMN applies_to VARCHAR(40) NOT NULL DEFAULT 'ANY' AFTER name,
    ADD COLUMN applies_value VARCHAR(80) NULL AFTER applies_to;

ALTER TABLE job_installations
    ADD COLUMN project_id INT UNSIGNED NULL AFTER job_id,
    ADD COLUMN project_site_id INT UNSIGNED NULL AFTER project_id,
    ADD COLUMN fulfilment_requirement_id INT UNSIGNED NULL AFTER project_site_id,
    ADD COLUMN service_request_id INT UNSIGNED NULL AFTER fulfilment_requirement_id,
    ADD COLUMN arrived_at DATETIME NULL AFTER arrival_time,
    ADD COLUMN arrival_latitude DECIMAL(10,7) NULL AFTER arrived_at,
    ADD COLUMN arrival_longitude DECIMAL(10,7) NULL AFTER arrival_latitude,
    ADD COLUMN device_started_at DATETIME NULL AFTER started_at,
    ADD COLUMN blocked_reason VARCHAR(40) NULL AFTER status,
    ADD COLUMN signoff_role VARCHAR(80) NULL AFTER customer_signoff_name,
    ADD COLUMN signoff_statement_version VARCHAR(20) NULL AFTER signoff_role,
    ADD COLUMN signoff_signature_id INT UNSIGNED NULL AFTER signoff_statement_version,
    ADD KEY idx_job_installations_project (project_id),
    ADD KEY idx_job_installations_site (project_site_id);

ALTER TABLE service_requests
    ADD COLUMN assigned_contractor_id INT UNSIGNED NULL AFTER assigned_user_id,
    ADD COLUMN customer_charge DECIMAL(14,2) NULL AFTER classification,
    ADD COLUMN internal_cost DECIMAL(14,2) NULL AFTER customer_charge,
    ADD COLUMN recovery_amount DECIMAL(14,2) NULL AFTER internal_cost;

CREATE TABLE couriers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(180) NOT NULL,
    account_reference VARCHAR(80) NULL,
    contact_name VARCHAR(120) NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(40) NULL,
    tracking_url_template VARCHAR(255) NULL,
    integration_type VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_couriers_active (active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shipment_number VARCHAR(40) NOT NULL,
    shipment_type VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    customer_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    project_site_id INT UNSIGNED NULL,
    dispatch_id INT UNSIGNED NULL,
    destination_address TEXT NULL,
    contact_name VARCHAR(120) NULL,
    contact_phone VARCHAR(40) NULL,
    required_date DATE NULL,
    courier_id INT UNSIGNED NULL,
    waybill_number VARCHAR(80) NULL,
    tracking_number VARCHAR(80) NULL,
    label_path VARCHAR(255) NULL,
    booked_at DATETIME NULL,
    collection_date DATE NULL,
    expected_delivery DATE NULL,
    actual_delivery DATETIME NULL,
    estimated_courier_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_courier_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customer_delivery_charge DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    vehicle_resource_id INT UNSIGNED NULL,
    driver_user_id INT UNSIGNED NULL,
    team_id INT UNSIGNED NULL,
    override_reason VARCHAR(255) NULL,
    internal_notes TEXT NULL,
    customer_note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shipments_number (shipment_number),
    KEY idx_shipments_status (status, required_date),
    KEY idx_shipments_tracking (tracking_number),
    KEY idx_shipments_waybill (waybill_number),
    KEY idx_shipments_job (job_id),
    KEY idx_shipments_project (project_id),
    KEY idx_shipments_site (project_site_id),
    KEY idx_shipments_customer (customer_id),
    KEY idx_shipments_created (created_at),
    KEY idx_shipments_courier (courier_id),
    CONSTRAINT fk_shipments_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_shipments_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_shipments_courier FOREIGN KEY (courier_id) REFERENCES couriers (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE packages
    ADD COLUMN shipment_id INT UNSIGNED NULL AFTER job_id,
    ADD KEY idx_packages_shipment (shipment_id),
    ADD CONSTRAINT fk_packages_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE dispatches
    ADD COLUMN shipment_id INT UNSIGNED NULL AFTER job_id,
    ADD KEY idx_dispatches_shipment (shipment_id),
    ADD CONSTRAINT fk_dispatches_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE shipment_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shipment_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    fulfilment_requirement_id INT UNSIGNED NULL,
    package_id INT UNSIGNED NULL,
    quantity DECIMAL(14,4) NOT NULL,
    description VARCHAR(255) NOT NULL,
    tracking_code VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_shipment_items_shipment (shipment_id),
    KEY idx_shipment_items_job (job_id),
    KEY idx_shipment_items_item (job_item_id),
    KEY idx_shipment_items_fulfilment (fulfilment_requirement_id),
    KEY idx_shipment_items_package (package_id),
    CONSTRAINT fk_shipment_items_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_shipment_items_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipment_tracking_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shipment_id INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    event_time DATETIME NOT NULL,
    location_text VARCHAR(180) NULL,
    description VARCHAR(255) NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'MANUAL',
    external_event_id VARCHAR(80) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tracking_external (shipment_id, external_event_id),
    KEY idx_tracking_shipment (shipment_id, event_time),
    KEY idx_tracking_status (status),
    CONSTRAINT fk_tracking_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipment_access_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shipment_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shipment_token_hash (token_hash),
    KEY idx_shipment_tokens_shipment (shipment_id),
    CONSTRAINT fk_shipment_tokens_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipment_pods (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shipment_id INT UNSIGNED NOT NULL,
    recipient_name VARCHAR(120) NOT NULL,
    recipient_role VARCHAR(80) NULL,
    recipient_contact VARCHAR(80) NULL,
    statement_version VARCHAR(20) NOT NULL DEFAULT 'POD-1',
    signature_id INT UNSIGNED NULL,
    photo_path VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    device_signed_at DATETIME NULL,
    signed_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shipment_pods (shipment_id),
    CONSTRAINT fk_shipment_pods_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE collection_handovers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shipment_id INT UNSIGNED NOT NULL,
    collected_by VARCHAR(120) NOT NULL,
    contact_detail VARCHAR(80) NULL,
    vehicle_registration VARCHAR(20) NULL,
    signature_id INT UNSIGNED NULL,
    device_signed_at DATETIME NULL,
    collected_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_collection_handover (shipment_id),
    CONSTRAINT fk_collection_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE delivery_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_date DATE NOT NULL,
    vehicle_resource_id INT UNSIGNED NULL,
    driver_user_id INT UNSIGNED NULL,
    team_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_delivery_runs_date (run_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE delivery_stops (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id INT UNSIGNED NOT NULL,
    shipment_id INT UNSIGNED NOT NULL,
    sequence_no INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
    fail_reason VARCHAR(40) NULL,
    arrived_at DATETIME NULL,
    completed_at DATETIME NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_delivery_stop (run_id, shipment_id),
    KEY idx_delivery_stops_status (status),
    KEY idx_delivery_stops_shipment (shipment_id),
    CONSTRAINT fk_delivery_stops_run FOREIGN KEY (run_id) REFERENCES delivery_runs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_delivery_stops_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logistics_exceptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shipment_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    project_site_id INT UNSIGNED NULL,
    installation_id INT UNSIGNED NULL,
    exception_type VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'OPEN',
    description VARCHAR(255) NOT NULL,
    photo_path VARCHAR(255) NULL,
    quantity DECIMAL(14,4) NULL,
    cause_text VARCHAR(255) NULL,
    disposition VARCHAR(20) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_logistics_ex_status (status, exception_type),
    KEY idx_logistics_ex_shipment (shipment_id),
    KEY idx_logistics_ex_job (job_id),
    KEY idx_logistics_ex_project (project_id),
    KEY idx_logistics_ex_site (project_site_id),
    CONSTRAINT fk_logistics_ex_shipment FOREIGN KEY (shipment_id) REFERENCES shipments (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logistics_costs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    shipment_id INT UNSIGNED NULL,
    source_type VARCHAR(40) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    customer_charge DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(180) NOT NULL,
    other_cost_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_logistics_cost_source (source_type, source_id),
    KEY idx_logistics_costs_job (job_id),
    KEY idx_logistics_costs_project (project_id),
    CONSTRAINT fk_logistics_costs_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE travel_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    start_odometer DECIMAL(12,1) NULL,
    end_odometer DECIMAL(12,1) NULL,
    distance_km DECIMAL(10,2) NULL,
    cost_per_km DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    internal_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    customer_charge DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_travel_logs_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_expenses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    expense_type VARCHAR(30) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    receipt_path VARCHAR(255) NULL,
    note VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'CAPTURED',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_field_expenses_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    supplier_id INT UNSIGNED NULL,
    company_name VARCHAR(180) NOT NULL,
    contractor_type VARCHAR(30) NOT NULL DEFAULT 'OTHER',
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    contact_name VARCHAR(120) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contractors_supplier (supplier_id),
    KEY idx_contractors_status (status, contractor_type),
    CONSTRAINT fk_contractors_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_rates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contractor_id INT UNSIGNED NOT NULL,
    pricing_method VARCHAR(20) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    label VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contractor_rates (contractor_id, active),
    CONSTRAINT fk_contractor_rates FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contractor_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    title VARCHAR(180) NOT NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    file_path VARCHAR(255) NULL,
    reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contractor_docs_expiry (expiry_date, reminder_sent),
    KEY idx_contractor_docs_contractor (contractor_id),
    CONSTRAINT fk_contractor_docs FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_service_areas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contractor_id INT UNSIGNED NOT NULL,
    province VARCHAR(80) NULL,
    city VARCHAR(80) NULL,
    radius_text VARCHAR(120) NULL,
    PRIMARY KEY (id),
    KEY idx_contractor_areas (contractor_id),
    CONSTRAINT fk_contractor_areas FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_availability (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contractor_id INT UNSIGNED NOT NULL,
    available_date DATE NOT NULL,
    available TINYINT(1) NOT NULL DEFAULT 1,
    note VARCHAR(180) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contractor_availability (contractor_id, available_date),
    CONSTRAINT fk_contractor_availability FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contractor_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contractor_users_email (email),
    KEY idx_contractor_users_contractor (contractor_id),
    CONSTRAINT fk_contractor_users FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_user_permissions (
    contractor_user_id INT UNSIGNED NOT NULL,
    code VARCHAR(60) NOT NULL,
    PRIMARY KEY (contractor_user_id, code),
    CONSTRAINT fk_contractor_user_perm FOREIGN KEY (contractor_user_id) REFERENCES contractor_users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_work_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_number VARCHAR(40) NOT NULL,
    contractor_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    project_id INT UNSIGNED NULL,
    project_site_id INT UNSIGNED NULL,
    service_request_id INT UNSIGNED NULL,
    fulfilment_requirement_id INT UNSIGNED NULL,
    installation_id INT UNSIGNED NULL,
    subcontract_order_id INT UNSIGNED NULL,
    original_work_order_id INT UNSIGNED NULL,
    work_type VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    scope TEXT NOT NULL,
    quantity DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    site_address TEXT NULL,
    contact_name VARCHAR(120) NULL,
    contact_phone VARCHAR(40) NULL,
    required_date DATE NULL,
    instructions TEXT NULL,
    pricing_method VARCHAR(20) NOT NULL DEFAULT 'FIXED',
    agreed_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_cost DECIMAL(14,2) NULL,
    actual_hours DECIMAL(10,2) NULL,
    mileage_km DECIMAL(10,2) NULL,
    variance_reason VARCHAR(255) NULL,
    material_supply VARCHAR(20) NOT NULL DEFAULT 'SIGN_FORGE',
    other_cost_id INT UNSIGNED NULL,
    internal_notes TEXT NULL,
    visible_terms VARCHAR(255) NULL,
    exposed TINYINT(1) NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    start_latitude DECIMAL(10,7) NULL,
    start_longitude DECIMAL(10,7) NULL,
    submitted_at DATETIME NULL,
    approved_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contractor_wo_number (work_order_number),
    KEY idx_cwo_contractor (contractor_id, status),
    KEY idx_cwo_status (status, required_date),
    KEY idx_cwo_job (job_id),
    KEY idx_cwo_project (project_id),
    KEY idx_cwo_site (project_site_id),
    KEY idx_cwo_fulfilment (fulfilment_requirement_id),
    KEY idx_cwo_created (created_at),
    CONSTRAINT fk_cwo_contractor FOREIGN KEY (contractor_id) REFERENCES contractors (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cwo_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE job_installations
    ADD COLUMN contractor_work_order_id INT UNSIGNED NULL AFTER service_request_id,
    ADD KEY idx_job_installations_cwo (contractor_work_order_id),
    ADD CONSTRAINT fk_job_installations_cwo FOREIGN KEY (contractor_work_order_id) REFERENCES contractor_work_orders (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE logistics_exceptions
    ADD COLUMN contractor_work_order_id INT UNSIGNED NULL AFTER installation_id,
    ADD KEY idx_logistics_ex_cwo (contractor_work_order_id);

ALTER TABLE service_requests
    ADD COLUMN original_work_order_id INT UNSIGNED NULL AFTER assigned_contractor_id;

CREATE TABLE contractor_responses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_id INT UNSIGNED NOT NULL,
    action VARCHAR(20) NOT NULL,
    change_kind VARCHAR(40) NULL,
    message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contractor_responses (work_order_id),
    CONSTRAINT fk_contractor_responses FOREIGN KEY (work_order_id) REFERENCES contractor_work_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_work_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_id INT UNSIGNED NOT NULL,
    production_file_id INT UNSIGNED NULL,
    file_role VARCHAR(40) NOT NULL DEFAULT 'INSTRUCTION',
    title VARCHAR(180) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_cwo_files (work_order_id),
    CONSTRAINT fk_cwo_files FOREIGN KEY (work_order_id) REFERENCES contractor_work_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_issues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_id INT UNSIGNED NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'MINOR',
    description VARCHAR(255) NOT NULL,
    suggested_action VARCHAR(255) NULL,
    photo_path VARCHAR(255) NULL,
    reason VARCHAR(40) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contractor_issues (work_order_id),
    CONSTRAINT fk_contractor_issues FOREIGN KEY (work_order_id) REFERENCES contractor_work_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(80) NOT NULL,
    invoice_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    file_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contractor_invoices (work_order_id),
    CONSTRAINT fk_contractor_invoices FOREIGN KEY (work_order_id) REFERENCES contractor_work_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_rework (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_id INT UNSIGNED NOT NULL,
    original_work_order_id INT UNSIGNED NULL,
    reason VARCHAR(255) NOT NULL,
    cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    delay_days INT UNSIGNED NULL,
    resolution VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contractor_rework (work_order_id),
    CONSTRAINT fk_contractor_rework FOREIGN KEY (work_order_id) REFERENCES contractor_work_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outsourced_receipts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_id INT UNSIGNED NOT NULL,
    quantity_received DECIMAL(14,4) NOT NULL,
    quantity_accepted DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    quantity_rejected DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    qc_status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    photo_path VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_outsourced_receipts (work_order_id),
    CONSTRAINT fk_outsourced_receipts FOREIGN KEY (work_order_id) REFERENCES contractor_work_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contractor_material_ledger (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    movement VARCHAR(20) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    stock_location_id INT UNSIGNED NULL,
    transfer_reference INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contractor_material (work_order_id, product_id),
    CONSTRAINT fk_contractor_material_wo FOREIGN KEY (work_order_id) REFERENCES contractor_work_orders (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE installation_signoffs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    installation_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    project_site_id INT UNSIGNED NULL,
    signer_name VARCHAR(120) NOT NULL,
    signer_role VARCHAR(80) NULL,
    statement_version VARCHAR(20) NOT NULL,
    signature_id INT UNSIGNED NULL,
    device_signed_at DATETIME NULL,
    signed_at DATETIME NOT NULL,
    created_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_install_signoffs (installation_id),
    CONSTRAINT fk_install_signoffs FOREIGN KEY (installation_id) REFERENCES job_installations (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO installation_checklist_templates (name, applies_to, applies_value, active)
SELECT 'Lightbox installation', 'PRODUCT', 'LIGHTBOX', 1
WHERE NOT EXISTS (SELECT 1 FROM installation_checklist_templates WHERE name = 'Lightbox installation');

INSERT INTO installation_checklist_template_items (template_id, label, sort_order)
SELECT t.id, i.label, i.sort_order
FROM installation_checklist_templates t
JOIN (
    SELECT 'Mounting secure' AS label, 10 AS sort_order
    UNION ALL SELECT 'Level checked', 20
    UNION ALL SELECT 'Electrical connections completed', 30
    UNION ALL SELECT 'Cable protected', 40
    UNION ALL SELECT 'Power test completed', 50
    UNION ALL SELECT 'Illumination tested', 60
    UNION ALL SELECT 'Face cleaned', 70
    UNION ALL SELECT 'Site cleaned', 80
    UNION ALL SELECT 'Photos captured', 90
) i
WHERE t.name = 'Lightbox installation'
  AND NOT EXISTS (
      SELECT 1 FROM installation_checklist_template_items x WHERE x.template_id = t.id AND x.label = i.label
  );

INSERT INTO installation_checklist_templates (name, applies_to, applies_value, active)
SELECT 'Vehicle branding', 'PRODUCT', 'VEHICLE', 1
WHERE NOT EXISTS (SELECT 1 FROM installation_checklist_templates WHERE name = 'Vehicle branding');

INSERT INTO installation_checklist_template_items (template_id, label, sort_order)
SELECT t.id, i.label, i.sort_order
FROM installation_checklist_templates t
JOIN (
    SELECT 'Vehicle inspected before install' AS label, 10 AS sort_order
    UNION ALL SELECT 'Existing damage photographed', 20
    UNION ALL SELECT 'Surface cleaned', 30
    UNION ALL SELECT 'Panels verified', 40
    UNION ALL SELECT 'Artwork orientation checked', 50
    UNION ALL SELECT 'Application completed', 60
    UNION ALL SELECT 'Edges inspected', 70
    UNION ALL SELECT 'Final photos', 80
    UNION ALL SELECT 'Customer handover', 90
) i
WHERE t.name = 'Vehicle branding'
  AND NOT EXISTS (
      SELECT 1 FROM installation_checklist_template_items x WHERE x.template_id = t.id AND x.label = i.label
  );

INSERT IGNORE INTO permissions (code, name, module) VALUES
('logistics.view', 'View logistics', 'logistics'),
('logistics.shipment.create', 'Create a shipment', 'logistics'),
('logistics.shipment.dispatch', 'Dispatch a shipment', 'logistics'),
('logistics.shipment.manage', 'Manage shipments and couriers', 'logistics'),
('logistics.delivery.manage', 'Manage own deliveries', 'logistics'),
('logistics.collection.manage', 'Manage customer collections', 'logistics'),
('logistics.exceptions.manage', 'Manage logistics exceptions', 'logistics'),
('installation.schedule', 'Schedule an installation pack', 'logistics'),
('installation.execute', 'Record installation field work', 'logistics'),
('installation.signoff', 'Record an installation sign-off', 'logistics'),
('contractor.manage', 'Manage contractor profiles', 'logistics'),
('contractor.assign', 'Assign a contractor', 'logistics'),
('contractor.work_order.create', 'Create a contractor work order', 'logistics'),
('contractor.work_order.approve', 'Review contractor completion', 'logistics'),
('contractor.cost.view', 'View contractor cost', 'logistics'),
('contractor.cost.approve', 'Approve contractor cost onto a job', 'logistics'),
('contractor.portal.manage', 'Manage contractor portal users', 'logistics');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.module = 'logistics';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DISPATCH' AND p.code IN (
    'logistics.view', 'logistics.shipment.create', 'logistics.shipment.dispatch',
    'logistics.delivery.manage', 'logistics.collection.manage', 'logistics.exceptions.manage'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN (
    'logistics.view', 'installation.execute', 'installation.signoff'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code = 'logistics.view';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code = 'logistics.view';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN ('logistics.view', 'contractor.cost.view');

INSERT INTO settings (setting_key, setting_value) VALUES
('shipment_prefix', 'SFSHP'),
('contractor_work_order_prefix', 'SFCWO'),
('map_url_template', 'https://www.google.com/maps/search/?api=1&query={query}'),
('travel_cost_per_km', '0.00'),
('contractor_variance_amount', '0.00'),
('logistics_incomplete_policy', 'OVERRIDE'),
('tracking_token_days', '30'),
('courier_webhook_secret', ''),
('pod_statement_version', 'POD-1'),
('install_statement_version', 'INSTALL-1')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

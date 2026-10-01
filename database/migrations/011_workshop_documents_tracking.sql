-- Phase 11 workshop execution, job cards, QR tracking, and digital documents.
-- Import once on a database that already has Phase 10. Do not re-import schema.sql.

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

INSERT INTO roles (code, name, description)
SELECT 'DISPATCH', 'Dispatch', 'Packing, delivery notes, and proof of delivery.'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE code = 'DISPATCH');

INSERT IGNORE INTO permissions (code, name, module) VALUES
('workshop.view', 'View the workshop floor', 'workshop'),
('workshop.scan', 'Scan workshop codes', 'workshop'),
('production.start', 'Start production', 'workshop'),
('production.pause', 'Pause production', 'workshop'),
('production.complete', 'Complete a production stage', 'workshop'),
('production.reprint', 'Record a reprint', 'workshop'),
('production.override_material', 'Issue material that does not match the job', 'workshop'),
('qc.perform', 'Record quality checks', 'workshop'),
('qc.override', 'Dispatch or complete after a failed quality check', 'workshop'),
('labels.print', 'Print labels', 'workshop'),
('labels.reprint', 'Reprint an existing label', 'workshop'),
('dispatch.view', 'View dispatches', 'workshop'),
('dispatch.create', 'Create and scan a dispatch', 'workshop'),
('dispatch.complete', 'Complete a dispatch', 'workshop'),
('delivery.signoff', 'Capture proof of delivery', 'workshop'),
('installation.signoff', 'Capture installation sign-off', 'workshop'),
('snags.view', 'View snags', 'workshop'),
('snags.manage', 'Manage snags', 'workshop'),
('documents.internal.view', 'View internal job cards and work orders', 'workshop'),
('documents.templates.manage', 'Manage document and label templates', 'workshop'),
('tracking.traceability.view', 'View production and material traceability', 'workshop'),
('kiosk.use', 'Use the workshop kiosk', 'workshop');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN (
    'workshop.view', 'workshop.scan', 'production.start', 'production.pause', 'production.complete',
    'production.reprint', 'production.override_material', 'qc.perform', 'qc.override',
    'labels.print', 'labels.reprint', 'dispatch.view', 'snags.view', 'documents.internal.view',
    'tracking.traceability.view', 'kiosk.use'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN (
    'workshop.view', 'workshop.scan', 'installation.signoff', 'snags.view', 'snags.manage',
    'documents.internal.view', 'labels.print', 'kiosk.use'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DISPATCH' AND p.code IN (
    'dashboard.view', 'jobs.view', 'workshop.view', 'workshop.scan', 'labels.print', 'labels.reprint',
    'dispatch.view', 'dispatch.create', 'dispatch.complete', 'delivery.signoff',
    'snags.view', 'documents.internal.view', 'kiosk.use', 'attachments.manage'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('workshop.view', 'dispatch.view', 'snags.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.code IN (
    'workshop.view', 'dispatch.view', 'snags.view', 'documents.internal.view', 'tracking.traceability.view'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN ('dispatch.view', 'documents.internal.view');

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('dispatch_prefix', 'SFD'),
('completion_prefix', 'SFCOMP'),
('package_prefix', 'SFPK'),
('pod_acceptance_statement', 'I confirm that the listed goods were received.'),
('completion_acceptance_statement', 'I confirm that the listed goods/services were received/completed.'),
('acceptance_statement_version', '1'),
('gps_capture_enabled', '0'),
('workshop_poll_seconds', '45'),
('workshop_time_tracking', '0'),
('individual_tracking_cap', '200'),
('kiosk_max_pin_attempts', '5');

INSERT IGNORE INTO reprint_reasons (code, label, active) VALUES
('PRINT_DEFECT', 'Print defect', 1),
('COLOUR_ISSUE', 'Colour issue', 1),
('ARTWORK_ERROR', 'Artwork error', 1),
('MATERIAL_DEFECT', 'Material defect', 1),
('APPLICATION_ERROR', 'Application error', 1),
('DAMAGE', 'Damage', 1),
('CUSTOMER_CHANGE', 'Customer change', 1),
('OTHER', 'Other', 1);

INSERT IGNORE INTO qc_checklist_items (product_id, label, sort_order, active)
SELECT NULL, label, sort_order, 1 FROM (
    SELECT 'Dimensions correct' AS label, 10 AS sort_order UNION ALL
    SELECT 'Artwork correct', 20 UNION ALL
    SELECT 'Spelling checked', 30 UNION ALL
    SELECT 'Colour visually acceptable', 40 UNION ALL
    SELECT 'No print defects', 50 UNION ALL
    SELECT 'No bubbles', 60 UNION ALL
    SELECT 'Edges finished', 70 UNION ALL
    SELECT 'Hardware complete', 80 UNION ALL
    SELECT 'Clean', 90 UNION ALL
    SELECT 'Quantity correct', 100
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM qc_checklist_items WHERE product_id IS NULL AND label = seed.label);

INSERT INTO label_templates (name, entity_type, width_mm, height_mm, orientation, layout_definition, active)
SELECT 'Roll label', 'ROLL', 100, 60, 'LANDSCAPE', '{"title":"ROLL","lines":["{{code}}","{{product}}","Width: {{width}}","Original: {{original}}","Remaining: {{remaining}}","Location: {{location}}"]}', 1
WHERE NOT EXISTS (SELECT 1 FROM label_templates WHERE name = 'Roll label');

INSERT INTO label_templates (name, entity_type, width_mm, height_mm, orientation, layout_definition, active)
SELECT 'Sheet label', 'SHEET', 100, 60, 'LANDSCAPE', '{"title":"SHEET","lines":["{{code}}","{{product}}","{{dimensions}}","Location: {{location}}"]}', 1
WHERE NOT EXISTS (SELECT 1 FROM label_templates WHERE name = 'Sheet label');

INSERT INTO label_templates (name, entity_type, width_mm, height_mm, orientation, layout_definition, active)
SELECT 'Offcut label', 'OFFCUT', 100, 60, 'LANDSCAPE', '{"title":"OFFCUT","lines":["{{code}}","{{product}}","{{dimensions}}","Location: {{location}}"]}', 1
WHERE NOT EXISTS (SELECT 1 FROM label_templates WHERE name = 'Offcut label');

INSERT INTO label_templates (name, entity_type, width_mm, height_mm, orientation, layout_definition, active)
SELECT 'Job label', 'JOB', 100, 70, 'LANDSCAPE', '{"title":"JOB","lines":["{{code}}","{{customer}}","{{description}}","{{dimensions}}","Qty: {{quantity}}","Due: {{due}}"]}', 1
WHERE NOT EXISTS (SELECT 1 FROM label_templates WHERE name = 'Job label');

INSERT INTO label_templates (name, entity_type, width_mm, height_mm, orientation, layout_definition, active)
SELECT 'Production item label', 'PRODUCTION_ITEM', 100, 70, 'LANDSCAPE', '{"title":"ITEM","lines":["{{code}}","{{customer}}","{{description}}","{{dimensions}}","Qty: {{quantity}}","Due: {{due}}"]}', 1
WHERE NOT EXISTS (SELECT 1 FROM label_templates WHERE name = 'Production item label');

INSERT INTO label_templates (name, entity_type, width_mm, height_mm, orientation, layout_definition, active)
SELECT 'Dispatch label', 'DISPATCH', 100, 60, 'LANDSCAPE', '{"title":"DISPATCH","lines":["{{code}}","{{customer}}","{{description}}"]}', 1
WHERE NOT EXISTS (SELECT 1 FROM label_templates WHERE name = 'Dispatch label');

INSERT INTO document_templates (document_type, name, template_version, layout_config, active)
SELECT 'JOB_CARD', 'Workshop job card', 1, '{{company.name}}\n{{job.number}}\n{{customer.name}}\n{{job.target_date}}', 1
WHERE NOT EXISTS (SELECT 1 FROM document_templates WHERE document_type = 'JOB_CARD' AND name = 'Workshop job card');

INSERT INTO document_templates (document_type, name, template_version, layout_config, active)
SELECT 'DELIVERY_NOTE', 'Delivery note', 1, '{{company.name}}\n{{document.number}}\n{{customer.name}}\n{{job.number}}', 1
WHERE NOT EXISTS (SELECT 1 FROM document_templates WHERE document_type = 'DELIVERY_NOTE');

INSERT INTO document_templates (document_type, name, template_version, layout_config, active)
SELECT 'COLLECTION_NOTE', 'Collection note', 1, '{{company.name}}\n{{document.number}}\n{{customer.name}}', 1
WHERE NOT EXISTS (SELECT 1 FROM document_templates WHERE document_type = 'COLLECTION_NOTE');

INSERT INTO document_templates (document_type, name, template_version, layout_config, active)
SELECT 'COMPLETION_CERTIFICATE', 'Completion certificate', 1, '{{company.name}}\n{{document.number}}\n{{customer.name}}\n{{job.number}}', 1
WHERE NOT EXISTS (SELECT 1 FROM document_templates WHERE document_type = 'COMPLETION_CERTIFICATE');

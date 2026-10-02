-- v1.1 Phase 3: sign specifications and manufacturing estimators.
-- Calculations are deterministic. They are not structural or electrical certification.

CREATE TABLE sign_specifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    name VARCHAR(180) NOT NULL,
    category VARCHAR(60) NOT NULL,
    estimator_type VARCHAR(30) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    default_recipe_id INT UNSIGNED NULL,
    default_production_route_id INT UNSIGNED NULL,
    engineering_review_required TINYINT(1) NOT NULL DEFAULT 0,
    electrical_review_required TINYINT(1) NOT NULL DEFAULT 0,
    height_review_mm DECIMAL(10,2) NULL,
    max_width_mm DECIMAL(10,2) NULL,
    max_height_mm DECIMAL(10,2) NULL,
    max_post_height_mm DECIMAL(10,2) NULL,
    waste_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    manufacturing_allowance_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    construction_type VARCHAR(80) NULL,
    frame_profile VARCHAR(160) NULL,
    face_material VARCHAR(160) NULL,
    return_material VARCHAR(160) NULL,
    back_material VARCHAR(160) NULL,
    illumination_type VARCHAR(80) NULL,
    mounting_method VARCHAR(80) NULL,
    finishing VARCHAR(80) NULL,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    superseded_by_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sign_spec_code_version (code, version),
    KEY idx_sign_spec_status (status, estimator_type),
    KEY idx_sign_spec_code (code),
    CONSTRAINT fk_sign_spec_recipe FOREIGN KEY (default_recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_spec_route FOREIGN KEY (default_production_route_id) REFERENCES production_route_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_spec_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_spec_approved FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sign_specifications
    ADD CONSTRAINT fk_sign_spec_superseded FOREIGN KEY (superseded_by_id) REFERENCES sign_specifications (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE specification_materials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specification_id INT UNSIGNED NOT NULL,
    role_code VARCHAR(40) NOT NULL,
    product_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    thickness_mm DECIMAL(10,2) NULL,
    quantity_formula VARCHAR(255) NULL,
    unit_code VARCHAR(20) NOT NULL DEFAULT 'm2',
    waste_percent DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_spec_materials_spec (specification_id),
    CONSTRAINT fk_spec_materials_spec FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_spec_materials_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE specification_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specification_id INT UNSIGNED NOT NULL,
    role_code VARCHAR(40) NOT NULL,
    product_id INT UNSIGNED NULL,
    electrical_profile_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    quantity_formula VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_spec_components_spec (specification_id),
    CONSTRAINT fk_spec_components_spec FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_spec_components_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE specification_labour (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specification_id INT UNSIGNED NOT NULL,
    operation_code VARCHAR(40) NOT NULL,
    description VARCHAR(180) NOT NULL,
    minutes_formula VARCHAR(255) NOT NULL,
    hourly_rate DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    installer_count INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_spec_labour_spec (specification_id),
    CONSTRAINT fk_spec_labour_spec FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE specification_operations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specification_id INT UNSIGNED NOT NULL,
    sequence_no INT UNSIGNED NOT NULL,
    operation_code VARCHAR(40) NOT NULL,
    description VARCHAR(180) NOT NULL,
    setup_minutes DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    run_minutes DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    hourly_rate DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    PRIMARY KEY (id),
    KEY idx_spec_operations_spec (specification_id, sequence_no),
    CONSTRAINT fk_spec_operations_spec FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE specification_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specification_id INT UNSIGNED NOT NULL,
    rule_type VARCHAR(40) NOT NULL,
    hardness VARCHAR(10) NOT NULL DEFAULT 'HARD',
    when_key VARCHAR(40) NOT NULL,
    when_op VARCHAR(10) NOT NULL DEFAULT 'EQ',
    when_value VARCHAR(80) NOT NULL,
    and_key VARCHAR(40) NULL,
    and_op VARCHAR(10) NULL,
    and_value VARCHAR(80) NULL,
    then_key VARCHAR(40) NULL,
    then_value VARCHAR(160) NULL,
    message VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_spec_rules_spec (specification_id, active),
    CONSTRAINT fk_spec_rules_spec FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE specification_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specification_id INT UNSIGNED NOT NULL,
    note_type VARCHAR(30) NOT NULL,
    body TEXT NOT NULL,
    customer_visible TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_spec_notes_spec (specification_id, note_type),
    CONSTRAINT fk_spec_notes_spec FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE specification_images (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    specification_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    storage_path VARCHAR(255) NULL,
    visibility VARCHAR(30) NOT NULL DEFAULT 'INTERNAL',
    PRIMARY KEY (id),
    KEY idx_spec_images_spec (specification_id),
    CONSTRAINT fk_spec_images_spec FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    make_name VARCHAR(80) NOT NULL,
    model_name VARCHAR(80) NOT NULL,
    variant_name VARCHAR(80) NULL,
    year_from SMALLINT UNSIGNED NOT NULL,
    year_to SMALLINT UNSIGNED NULL,
    body_type VARCHAR(40) NOT NULL,
    notes TEXT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'MANUAL',
    verified TINYINT(1) NOT NULL DEFAULT 0,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    superseded_by_id INT UNSIGNED NULL,
    template_file_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vehicle_templates_make (make_name, model_name, year_from),
    CONSTRAINT fk_vehicle_templates_superseded FOREIGN KEY (superseded_by_id) REFERENCES vehicle_templates (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicle_template_panels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    panel_code VARCHAR(40) NOT NULL,
    width_mm DECIMAL(10,2) NOT NULL,
    height_mm DECIMAL(10,2) NOT NULL,
    area_m2 DECIMAL(14,4) NOT NULL,
    complexity_factor DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    bleed_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    overlap_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    orientation_locked TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vehicle_panel (template_id, panel_code),
    CONSTRAINT fk_vehicle_panels_template FOREIGN KEY (template_id) REFERENCES vehicle_templates (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE electrical_component_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    kind VARCHAR(30) NOT NULL,
    product_id INT UNSIGNED NULL,
    module_watts DECIMAL(10,3) NULL,
    rated_watts DECIMAL(10,2) NULL,
    max_load_percent DECIMAL(6,2) NULL,
    spacing_mm DECIMAL(10,2) NULL,
    modules_per_m2 DECIMAL(10,2) NULL,
    modules_per_letter DECIMAL(10,2) NULL,
    max_chain INT UNSIGNED NULL,
    voltage VARCHAR(20) NULL,
    method_code VARCHAR(30) NULL,
    environmental_rating VARCHAR(40) NULL,
    notes TEXT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_electrical_profiles_code (code),
    CONSTRAINT fk_electrical_profiles_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE specification_components
    ADD CONSTRAINT fk_spec_components_profile FOREIGN KEY (electrical_profile_id) REFERENCES electrical_component_profiles (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE sign_calculations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_id INT UNSIGNED NULL,
    parent_calculation_id INT UNSIGNED NULL,
    estimator_type VARCHAR(30) NOT NULL,
    specification_id INT UNSIGNED NULL,
    specification_code VARCHAR(40) NULL,
    specification_version INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    project_site_id INT UNSIGNED NULL,
    quote_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'CALCULATED',
    outside_specification TINYINT(1) NOT NULL DEFAULT 0,
    technical_review_required TINYINT(1) NOT NULL DEFAULT 0,
    input_json JSON NOT NULL,
    output_json JSON NOT NULL,
    input_hash CHAR(64) NOT NULL,
    material_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    labour_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    machine_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    installation_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sell_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    gross_profit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    margin_percent DECIMAL(8,2) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sign_calc_type (estimator_type, status),
    KEY idx_sign_calc_spec (specification_id),
    KEY idx_sign_calc_quote (quote_id),
    KEY idx_sign_calc_job (job_id),
    KEY idx_sign_calc_project (project_id),
    KEY idx_sign_calc_hash (input_hash),
    CONSTRAINT fk_sign_calc_estimate FOREIGN KEY (estimate_id) REFERENCES estimates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_calc_parent FOREIGN KEY (parent_calculation_id) REFERENCES sign_calculations (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_calc_spec FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_calc_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_calc_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_calc_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_calc_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_calc_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sign_calc_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sign_calculation_overrides (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    calculation_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    calculated_value VARCHAR(80) NOT NULL,
    overridden_value VARCHAR(80) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sign_overrides_calc (calculation_id),
    CONSTRAINT fk_sign_overrides_calc FOREIGN KEY (calculation_id) REFERENCES sign_calculations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sign_overrides_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE geometry_analyses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    calculation_id INT UNSIGNED NULL,
    original_name VARCHAR(180) NULL,
    byte_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    open_paths INT UNSIGNED NOT NULL DEFAULT 0,
    duplicate_paths INT UNSIGNED NOT NULL DEFAULT 0,
    area_mm2 DECIMAL(18,4) NULL,
    perimeter_mm DECIMAL(18,4) NULL,
    sanitized_svg MEDIUMTEXT NULL,
    warnings_json JSON NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_geometry_sha (sha256),
    KEY idx_geometry_calc (calculation_id),
    CONSTRAINT fk_geometry_calc FOREIGN KEY (calculation_id) REFERENCES sign_calculations (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_geometry_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE geometry_cache (
    cache_key CHAR(64) NOT NULL,
    estimator_version VARCHAR(20) NOT NULL,
    geometry_json JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE products
    ADD COLUMN thickness_mm DECIMAL(10,2) NULL AFTER kerf_mm,
    ADD COLUMN density_kg_m3 DECIMAL(10,2) NULL AFTER thickness_mm;

ALTER TABLE jobs
    ADD COLUMN technical_snapshot_json JSON NULL AFTER service_reason;

ALTER TABLE customer_assets
    ADD COLUMN specification_id INT UNSIGNED NULL AFTER original_job_item_id,
    ADD COLUMN technical_snapshot_json JSON NULL AFTER specification_id,
    ADD KEY idx_assets_specification (specification_id),
    ADD CONSTRAINT fk_assets_specification FOREIGN KEY (specification_id) REFERENCES sign_specifications (id) ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO sign_specifications (
    code, version, name, category, estimator_type, description, status, effective_from,
    engineering_review_required, electrical_review_required, height_review_mm,
    manufacturing_allowance_percent, construction_type, frame_profile, face_material,
    return_material, back_material, illumination_type, mounting_method, finishing
) VALUES
('LBX-ACM-001', 1, 'Standard ACM illuminated lightbox', 'LIGHTBOX', 'LIGHTBOX',
 'Manufacturing default for a rectangular illuminated lightbox. Not a structural certificate.',
 'APPROVED', CURDATE(), 0, 1, NULL, 5.00, 'FABRICATED_BOX', '25 x 25 x 1.6 mm aluminium tube',
 'Acrylic, flexface, or fabric as selected', '3 mm ACM', '3 mm ACM', 'LED modules', 'Wall', 'Paint'),
('PYL-STD-001', 1, 'Panelled pylon manufacturing allowance', 'PYLON', 'PYLON',
 'Manufacturing quantities from an approved design. Height above the specification threshold needs engineering review.',
 'APPROVED', CURDATE(), 1, 1, 6000.00, 5.00, 'PANELLED_PYLON', 'Aluminium frame from the approved design',
 'ACM or acrylic as selected', NULL, 'ACM', 'Optional LED', 'Base plate', 'Paint');

INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
SELECT id, 1, 'CUT', 'Cut', 15, 10, 0 FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
SELECT id, 2, 'FABRICATE', 'Fabricate', 20, 20, 0 FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
SELECT id, 3, 'PAINT', 'Paint', 10, 15, 0 FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
SELECT id, 4, 'ELECTRICAL', 'Electrical', 10, 20, 0 FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
SELECT id, 5, 'ASSEMBLE', 'Assemble', 10, 20, 0 FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
SELECT id, 6, 'QC', 'Quality check', 5, 10, 0 FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
SELECT id, 7, 'PACK', 'Pack', 5, 5, 0 FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_operations (specification_id, sequence_no, operation_code, description, setup_minutes, run_minutes, hourly_rate)
SELECT id, 8, 'INSTALL', 'Install', 15, 30, 0 FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;

INSERT INTO specification_notes (specification_id, note_type, body, customer_visible)
SELECT id, 'QC', 'Faces secure. Returns complete. LED operation. Polarity. PSU load checked against the profile. Water ingress. Finish. Artwork matches approval.', 0
FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_notes (specification_id, note_type, body, customer_visible)
SELECT id, 'WARNING', 'Drainage and service access are required on this lightbox specification. This note is a manufacturing assumption, not a certificate.', 0
FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;
INSERT INTO specification_notes (specification_id, note_type, body, customer_visible)
SELECT id, 'INSTALLATION', 'Confirm fixings on site. Site instructions add to this standard list. They do not replace it silently.', 1
FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;

INSERT INTO specification_rules (specification_id, rule_type, hardness, when_key, when_op, when_value, then_key, then_value, message)
SELECT id, 'REQUIRES', 'HARD', 'illuminated', 'EQ', 'YES', 'electrical_components', 'YES', 'Illuminated construction requires electrical components.'
FROM sign_specifications WHERE code = 'LBX-ACM-001' AND version = 1;

INSERT INTO specification_rules (specification_id, rule_type, hardness, when_key, when_op, when_value, message)
SELECT id, 'EXCLUDES', 'HARD', 'face_material', 'EQ', 'CORREX', '3 mm Correx cannot be selected with this freestanding illuminated pylon specification.'
FROM sign_specifications WHERE code = 'PYL-STD-001' AND version = 1;

INSERT INTO specification_rules (specification_id, rule_type, hardness, when_key, when_op, when_value, message)
SELECT id, 'ENGINEERING_REVIEW', 'SOFT', 'height_mm', 'GT', '6000', 'Height is above the review threshold defined on PYL-STD-001. Professional structural review is required. This estimate does not certify the structure.'
FROM sign_specifications WHERE code = 'PYL-STD-001' AND version = 1;

INSERT INTO electrical_component_profiles (
    code, name, kind, module_watts, rated_watts, max_load_percent, spacing_mm, modules_per_m2, voltage, method_code, notes
) VALUES
('LED-MOD-12', 'LED module 1.2 W', 'LED_MODULE', 1.200, NULL, NULL, 150.00, 25.00, '12V', 'MODULES_PER_M2', 'Planning profile. Not an electrical certificate.'),
('PSU-100-80', '100 W PSU at 80 percent planning load', 'PSU', NULL, 100.00, 80.00, NULL, NULL, '12V', NULL, 'Usable planning capacity is the rated watts times the configured load percent.');

INSERT INTO vehicle_templates (make_name, model_name, variant_name, year_from, year_to, body_type, source, verified, notes)
VALUES ('Ford', 'Ranger', 'Double Cab', 2017, 2017, 'DOUBLE_CAB', 'MANUAL', 1, 'Example dimensions for estimating. Confirm before production. Not a licensed template library.');

INSERT INTO vehicle_template_panels (template_id, panel_code, width_mm, height_mm, area_m2, bleed_mm, overlap_mm)
SELECT id, 'LEFT_FRONT_DOOR', 1000, 800, 0.8000, 10, 0 FROM vehicle_templates WHERE make_name = 'Ford' AND model_name = 'Ranger' AND year_from = 2017;
INSERT INTO vehicle_template_panels (template_id, panel_code, width_mm, height_mm, area_m2, bleed_mm, overlap_mm)
SELECT id, 'RIGHT_FRONT_DOOR', 1000, 800, 0.8000, 10, 0 FROM vehicle_templates WHERE make_name = 'Ford' AND model_name = 'Ranger' AND year_from = 2017;
INSERT INTO vehicle_template_panels (template_id, panel_code, width_mm, height_mm, area_m2, bleed_mm, overlap_mm)
SELECT id, 'LEFT_REAR_DOOR', 900, 800, 0.7200, 10, 0 FROM vehicle_templates WHERE make_name = 'Ford' AND model_name = 'Ranger' AND year_from = 2017;
INSERT INTO vehicle_template_panels (template_id, panel_code, width_mm, height_mm, area_m2, bleed_mm, overlap_mm)
SELECT id, 'RIGHT_REAR_DOOR', 900, 800, 0.7200, 10, 0 FROM vehicle_templates WHERE make_name = 'Ford' AND model_name = 'Ranger' AND year_from = 2017;
INSERT INTO vehicle_template_panels (template_id, panel_code, width_mm, height_mm, area_m2, bleed_mm, overlap_mm)
SELECT id, 'TAILGATE', 1400, 500, 0.7000, 10, 0 FROM vehicle_templates WHERE make_name = 'Ford' AND model_name = 'Ranger' AND year_from = 2017;
INSERT INTO vehicle_template_panels (template_id, panel_code, width_mm, height_mm, area_m2, bleed_mm, overlap_mm)
SELECT id, 'CANOPY_LEFT', 1800, 400, 0.7200, 10, 0 FROM vehicle_templates WHERE make_name = 'Ford' AND model_name = 'Ranger' AND year_from = 2017;
INSERT INTO vehicle_template_panels (template_id, panel_code, width_mm, height_mm, area_m2, bleed_mm, overlap_mm)
SELECT id, 'CANOPY_RIGHT', 1800, 400, 0.7200, 10, 0 FROM vehicle_templates WHERE make_name = 'Ford' AND model_name = 'Ranger' AND year_from = 2017;

INSERT INTO permissions (code, name, module) VALUES
('specifications.view', 'View sign specifications', 'signage'),
('specifications.create', 'Create sign specifications', 'signage'),
('specifications.edit', 'Edit sign specifications', 'signage'),
('specifications.approve', 'Approve sign specifications', 'signage'),
('specifications.archive', 'Archive sign specifications', 'signage'),
('estimators.use', 'Run signage estimators', 'signage'),
('estimators.override', 'Override a calculated estimator quantity', 'signage'),
('estimators.view_costs', 'View estimator cost and margin', 'signage'),
('estimators.technical_review', 'Complete technical estimate review', 'signage'),
('vehicle_templates.view', 'View vehicle templates', 'signage'),
('vehicle_templates.manage', 'Manage vehicle templates', 'signage'),
('geometry.upload', 'Upload vector geometry', 'signage'),
('electrical_profiles.manage', 'Manage electrical component profiles', 'signage');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.module = 'signage';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('specifications.view', 'estimators.use', 'vehicle_templates.view');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DESIGN' AND p.code IN (
    'specifications.view', 'specifications.create', 'specifications.edit',
    'estimators.use', 'estimators.override', 'estimators.technical_review',
    'vehicle_templates.view', 'vehicle_templates.manage', 'geometry.upload'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code = 'specifications.view';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code = 'specifications.view';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN ('specifications.view', 'estimators.view_costs');

INSERT INTO settings (setting_key, setting_value) VALUES
('geometry_max_bytes', '262144'),
('geometry_max_paths', '200'),
('estimator_version', '1'),
('dimension_warn_mm', '8000'),
('wrap_hours_standard', '1.5'),
('wrap_hours_moderate', '2.5'),
('wrap_hours_complex', '4'),
('wrap_hours_specialist', '6'),
('wrap_factor_standard', '1'),
('wrap_factor_moderate', '1.25'),
('wrap_factor_complex', '1.50'),
('wrap_factor_specialist', '2'),
('wrap_removal_hours', '1'),
('access_cost_ladder', '0'),
('access_cost_scaffold', '0'),
('access_cost_cherry_picker', '0'),
('access_cost_crane', '0'),
('access_cost_other', '0');

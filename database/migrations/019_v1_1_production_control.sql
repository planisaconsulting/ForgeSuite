-- v1.1 Phase 4: release to production and line-item fulfilment.
-- Accepted is not released. Released is not started. Release does not consume stock.

ALTER TABLE jobs
    ADD COLUMN preparation_status VARCHAR(30) NOT NULL DEFAULT 'NOT_READY' AFTER status,
    ADD KEY idx_jobs_preparation (preparation_status, target_date);

ALTER TABLE job_items
    ADD COLUMN release_status VARCHAR(30) NOT NULL DEFAULT 'NOT_RELEASED' AFTER production_status,
    ADD COLUMN released_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER release_status,
    ADD COLUMN good_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER released_quantity,
    ADD COLUMN waste_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER good_quantity,
    ADD COLUMN rework_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER waste_quantity,
    ADD COLUMN fulfilment_type VARCHAR(30) NULL AFTER rework_quantity,
    ADD KEY idx_job_items_release (job_id, release_status);

ALTER TABLE job_production_stages
    ADD COLUMN started_by INT UNSIGNED NULL AFTER started_at,
    ADD COLUMN blocked_reason VARCHAR(40) NULL AFTER notes,
    ADD COLUMN pause_reason VARCHAR(40) NULL AFTER blocked_reason,
    ADD COLUMN release_id INT UNSIGNED NULL AFTER pause_reason,
    ADD COLUMN good_quantity DECIMAL(14,4) NULL AFTER release_id,
    ADD COLUMN waste_quantity DECIMAL(14,4) NULL AFTER good_quantity,
    ADD COLUMN rework_quantity DECIMAL(14,4) NULL AFTER waste_quantity,
    ADD KEY idx_job_stages_release (release_id, status);

ALTER TABLE stock_reservations
    ADD COLUMN source_release_id INT UNSIGNED NULL AFTER job_id,
    ADD KEY idx_stock_reservations_release (source_release_id);

CREATE TABLE production_release_policies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    applies_to VARCHAR(30) NOT NULL DEFAULT 'DEFAULT',
    applies_value VARCHAR(80) NULL,
    approval_required TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_release_policies_code (code),
    KEY idx_release_policies_match (applies_to, applies_value, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_release_policy_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    policy_id INT UNSIGNED NOT NULL,
    check_code VARCHAR(40) NOT NULL,
    severity VARCHAR(40) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_policy_check (policy_id, check_code),
    CONSTRAINT fk_policy_checks_policy FOREIGN KEY (policy_id) REFERENCES production_release_policies (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_releases (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_number VARCHAR(40) NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    release_version INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    requested_by INT UNSIGNED NULL,
    requested_at TIMESTAMP NULL DEFAULT NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    released_by INT UNSIGNED NULL,
    released_at TIMESTAMP NULL DEFAULT NULL,
    cancelled_by INT UNSIGNED NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    release_notes TEXT NULL,
    override_reason VARCHAR(255) NULL,
    snapshot_json JSON NULL,
    idempotency_key VARCHAR(80) NULL,
    superseded_by_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_releases_number (release_number),
    UNIQUE KEY uq_releases_job_version (job_id, release_version),
    UNIQUE KEY uq_releases_idempotency (idempotency_key),
    KEY idx_releases_job_status (job_id, status),
    KEY idx_releases_released_at (released_at),
    CONSTRAINT fk_releases_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_releases_requested FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_releases_released FOREIGN KEY (released_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_release_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'RELEASED',
    PRIMARY KEY (id),
    UNIQUE KEY uq_release_items (release_id, job_item_id),
    KEY idx_release_items_item (job_item_id, status),
    CONSTRAINT fk_release_items_release FOREIGN KEY (release_id) REFERENCES production_releases (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_release_items_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_release_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id INT UNSIGNED NOT NULL,
    check_code VARCHAR(40) NOT NULL,
    result VARCHAR(20) NOT NULL,
    severity VARCHAR(40) NOT NULL,
    message VARCHAR(255) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_release_checks_release (release_id, result),
    CONSTRAINT fk_release_checks_release FOREIGN KEY (release_id) REFERENCES production_releases (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_release_overrides (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id INT UNSIGNED NOT NULL,
    check_code VARCHAR(40) NOT NULL,
    original_result VARCHAR(20) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_release_overrides_release (release_id, check_code),
    CONSTRAINT fk_release_overrides_release FOREIGN KEY (release_id) REFERENCES production_releases (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_release_overrides_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_changes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    release_id INT UNSIGNED NULL,
    source VARCHAR(20) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    requested_change TEXT NOT NULL,
    artwork_impact VARCHAR(255) NULL,
    material_impact VARCHAR(255) NULL,
    schedule_impact VARCHAR(255) NULL,
    cost_impact VARCHAR(255) NULL,
    impact VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    variation_id INT UNSIGNED NULL,
    requested_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_production_changes_job (job_id, created_at),
    CONSTRAINT fk_production_changes_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_production_changes_release FOREIGN KEY (release_id) REFERENCES production_releases (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_production_changes_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    release_id INT UNSIGNED NULL,
    category VARCHAR(30) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'DRAFT',
    version_label VARCHAR(40) NOT NULL,
    artwork_revision INT UNSIGNED NULL,
    original_name VARCHAR(180) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_production_files_job (job_id, category, status),
    CONSTRAINT fk_production_files_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_production_files_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_production_files_release FOREIGN KEY (release_id) REFERENCES production_releases (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE colour_references (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    colour_system VARCHAR(20) NOT NULL,
    code VARCHAR(80) NOT NULL,
    source VARCHAR(40) NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_colour_refs_job (job_id),
    CONSTRAINT fk_colour_refs_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_colour_refs_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fulfilment_requirements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    fulfilment_type VARCHAR(30) NOT NULL,
    quantity DECIMAL(14,4) NOT NULL,
    fulfilled_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    project_site_id INT UNSIGNED NULL,
    address TEXT NULL,
    contact_name VARCHAR(120) NULL,
    required_date DATE NULL,
    scheduled_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'NOT_READY',
    packing_status VARCHAR(30) NOT NULL DEFAULT 'NOT_REQUIRED',
    instructions TEXT NULL,
    collected_by VARCHAR(120) NULL,
    collected_at DATETIME NULL,
    carrier VARCHAR(80) NULL,
    waybill VARCHAR(80) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fulfilment_job (job_id, status),
    KEY idx_fulfilment_item (job_item_id),
    KEY idx_fulfilment_site (project_site_id),
    CONSTRAINT fk_fulfilment_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fulfilment_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_fulfilment_site FOREIGN KEY (project_site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_rework (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    stage_id INT UNSIGNED NULL,
    reason_code VARCHAR(40) NOT NULL,
    material_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    labour_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    machine_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    other_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rework_job (job_id),
    CONSTRAINT fk_rework_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rework_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_rework_stage FOREIGN KEY (stage_id) REFERENCES job_production_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_rework_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_packs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id INT UNSIGNED NOT NULL,
    generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    superseded TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_packs_release (release_id, superseded),
    CONSTRAINT fk_packs_release FOREIGN KEY (release_id) REFERENCES production_releases (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_control_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    stage_id INT UNSIGNED NULL,
    audience VARCHAR(20) NOT NULL DEFAULT 'INTERNAL',
    body TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_control_notes_job (job_id, audience),
    CONSTRAINT fk_control_notes_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE material_substitutions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    requirement_id INT UNSIGNED NULL,
    from_product_id INT UNSIGNED NULL,
    to_product_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PROPOSED',
    yield_note VARCHAR(255) NULL,
    cost_note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_substitutions_job (job_id, status),
    CONSTRAINT fk_substitutions_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_stage_actions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stage_id INT UNSIGNED NOT NULL,
    action VARCHAR(20) NOT NULL,
    idempotency_key VARCHAR(80) NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stage_action_key (idempotency_key),
    KEY idx_stage_actions_stage (stage_id, action),
    CONSTRAINT fk_stage_actions_stage FOREIGN KEY (stage_id) REFERENCES job_production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stage_actions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE stock_reservations
    ADD CONSTRAINT fk_stock_reservations_release FOREIGN KEY (source_release_id) REFERENCES production_releases (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE job_production_stages
    ADD CONSTRAINT fk_job_stages_release FOREIGN KEY (release_id) REFERENCES production_releases (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_stages_started_by FOREIGN KEY (started_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO production_release_policies (code, name, applies_to, applies_value, approval_required) VALUES
('DEFAULT', 'Default release policy', 'DEFAULT', NULL, 0),
('VEHICLE', 'Vehicle branding', 'JOB_TYPE', 'VEHICLE_WRAP', 0),
('CHANNEL', 'Channel letters', 'JOB_TYPE', 'CHANNEL_LETTER', 1);

INSERT INTO production_release_policy_checks (policy_id, check_code, severity)
SELECT id, 'JOB_VALID', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'CUSTOMER_CONFIRMED', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'SCOPE_CONFIRMED', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'QUANTITIES_CONFIRMED', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'DIMENSIONS_CONFIRMED', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'ARTWORK_APPROVED', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'ARTWORK_REVISION', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'SPECIFICATION_CONFIRMED', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'TECHNICAL_REVIEW', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'BOM_GENERATED', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'MATERIAL_REQUIREMENTS', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'MATERIAL_AVAILABILITY', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'SHORTAGES_IDENTIFIED', 'INFORMATIONAL' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'PRODUCTION_ROUTE', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'PRODUCTION_FILES', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'QC_CHECKLIST', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'TARGET_DATE', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'FULFILMENT_METHOD', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'LOCATION_CONFIRMED', 'WARNING' FROM production_release_policies WHERE code = 'DEFAULT'
UNION ALL SELECT id, 'SPECIAL_INSTRUCTIONS', 'INFORMATIONAL' FROM production_release_policies WHERE code = 'DEFAULT';

INSERT INTO production_release_policy_checks (policy_id, check_code, severity)
SELECT id, 'ARTWORK_APPROVED', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'VEHICLE'
UNION ALL SELECT id, 'DIMENSIONS_CONFIRMED', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'VEHICLE'
UNION ALL SELECT id, 'MATERIAL_AVAILABILITY', 'WARNING' FROM production_release_policies WHERE code = 'VEHICLE'
UNION ALL SELECT id, 'FULFILMENT_METHOD', 'WARNING' FROM production_release_policies WHERE code = 'VEHICLE';

INSERT INTO production_release_policy_checks (policy_id, check_code, severity)
SELECT id, 'SPECIFICATION_CONFIRMED', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'CHANNEL'
UNION ALL SELECT id, 'ARTWORK_APPROVED', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'CHANNEL'
UNION ALL SELECT id, 'TECHNICAL_REVIEW', 'BLOCK_NO_OVERRIDE' FROM production_release_policies WHERE code = 'CHANNEL'
UNION ALL SELECT id, 'MATERIAL_AVAILABILITY', 'BLOCK_OVERRIDE_WITH_PERMISSION' FROM production_release_policies WHERE code = 'CHANNEL';

INSERT INTO permissions (code, name, module) VALUES
('production.release.view', 'View production release state', 'production_control'),
('production.release.request', 'Request a production release', 'production_control'),
('production.release.approve', 'Release a job to production', 'production_control'),
('production.release.override', 'Override an overridable release block', 'production_control'),
('production.release.cancel', 'Cancel a production release', 'production_control'),
('production.change.request', 'Request a production change', 'production_control'),
('production.change.approve', 'Approve a production change', 'production_control'),
('production.queue.view', 'View the released production queue', 'production_control'),
('production.supervise', 'Supervise production exceptions', 'production_control'),
('production.stage.start', 'Start a released production stage', 'production_control'),
('production.stage.complete', 'Complete a released production stage', 'production_control'),
('production.stage.block', 'Pause or block a production stage', 'production_control'),
('production.rework.manage', 'Record production rework', 'production_control'),
('production.qc.disposition', 'Record a QC disposition', 'production_control'),
('fulfilment.manage', 'Manage line-item fulfilment', 'production_control'),
('production.view_costs', 'View production cost on the release', 'production_control');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.module = 'production_control';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code = 'production.release.view';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DESIGN' AND p.code = 'production.release.view';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN (
    'production.queue.view', 'production.stage.start', 'production.stage.complete',
    'production.stage.block', 'production.rework.manage'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN ('production.release.view', 'fulfilment.manage');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DISPATCH' AND p.code IN ('production.release.view', 'fulfilment.manage');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code = 'production.view_costs';

INSERT INTO settings (setting_key, setting_value) VALUES
('release_prefix', 'SFR');

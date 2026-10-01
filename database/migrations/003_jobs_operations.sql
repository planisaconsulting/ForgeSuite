-- Sign-Forge Management System
-- Phase 3: jobs, artwork, production, installation, and actual costing.
--
-- Apply this once to a database that already has Phase 1 and Phase 2.
-- It does not drop customers, quotations, or existing job rows.
-- A fresh install uses schema.sql, which already includes these tables.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE jobs MODIFY status VARCHAR(40) NOT NULL DEFAULT 'NEW';

SET @sf_jobs_ready = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'archived'
);
SET @sf_jobs_sql = IF(
    @sf_jobs_ready = 0,
    'ALTER TABLE jobs
        ADD COLUMN opportunity_id INT UNSIGNED NULL AFTER quote_revision_number,
        ADD COLUMN description TEXT NULL AFTER title,
        ADD COLUMN project_manager_id INT UNSIGNED NULL AFTER assigned_to,
        ADD COLUMN production_due_date DATE NULL AFTER target_date,
        ADD COLUMN installation_date DATE NULL AFTER production_due_date,
        ADD COLUMN delivery_method VARCHAR(20) NOT NULL DEFAULT ''INSTALLATION'' AFTER installation_date,
        ADD COLUMN site_address TEXT NULL AFTER delivery_method,
        ADD COLUMN site_contact_name VARCHAR(120) NULL AFTER site_address,
        ADD COLUMN site_contact_phone VARCHAR(40) NULL AFTER site_contact_name,
        ADD COLUMN customer_po_number VARCHAR(80) NULL AFTER site_contact_phone,
        ADD COLUMN quoted_revenue_snapshot DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER customer_po_number,
        ADD COLUMN quoted_cost_snapshot DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER quoted_revenue_snapshot,
        ADD COLUMN actual_material_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER quoted_cost_snapshot,
        ADD COLUMN actual_labour_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER actual_material_cost,
        ADD COLUMN actual_other_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER actual_labour_cost,
        ADD COLUMN actual_total_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER actual_other_cost,
        ADD COLUMN customer_notes TEXT NULL AFTER actual_total_cost,
        ADD COLUMN production_notes TEXT NULL AFTER customer_notes,
        ADD COLUMN installation_notes TEXT NULL AFTER production_notes,
        ADD COLUMN internal_notes TEXT NULL AFTER installation_notes,
        ADD COLUMN artwork_override_by INT UNSIGNED NULL AFTER internal_notes,
        ADD COLUMN artwork_override_reason VARCHAR(255) NULL AFTER artwork_override_by,
        ADD COLUMN artwork_override_at TIMESTAMP NULL DEFAULT NULL AFTER artwork_override_reason,
        ADD COLUMN completion_override_by INT UNSIGNED NULL AFTER artwork_override_at,
        ADD COLUMN completion_override_reason VARCHAR(255) NULL AFTER completion_override_by,
        ADD COLUMN completion_override_at TIMESTAMP NULL DEFAULT NULL AFTER completion_override_reason,
        ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL AFTER completion_override_at,
        ADD COLUMN completed_by INT UNSIGNED NULL AFTER completed_at,
        ADD COLUMN version_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER completed_by,
        ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0 AFTER version_number,
        ADD KEY idx_jobs_target (target_date),
        ADD KEY idx_jobs_assigned (assigned_to),
        ADD KEY idx_jobs_install (installation_date),
        ADD KEY idx_jobs_opportunity (opportunity_id),
        ADD CONSTRAINT fk_jobs_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
        ADD CONSTRAINT fk_jobs_manager FOREIGN KEY (project_manager_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
        ADD CONSTRAINT fk_jobs_artwork_override FOREIGN KEY (artwork_override_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
        ADD CONSTRAINT fk_jobs_completion_override FOREIGN KEY (completion_override_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
        ADD CONSTRAINT fk_jobs_completed_by FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE sf_jobs_stmt FROM @sf_jobs_sql;
EXECUTE sf_jobs_stmt;
DEALLOCATE PREPARE sf_jobs_stmt;

SET @sf_user_cost = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'hourly_cost'
);
SET @sf_user_sql = IF(@sf_user_cost = 0, 'ALTER TABLE users ADD COLUMN hourly_cost DECIMAL(14,2) NULL AFTER must_change_password', 'SELECT 1');
PREPARE sf_user_stmt FROM @sf_user_sql;
EXECUTE sf_user_stmt;
DEALLOCATE PREPARE sf_user_stmt;

SET @sf_attach_purpose = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attachments' AND COLUMN_NAME = 'purpose'
);
SET @sf_attach_sql = IF(
    @sf_attach_purpose = 0,
    'ALTER TABLE attachments ADD COLUMN purpose VARCHAR(40) NOT NULL DEFAULT ''GENERAL'' AFTER mime_type, ADD COLUMN notes VARCHAR(255) NULL AFTER purpose',
    'SELECT 1'
);
PREPARE sf_attach_stmt FROM @sf_attach_sql;
EXECUTE sf_attach_stmt;
DEALLOCATE PREPARE sf_attach_stmt;

CREATE TABLE IF NOT EXISTS teams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_teams_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
    team_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (team_id, user_id),
    KEY idx_team_members_user (user_id),
    CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_team_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_stages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_production_stages_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_route_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_route_templates_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_route_template_stages (
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

CREATE TABLE IF NOT EXISTS job_items (
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

CREATE TABLE IF NOT EXISTS job_tasks (
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

CREATE TABLE IF NOT EXISTS job_production_stages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    production_stage_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'NOT_STARTED',
    assigned_to INT UNSIGNED NULL,
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

CREATE TABLE IF NOT EXISTS job_artworks (
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

CREATE TABLE IF NOT EXISTS artwork_approvals (
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

CREATE TABLE IF NOT EXISTS job_material_requirements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    required_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    calculated_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    manual_adjustment DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    final_required_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
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

CREATE TABLE IF NOT EXISTS job_material_usage (
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

CREATE TABLE IF NOT EXISTS job_time_entries (
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

CREATE TABLE IF NOT EXISTS job_other_costs (
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

CREATE TABLE IF NOT EXISTS job_installations (
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

CREATE TABLE IF NOT EXISTS installation_checklist_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installation_checklist_template_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    label VARCHAR(180) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_install_template_items (template_id, sort_order),
    CONSTRAINT fk_install_template_items FOREIGN KEY (template_id) REFERENCES installation_checklist_templates (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installation_checklist_items (
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

CREATE TABLE IF NOT EXISTS qc_check_definitions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_qc_definitions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_quality_checks (
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

CREATE TABLE IF NOT EXISTS job_status_history (
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

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO permissions (code, name, module)
SELECT * FROM (
    SELECT 'jobs.create' AS code, 'Create jobs from accepted quotes' AS name, 'operations' AS module
    UNION ALL SELECT 'jobs.edit', 'Edit job details', 'operations'
    UNION ALL SELECT 'jobs.assign', 'Assign jobs and tasks', 'operations'
    UNION ALL SELECT 'jobs.change_status', 'Change job status', 'operations'
    UNION ALL SELECT 'jobs.complete', 'Complete or archive a job', 'operations'
    UNION ALL SELECT 'jobs.reopen', 'Reopen a completed or cancelled job', 'operations'
    UNION ALL SELECT 'artwork.upload', 'Upload artwork proofs', 'operations'
    UNION ALL SELECT 'artwork.approve_record', 'Record customer artwork approval', 'operations'
    UNION ALL SELECT 'artwork.override_approval', 'Proceed without artwork approval', 'operations'
    UNION ALL SELECT 'production.view', 'View the production board', 'operations'
    UNION ALL SELECT 'production.update', 'Update production stages and tasks', 'operations'
    UNION ALL SELECT 'materials.view', 'View material requirements', 'operations'
    UNION ALL SELECT 'materials.record_usage', 'Record material usage and waste', 'operations'
    UNION ALL SELECT 'time.record', 'Record labour time', 'operations'
    UNION ALL SELECT 'time.view_all', 'See every person''s time entries', 'operations'
    UNION ALL SELECT 'installations.view', 'View installations', 'operations'
    UNION ALL SELECT 'installations.schedule', 'Schedule installations', 'operations'
    UNION ALL SELECT 'installations.complete', 'Complete installations', 'operations'
    UNION ALL SELECT 'costing.edit', 'Record other job costs', 'operations'
) AS incoming
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.code = incoming.code);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
    'jobs.view', 'jobs.create', 'jobs.edit', 'installations.view', 'artwork.approve_record', 'costing.view'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DESIGN' AND p.code IN (
    'jobs.view', 'artwork.upload', 'artwork.approve_record', 'production.view', 'time.record', 'attachments.manage'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN (
    'jobs.view', 'jobs.change_status', 'production.view', 'production.update',
    'materials.view', 'materials.record_usage', 'time.record', 'attachments.manage'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN (
    'jobs.view', 'installations.view', 'installations.complete', 'time.record', 'attachments.manage'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN (
    'jobs.view', 'costing.view', 'costing.edit', 'time.view_all', 'materials.view'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO settings (setting_key, setting_value)
SELECT 'default_labour_hourly_cost', '0'
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE setting_key = 'default_labour_hourly_cost');

INSERT INTO teams (code, name) SELECT 'DESIGN', 'Design' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE code = 'DESIGN');
INSERT INTO teams (code, name) SELECT 'PRODUCTION', 'Production' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE code = 'PRODUCTION');
INSERT INTO teams (code, name) SELECT 'INSTALLATION', 'Installation' WHERE NOT EXISTS (SELECT 1 FROM teams WHERE code = 'INSTALLATION');

INSERT INTO production_stages (name, description, sort_order)
SELECT 'Artwork', 'Design and customer proof', 10 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Artwork');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Printing', 'Print the graphics', 20 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Printing');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Lamination', 'Laminate printed graphics', 30 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Lamination');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Cutting', 'Cut to size', 40 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Cutting');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'CNC', 'CNC cutting or routing', 50 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'CNC');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Fabrication', 'Fabricate the structure', 60 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Fabrication');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Painting', 'Paint and finish', 70 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Painting');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Assembly', 'Assemble the sign', 80 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Assembly');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Electrical', 'LED and electrical work', 90 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Electrical');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Quality Control', 'Check the finished work', 100 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Quality Control');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Packing', 'Pack for collection or delivery', 110 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Packing');
INSERT INTO production_stages (name, description, sort_order)
SELECT 'Installation', 'Install on site', 120 WHERE NOT EXISTS (SELECT 1 FROM production_stages WHERE name = 'Installation');

INSERT INTO production_route_templates (code, name, description)
SELECT 'PRINTED_VINYL', 'Printed vinyl', 'Artwork, print, laminate, cut, and quality control.'
WHERE NOT EXISTS (SELECT 1 FROM production_route_templates WHERE code = 'PRINTED_VINYL');
INSERT INTO production_route_templates (code, name, description)
SELECT 'ACM_SIGN', 'ACM sign', 'Artwork, print, laminate, CNC, and quality control.'
WHERE NOT EXISTS (SELECT 1 FROM production_route_templates WHERE code = 'ACM_SIGN');
INSERT INTO production_route_templates (code, name, description)
SELECT 'FABRICATED_SIGN', 'Fabricated sign', 'Artwork, CNC, fabrication, paint, assembly, and quality control.'
WHERE NOT EXISTS (SELECT 1 FROM production_route_templates WHERE code = 'FABRICATED_SIGN');
INSERT INTO production_route_templates (code, name, description)
SELECT 'ILLUMINATED_SIGN', 'Illuminated sign', 'Fabrication plus electrical test and quality control.'
WHERE NOT EXISTS (SELECT 1 FROM production_route_templates WHERE code = 'ILLUMINATED_SIGN');

INSERT INTO production_route_template_stages (template_id, production_stage_id, sort_order)
SELECT t.id, s.id, v.sort_order
FROM (
    SELECT 'PRINTED_VINYL' AS code, 'Artwork' AS stage, 10 AS sort_order
    UNION ALL SELECT 'PRINTED_VINYL', 'Printing', 20
    UNION ALL SELECT 'PRINTED_VINYL', 'Lamination', 30
    UNION ALL SELECT 'PRINTED_VINYL', 'Cutting', 40
    UNION ALL SELECT 'PRINTED_VINYL', 'Quality Control', 50
    UNION ALL SELECT 'ACM_SIGN', 'Artwork', 10
    UNION ALL SELECT 'ACM_SIGN', 'Printing', 20
    UNION ALL SELECT 'ACM_SIGN', 'Lamination', 30
    UNION ALL SELECT 'ACM_SIGN', 'CNC', 40
    UNION ALL SELECT 'ACM_SIGN', 'Quality Control', 50
    UNION ALL SELECT 'FABRICATED_SIGN', 'Artwork', 10
    UNION ALL SELECT 'FABRICATED_SIGN', 'CNC', 20
    UNION ALL SELECT 'FABRICATED_SIGN', 'Fabrication', 30
    UNION ALL SELECT 'FABRICATED_SIGN', 'Painting', 40
    UNION ALL SELECT 'FABRICATED_SIGN', 'Assembly', 50
    UNION ALL SELECT 'FABRICATED_SIGN', 'Quality Control', 60
    UNION ALL SELECT 'ILLUMINATED_SIGN', 'Artwork', 10
    UNION ALL SELECT 'ILLUMINATED_SIGN', 'CNC', 20
    UNION ALL SELECT 'ILLUMINATED_SIGN', 'Fabrication', 30
    UNION ALL SELECT 'ILLUMINATED_SIGN', 'Painting', 40
    UNION ALL SELECT 'ILLUMINATED_SIGN', 'Electrical', 50
    UNION ALL SELECT 'ILLUMINATED_SIGN', 'Assembly', 60
    UNION ALL SELECT 'ILLUMINATED_SIGN', 'Quality Control', 70
) AS v
INNER JOIN production_route_templates t ON t.code = v.code
INNER JOIN production_stages s ON s.name = v.stage
WHERE NOT EXISTS (
    SELECT 1 FROM production_route_template_stages x
    WHERE x.template_id = t.id AND x.production_stage_id = s.id
);

INSERT INTO installation_checklist_templates (name)
SELECT 'Standard installation'
WHERE NOT EXISTS (SELECT 1 FROM installation_checklist_templates WHERE name = 'Standard installation');

INSERT INTO installation_checklist_template_items (template_id, label, sort_order)
SELECT t.id, v.label, v.sort_order
FROM installation_checklist_templates t
INNER JOIN (
    SELECT 'Correct signage loaded' AS label, 10 AS sort_order
    UNION ALL SELECT 'Tools loaded', 20
    UNION ALL SELECT 'Fixings loaded', 30
    UNION ALL SELECT 'Electrical components loaded', 40
    UNION ALL SELECT 'PPE', 50
    UNION ALL SELECT 'Site access confirmed', 60
    UNION ALL SELECT 'Sign installed level', 70
    UNION ALL SELECT 'Fixings checked', 80
    UNION ALL SELECT 'Electrical tested', 90
    UNION ALL SELECT 'Site cleaned', 100
    UNION ALL SELECT 'Completion photos taken', 110
    UNION ALL SELECT 'Customer sign-off', 120
) AS v
WHERE t.name = 'Standard installation'
  AND NOT EXISTS (
      SELECT 1 FROM installation_checklist_template_items i
      WHERE i.template_id = t.id AND i.label = v.label
  );

INSERT INTO qc_check_definitions (name, sort_order)
SELECT v.name, v.sort_order FROM (
    SELECT 'Correct dimensions' AS name, 10 AS sort_order
    UNION ALL SELECT 'Correct spelling', 20
    UNION ALL SELECT 'Correct colours', 30
    UNION ALL SELECT 'Artwork matches approval', 40
    UNION ALL SELECT 'Print quality', 50
    UNION ALL SELECT 'Lamination quality', 60
    UNION ALL SELECT 'Cut quality', 70
    UNION ALL SELECT 'Fabrication quality', 80
    UNION ALL SELECT 'Electrical test', 90
    UNION ALL SELECT 'Correct quantity', 100
    UNION ALL SELECT 'Clean and finished', 110
    UNION ALL SELECT 'Packaging', 120
    UNION ALL SELECT 'Installation hardware included', 130
) AS v
WHERE NOT EXISTS (SELECT 1 FROM qc_check_definitions d WHERE d.name = v.name);

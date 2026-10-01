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

INSERT INTO project_types (code, name) VALUES
('MULTI_SITE_ROLLOUT', 'Multi-site rollout'),
('FLEET_BRANDING', 'Fleet branding'),
('CORPORATE_REBRAND', 'Corporate rebrand'),
('SIGNAGE_PROGRAMME', 'Signage programme'),
('CAMPAIGN', 'Campaign'),
('NEW_STORE', 'New store'),
('REFURBISHMENT', 'Refurbishment'),
('MAINTENANCE_PROGRAMME', 'Maintenance programme'),
('CUSTOM', 'Custom');

INSERT INTO milestone_types (code, name, default_weight) VALUES
('CONTRACT_AWARDED', 'Contract awarded', 5.00),
('SITE_SURVEYS_COMPLETE', 'Site surveys complete', 10.00),
('MEASUREMENTS_APPROVED', 'Measurements approved', 5.00),
('ARTWORK_SUBMITTED', 'Artwork submitted', 5.00),
('ARTWORK_APPROVED', 'Artwork approved', 10.00),
('PROCUREMENT_COMPLETE', 'Procurement complete', 10.00),
('PRODUCTION_STARTED', 'Production started', 5.00),
('PRODUCTION_COMPLETE', 'Production complete', 20.00),
('INSTALLATION_STARTED', 'Installation started', 5.00),
('INSTALLATION_COMPLETE', 'Installation complete', 15.00),
('SNAGS_RESOLVED', 'Snags resolved', 5.00),
('PROJECT_HANDOVER', 'Project handover', 5.00),
('CUSTOM', 'Custom', 0.00);

INSERT INTO delay_reasons (code, name) VALUES
('CUSTOMER_DELAY', 'Customer delay'),
('ARTWORK_DELAY', 'Artwork delay'),
('MATERIAL_DELAY', 'Material delay'),
('WEATHER', 'Weather'),
('SITE_NOT_READY', 'Site not ready'),
('INTERNAL_CAPACITY', 'Internal capacity'),
('SCOPE_CHANGE', 'Scope change'),
('OTHER', 'Other');

INSERT INTO risk_categories (code, name) VALUES
('CUSTOMER', 'Customer'),
('ARTWORK', 'Artwork'),
('MATERIAL', 'Material'),
('SUPPLIER', 'Supplier'),
('PRODUCTION', 'Production'),
('INSTALLATION', 'Installation'),
('SITE', 'Site'),
('WEATHER', 'Weather'),
('FINANCIAL', 'Financial'),
('SCHEDULE', 'Schedule'),
('OTHER', 'Other');

INSERT INTO issue_categories (code, name) VALUES
('CUSTOMER', 'Customer'),
('ARTWORK', 'Artwork'),
('MATERIAL', 'Material'),
('PRODUCTION', 'Production'),
('INSTALLATION', 'Installation'),
('SITE', 'Site'),
('FINANCIAL', 'Financial'),
('OTHER', 'Other');

INSERT INTO project_templates (name, project_type_id, description, template_version)
SELECT 'Multi-branch rebrand', id, 'Survey, artwork, production, installation, and handover for a branch rebrand.', 1
FROM project_types WHERE code = 'CORPORATE_REBRAND';

INSERT INTO project_template_milestones (template_id, name, milestone_type, weight, sequence, blocking)
SELECT t.id, m.name, m.milestone_type, m.weight, m.sequence, m.blocking
FROM project_templates t
JOIN (
    SELECT 'Site surveys' AS name, 'SITE_SURVEYS_COMPLETE' AS milestone_type, 10.00 AS weight, 1 AS sequence, 1 AS blocking
    UNION ALL SELECT 'Artwork approved', 'ARTWORK_APPROVED', 15.00, 2, 1
    UNION ALL SELECT 'Production complete', 'PRODUCTION_COMPLETE', 40.00, 3, 1
    UNION ALL SELECT 'Installation complete', 'INSTALLATION_COMPLETE', 30.00, 4, 1
    UNION ALL SELECT 'Handover', 'PROJECT_HANDOVER', 5.00, 5, 0
) m
WHERE t.name = 'Multi-branch rebrand';

INSERT INTO project_templates (name, project_type_id, description, template_version)
SELECT 'Fleet branding programme', id, 'Repeat vehicle branding across a fleet.', 1 FROM project_types WHERE code = 'FLEET_BRANDING';
INSERT INTO project_templates (name, project_type_id, description, template_version)
SELECT 'Store opening', id, 'Signage for a new store opening.', 1 FROM project_types WHERE code = 'NEW_STORE';
INSERT INTO project_templates (name, project_type_id, description, template_version)
SELECT 'Signage refresh', id, 'Replacement of existing signage.', 1 FROM project_types WHERE code = 'REFURBISHMENT';
INSERT INTO project_templates (name, project_type_id, description, template_version)
SELECT 'National rollout', id, 'Phased national signage rollout.', 1 FROM project_types WHERE code = 'MULTI_SITE_ROLLOUT';

INSERT INTO permissions (code, name, module) VALUES
('projects.view', 'View all projects', 'projects'),
('projects.view_assigned', 'View assigned projects', 'projects'),
('projects.create', 'Create projects', 'projects'),
('projects.edit', 'Edit projects', 'projects'),
('projects.archive', 'Archive projects', 'projects'),
('projects.complete', 'Complete projects', 'projects'),
('projects.view_financials', 'View project financials', 'projects'),
('projects.manage_team', 'Manage the project team', 'projects'),
('projects.manage_sites', 'Manage project sites', 'projects'),
('projects.import_sites', 'Import project sites', 'projects'),
('projects.bulk_create_jobs', 'Create draft rollout jobs', 'projects'),
('projects.manage_milestones', 'Manage project milestones', 'projects'),
('projects.manage_risks', 'Manage project risks', 'projects'),
('projects.manage_issues', 'Manage project issues', 'projects'),
('projects.manage_budget', 'Manage the project budget', 'projects'),
('projects.manage_changes', 'Manage project changes', 'projects'),
('projects.generate_handover', 'Generate a handover pack', 'projects'),
('projects.view_reports', 'View project reports', 'projects');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN' AND p.module = 'projects';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.module = 'projects';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
    'projects.view', 'projects.view_assigned', 'projects.create', 'projects.view_financials', 'projects.manage_sites'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN (
    'projects.view', 'projects.view_assigned', 'projects.view_financials', 'projects.view_reports'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('DESIGN', 'PRODUCTION', 'INSTALLER') AND p.code = 'projects.view_assigned';

INSERT INTO settings (setting_key, setting_value) VALUES
('project_prefix', 'SFP'),
('project_closeout_block_critical_snags', '1'),
('project_closeout_block_open_invoices', '0'),
('project_date_reason_required', '0');

UPDATE settings SET setting_value = '15' WHERE setting_key = 'schema_version';

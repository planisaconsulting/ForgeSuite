-- Sign-Forge Management System
-- Phase 7: site surveys, signage recipes, and the customer portal.
--
-- Apply this once to a database that already has Phases 1 to 6.
-- It does not rebuild quotations, jobs, stock, or invoices.
-- Existing attachments stay INTERNAL. Existing quotes are not recalculated.

SET NAMES utf8mb4;

ALTER TABLE products
    MODIFY product_type ENUM('MATERIAL', 'COMPONENT', 'SERVICE', 'LABOUR', 'CONSUMABLE', 'FINISHED_PRODUCT') NOT NULL DEFAULT 'MATERIAL';

CREATE TABLE IF NOT EXISTS recipe_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipe_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    category_id INT UNSIGNED NULL,
    finished_product_id INT UNSIGNED NULL,
    recipe_type VARCHAR(20) NOT NULL DEFAULT 'SIGNAGE',
    pricing_method VARCHAR(20) NOT NULL DEFAULT 'MARKUP',
    production_route_template_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipes_code (code),
    KEY idx_recipes_active (active, name),
    CONSTRAINT fk_recipes_category FOREIGN KEY (category_id) REFERENCES recipe_categories (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recipes_product FOREIGN KEY (finished_product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recipes_route FOREIGN KEY (production_route_template_id) REFERENCES production_route_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recipes_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_inputs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    code VARCHAR(40) NOT NULL,
    label VARCHAR(120) NOT NULL,
    input_type VARCHAR(20) NOT NULL DEFAULT 'NUMBER',
    unit VARCHAR(20) NULL,
    required TINYINT(1) NOT NULL DEFAULT 1,
    default_value VARCHAR(120) NULL,
    min_value VARCHAR(40) NULL,
    max_value VARCHAR(40) NULL,
    options_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipe_inputs_code (recipe_id, code),
    CONSTRAINT fk_recipe_inputs_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    component_type VARCHAR(20) NOT NULL DEFAULT 'MATERIAL',
    product_id INT UNSIGNED NULL,
    nested_recipe_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    quantity_formula VARCHAR(255) NOT NULL,
    waste_percent_override DECIMAL(7,2) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    cost_calculation_method VARCHAR(20) NOT NULL DEFAULT 'PRODUCT',
    rounding_rule VARCHAR(10) NOT NULL DEFAULT 'NONE',
    pack_size DECIMAL(14,4) NULL,
    yield_mode VARCHAR(10) NOT NULL DEFAULT 'NONE',
    condition_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    optional TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recipe_items_recipe (recipe_id, sort_order),
    CONSTRAINT fk_recipe_items_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_recipe_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_recipe_items_nested FOREIGN KEY (nested_recipe_id) REFERENCES recipes (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recipe_versions (recipe_id, version_number),
    CONSTRAINT fk_recipe_versions_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_recipe_versions_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signage_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(180) NOT NULL,
    category VARCHAR(80) NULL,
    finished_product_id INT UNSIGNED NULL,
    recipe_id INT UNSIGNED NULL,
    default_inputs_json JSON NULL,
    customer_description TEXT NULL,
    internal_description TEXT NULL,
    notes TEXT NULL,
    production_route_template_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_signage_templates_code (code),
    CONSTRAINT fk_signage_templates_product FOREIGN KEY (finished_product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_signage_templates_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_signage_templates_route FOREIGN KEY (production_route_template_id) REFERENCES production_route_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_signage_templates_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_recipe_snapshots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_item_id INT UNSIGNED NOT NULL,
    recipe_id INT UNSIGNED NULL,
    recipe_version INT UNSIGNED NOT NULL,
    input_snapshot_json JSON NOT NULL,
    component_snapshot_json JSON NOT NULL,
    cost_snapshot_json JSON NOT NULL,
    production_route_snapshot_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quote_recipe_item (quote_item_id),
    CONSTRAINT fk_quote_recipe_item FOREIGN KEY (quote_item_id) REFERENCES quote_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_quote_recipe_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_expected_labour (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    job_item_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    expected_minutes DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    hourly_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    source VARCHAR(20) NOT NULL DEFAULT 'RECIPE',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_expected_labour_job (job_id),
    CONSTRAINT fk_expected_labour_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_expected_labour_item FOREIGN KEY (job_item_id) REFERENCES job_items (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_surveys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    survey_number VARCHAR(40) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    quote_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    site_name VARCHAR(180) NOT NULL,
    address_line_1 VARCHAR(180) NULL,
    address_line_2 VARCHAR(180) NULL,
    city VARCHAR(80) NULL,
    province VARCHAR(80) NULL,
    postal_code VARCHAR(20) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    site_contact_name VARCHAR(120) NULL,
    site_contact_phone VARCHAR(40) NULL,
    survey_date DATE NULL,
    surveyed_by INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    environment VARCHAR(20) NULL,
    surface_type VARCHAR(80) NULL,
    mounting_height_mm DECIMAL(10,2) NULL,
    access_difficulty VARCHAR(40) NULL,
    ladder_required TINYINT(1) NOT NULL DEFAULT 0,
    scaffolding_required TINYINT(1) NOT NULL DEFAULT 0,
    cherry_picker_required TINYINT(1) NOT NULL DEFAULT 0,
    electrical_supply TINYINT(1) NOT NULL DEFAULT 0,
    power_location VARCHAR(180) NULL,
    height_notes TEXT NULL,
    traffic_notes TEXT NULL,
    special_access TEXT NULL,
    access_notes TEXT NULL,
    installation_notes TEXT NULL,
    electrical_notes TEXT NULL,
    general_notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_surveys_number (survey_number),
    KEY idx_site_surveys_customer (customer_id),
    KEY idx_site_surveys_status (status),
    CONSTRAINT fk_site_surveys_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_user FOREIGN KEY (surveyed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_site_surveys_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_survey_measurements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_survey_id INT UNSIGNED NOT NULL,
    reference VARCHAR(80) NOT NULL,
    measurement_type VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    width_mm DECIMAL(12,2) NULL,
    height_mm DECIMAL(12,2) NULL,
    depth_mm DECIMAL(12,2) NULL,
    length_mm DECIMAL(12,2) NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
    description VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_survey_measurements_survey (site_survey_id),
    CONSTRAINT fk_survey_measurements_survey FOREIGN KEY (site_survey_id) REFERENCES site_surveys (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    customer_contact_id INT UNSIGNED NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_portal_users_email (email),
    KEY idx_portal_users_customer (customer_id),
    CONSTRAINT fk_portal_users_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_users_contact FOREIGN KEY (customer_contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_access_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NOT NULL,
    purpose VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_portal_tokens_hash (token_hash),
    KEY idx_portal_tokens_customer (customer_id, purpose),
    CONSTRAINT fk_portal_tokens_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_tokens_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_actions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NULL,
    statement_version VARCHAR(20) NULL,
    statement_text TEXT NULL,
    ip_address VARCHAR(45) NULL,
    payload_json JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_portal_actions_entity (entity_type, entity_id),
    CONSTRAINT fk_portal_actions_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_actions_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    portal_user_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    event VARCHAR(40) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_portal_audit_customer (customer_id, created_at),
    CONSTRAINT fk_portal_audit_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_rate_limits (
    bucket VARCHAR(190) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    PRIMARY KEY (bucket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_portal_messages_entity (entity_type, entity_id),
    CONSTRAINT fk_portal_messages_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_portal_messages_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portal_status_map (
    internal_status VARCHAR(40) NOT NULL,
    customer_label VARCHAR(80) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (internal_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_templates_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sf_att_vis = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attachments' AND COLUMN_NAME = 'visibility');
SET @sf_att_vis_sql = IF(@sf_att_vis = 0, 'ALTER TABLE attachments ADD COLUMN visibility VARCHAR(30) NOT NULL DEFAULT ''INTERNAL'' AFTER purpose, ADD COLUMN photo_tag VARCHAR(40) NULL AFTER visibility, ADD COLUMN measurement_id INT UNSIGNED NULL AFTER photo_tag, ADD COLUMN portal_user_id INT UNSIGNED NULL AFTER measurement_id, ADD COLUMN annotation_json JSON NULL AFTER portal_user_id', 'SELECT 1');
PREPARE sf_att_vis_stmt FROM @sf_att_vis_sql;
EXECUTE sf_att_vis_stmt;
DEALLOCATE PREPARE sf_att_vis_stmt;

SET @sf_req_pack = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_material_requirements' AND COLUMN_NAME = 'purchase_quantity');
SET @sf_req_pack_sql = IF(@sf_req_pack = 0, 'ALTER TABLE job_material_requirements ADD COLUMN purchase_quantity DECIMAL(14,4) NULL AFTER final_required_quantity, ADD COLUMN pack_size DECIMAL(14,4) NULL AFTER purchase_quantity', 'SELECT 1');
PREPARE sf_req_pack_stmt FROM @sf_req_pack_sql;
EXECUTE sf_req_pack_stmt;
DEALLOCATE PREPARE sf_req_pack_stmt;

SET @sf_att_fk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attachments' AND CONSTRAINT_NAME = 'fk_attachments_measurement');
SET @sf_att_fk_sql = IF(@sf_att_fk = 0, 'ALTER TABLE attachments ADD CONSTRAINT fk_attachments_measurement FOREIGN KEY (measurement_id) REFERENCES site_survey_measurements (id) ON DELETE SET NULL ON UPDATE CASCADE, ADD CONSTRAINT fk_attachments_portal_user FOREIGN KEY (portal_user_id) REFERENCES portal_users (id) ON DELETE SET NULL ON UPDATE CASCADE', 'SELECT 1');
PREPARE sf_att_fk_stmt FROM @sf_att_fk_sql;
EXECUTE sf_att_fk_stmt;
DEALLOCATE PREPARE sf_att_fk_stmt;

INSERT INTO recipe_categories (name, sort_order)
SELECT v.name, v.sort_order FROM (
    SELECT 'Boards' AS name, 10 AS sort_order
    UNION ALL SELECT 'Vinyl', 20
    UNION ALL SELECT 'Vehicle Branding', 30
    UNION ALL SELECT 'Banners', 40
    UNION ALL SELECT 'Fabricated Signs', 50
    UNION ALL SELECT 'Illuminated Signs', 60
    UNION ALL SELECT '3D Lettering', 70
    UNION ALL SELECT 'Installation', 80
) AS v
WHERE NOT EXISTS (SELECT 1 FROM recipe_categories c WHERE c.name = v.name);

INSERT INTO portal_status_map (internal_status, customer_label, sort_order)
SELECT v.internal_status, v.customer_label, v.sort_order FROM (
    SELECT 'NEW' AS internal_status, 'In preparation' AS customer_label, 10 AS sort_order
    UNION ALL SELECT 'AWAITING_ARTWORK', 'In preparation', 20
    UNION ALL SELECT 'AWAITING_CUSTOMER_APPROVAL', 'In preparation', 30
    UNION ALL SELECT 'APPROVED_FOR_PRODUCTION', 'In production', 40
    UNION ALL SELECT 'MATERIALS_REQUIRED', 'In production', 50
    UNION ALL SELECT 'READY_FOR_PRODUCTION', 'In production', 60
    UNION ALL SELECT 'IN_PRODUCTION', 'In production', 70
    UNION ALL SELECT 'QUALITY_CONTROL', 'In production', 80
    UNION ALL SELECT 'READY_FOR_INSTALLATION', 'Ready for installation', 90
    UNION ALL SELECT 'INSTALLATION_SCHEDULED', 'Ready for installation', 100
    UNION ALL SELECT 'INSTALLATION_IN_PROGRESS', 'Ready for installation', 110
    UNION ALL SELECT 'READY_FOR_COLLECTION', 'Ready for collection', 120
    UNION ALL SELECT 'COMPLETED', 'Completed', 130
    UNION ALL SELECT 'ON_HOLD', 'On hold', 140
    UNION ALL SELECT 'CANCELLED', 'Cancelled', 150
) AS v
WHERE NOT EXISTS (SELECT 1 FROM portal_status_map m WHERE m.internal_status = v.internal_status);

INSERT INTO email_templates (code, name, subject, body, active)
SELECT v.code, v.name, v.subject, v.body, 0 FROM (
    SELECT 'QUOTE_AVAILABLE' AS code, 'Quote available' AS name, 'Your quotation is ready' AS subject, 'A quotation is available in the Sign-Forge portal. Open the link your salesperson sent you. This message is not sent until email delivery is configured.' AS body
    UNION ALL SELECT 'ARTWORK_APPROVAL', 'Artwork approval requested', 'Please approve your artwork', 'Artwork is ready for your review in the portal. Check spelling, contact details, colours, dimensions, and layout before you approve.'
    UNION ALL SELECT 'INVOICE_AVAILABLE', 'Invoice available', 'Your invoice is ready', 'An invoice is available in the portal, including the total, the amount paid, and the balance.'
    UNION ALL SELECT 'INSTALLATION_SCHEDULED', 'Installation scheduled', 'Your installation is scheduled', 'An installation date is on your job in the portal.'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM email_templates t WHERE t.code = v.code);

INSERT INTO permissions (code, name, module)
SELECT * FROM (
    SELECT 'recipes.view' AS code, 'View signage recipes' AS name, 'automation' AS module
    UNION ALL SELECT 'recipes.create', 'Create signage recipes', 'automation'
    UNION ALL SELECT 'recipes.edit', 'Edit signage recipes', 'automation'
    UNION ALL SELECT 'recipes.deactivate', 'Deactivate signage recipes', 'automation'
    UNION ALL SELECT 'recipes.view_cost', 'See recipe cost and margin', 'automation'
    UNION ALL SELECT 'recipes.test', 'Test a recipe without a quote', 'automation'
    UNION ALL SELECT 'templates.view', 'View signage templates', 'sales'
    UNION ALL SELECT 'templates.manage', 'Manage signage templates', 'sales'
    UNION ALL SELECT 'site_surveys.view', 'View site surveys', 'crm'
    UNION ALL SELECT 'site_surveys.create', 'Create site surveys', 'crm'
    UNION ALL SELECT 'site_surveys.edit', 'Edit site surveys', 'crm'
    UNION ALL SELECT 'site_surveys.complete', 'Complete site surveys', 'crm'
    UNION ALL SELECT 'portal.manage', 'Manage the customer portal', 'admin'
    UNION ALL SELECT 'portal.access_manage', 'Issue customer portal access', 'admin'
) AS incoming
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.code = incoming.code);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN'
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
    'recipes.view', 'recipes.test', 'recipes.view_cost',
    'templates.view', 'templates.manage',
    'site_surveys.view', 'site_surveys.create', 'site_surveys.edit', 'site_surveys.complete',
    'portal.access_manage'
)
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN (
    'site_surveys.view', 'site_surveys.create', 'site_surveys.edit'
)
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DESIGN' AND p.code IN ('site_surveys.view', 'recipes.view', 'templates.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN ('site_surveys.view', 'recipes.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.code IN (
    'recipes.view', 'recipes.view_cost', 'recipes.test', 'templates.view', 'site_surveys.view'
)
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO settings (setting_key, setting_value)
SELECT v.setting_key, v.setting_value FROM (
    SELECT 'survey_prefix' AS setting_key, 'SFS' AS setting_value
    UNION ALL SELECT 'travel_rate_per_km', '0'
    UNION ALL SELECT 'quote_acceptance_statement', 'I accept this quotation, including the revision, total, VAT, terms, and expiry shown above.'
    UNION ALL SELECT 'artwork_approval_statement', 'Please check spelling, contact details, colours, dimensions and layout carefully before approving. I approve this artwork revision for production.'
    UNION ALL SELECT 'portal_link_hours', '72'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = v.setting_key);

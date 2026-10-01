-- Sign-Forge Management System
-- Phase 9: estimating, yield, and pricing intelligence.
--
-- Apply this once to a database that already has Phases 1 to 8.
-- It does not change historical quote or job selling prices.

SET NAMES utf8mb4;

SET @sf_dir = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'direction_sensitive');
SET @sf_dir_sql = IF(@sf_dir = 0, 'ALTER TABLE products ADD COLUMN direction_sensitive TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_nesting, ADD COLUMN kerf_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER direction_sensitive, ADD COLUMN sheet_edge_margin_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER kerf_mm, ADD COLUMN horizontal_spacing_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER sheet_edge_margin_mm, ADD COLUMN vertical_spacing_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER horizontal_spacing_mm, ADD COLUMN print_edge_margin_mm DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER vertical_spacing_mm', 'SELECT 1');
PREPARE sf_dir_stmt FROM @sf_dir_sql;
EXECUTE sf_dir_stmt;
DEALLOCATE PREPARE sf_dir_stmt;

SET @sf_basis = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recipe_items' AND COLUMN_NAME = 'quantity_basis');
SET @sf_basis_sql = IF(@sf_basis = 0, 'ALTER TABLE recipe_items ADD COLUMN quantity_basis VARCHAR(20) NOT NULL DEFAULT ''PER_UNIT'' AFTER yield_mode, ADD COLUMN batch_size INT UNSIGNED NULL AFTER quantity_basis', 'SELECT 1');
PREPARE sf_basis_stmt FROM @sf_basis_sql;
EXECUTE sf_basis_stmt;
DEALLOCATE PREPARE sf_basis_stmt;

CREATE TABLE IF NOT EXISTS estimates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_number VARCHAR(40) NOT NULL,
    revision_number INT UNSIGNED NOT NULL DEFAULT 1,
    customer_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    quote_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    recipe_id INT UNSIGNED NULL,
    recipe_version INT UNSIGNED NULL,
    estimate_type VARCHAR(30) NOT NULL DEFAULT 'GENERAL',
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    subtotal_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    material_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    labour_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    machine_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    installation_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    travel_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    subcontract_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    other_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    recommended_sell_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_gross_profit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    expected_margin DECIMAL(8,2) NULL,
    confidence_basis VARCHAR(30) NOT NULL DEFAULT 'NO_HISTORY',
    snapshot_json JSON NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estimates_number (estimate_number),
    KEY idx_estimates_customer (customer_id),
    KEY idx_estimates_quote (quote_id),
    KEY idx_estimates_job (job_id),
    KEY idx_estimates_status (status),
    KEY idx_estimates_recipe (recipe_id),
    CONSTRAINT fk_estimates_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_estimates_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_revisions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    snapshot_json JSON NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estimate_revisions (estimate_id, revision_number),
    CONSTRAINT fk_estimate_revisions_estimate FOREIGN KEY (estimate_id) REFERENCES estimates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_estimate_revisions_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estimate_components (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estimate_id INT UNSIGNED NOT NULL,
    component_type VARCHAR(20) NOT NULL,
    product_id INT UNSIGNED NULL,
    recipe_item_id INT UNSIGNED NULL,
    description VARCHAR(180) NOT NULL,
    billable_quantity DECIMAL(14,4) NULL,
    estimated_quantity DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    actual_quantity DECIMAL(14,4) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'unit',
    unit_cost_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    calculation_method VARCHAR(30) NOT NULL DEFAULT 'QUANTITY',
    calculation_details_json JSON NULL,
    manual_override TINYINT(1) NOT NULL DEFAULT 0,
    override_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estimate_components_estimate (estimate_id),
    CONSTRAINT fk_estimate_components_estimate FOREIGN KEY (estimate_id) REFERENCES estimates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_estimate_components_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_recommendations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recommendation_type VARCHAR(40) NOT NULL,
    recipe_id INT UNSIGNED NULL,
    recipe_item_id INT UNSIGNED NULL,
    product_id INT UNSIGNED NULL,
    current_value VARCHAR(40) NOT NULL,
    suggested_value VARCHAR(40) NOT NULL,
    sample_size INT UNSIGNED NOT NULL DEFAULT 0,
    confidence_basis VARCHAR(30) NOT NULL DEFAULT 'LIMITED_HISTORY',
    reason TEXT NOT NULL,
    statistic_json JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'NEW',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pricing_recommendations_status (status),
    KEY idx_pricing_recommendations_recipe (recipe_id),
    CONSTRAINT fk_pricing_recommendations_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pricing_recommendations_user FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_risk_reviews (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    quote_id INT UNSIGNED NULL,
    estimate_id INT UNSIGNED NULL,
    level VARCHAR(20) NOT NULL,
    warnings_json JSON NOT NULL,
    override_reason VARCHAR(255) NULL,
    override_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_quote_risk_quote (quote_id),
    KEY idx_quote_risk_estimate (estimate_id),
    CONSTRAINT fk_quote_risk_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_risk_estimate FOREIGN KEY (estimate_id) REFERENCES estimates (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_quote_risk_user FOREIGN KEY (override_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installation_access_levels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(80) NOT NULL,
    multiplier DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_installation_access_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installation_height_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(80) NOT NULL,
    equipment_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_installation_height_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO installation_access_levels (code, name, multiplier, notes)
SELECT v.code, v.name, v.multiplier, v.notes FROM (
    SELECT 'EASY' AS code, 'Easy' AS name, 1.00 AS multiplier, 'No extra time. Multiplier 1.00.' AS notes
    UNION ALL SELECT 'STANDARD', 'Standard', 1.00, 'Normal access. Multiplier 1.00.'
    UNION ALL SELECT 'DIFFICULT', 'Difficult', 1.25, 'Site labour x 1.25.'
    UNION ALL SELECT 'SPECIALIST', 'Specialist', 1.50, 'Site labour x 1.50.'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM installation_access_levels a WHERE a.code = v.code);

INSERT INTO installation_height_categories (code, name, equipment_cost, notes)
SELECT v.code, v.name, v.equipment_cost, v.notes FROM (
    SELECT 'GROUND' AS code, 'Ground level' AS name, 0.00 AS equipment_cost, 'No equipment cost.' AS notes
    UNION ALL SELECT 'LADDER', 'Ladder', 0.00, 'Use a ladder already on the job.'
    UNION ALL SELECT 'SCAFFOLD', 'Scaffolding', 450.00, 'Default equipment cost. Edit it.'
    UNION ALL SELECT 'CHERRY_PICKER', 'Cherry picker', 1800.00, 'Default equipment cost. Edit it.'
    UNION ALL SELECT 'OTHER', 'Other', 0.00, 'Enter the equipment cost on the estimate.'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM installation_height_categories h WHERE h.code = v.code);

INSERT INTO permissions (code, name, module)
SELECT v.code, v.name, 'estimating' FROM (
    SELECT 'estimates.view' AS code, 'View internal estimates' AS name
    UNION ALL SELECT 'estimates.create', 'Create internal estimates'
    UNION ALL SELECT 'estimates.edit', 'Edit internal estimates'
    UNION ALL SELECT 'estimates.approve', 'Approve an estimate before it feeds a quote'
    UNION ALL SELECT 'estimates.view_cost', 'View estimate cost'
    UNION ALL SELECT 'yield.view', 'View sheet and roll yield'
    UNION ALL SELECT 'yield.override', 'Override a calculated yield'
    UNION ALL SELECT 'pricing_intelligence.view', 'View pricing intelligence'
    UNION ALL SELECT 'pricing_recommendations.review', 'Review a pricing recommendation'
    UNION ALL SELECT 'pricing_recommendations.apply', 'Apply a pricing recommendation to a new recipe version'
    UNION ALL SELECT 'quote_risk.override', 'Issue a quote below the margin rule with a reason'
    UNION ALL SELECT 'historical_costing.view', 'View historical estimate and actual cost'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.code = v.code);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN' AND p.code IN (
    'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.approve', 'estimates.view_cost',
    'yield.view', 'yield.override', 'pricing_intelligence.view', 'pricing_recommendations.review',
    'pricing_recommendations.apply', 'quote_risk.override', 'historical_costing.view'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.code IN (
    'estimates.view', 'estimates.view_cost', 'yield.view', 'pricing_intelligence.view',
    'pricing_recommendations.review', 'historical_costing.view', 'quote_risk.override'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
    'estimates.view', 'estimates.create', 'estimates.edit', 'estimates.view_cost',
    'yield.view', 'yield.override', 'pricing_intelligence.view'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN ('estimates.view', 'estimates.view_cost', 'historical_costing.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO settings (setting_key, setting_value)
SELECT v.setting_key, v.setting_value FROM (
    SELECT 'estimate_prefix' AS setting_key, 'SFE' AS setting_value
    UNION ALL SELECT 'minimum_sample_size', '5'
    UNION ALL SELECT 'cost_age_warning_days', '90'
    UNION ALL SELECT 'price_volatility_percent', '5'
    UNION ALL SELECT 'price_volatility_days', '90'
    UNION ALL SELECT 'target_margin_percent', '35'
    UNION ALL SELECT 'quote_risk_block', '0'
    UNION ALL SELECT 'estimate_approval_margin_percent', '25'
    UNION ALL SELECT 'estimate_approval_cost', '50000'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = v.setting_key);

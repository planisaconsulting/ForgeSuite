-- v1.1 Phase 9 sales intake. AI may propose structure. Pricing stays on the estimate and quote services.

CREATE TABLE sales_intakes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_number VARCHAR(40) NOT NULL,
    source_type VARCHAR(30) NOT NULL,
    source_reference_id INT UNSIGNED NULL,
    communication_id INT UNSIGNED NULL,
    external_message_id VARCHAR(180) NULL,
    customer_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    lead_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    project_site_id INT UNSIGNED NULL,
    asset_id INT UNSIGNED NULL,
    service_request_id INT UNSIGNED NULL,
    estimate_id INT UNSIGNED NULL,
    quote_id INT UNSIGNED NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'NEW',
    intent_type VARCHAR(40) NOT NULL DEFAULT 'UNKNOWN',
    language_code VARCHAR(8) NULL,
    assigned_user_id INT UNSIGNED NULL,
    sender_name VARCHAR(180) NULL,
    sender_email VARCHAR(190) NULL,
    sender_phone VARCHAR(40) NULL,
    subject VARCHAR(180) NULL,
    original_message MEDIUMTEXT NOT NULL,
    requested_date DATE NULL,
    requested_date_text VARCHAR(160) NULL,
    date_promised TINYINT(1) NOT NULL DEFAULT 0,
    installation_proposed VARCHAR(20) NULL,
    customer_budget VARCHAR(40) NULL,
    discount_requested_percent DECIMAL(6,2) NULL,
    discount_applied TINYINT(1) NOT NULL DEFAULT 0,
    match_state VARCHAR(20) NULL,
    duplicate_of_id INT UNSIGNED NULL,
    estimator_kind VARCHAR(40) NULL,
    estimator_payload JSON NULL,
    analysis_hash CHAR(64) NULL,
    analysis_count INT UNSIGNED NOT NULL DEFAULT 0,
    requirements_confirmed_at DATETIME NULL,
    next_action VARCHAR(180) NULL,
    received_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    first_reviewed_at DATETIME NULL,
    first_response_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_intakes_number (intake_number),
    KEY idx_sales_intakes_status (status, received_at),
    KEY idx_sales_intakes_customer (customer_id),
    KEY idx_sales_intakes_contact (contact_id),
    KEY idx_sales_intakes_assigned (assigned_user_id, status),
    KEY idx_sales_intakes_source (source_type, received_at),
    KEY idx_sales_intakes_lead (lead_id),
    KEY idx_sales_intakes_opportunity (opportunity_id),
    KEY idx_sales_intakes_message (external_message_id),
    KEY idx_sales_intakes_hash (analysis_hash),
    CONSTRAINT fk_sales_intakes_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sales_intakes_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sales_intakes_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sales_intakes_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sales_intakes_communication FOREIGN KEY (communication_id) REFERENCES communications (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sales_intakes_assigned FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_sales_intakes_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_analyses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_id INT UNSIGNED NOT NULL,
    provider VARCHAR(40) NOT NULL,
    model_name VARCHAR(80) NULL,
    prompt_version VARCHAR(20) NOT NULL DEFAULT '1',
    input_hash CHAR(64) NOT NULL,
    input_reference VARCHAR(80) NOT NULL,
    structured_json JSON NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_intake_analyses_hash (intake_id, input_hash),
    KEY idx_intake_analyses_status (status, created_at),
    CONSTRAINT fk_intake_analyses_intake FOREIGN KEY (intake_id) REFERENCES sales_intakes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_intake_analyses_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_id INT UNSIGNED NOT NULL,
    line_no INT UNSIGNED NOT NULL,
    requirement_type VARCHAR(30) NOT NULL DEFAULT 'SIGNAGE',
    description VARCHAR(255) NULL,
    quantity DECIMAL(14,4) NULL,
    width_mm DECIMAL(10,2) NULL,
    height_mm DECIMAL(10,2) NULL,
    depth_mm DECIMAL(10,2) NULL,
    thickness_mm DECIMAL(10,2) NULL,
    material_text VARCHAR(120) NULL,
    material_category VARCHAR(120) NULL,
    finish VARCHAR(80) NULL,
    print_sides TINYINT UNSIGNED NULL,
    installation_proposed VARCHAR(20) NULL,
    is_approximate TINYINT(1) NOT NULL DEFAULT 0,
    dimension_source VARCHAR(40) NOT NULL DEFAULT 'AI_EXTRACTED_PENDING_REVIEW',
    original_text VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    review_status VARCHAR(20) NOT NULL DEFAULT 'PROPOSED',
    PRIMARY KEY (id),
    UNIQUE KEY uq_intake_items_line (intake_id, line_no),
    KEY idx_intake_items_intake (intake_id),
    CONSTRAINT fk_intake_items_intake FOREIGN KEY (intake_id) REFERENCES sales_intakes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_fields (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    proposed_value VARCHAR(255) NULL,
    confirmed_value VARCHAR(255) NULL,
    conflict_value VARCHAR(255) NULL,
    original_text VARCHAR(255) NULL,
    extraction_method VARCHAR(30) NOT NULL,
    evidence VARCHAR(40) NOT NULL,
    review_status VARCHAR(20) NOT NULL DEFAULT 'PROPOSED',
    is_approximate TINYINT(1) NOT NULL DEFAULT 0,
    dimension_source VARCHAR(40) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_intake_fields_key (intake_id, field_key),
    KEY idx_intake_fields_review (intake_id, review_status),
    CONSTRAINT fk_intake_fields_intake FOREIGN KEY (intake_id) REFERENCES sales_intakes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(80) NOT NULL,
    question_text VARCHAR(500) NOT NULL,
    draft_text VARCHAR(500) NULL,
    language_code VARCHAR(8) NOT NULL DEFAULT 'EN',
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    answer_text VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intake_questions_intake (intake_id, status),
    CONSTRAINT fk_intake_questions_intake FOREIGN KEY (intake_id) REFERENCES sales_intakes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_product_matches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_id INT UNSIGNED NOT NULL,
    line_no INT UNSIGNED NOT NULL DEFAULT 1,
    product_id INT UNSIGNED NULL,
    catalogue_item_id INT UNSIGNED NULL,
    specification_id INT UNSIGNED NULL,
    recipe_id INT UNSIGNED NULL,
    match_state VARCHAR(30) NOT NULL,
    match_rank INT UNSIGNED NOT NULL DEFAULT 1,
    why_text VARCHAR(255) NOT NULL,
    missing_json JSON NULL,
    warnings_json JSON NULL,
    review_status VARCHAR(20) NOT NULL DEFAULT 'PROPOSED',
    PRIMARY KEY (id),
    KEY idx_intake_matches_intake (intake_id, review_status),
    KEY idx_intake_matches_product (product_id),
    CONSTRAINT fk_intake_matches_intake FOREIGN KEY (intake_id) REFERENCES sales_intakes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(180) NOT NULL,
    sha256 CHAR(64) NOT NULL,
    mime_type VARCHAR(80) NULL,
    rejected TINYINT(1) NOT NULL DEFAULT 0,
    reject_reason VARCHAR(180) NULL,
    extracted_text MEDIUMTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intake_files_intake (intake_id),
    KEY idx_intake_files_hash (sha256),
    CONSTRAINT fk_intake_files_intake FOREIGN KEY (intake_id) REFERENCES sales_intakes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_import_rows (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_id INT UNSIGNED NOT NULL,
    row_no INT UNSIGNED NOT NULL,
    site_code VARCHAR(40) NULL,
    branch_name VARCHAR(180) NULL,
    address_text VARCHAR(255) NULL,
    sign_type VARCHAR(120) NULL,
    quantity DECIMAL(14,4) NULL,
    width_mm DECIMAL(10,2) NULL,
    height_mm DECIMAL(10,2) NULL,
    issues VARCHAR(255) NULL,
    PRIMARY KEY (id),
    KEY idx_intake_import_intake (intake_id, row_no),
    CONSTRAINT fk_intake_import_intake FOREIGN KEY (intake_id) REFERENCES sales_intakes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_term_mappings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_term VARCHAR(80) NOT NULL,
    erp_category VARCHAR(120) NOT NULL,
    product_id INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_intake_terms_term (customer_term),
    CONSTRAINT fk_intake_terms_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE intake_requirement_rules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    requirement_type VARCHAR(30) NOT NULL,
    material_category VARCHAR(120) NULL,
    field_name VARCHAR(80) NOT NULL,
    prompt_en VARCHAR(255) NOT NULL,
    prompt_af VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_intake_rules_type (requirement_type, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_review_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intake_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_product_review_intake (intake_id, status),
    CONSTRAINT fk_product_review_intake FOREIGN KEY (intake_id) REFERENCES sales_intakes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO intake_term_mappings (customer_term, erp_category, product_id) VALUES
('perspex', 'Acrylic', NULL),
('alubond', 'ACM', NULL),
('chromadek', 'Chromadek signage', NULL),
('acm', 'ACM', NULL),
('one-way vision', 'Perforated window vinyl', NULL),
('sticker', 'Vinyl', NULL),
('borde', 'Board', NULL);

INSERT IGNORE INTO intake_requirement_rules (requirement_type, material_category, field_name, prompt_en, prompt_af) VALUES
('SIGNAGE', 'ACM', 'thickness_mm', 'Please confirm the ACM thickness.', 'Bevestig asseblief die ACM-dikte.'),
('VEHICLE', NULL, 'make', 'Please confirm the vehicle make.', 'Bevestig asseblief die voertuigmerk.'),
('VEHICLE', NULL, 'model', 'Please confirm the vehicle model.', 'Bevestig asseblief die model.'),
('VEHICLE', NULL, 'year', 'Please confirm the vehicle year.', 'Bevestig asseblief die jaar.'),
('VEHICLE', NULL, 'body', 'Please confirm the body style.', 'Bevestig asseblief die bakstyl.'),
('VEHICLE', NULL, 'coverage', 'Please confirm full or partial coverage.', 'Bevestig asseblief volle of gedeeltelike dekking.'),
('VEHICLE', NULL, 'installation', 'Please confirm whether Sign-Forge must install the branding.', 'Bevestig asseblief of Sign-Forge moet installeer.'),
('ILLUMINATED', NULL, 'font', 'Please confirm the font or supply vector artwork.', 'Bevestig asseblief die lettertipe of stuur vektor-kuns.'),
('ILLUMINATED', NULL, 'depth', 'Please confirm the letter depth.', 'Bevestig asseblief die letterdiepte.'),
('ILLUMINATED', NULL, 'face_colour', 'Please confirm the face colour.', 'Bevestig asseblief die voorkleur.'),
('ILLUMINATED', NULL, 'mounting', 'Please confirm the mounting method.', 'Bevestig asseblief die monteermetode.'),
('ILLUMINATED', NULL, 'site', 'Please confirm the site address.', 'Bevestig asseblief die terreinadres.'),
('ILLUMINATED', NULL, 'power', 'Please confirm power and access.', 'Bevestig asseblief krag en toegang.');

INSERT IGNORE INTO permissions (code, name, module) VALUES
('sales_intake.view', 'View sales intake', 'sales_intake'),
('sales_intake.create', 'Capture a sales intake', 'sales_intake'),
('sales_intake.assign', 'Assign sales intake', 'sales_intake'),
('sales_intake.review', 'Review sales intake', 'sales_intake'),
('sales_intake.confirm_customer', 'Confirm the intake customer', 'sales_intake'),
('sales_intake.confirm_requirements', 'Confirm intake requirements', 'sales_intake'),
('sales_intake.match_product', 'Match an intake product', 'sales_intake'),
('sales_intake.create_estimate', 'Create an estimate from intake', 'sales_intake'),
('sales_intake.create_quote', 'Create a draft quote from intake', 'sales_intake'),
('sales_intake.ai_analyse', 'Run intake analysis', 'sales_intake'),
('sales_intake.view_ai_audit', 'View intake provider cost', 'sales_intake'),
('sales_intake.admin', 'Administer sales intake', 'sales_intake');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.module = 'sales_intake';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
    'sales_intake.view', 'sales_intake.create', 'sales_intake.review',
    'sales_intake.confirm_customer', 'sales_intake.confirm_requirements',
    'sales_intake.match_product', 'sales_intake.create_estimate',
    'sales_intake.create_quote', 'sales_intake.ai_analyse'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code = 'sales_intake.view';

INSERT INTO settings (setting_key, setting_value) VALUES
('intake_prefix', 'SFIN'),
('intake_max_analyses', '3'),
('intake_max_input_chars', '8000'),
('intake_max_attachment_mb', '8'),
('intake_analysis_retention_days', '365'),
('intake_prompt_version', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT IGNORE INTO feature_flags (feature_key, enabled) VALUES
('AI_INTAKE_ANALYSIS', 0),
('AI_DOCUMENT_EXTRACTION', 0),
('AI_PRODUCT_MATCH_ASSIST', 0),
('AI_MESSAGE_DRAFTING', 0),
('AI_QUOTE_DESCRIPTION', 0);

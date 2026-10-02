-- v1.1 Phase 7: artwork revisions, proofs, annotations, and production files.
-- Extends job_artworks, artwork_approvals, and production_files.
-- A proof is not a production file. Approval belongs to one revision.

ALTER TABLE job_artworks
    ADD COLUMN artwork_number VARCHAR(40) NULL AFTER id,
    ADD COLUMN project_id INT UNSIGNED NULL AFTER job_item_id,
    ADD COLUMN site_id INT UNSIGNED NULL AFTER project_id,
    ADD COLUMN quote_id INT UNSIGNED NULL AFTER site_id,
    ADD COLUMN catalogue_item_id INT UNSIGNED NULL AFTER quote_id,
    ADD COLUMN asset_id INT UNSIGNED NULL AFTER catalogue_item_id,
    ADD COLUMN customer_order_id INT UNSIGNED NULL AFTER asset_id,
    ADD COLUMN master_artwork_id INT UNSIGNED NULL AFTER customer_order_id,
    ADD COLUMN designer_id INT UNSIGNED NULL AFTER uploaded_by,
    ADD COLUMN reviewer_id INT UNSIGNED NULL AFTER designer_id,
    ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL' AFTER status,
    ADD COLUMN design_due DATE NULL AFTER priority,
    ADD COLUMN customer_due DATE NULL AFTER design_due,
    ADD COLUMN production_required_date DATE NULL AFTER customer_due,
    ADD COLUMN finished_width_mm DECIMAL(14,2) NULL AFTER notes,
    ADD COLUMN finished_height_mm DECIMAL(14,2) NULL AFTER finished_width_mm,
    ADD COLUMN scale_label VARCHAR(20) NULL AFTER finished_height_mm,
    ADD COLUMN bleed_top_mm DECIMAL(10,2) NULL AFTER scale_label,
    ADD COLUMN bleed_right_mm DECIMAL(10,2) NULL AFTER bleed_top_mm,
    ADD COLUMN bleed_bottom_mm DECIMAL(10,2) NULL AFTER bleed_right_mm,
    ADD COLUMN bleed_left_mm DECIMAL(10,2) NULL AFTER bleed_bottom_mm,
    ADD COLUMN safe_area_mm DECIMAL(10,2) NULL AFTER bleed_left_mm,
    ADD COLUMN cut_contour VARCHAR(30) NOT NULL DEFAULT 'NOT_REQUIRED' AFTER safe_area_mm,
    ADD COLUMN tiling_json JSON NULL AFTER cut_contour,
    ADD COLUMN variables_json JSON NULL AFTER tiling_json,
    ADD COLUMN reuse_class VARCHAR(30) NOT NULL DEFAULT 'ONE_TIME' AFTER variables_json,
    ADD COLUMN library_category VARCHAR(40) NULL AFTER reuse_class,
    ADD COLUMN vehicle_template_id INT UNSIGNED NULL AFTER library_category,
    ADD COLUMN sign_kind VARCHAR(40) NULL AFTER vehicle_template_id,
    ADD COLUMN production_file_required TINYINT(1) NOT NULL DEFAULT 0 AFTER sign_kind,
    ADD COLUMN physical_sample_status VARCHAR(30) NOT NULL DEFAULT 'NOT_REQUIRED' AFTER production_file_required,
    ADD COLUMN colour_ack_required TINYINT(1) NOT NULL DEFAULT 0 AFTER physical_sample_status,
    ADD COLUMN current_revision_id INT UNSIGNED NULL AFTER colour_ack_required,
    ADD COLUMN checkout_user_id INT UNSIGNED NULL AFTER current_revision_id,
    ADD COLUMN checkout_at TIMESTAMP NULL DEFAULT NULL AFTER checkout_user_id,
    ADD COLUMN first_proof_sent_at TIMESTAMP NULL DEFAULT NULL AFTER checkout_at,
    ADD COLUMN last_customer_reminder_at TIMESTAMP NULL DEFAULT NULL AFTER first_proof_sent_at,
    ADD COLUMN block_reason VARCHAR(40) NULL AFTER last_customer_reminder_at,
    ADD UNIQUE KEY uq_job_artworks_number (artwork_number),
    ADD KEY idx_job_artworks_project (project_id, status),
    ADD KEY idx_job_artworks_site (site_id),
    ADD KEY idx_job_artworks_designer (designer_id, status),
    ADD KEY idx_job_artworks_status_created (status, created_at);

ALTER TABLE job_artworks
    ADD CONSTRAINT fk_job_artworks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_site FOREIGN KEY (site_id) REFERENCES project_sites (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_catalogue FOREIGN KEY (catalogue_item_id) REFERENCES customer_catalogue_items (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_asset FOREIGN KEY (asset_id) REFERENCES customer_assets (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_order FOREIGN KEY (customer_order_id) REFERENCES customer_orders (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_master FOREIGN KEY (master_artwork_id) REFERENCES job_artworks (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_designer FOREIGN KEY (designer_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_job_artworks_checkout FOREIGN KEY (checkout_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE artwork_approvals
    ADD COLUMN revision_id INT UNSIGNED NULL AFTER job_artwork_id,
    ADD COLUMN proof_id INT UNSIGNED NULL AFTER revision_id,
    ADD COLUMN statement_version VARCHAR(20) NULL AFTER notes,
    ADD COLUMN ip_address VARCHAR(64) NULL AFTER statement_version,
    ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address,
    ADD COLUMN portal_user_id INT UNSIGNED NULL AFTER user_agent,
    ADD COLUMN share_token_id INT UNSIGNED NULL AFTER portal_user_id,
    ADD KEY idx_artwork_approvals_revision (revision_id),
    ADD KEY idx_artwork_approvals_proof (proof_id);

ALTER TABLE production_files
    ADD COLUMN artwork_id INT UNSIGNED NULL AFTER job_item_id,
    ADD COLUMN artwork_revision_id INT UNSIGNED NULL AFTER artwork_id,
    ADD COLUMN proof_id INT UNSIGNED NULL AFTER artwork_revision_id,
    ADD COLUMN pf_number INT UNSIGNED NULL AFTER version_label,
    ADD COLUMN sha256 CHAR(64) NULL AFTER original_name,
    ADD COLUMN stored_filename VARCHAR(255) NULL AFTER sha256,
    ADD COLUMN mime_type VARCHAR(120) NULL AFTER stored_filename,
    ADD COLUMN file_size INT UNSIGNED NULL AFTER mime_type,
    ADD COLUMN visibility VARCHAR(20) NOT NULL DEFAULT 'PRODUCTION' AFTER file_size,
    ADD COLUMN change_class VARCHAR(30) NULL AFTER visibility,
    ADD COLUMN scale_label VARCHAR(20) NULL AFTER change_class,
    ADD COLUMN superseded_by INT UNSIGNED NULL AFTER scale_label,
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER superseded_by,
    ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL AFTER approved_by,
    ADD KEY idx_production_files_artwork (artwork_id, status),
    ADD KEY idx_production_files_hash (sha256),
    ADD KEY idx_production_files_revision (artwork_revision_id);

ALTER TABLE customer_artwork_library
    ADD COLUMN category VARCHAR(40) NOT NULL DEFAULT 'OTHER' AFTER title,
    ADD COLUMN reuse_class VARCHAR(30) NOT NULL DEFAULT 'ONE_TIME' AFTER status,
    ADD COLUMN revision_id INT UNSIGNED NULL AFTER artwork_id,
    ADD COLUMN brand_asset_id INT UNSIGNED NULL AFTER revision_id;

CREATE TABLE artwork_revisions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artwork_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    revision_label VARCHAR(12) NOT NULL,
    source_revision_id INT UNSIGNED NULL,
    reason_code VARCHAR(40) NOT NULL DEFAULT 'INITIAL',
    change_class VARCHAR(30) NOT NULL DEFAULT 'CUSTOMER_VISIBLE',
    change_summary VARCHAR(500) NOT NULL,
    internal_notes TEXT NULL,
    customer_notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    internal_review_status VARCHAR(20) NOT NULL DEFAULT 'NOT_REQUIRED',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_artwork_revision_number (artwork_id, revision_number),
    KEY idx_artwork_revisions_status (artwork_id, status),
    KEY idx_artwork_revisions_created (created_at),
    CONSTRAINT fk_artwork_revisions_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_revisions_source FOREIGN KEY (source_revision_id) REFERENCES artwork_revisions (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_revisions_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_proofs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artwork_id INT UNSIGNED NOT NULL,
    revision_id INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    extension VARCHAR(12) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    page_count INT UNSIGNED NULL,
    width_px INT UNSIGNED NULL,
    height_px INT UNSIGNED NULL,
    colour_mode VARCHAR(20) NOT NULL DEFAULT 'UNKNOWN',
    inspection_note VARCHAR(180) NOT NULL DEFAULT 'Not automatically verified.',
    visibility VARCHAR(20) NOT NULL DEFAULT 'CUSTOMER',
    watermark_label VARCHAR(40) NOT NULL DEFAULT 'PROOF',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_artwork_proofs_revision (revision_id),
    KEY idx_artwork_proofs_artwork (artwork_id),
    KEY idx_artwork_proofs_hash (sha256),
    CONSTRAINT fk_artwork_proofs_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_proofs_rev FOREIGN KEY (revision_id) REFERENCES artwork_revisions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artwork_id INT UNSIGNED NULL,
    revision_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    site_id INT UNSIGNED NULL,
    asset_id INT UNSIGNED NULL,
    category VARCHAR(40) NOT NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'INTERNAL',
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    extension VARCHAR(12) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_artwork_files_artwork (artwork_id, category),
    KEY idx_artwork_files_revision (revision_id),
    KEY idx_artwork_files_job (job_id),
    KEY idx_artwork_files_customer (customer_id),
    KEY idx_artwork_files_project (project_id),
    KEY idx_artwork_files_site (site_id),
    KEY idx_artwork_files_hash (sha256),
    KEY idx_artwork_files_category (category, uploaded_at),
    CONSTRAINT fk_artwork_files_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_files_rev FOREIGN KEY (revision_id) REFERENCES artwork_revisions (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_files_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_files_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_annotations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    proof_id INT UNSIGNED NOT NULL,
    artwork_id INT UNSIGNED NOT NULL,
    page_number INT UNSIGNED NOT NULL DEFAULT 1,
    kind VARCHAR(12) NOT NULL,
    x DECIMAL(8,6) NOT NULL,
    y DECIMAL(8,6) NOT NULL,
    width DECIMAL(8,6) NULL,
    height DECIMAL(8,6) NULL,
    annotation_type VARCHAR(30) NOT NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'CUSTOMER_SHARED',
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    body TEXT NOT NULL,
    author_kind VARCHAR(12) NOT NULL,
    author_user_id INT UNSIGNED NULL,
    author_portal_user_id INT UNSIGNED NULL,
    author_name VARCHAR(120) NULL,
    resolved_revision_id INT UNSIGNED NULL,
    task_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_artwork_annotations_proof (proof_id, page_number, status),
    KEY idx_artwork_annotations_artwork (artwork_id, visibility),
    KEY idx_artwork_annotations_status (status),
    CONSTRAINT fk_artwork_annotations_proof FOREIGN KEY (proof_id) REFERENCES artwork_proofs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_annotations_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_annotations_resolved FOREIGN KEY (resolved_revision_id) REFERENCES artwork_revisions (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_annotation_comments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    annotation_id INT UNSIGNED NOT NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'CUSTOMER_SHARED',
    body TEXT NOT NULL,
    author_kind VARCHAR(12) NOT NULL,
    author_user_id INT UNSIGNED NULL,
    author_portal_user_id INT UNSIGNED NULL,
    author_name VARCHAR(120) NULL,
    mentioned_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_annotation_comments_ann (annotation_id),
    CONSTRAINT fk_annotation_comments_ann FOREIGN KEY (annotation_id) REFERENCES artwork_annotations (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_revision_comments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    revision_id INT UNSIGNED NOT NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'CUSTOMER_SHARED',
    body TEXT NOT NULL,
    author_kind VARCHAR(12) NOT NULL,
    author_user_id INT UNSIGNED NULL,
    author_portal_user_id INT UNSIGNED NULL,
    author_name VARCHAR(120) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_revision_comments_rev (revision_id, visibility),
    CONSTRAINT fk_revision_comments_rev FOREIGN KEY (revision_id) REFERENCES artwork_revisions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_share_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    artwork_id INT UNSIGNED NOT NULL,
    revision_id INT UNSIGNED NOT NULL,
    proof_id INT UNSIGNED NOT NULL,
    permission VARCHAR(12) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    approver_name VARCHAR(120) NULL,
    approver_email VARCHAR(180) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_artwork_share_hash (token_hash),
    KEY idx_artwork_share_proof (proof_id, expires_at),
    CONSTRAINT fk_artwork_share_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_share_rev FOREIGN KEY (revision_id) REFERENCES artwork_revisions (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_share_proof FOREIGN KEY (proof_id) REFERENCES artwork_proofs (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_preflight_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_file_id INT UNSIGNED NOT NULL,
    check_code VARCHAR(40) NOT NULL,
    result VARCHAR(40) NOT NULL,
    source VARCHAR(20) NOT NULL,
    detail VARCHAR(255) NULL,
    checked_by INT UNSIGNED NULL,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_preflight_file (production_file_id, check_code),
    CONSTRAINT fk_preflight_file FOREIGN KEY (production_file_id) REFERENCES production_files (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_physical_proofs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artwork_id INT UNSIGNED NOT NULL,
    sample_reference VARCHAR(80) NOT NULL,
    sample_kind VARCHAR(40) NOT NULL DEFAULT 'VINYL_SWATCH',
    status VARCHAR(30) NOT NULL DEFAULT 'REQUIRED',
    notes VARCHAR(255) NULL,
    approver_name VARCHAR(120) NULL,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_physical_proofs_art (artwork_id, status),
    CONSTRAINT fk_physical_proofs_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_blocks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artwork_id INT UNSIGNED NOT NULL,
    reason VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    customer_message VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_artwork_blocks_art (artwork_id, status),
    CONSTRAINT fk_artwork_blocks_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_brand_assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'LOGO',
    title VARCHAR(180) NOT NULL,
    version_number INT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'CURRENT',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sha256 CHAR(64) NULL,
    original_filename VARCHAR(255) NULL,
    stored_filename VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    file_size INT UNSIGNED NULL,
    supersedes_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_brand_assets_customer (customer_id, status, is_primary),
    CONSTRAINT fk_brand_assets_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_brand_links (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    artwork_id INT UNSIGNED NOT NULL,
    brand_asset_id INT UNSIGNED NOT NULL,
    linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_artwork_brand (artwork_id, brand_asset_id),
    CONSTRAINT fk_artwork_brand_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_brand_asset FOREIGN KEY (brand_asset_id) REFERENCES customer_brand_assets (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_release_snapshots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    release_id INT UNSIGNED NOT NULL,
    artwork_id INT UNSIGNED NOT NULL,
    revision_id INT UNSIGNED NULL,
    proof_id INT UNSIGNED NULL,
    production_file_id INT UNSIGNED NULL,
    file_hash CHAR(64) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_artwork_release_snap (release_id),
    KEY idx_artwork_release_file (production_file_id),
    CONSTRAINT fk_artwork_release_snap_rel FOREIGN KEY (release_id) REFERENCES production_releases (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_artwork_release_snap_art FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_download_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_file_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    revision_label VARCHAR(20) NULL,
    version_label VARCHAR(20) NULL,
    downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_artwork_downloads_file (production_file_id, downloaded_at),
    CONSTRAINT fk_artwork_downloads_file FOREIGN KEY (production_file_id) REFERENCES production_files (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE artwork_checklist_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    revision_id INT UNSIGNED NOT NULL,
    check_code VARCHAR(40) NOT NULL,
    result VARCHAR(30) NOT NULL DEFAULT 'OPEN',
    checked_by INT UNSIGNED NULL,
    checked_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_artwork_checklist (revision_id, check_code),
    CONSTRAINT fk_artwork_checklist_rev FOREIGN KEY (revision_id) REFERENCES artwork_revisions (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE artwork_approvals
    ADD CONSTRAINT fk_artwork_approvals_revision FOREIGN KEY (revision_id) REFERENCES artwork_revisions (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_artwork_approvals_proof FOREIGN KEY (proof_id) REFERENCES artwork_proofs (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE production_files
    ADD CONSTRAINT fk_production_files_artwork FOREIGN KEY (artwork_id) REFERENCES job_artworks (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_production_files_art_rev FOREIGN KEY (artwork_revision_id) REFERENCES artwork_revisions (id) ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO permissions (code, name, module) VALUES
('artwork.view', 'View artwork workspace', 'operations'),
('artwork.create', 'Create artwork', 'operations'),
('artwork.edit', 'Edit artwork details', 'operations'),
('artwork.assign', 'Assign a designer', 'operations'),
('artwork.revise', 'Create an artwork revision', 'operations'),
('artwork.internal_review', 'Request internal artwork review', 'operations'),
('artwork.send_proof', 'Send an artwork proof', 'operations'),
('artwork.comment_internal', 'Add an internal artwork note', 'operations'),
('artwork.approve_internal', 'Complete internal artwork review', 'operations'),
('artwork.production_file.create', 'Prepare a production file', 'operations'),
('artwork.production_file.approve', 'Approve a production file', 'operations'),
('artwork.production_file.supersede', 'Supersede a production file', 'operations'),
('artwork.physical_proof.manage', 'Manage a physical sample', 'operations'),
('artwork.files.download_source', 'Download a design source file', 'operations'),
('artwork.admin', 'Administer artwork storage', 'operations');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.code IN (
    'artwork.view', 'artwork.create', 'artwork.edit', 'artwork.assign', 'artwork.revise',
    'artwork.internal_review', 'artwork.send_proof', 'artwork.comment_internal', 'artwork.approve_internal',
    'artwork.production_file.create', 'artwork.production_file.approve', 'artwork.production_file.supersede',
    'artwork.physical_proof.manage', 'artwork.files.download_source', 'artwork.admin'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DESIGN' AND p.code IN (
    'artwork.view', 'artwork.create', 'artwork.edit', 'artwork.assign', 'artwork.revise',
    'artwork.internal_review', 'artwork.send_proof', 'artwork.comment_internal', 'artwork.approve_internal',
    'artwork.production_file.create', 'artwork.physical_proof.manage', 'artwork.files.download_source'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code = 'artwork.view';

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('artwork.view', 'artwork.create', 'artwork.send_proof');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code = 'artwork.view';

INSERT INTO settings (setting_key, setting_value) VALUES
('artwork_prefix', 'SFAW'),
('artwork_production_only_reapproval', 'NO'),
('dpi_small_print', '150'),
('dpi_large_format', '75'),
('large_format_min_mm', '1000'),
('artwork_reminder_days', '3'),
('artwork_upload_max_mb', '32'),
('artwork_retention_note', 'Approved artwork and production files are retained. Nothing is deleted automatically.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

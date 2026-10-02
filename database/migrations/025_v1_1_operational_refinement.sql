-- v1.1 Phase 10: operational expenses, mileage, trips, and calendar feeds.
-- An expense is a cost record. It is not a payment, a wage, or a ledger posting.
-- The calendar reads operational dates. It does not store a second schedule.

CREATE TABLE expense_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    receipt_required TINYINT(1) NOT NULL DEFAULT 1,
    approval_required TINYINT(1) NOT NULL DEFAULT 1,
    approval_threshold DECIMAL(14,2) NULL,
    prevent_self_approval TINYINT(1) NOT NULL DEFAULT 1,
    reimbursable TINYINT(1) NOT NULL DEFAULT 0,
    vat_treatment VARCHAR(20) NOT NULL DEFAULT 'REVIEW',
    costing_behaviour VARCHAR(30) NOT NULL DEFAULT 'JOB_OTHER',
    other_cost_type VARCHAR(40) NOT NULL DEFAULT 'OTHER',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expense_categories_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_trips (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_number VARCHAR(40) NOT NULL,
    trip_date DATE NOT NULL,
    driver_user_id INT UNSIGNED NULL,
    vehicle_label VARCHAR(120) NULL,
    purpose VARCHAR(180) NOT NULL,
    origin_text VARCHAR(180) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    allocation_method VARCHAR(20) NOT NULL DEFAULT 'EQUAL',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_trips_number (trip_number),
    KEY idx_field_trips_date (trip_date, status),
    KEY idx_field_trips_driver (driver_user_id),
    CONSTRAINT fk_field_trips_driver FOREIGN KEY (driver_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_field_trips_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expenses (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_number VARCHAR(40) NOT NULL,
    expense_date DATE NOT NULL,
    submitted_by INT UNSIGNED NOT NULL,
    incurred_by INT UNSIGNED NULL,
    expense_category_id INT UNSIGNED NOT NULL,
    description VARCHAR(180) NOT NULL,
    merchant_name VARCHAR(180) NULL,
    supplier_id INT UNSIGNED NULL,
    amount_ex_vat DECIMAL(14,2) NULL,
    vat_amount DECIMAL(14,2) NULL,
    amount_inc_vat DECIMAL(14,2) NOT NULL,
    currency_code CHAR(3) NOT NULL DEFAULT 'ZAR',
    payment_method VARCHAR(30) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    reimbursement_status VARCHAR(30) NOT NULL DEFAULT 'NOT_APPLICABLE',
    receipt_required TINYINT(1) NOT NULL DEFAULT 1,
    receipt_path VARCHAR(255) NULL,
    receipt_sha256 CHAR(64) NULL,
    receipt_name VARCHAR(180) NULL,
    reference_text VARCHAR(80) NULL,
    notes TEXT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    represented_by_type VARCHAR(40) NULL,
    represented_by_id INT UNSIGNED NULL,
    duplicate_of_id INT UNSIGNED NULL,
    idempotency_key VARCHAR(80) NULL,
    client_local_id VARCHAR(80) NULL,
    trip_id INT UNSIGNED NULL,
    extraction_json JSON NULL,
    confirmed_json JSON NULL,
    submitted_at DATETIME NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejected_by INT UNSIGNED NULL,
    rejection_reason VARCHAR(255) NULL,
    reversed_at DATETIME NULL,
    reversed_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expenses_number (expense_number),
    UNIQUE KEY uq_expenses_idempotency (idempotency_key),
    UNIQUE KEY uq_expenses_local (client_local_id),
    KEY idx_expenses_date (expense_date),
    KEY idx_expenses_status (status, expense_date),
    KEY idx_expenses_submitter (submitted_by, status),
    KEY idx_expenses_category (expense_category_id),
    KEY idx_expenses_reimbursement (reimbursement_status, status),
    KEY idx_expenses_merchant (merchant_name, expense_date),
    KEY idx_expenses_receipt (receipt_sha256),
    KEY idx_expenses_trip (trip_id),
    CONSTRAINT fk_expenses_category FOREIGN KEY (expense_category_id) REFERENCES expense_categories (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_expenses_submitter FOREIGN KEY (submitted_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_expenses_incurred FOREIGN KEY (incurred_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_expenses_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_expenses_trip FOREIGN KEY (trip_id) REFERENCES field_trips (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_expenses_approved FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_allocations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_id INT UNSIGNED NOT NULL,
    line_no INT UNSIGNED NOT NULL,
    target_type VARCHAR(30) NOT NULL,
    target_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    method VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    percent DECIMAL(8,4) NULL,
    amount DECIMAL(14,2) NOT NULL,
    calculation_note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expense_allocations_line (expense_id, line_no),
    KEY idx_expense_allocations_job (job_id),
    KEY idx_expense_allocations_project (project_id),
    KEY idx_expense_allocations_target (target_type, target_id),
    CONSTRAINT fk_expense_allocations_expense FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_expense_allocations_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_expense_allocations_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_cost_postings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_id INT UNSIGNED NOT NULL,
    allocation_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    other_cost_id INT UNSIGNED NULL,
    direct_cost_id INT UNSIGNED NULL,
    amount DECIMAL(14,2) NOT NULL,
    reversed_at DATETIME NULL,
    reversal_other_cost_id INT UNSIGNED NULL,
    reversal_direct_cost_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expense_postings_allocation (allocation_id),
    KEY idx_expense_postings_expense (expense_id),
    KEY idx_expense_postings_job (job_id),
    KEY idx_expense_postings_project (project_id),
    CONSTRAINT fk_expense_postings_expense FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_expense_postings_allocation FOREIGN KEY (allocation_id) REFERENCES expense_allocations (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reimbursement_batches (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_number VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    notes VARCHAR(255) NULL,
    exported_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reimbursement_batches_number (batch_number),
    KEY idx_reimbursement_batches_status (status),
    CONSTRAINT fk_reimbursement_batches_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reimbursement_batch_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id INT UNSIGNED NOT NULL,
    expense_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reimbursement_items_expense (expense_id),
    KEY idx_reimbursement_items_batch (batch_id),
    CONSTRAINT fk_reimbursement_items_batch FOREIGN KEY (batch_id) REFERENCES reimbursement_batches (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reimbursement_items_expense FOREIGN KEY (expense_id) REFERENCES expenses (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mileage_records (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    vehicle_label VARCHAR(120) NULL,
    vehicle_use VARCHAR(20) NOT NULL DEFAULT 'COMPANY',
    trip_date DATE NOT NULL,
    origin_text VARCHAR(180) NULL,
    destination_text VARCHAR(180) NULL,
    start_odometer DECIMAL(12,1) NULL,
    end_odometer DECIMAL(12,1) NULL,
    distance_km DECIMAL(10,2) NOT NULL,
    purpose VARCHAR(180) NOT NULL,
    job_id INT UNSIGNED NULL,
    project_id INT UNSIGNED NULL,
    trip_id INT UNSIGNED NULL,
    rate_per_km DECIMAL(14,4) NOT NULL,
    cost DECIMAL(14,2) NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'MANUAL',
    reimbursable TINYINT(1) NOT NULL DEFAULT 0,
    anomaly TINYINT(1) NOT NULL DEFAULT 0,
    anomaly_note VARCHAR(255) NULL,
    expense_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mileage_user (user_id, trip_date),
    KEY idx_mileage_job (job_id),
    KEY idx_mileage_project (project_id),
    KEY idx_mileage_trip (trip_id),
    CONSTRAINT fk_mileage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mileage_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_mileage_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_mileage_trip FOREIGN KEY (trip_id) REFERENCES field_trips (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_trip_stops (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    label VARCHAR(180) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_field_trip_stops_trip (trip_id, sort_order),
    KEY idx_field_trip_stops_job (job_id),
    CONSTRAINT fk_field_trip_stops_trip FOREIGN KEY (trip_id) REFERENCES field_trips (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_trip_stops_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_trip_allocations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    percent DECIMAL(8,4) NULL,
    amount DECIMAL(14,2) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_trip_allocations (trip_id, job_id),
    CONSTRAINT fk_field_trip_allocations_trip FOREIGN KEY (trip_id) REFERENCES field_trips (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_trip_allocations_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_trip_postings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    trip_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    other_cost_id INT UNSIGNED NULL,
    logistics_cost_id INT UNSIGNED NULL,
    amount DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_trip_postings (trip_id, job_id),
    CONSTRAINT fk_field_trip_postings_trip FOREIGN KEY (trip_id) REFERENCES field_trips (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_trip_postings_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_feeds (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    scope VARCHAR(30) NOT NULL,
    detail_level VARCHAR(20) NOT NULL DEFAULT 'BASIC',
    token_hash CHAR(64) NOT NULL,
    project_id INT UNSIGNED NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calendar_feeds_token (token_hash),
    KEY idx_calendar_feeds_user (user_id, revoked_at),
    CONSTRAINT fk_calendar_feeds_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_favourites (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    label VARCHAR(180) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_favourites (user_id, entity_type, entity_id),
    CONSTRAINT fk_user_favourites_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entity_merges (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(40) NOT NULL,
    source_id INT UNSIGNED NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    preview_json JSON NULL,
    executed_by INT UNSIGNED NULL,
    executed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_entity_merges_source (entity_type, source_id),
    KEY idx_entity_merges_target (entity_type, target_id),
    CONSTRAINT fk_entity_merges_user FOREIGN KEY (executed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_quality_issues (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    issue_key VARCHAR(80) NOT NULL,
    severity VARCHAR(20) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    message VARCHAR(255) NOT NULL,
    href VARCHAR(180) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_quality_key (issue_key),
    KEY idx_data_quality_status (status, severity),
    KEY idx_data_quality_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE customers
    ADD COLUMN merged_into_id INT UNSIGNED NULL AFTER active,
    ADD KEY idx_customers_merged (merged_into_id);

ALTER TABLE customers
    ADD CONSTRAINT fk_customers_merged FOREIGN KEY (merged_into_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE;

INSERT IGNORE INTO expense_categories
    (code, name, receipt_required, approval_required, approval_threshold, prevent_self_approval, reimbursable, vat_treatment, costing_behaviour, other_cost_type)
VALUES
('MATERIALS', 'Materials', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'OTHER'),
('TOOLS', 'Tools', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'OTHER'),
('CONSUMABLES', 'Consumables', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'OTHER'),
('PARKING', 'Parking', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'TRAVEL'),
('TOLLS', 'Tolls', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'TRAVEL'),
('FUEL', 'Fuel', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'TRAVEL'),
('TRAVEL', 'Travel', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'TRAVEL'),
('ACCOMMODATION', 'Accommodation', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'ACCOMMODATION'),
('MEALS', 'Meals', 1, 1, 500.00, 1, 0, 'REVIEW', 'JOB_OTHER', 'ACCOMMODATION'),
('COURIER', 'Courier', 1, 1, 500.00, 1, 0, 'REVIEW', 'JOB_OTHER', 'COURIER'),
('SUBCONTRACT', 'Subcontract', 1, 1, 500.00, 1, 0, 'REVIEW', 'DO_NOT_POST', 'SUBCONTRACTOR'),
('SITE_COST', 'Site cost', 1, 1, 500.00, 1, 1, 'REVIEW', 'JOB_OTHER', 'OTHER'),
('VEHICLE', 'Vehicle', 1, 1, 500.00, 1, 0, 'REVIEW', 'JOB_OTHER', 'TRAVEL'),
('OFFICE', 'Office', 0, 1, 500.00, 1, 0, 'REVIEW', 'JOB_OTHER', 'OTHER'),
('OTHER', 'Other', 1, 1, 500.00, 1, 0, 'REVIEW', 'JOB_OTHER', 'OTHER');

INSERT IGNORE INTO permissions (code, name, module) VALUES
('expenses.view', 'View expenses', 'expenses'),
('expenses.create', 'Capture an expense', 'expenses'),
('expenses.submit', 'Submit an expense', 'expenses'),
('expenses.approve', 'Approve an expense', 'expenses'),
('expenses.reject', 'Reject an expense', 'expenses'),
('expenses.reverse', 'Reverse an expense', 'expenses'),
('expenses.view_all', 'View every expense', 'expenses'),
('expenses.reimbursement.manage', 'Prepare reimbursement batches', 'expenses'),
('expenses.export', 'Export expenses', 'expenses'),
('mileage.create', 'Record mileage', 'mileage'),
('mileage.approve', 'Approve mileage', 'mileage'),
('trips.manage', 'Manage field trips', 'trips'),
('calendar.view_company', 'View the company calendar', 'calendar'),
('calendar.manage_feed', 'Manage calendar feeds', 'calendar'),
('data_quality.view', 'View data quality', 'data_quality'),
('data_quality.manage', 'Record data quality issues', 'data_quality'),
('entity_merge.preview', 'Preview a customer merge', 'entity_merge'),
('entity_merge.execute', 'Merge duplicate customers', 'entity_merge');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code IN ('ADMIN', 'MANAGEMENT') AND p.code IN (
    'expenses.view', 'expenses.create', 'expenses.submit', 'expenses.approve', 'expenses.reject',
    'expenses.reverse', 'expenses.view_all', 'expenses.reimbursement.manage', 'expenses.export',
    'mileage.create', 'mileage.approve', 'trips.manage', 'calendar.view_company', 'calendar.manage_feed',
    'data_quality.view', 'data_quality.manage', 'entity_merge.preview', 'entity_merge.execute'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('expenses.view', 'expenses.create', 'expenses.submit', 'mileage.create');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN (
    'expenses.view', 'expenses.view_all', 'expenses.approve', 'expenses.reject', 'expenses.reverse',
    'expenses.reimbursement.manage', 'expenses.export', 'data_quality.view'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN ('expenses.view', 'expenses.create', 'expenses.submit', 'mileage.create');

INSERT INTO settings (setting_key, setting_value) VALUES
('expense_prefix', 'SFEXP'),
('reimbursement_prefix', 'SFREB'),
('trip_prefix', 'SFTRIP'),
('mileage_rate_per_km', '4.50')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('company_timezone', 'Africa/Johannesburg');

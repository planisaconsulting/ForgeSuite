-- Sign-Forge Management System
-- Phase 8: resource planning, scheduling, capacity, and field operations.
--
-- Apply this once to a database that already has Phases 1 to 7.
-- It schedules existing jobs, tasks, production stages, and installations.
-- It does not create a second job system.

SET NAMES utf8mb4;

SET @sf_job_promise = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jobs' AND COLUMN_NAME = 'customer_promised_date');
SET @sf_job_promise_sql = IF(@sf_job_promise = 0, 'ALTER TABLE jobs ADD COLUMN original_target_date DATE NULL AFTER target_date, ADD COLUMN customer_promised_date DATE NULL AFTER original_target_date', 'SELECT 1');
PREPARE sf_job_promise_stmt FROM @sf_job_promise_sql;
EXECUTE sf_job_promise_stmt;
DEALLOCATE PREPARE sf_job_promise_stmt;

SET @sf_sup_sub = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND COLUMN_NAME = 'is_subcontractor');
SET @sf_sup_sub_sql = IF(@sf_sup_sub = 0, 'ALTER TABLE suppliers ADD COLUMN is_subcontractor TINYINT(1) NOT NULL DEFAULT 0 AFTER active, ADD COLUMN capabilities VARCHAR(255) NULL AFTER is_subcontractor', 'SELECT 1');
PREPARE sf_sup_sub_stmt FROM @sf_sup_sub_sql;
EXECUTE sf_sup_sub_stmt;
DEALLOCATE PREPARE sf_sup_sub_stmt;

SET @sf_team_type = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teams' AND COLUMN_NAME = 'team_type');
SET @sf_team_type_sql = IF(@sf_team_type = 0, 'ALTER TABLE teams ADD COLUMN team_type VARCHAR(30) NOT NULL DEFAULT ''GENERAL'' AFTER name', 'SELECT 1');
PREPARE sf_team_type_stmt FROM @sf_team_type_sql;
EXECUTE sf_team_type_stmt;
DEALLOCATE PREPARE sf_team_type_stmt;

SET @sf_stage_est = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_production_stages' AND COLUMN_NAME = 'estimated_minutes');
SET @sf_stage_est_sql = IF(@sf_stage_est = 0, 'ALTER TABLE job_production_stages ADD COLUMN required_resource_type VARCHAR(30) NULL AFTER assigned_to, ADD COLUMN estimated_minutes INT UNSIGNED NULL AFTER required_resource_type, ADD COLUMN actual_minutes INT UNSIGNED NULL AFTER estimated_minutes', 'SELECT 1');
PREPARE sf_stage_est_stmt FROM @sf_stage_est_sql;
EXECUTE sf_stage_est_stmt;
DEALLOCATE PREPARE sf_stage_est_stmt;

CREATE TABLE IF NOT EXISTS resources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_type VARCHAR(30) NOT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    capacity_type VARCHAR(20) NOT NULL DEFAULT 'MINUTES',
    default_daily_capacity DECIMAL(12,2) NOT NULL DEFAULT 480.00,
    concurrent_capacity INT UNSIGNED NOT NULL DEFAULT 1,
    internal_hourly_cost DECIMAL(14,4) NULL,
    internal_cost_per_km DECIMAL(14,4) NULL,
    linked_user_id INT UNSIGNED NULL,
    linked_team_id INT UNSIGNED NULL,
    linked_supplier_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'AVAILABLE',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_resources_code (code),
    KEY idx_resources_type (resource_type, active),
    KEY idx_resources_user (linked_user_id),
    KEY idx_resources_team (linked_team_id),
    KEY idx_resources_supplier (linked_supplier_id),
    CONSTRAINT fk_resources_user FOREIGN KEY (linked_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_resources_team FOREIGN KEY (linked_team_id) REFERENCES teams (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_resources_supplier FOREIGN KEY (linked_supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    monday_start TIME NULL,
    monday_end TIME NULL,
    tuesday_start TIME NULL,
    tuesday_end TIME NULL,
    wednesday_start TIME NULL,
    wednesday_end TIME NULL,
    thursday_start TIME NULL,
    thursday_end TIME NULL,
    friday_start TIME NULL,
    friday_end TIME NULL,
    saturday_start TIME NULL,
    saturday_end TIME NULL,
    sunday_start TIME NULL,
    sunday_end TIME NULL,
    break_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_work_schedules_default (is_default, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_work_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    work_schedule_id INT UNSIGNED NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resource_schedules_resource (resource_id, effective_from),
    CONSTRAINT fk_resource_schedules_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_resource_schedules_schedule FOREIGN KEY (work_schedule_id) REFERENCES work_schedules (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_unavailability (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    reason_type VARCHAR(30) NOT NULL,
    description VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_unavailability_resource (resource_id),
    KEY idx_unavailability_start (start_datetime),
    KEY idx_unavailability_end (end_datetime),
    CONSTRAINT fk_unavailability_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_unavailability_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_exceptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    exception_date DATE NOT NULL,
    name VARCHAR(180) NOT NULL,
    exception_type VARCHAR(30) NOT NULL DEFAULT 'PUBLIC_HOLIDAY',
    working_day_override TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calendar_exceptions_date (exception_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type VARCHAR(30) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    resource_id INT UNSIGNED NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    estimated_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
    locked TINYINT(1) NOT NULL DEFAULT 0,
    customer_visible TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    override_reason VARCHAR(255) NULL,
    override_by INT UNSIGNED NULL,
    override_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_schedule_resource (resource_id),
    KEY idx_schedule_start (start_datetime),
    KEY idx_schedule_end (end_datetime),
    KEY idx_schedule_job (job_id),
    KEY idx_schedule_status (status),
    KEY idx_schedule_entity (entity_type, entity_id),
    CONSTRAINT fk_schedule_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_override_by FOREIGN KEY (override_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_entry_resources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_entry_id INT UNSIGNED NOT NULL,
    resource_id INT UNSIGNED NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'ASSIGNED',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_entry_resource (schedule_entry_id, resource_id),
    KEY idx_schedule_entry_resources_resource (resource_id),
    CONSTRAINT fk_schedule_entry_resources_entry FOREIGN KEY (schedule_entry_id) REFERENCES schedule_entries (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_entry_resources_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_entry_id INT UNSIGNED NOT NULL,
    old_start DATETIME NULL,
    old_end DATETIME NULL,
    new_start DATETIME NULL,
    new_end DATETIME NULL,
    changed_by INT UNSIGNED NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_schedule_history_entry (schedule_entry_id, created_at),
    CONSTRAINT fk_schedule_history_entry FOREIGN KEY (schedule_entry_id) REFERENCES schedule_entries (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_schedule_history_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_dependencies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    predecessor_task_id INT UNSIGNED NULL,
    successor_task_id INT UNSIGNED NULL,
    predecessor_stage_id INT UNSIGNED NULL,
    successor_stage_id INT UNSIGNED NULL,
    dependency_type VARCHAR(30) NOT NULL DEFAULT 'FINISH_TO_START',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dependencies_pred_task (predecessor_task_id),
    KEY idx_dependencies_succ_task (successor_task_id),
    KEY idx_dependencies_pred_stage (predecessor_stage_id),
    KEY idx_dependencies_succ_stage (successor_stage_id),
    CONSTRAINT fk_dependencies_pred_task FOREIGN KEY (predecessor_task_id) REFERENCES job_tasks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dependencies_succ_task FOREIGN KEY (successor_task_id) REFERENCES job_tasks (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dependencies_pred_stage FOREIGN KEY (predecessor_stage_id) REFERENCES job_production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dependencies_succ_stage FOREIGN KEY (successor_stage_id) REFERENCES job_production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stage_resource_requirements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_production_stage_id INT UNSIGNED NULL,
    production_stage_id INT UNSIGNED NULL,
    resource_type VARCHAR(30) NOT NULL,
    resource_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stage_requirements_job_stage (job_production_stage_id),
    CONSTRAINT fk_stage_requirements_job_stage FOREIGN KEY (job_production_stage_id) REFERENCES job_production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stage_requirements_stage FOREIGN KEY (production_stage_id) REFERENCES production_stages (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_stage_requirements_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS machine_details (
    resource_id INT UNSIGNED NOT NULL,
    manufacturer VARCHAR(120) NULL,
    model VARCHAR(120) NULL,
    serial_number VARCHAR(120) NULL,
    purchase_date DATE NULL,
    service_interval_days INT UNSIGNED NULL,
    service_interval_hours DECIMAL(12,2) NULL,
    last_service_date DATE NULL,
    next_service_date DATE NULL,
    meter_hours DECIMAL(12,2) NULL,
    notes TEXT NULL,
    PRIMARY KEY (resource_id),
    CONSTRAINT fk_machine_details_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_records (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    maintenance_type VARCHAR(30) NOT NULL,
    scheduled_date DATE NULL,
    completed_date DATE NULL,
    downtime_start DATETIME NULL,
    downtime_end DATETIME NULL,
    description VARCHAR(255) NOT NULL,
    supplier_id INT UNSIGNED NULL,
    cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    meter_reading DECIMAL(14,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PLANNED',
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_maintenance_resource (resource_id),
    KEY idx_maintenance_scheduled (scheduled_date),
    CONSTRAINT fk_maintenance_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_maintenance_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_maintenance_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resource_downtime (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resource_id INT UNSIGNED NOT NULL,
    maintenance_record_id INT UNSIGNED NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    reason VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_downtime_resource (resource_id, started_at),
    CONSTRAINT fk_downtime_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_downtime_maintenance FOREIGN KEY (maintenance_record_id) REFERENCES maintenance_records (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_downtime_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_details (
    resource_id INT UNSIGNED NOT NULL,
    registration_number VARCHAR(40) NOT NULL,
    make VARCHAR(80) NULL,
    model VARCHAR(80) NULL,
    year SMALLINT UNSIGNED NULL,
    vin VARCHAR(40) NULL,
    odometer DECIMAL(12,1) NOT NULL DEFAULT 0.0,
    service_interval_km DECIMAL(12,1) NULL,
    service_interval_days INT UNSIGNED NULL,
    last_service_date DATE NULL,
    last_service_odometer DECIMAL(12,1) NULL,
    next_service_date DATE NULL,
    next_service_odometer DECIMAL(12,1) NULL,
    licence_expiry DATE NULL,
    notes TEXT NULL,
    PRIMARY KEY (resource_id),
    UNIQUE KEY uq_vehicle_registration (registration_number),
    CONSTRAINT fk_vehicle_details_resource FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_usage (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vehicle_resource_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    start_odometer DECIMAL(12,1) NOT NULL,
    end_odometer DECIMAL(12,1) NOT NULL,
    distance_km DECIMAL(12,1) NOT NULL,
    rate_per_km_snapshot DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    travel_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    other_cost_id INT UNSIGNED NULL,
    usage_date DATE NOT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vehicle_usage_vehicle (vehicle_resource_id),
    KEY idx_vehicle_usage_date (usage_date),
    KEY idx_vehicle_usage_job (job_id),
    CONSTRAINT fk_vehicle_usage_vehicle FOREIGN KEY (vehicle_resource_id) REFERENCES resources (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vehicle_usage_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_vehicle_usage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vehicle_usage_other FOREIGN KEY (other_cost_id) REFERENCES job_other_costs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recurring_job_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT NULL,
    frequency_type VARCHAR(30) NOT NULL,
    interval_value INT UNSIGNED NOT NULL DEFAULT 1,
    next_run_date DATE NOT NULL,
    generation_mode VARCHAR(20) NOT NULL DEFAULT 'FOLLOW_UP',
    default_recipe_id INT UNSIGNED NULL,
    anchor_job_id INT UNSIGNED NULL,
    assigned_to INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recurring_next (active, next_run_date),
    KEY idx_recurring_customer (customer_id),
    CONSTRAINT fk_recurring_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_recipe FOREIGN KEY (default_recipe_id) REFERENCES recipes (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_job FOREIGN KEY (anchor_job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_user FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_created FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recurring_followups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    job_task_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_followups_template (template_id),
    CONSTRAINT fk_followups_template FOREIGN KEY (template_id) REFERENCES recurring_job_templates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_followups_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_followups_task FOREIGN KEY (job_task_id) REFERENCES job_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recurring_job_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id INT UNSIGNED NOT NULL,
    period_key VARCHAR(40) NOT NULL,
    followup_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_recurring_period (template_id, period_key),
    CONSTRAINT fk_recurring_runs_template FOREIGN KEY (template_id) REFERENCES recurring_job_templates (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_recurring_runs_followup FOREIGN KEY (followup_id) REFERENCES recurring_followups (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subcontract_orders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(40) NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    required_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    actual_cost DECIMAL(14,2) NULL,
    supplier_reference VARCHAR(80) NULL,
    other_cost_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subcontract_number (order_number),
    KEY idx_subcontract_job (job_id),
    KEY idx_subcontract_supplier (supplier_id),
    KEY idx_subcontract_status (status),
    CONSTRAINT fk_subcontract_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_subcontract_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_subcontract_other FOREIGN KEY (other_cost_id) REFERENCES job_other_costs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_subcontract_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS block_reasons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_block_reasons_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_blocks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id INT UNSIGNED NOT NULL,
    task_id INT UNSIGNED NULL,
    stage_id INT UNSIGNED NULL,
    reason_id INT UNSIGNED NOT NULL,
    reason_text VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'OPEN',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cleared_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_work_blocks_job (job_id, status),
    CONSTRAINT fk_work_blocks_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_work_blocks_task FOREIGN KEY (task_id) REFERENCES job_tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_work_blocks_stage FOREIGN KEY (stage_id) REFERENCES job_production_stages (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_work_blocks_reason FOREIGN KEY (reason_id) REFERENCES block_reasons (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_work_blocks_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO work_schedules (
    name, monday_start, monday_end, tuesday_start, tuesday_end, wednesday_start, wednesday_end,
    thursday_start, thursday_end, friday_start, friday_end, break_minutes, is_default, active
)
SELECT 'Company hours', '08:00:00', '17:00:00', '08:00:00', '17:00:00', '08:00:00', '17:00:00',
    '08:00:00', '17:00:00', '08:00:00', '17:00:00', 60, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM work_schedules WHERE is_default = 1);

INSERT INTO resources (resource_type, code, name, description, capacity_type, default_daily_capacity, concurrent_capacity, status, active)
SELECT 'WORK_AREA', v.code, v.name, v.description, 'MINUTES', 480.00, 8, 'AVAILABLE', 1
FROM (
    SELECT 'WA-DESIGN' AS code, 'Design' AS name, 'Artwork and design' AS description
    UNION ALL SELECT 'WA-PRINT', 'Print room', 'Large-format printing'
    UNION ALL SELECT 'WA-LAM', 'Lamination', 'Laminating'
    UNION ALL SELECT 'WA-CNC', 'CNC', 'Routing and cutting'
    UNION ALL SELECT 'WA-FAB', 'Fabrication', 'Metal and general fabrication'
    UNION ALL SELECT 'WA-PAINT', 'Painting', 'Paint and finishing'
    UNION ALL SELECT 'WA-ASSY', 'Assembly', 'Assembly'
    UNION ALL SELECT 'WA-ELEC', 'Electrical', 'Electrical work'
    UNION ALL SELECT 'WA-PACK', 'Packing', 'Packing'
    UNION ALL SELECT 'WA-INST', 'Installation', 'Site installation'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM resources r WHERE r.code = v.code);

INSERT INTO block_reasons (code, name, active)
SELECT v.code, v.name, 1 FROM (
    SELECT 'WAITING_MATERIAL' AS code, 'Waiting for material' AS name
    UNION ALL SELECT 'WAITING_ARTWORK', 'Waiting for artwork'
    UNION ALL SELECT 'MACHINE_BREAKDOWN', 'Machine breakdown'
    UNION ALL SELECT 'CUSTOMER_QUERY', 'Customer query'
    UNION ALL SELECT 'SITE_UNAVAILABLE', 'Site unavailable'
    UNION ALL SELECT 'WEATHER_DELAY', 'Weather delay'
    UNION ALL SELECT 'OTHER', 'Other'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM block_reasons b WHERE b.code = v.code);

INSERT INTO calendar_exceptions (exception_date, name, exception_type, working_day_override, notes)
SELECT v.exception_date, v.name, 'PUBLIC_HOLIDAY', 0, 'Stored holiday. Edit or replace this date. It is not hard-coded in the scheduler.'
FROM (
    SELECT '2026-01-01' AS exception_date, 'New Year''s Day' AS name
    UNION ALL SELECT '2026-03-21', 'Human Rights Day'
    UNION ALL SELECT '2026-04-03', 'Good Friday'
    UNION ALL SELECT '2026-04-06', 'Family Day'
    UNION ALL SELECT '2026-04-27', 'Freedom Day'
    UNION ALL SELECT '2026-05-01', 'Workers'' Day'
    UNION ALL SELECT '2026-06-16', 'Youth Day'
    UNION ALL SELECT '2026-08-10', 'National Women''s Day observed'
    UNION ALL SELECT '2026-09-24', 'Heritage Day'
    UNION ALL SELECT '2026-12-16', 'Day of Reconciliation'
    UNION ALL SELECT '2026-12-25', 'Christmas Day'
    UNION ALL SELECT '2026-12-28', 'Day of Goodwill observed'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM calendar_exceptions c WHERE c.exception_date = v.exception_date);

INSERT INTO permissions (code, name, module)
SELECT v.code, v.name, 'operations' FROM (
    SELECT 'schedule.view' AS code, 'View the production schedule' AS name
    UNION ALL SELECT 'schedule.manage', 'Create and move scheduled work'
    UNION ALL SELECT 'schedule.override_conflict', 'Override a scheduling warning with a reason'
    UNION ALL SELECT 'resources.view', 'View resources'
    UNION ALL SELECT 'resources.manage', 'Manage resources and work areas'
    UNION ALL SELECT 'staff_availability.manage', 'Record leave and other staff unavailability'
    UNION ALL SELECT 'machines.manage', 'Manage machines and equipment'
    UNION ALL SELECT 'maintenance.view', 'View maintenance'
    UNION ALL SELECT 'maintenance.manage', 'Record maintenance and downtime'
    UNION ALL SELECT 'vehicles.view', 'View vehicles'
    UNION ALL SELECT 'vehicles.manage', 'Manage vehicles and mileage'
    UNION ALL SELECT 'recurring_jobs.view', 'View recurring job templates'
    UNION ALL SELECT 'recurring_jobs.manage', 'Manage recurring job templates'
    UNION ALL SELECT 'subcontractors.view', 'View subcontract orders'
    UNION ALL SELECT 'subcontractors.manage', 'Manage subcontract orders'
    UNION ALL SELECT 'capacity.view', 'View capacity and utilisation'
    UNION ALL SELECT 'resource_cost.view', 'View internal resource costs'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM permissions p WHERE p.code = v.code);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'ADMIN'
  AND p.code IN (
    'schedule.view', 'schedule.manage', 'schedule.override_conflict',
    'resources.view', 'resources.manage', 'staff_availability.manage',
    'machines.manage', 'maintenance.view', 'maintenance.manage',
    'vehicles.view', 'vehicles.manage', 'recurring_jobs.view', 'recurring_jobs.manage',
    'subcontractors.view', 'subcontractors.manage', 'capacity.view', 'resource_cost.view'
  )
  AND NOT EXISTS (
    SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.code IN (
    'schedule.view', 'schedule.manage', 'schedule.override_conflict',
    'resources.view', 'capacity.view', 'maintenance.view', 'vehicles.view',
    'recurring_jobs.view', 'subcontractors.view', 'resource_cost.view'
) AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN ('schedule.view', 'resources.view', 'maintenance.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DESIGN' AND p.code IN ('schedule.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN ('schedule.view', 'capacity.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN ('schedule.view', 'vehicles.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN ('subcontractors.view', 'subcontractors.manage', 'resource_cost.view')
  AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id = r.id AND rp.permission_id = p.id);

INSERT INTO settings (setting_key, setting_value)
SELECT v.setting_key, v.setting_value FROM (
    SELECT 'subcontract_prefix' AS setting_key, 'SFSUB' AS setting_value
    UNION ALL SELECT 'schedule_change_notify_minutes', '30'
    UNION ALL SELECT 'machine_cost_in_job', '0'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM settings s WHERE s.setting_key = v.setting_key);

UPDATE jobs
SET original_target_date = target_date
WHERE original_target_date IS NULL AND target_date IS NOT NULL;

UPDATE jobs
SET customer_promised_date = target_date
WHERE customer_promised_date IS NULL AND target_date IS NOT NULL;

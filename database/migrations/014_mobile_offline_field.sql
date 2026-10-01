-- Phase 14 mobile field work, offline drafts, and device sync.
-- Import once on a database that already has Phase 13. Do not re-import schema.sql.
-- Do not modify migrations 001 to 013.

SET NAMES utf8mb4;

CREATE TABLE user_devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    device_uuid CHAR(36) NOT NULL,
    device_name VARCHAR(80) NOT NULL,
    platform VARCHAR(40) NULL,
    app_version VARCHAR(20) NULL,
    sw_version VARCHAR(40) NULL,
    last_seen_at TIMESTAMP NULL DEFAULT NULL,
    last_sync_at TIMESTAMP NULL DEFAULT NULL,
    pending_count INT UNSIGNED NULL,
    trusted TINYINT(1) NOT NULL DEFAULT 0,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_devices_uuid (device_uuid),
    KEY idx_user_devices_user (user_id, revoked_at),
    CONSTRAINT fk_user_devices_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sync_operations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_uuid CHAR(36) NOT NULL,
    local_uuid CHAR(36) NULL,
    user_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NULL,
    entity_type VARCHAR(40) NOT NULL,
    operation_type VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL,
    processed_at TIMESTAMP NULL DEFAULT NULL,
    server_entity_id INT UNSIGNED NULL,
    error_code VARCHAR(40) NULL,
    result_json JSON NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sync_operations_uuid (operation_uuid),
    KEY idx_sync_operations_status (status, user_id),
    KEY idx_sync_operations_entity (entity_type, server_entity_id),
    CONSTRAINT fk_sync_operations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sync_operations_device FOREIGN KEY (device_id) REFERENCES user_devices (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sync_conflicts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    operation_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    conflict_type VARCHAR(30) NOT NULL,
    server_text VARCHAR(2000) NOT NULL,
    client_text VARCHAR(2000) NOT NULL,
    field_label VARCHAR(80) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    resolution VARCHAR(20) NULL,
    resolved_by INT UNSIGNED NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sync_conflicts_operation (operation_uuid),
    KEY idx_sync_conflicts_status (status, created_at),
    CONSTRAINT fk_sync_conflicts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sync_conflicts_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_packs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pack_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NULL,
    pack_type VARCHAR(20) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    artwork_revision INT UNSIGNED NOT NULL DEFAULT 0,
    critical_hash CHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    downloaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_packs_uuid (pack_uuid),
    KEY idx_field_packs_entity (entity_type, entity_id, status),
    KEY idx_field_packs_user (user_id, status),
    CONSTRAINT fk_field_packs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_packs_device FOREIGN KEY (device_id) REFERENCES user_devices (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    category VARCHAR(40) NOT NULL,
    caption VARCHAR(180) NULL,
    original_path VARCHAR(255) NULL,
    display_path VARCHAR(255) NOT NULL,
    thumb_path VARCHAR(255) NOT NULL,
    annotated_path VARCHAR(255) NULL,
    file_size INT UNSIGNED NOT NULL,
    captured_at DATETIME NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_photos_local (local_uuid),
    KEY idx_field_photos_entity (entity_type, entity_id, sort_order),
    CONSTRAINT fk_field_photos_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    note_type VARCHAR(40) NOT NULL DEFAULT 'NOTE',
    body VARCHAR(2000) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_notes_local (local_uuid),
    KEY idx_field_notes_entity (entity_type, entity_id),
    CONSTRAINT fk_field_notes_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_checklist_answers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    installation_id INT UNSIGNED NOT NULL,
    checklist_item_id INT UNSIGNED NOT NULL,
    answer VARCHAR(10) NOT NULL,
    note VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_checks_local (local_uuid),
    KEY idx_field_checks_installation (installation_id),
    CONSTRAINT fk_field_checks_installation FOREIGN KEY (installation_id) REFERENCES job_installations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_checks_item FOREIGN KEY (checklist_item_id) REFERENCES installation_checklist_items (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_checks_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_time_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    started_at_local DATETIME NOT NULL,
    ended_at_local DATETIME NULL,
    timezone_name VARCHAR(64) NOT NULL,
    server_received_at DATETIME NOT NULL,
    clock_flag TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_time_local (local_uuid),
    KEY idx_field_time_entity (entity_type, entity_id),
    CONSTRAINT fk_field_time_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_travel (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NOT NULL,
    installation_id INT UNSIGNED NULL,
    vehicle_resource_id INT UNSIGNED NULL,
    event_type VARCHAR(20) NOT NULL,
    start_odometer DECIMAL(12,1) NULL,
    end_odometer DECIMAL(12,1) NULL,
    manual_km DECIMAL(12,1) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    occurred_at_local DATETIME NOT NULL,
    timezone_name VARCHAR(64) NOT NULL,
    vehicle_usage_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_travel_local (local_uuid),
    KEY idx_field_travel_job (job_id),
    CONSTRAINT fk_field_travel_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_travel_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_travel_vehicle FOREIGN KEY (vehicle_resource_id) REFERENCES resources (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE field_scans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    local_uuid CHAR(36) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    pack_id INT UNSIGNED NULL,
    code VARCHAR(80) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'PENDING_VALIDATION',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_scans_local (local_uuid),
    KEY idx_field_scans_user (user_id, status),
    CONSTRAINT fk_field_scans_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_field_scans_pack FOREIGN KEY (pack_id) REFERENCES field_packs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_subscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NULL,
    endpoint VARCHAR(500) NOT NULL,
    public_key VARCHAR(255) NOT NULL,
    auth_token VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_subscriptions_endpoint (endpoint),
    KEY idx_push_subscriptions_user (user_id, active),
    CONSTRAINT fk_push_subscriptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_push_subscriptions_device FOREIGN KEY (device_id) REFERENCES user_devices (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE push_messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(80) NOT NULL,
    body VARCHAR(180) NOT NULL,
    status VARCHAR(30) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_push_messages_user (user_id, created_at),
    CONSTRAINT fk_push_messages_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mobile_problem_reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    app_version VARCHAR(20) NOT NULL,
    sw_version VARCHAR(40) NULL,
    platform VARCHAR(40) NULL,
    module_name VARCHAR(40) NOT NULL,
    online_flag TINYINT(1) NOT NULL DEFAULT 1,
    error_code VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mobile_reports_user (user_id, created_at),
    CONSTRAINT fk_mobile_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, name, module) VALUES
('mobile.use', 'Use the mobile field shell', 'mobile'),
('offline.use', 'Sync offline field work', 'mobile'),
('field_pack.download', 'Download a field pack', 'mobile'),
('site_survey.mobile', 'Use site survey field mode', 'mobile'),
('installation.mobile', 'Use installer field mode', 'mobile'),
('delivery.mobile', 'Use delivery field mode', 'mobile'),
('workshop.tablet', 'Use workshop tablet mode', 'mobile'),
('device.view_own', 'View own devices', 'mobile'),
('device.manage_own', 'Rename or revoke own devices', 'mobile'),
('device.manage_all', 'Manage every device', 'mobile'),
('sync_conflicts.view', 'View sync conflicts', 'mobile'),
('sync_conflicts.resolve', 'Resolve sync conflicts', 'mobile'),
('push_notifications.use', 'Register for push notifications', 'mobile');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
    'mobile.use', 'offline.use', 'field_pack.download', 'site_survey.mobile',
    'device.view_own', 'device.manage_own', 'push_notifications.use'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'INSTALLER' AND p.code IN (
    'mobile.use', 'offline.use', 'field_pack.download', 'site_survey.mobile', 'installation.mobile',
    'device.view_own', 'device.manage_own', 'sync_conflicts.view', 'push_notifications.use'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DISPATCH' AND p.code IN (
    'mobile.use', 'offline.use', 'field_pack.download', 'delivery.mobile',
    'device.view_own', 'device.manage_own', 'push_notifications.use'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN (
    'mobile.use', 'offline.use', 'workshop.tablet', 'device.view_own', 'device.manage_own', 'push_notifications.use'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.code IN (
    'mobile.use', 'device.view_own', 'device.manage_own', 'device.manage_all',
    'sync_conflicts.view', 'sync_conflicts.resolve', 'push_notifications.use'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ADMIN' AND p.code IN (
    'mobile.use', 'offline.use', 'field_pack.download', 'site_survey.mobile', 'installation.mobile',
    'delivery.mobile', 'workshop.tablet', 'device.view_own', 'device.manage_own', 'device.manage_all',
    'sync_conflicts.view', 'sync_conflicts.resolve', 'push_notifications.use'
);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('offline_enabled', '1'),
('offline_session_hours', '24'),
('field_pack_max_age_hours', '72'),
('image_compression_quality', '70'),
('offline_storage_warning_mb', '300'),
('gps_capture_policy', 'OPTIONAL'),
('photo_required_on_complete', '0'),
('signature_required_on_complete', '1'),
('auto_sync', '1'),
('push_enabled', '0'),
('field_pack_cleanup_days', '14'),
('measurement_sanity_mm', '20000'),
('image_max_upload_bytes', '1500000'),
('image_keep_original', '0'),
('quiet_hours_start', '20:00'),
('quiet_hours_end', '07:00'),
('urgent_push_during_quiet', '0'),
('clock_skew_hours', '12'),
('installation_safety_ack', '1'),
('schema_version', '14');

UPDATE feature_flags SET enabled = 1 WHERE feature_key = 'OFFLINE_FIELD_MODE';

<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Mobile devices, field packs, and sync records.
 * Stock, invoices, and payments are not written here.
 */
final class FieldRepository extends Repository
{
    public function deviceByUuid(string $uuid): ?array
    {
        return $this->one('SELECT * FROM user_devices WHERE device_uuid = ? LIMIT 1', [$uuid]);
    }

    public function insertDevice(array $data): int
    {
        $this->run(
            'INSERT INTO user_devices (user_id, device_uuid, device_name, platform, app_version, sw_version, last_seen_at, trusted)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                $data['user_id'], $data['device_uuid'], $data['device_name'], $data['platform'],
                $data['app_version'], $data['sw_version'], $data['trusted'],
            ]
        );

        return $this->insertId();
    }

    public function touchDevice(int $id, array $data): void
    {
        $this->run(
            'UPDATE user_devices SET last_seen_at = NOW(), app_version = ?, sw_version = ?, pending_count = ?, platform = COALESCE(?, platform) WHERE id = ?',
            [$data['app_version'], $data['sw_version'], $data['pending_count'], $data['platform'], $id]
        );
    }

    public function markSynced(int $id): void
    {
        $this->run('UPDATE user_devices SET last_sync_at = NOW(), last_seen_at = NOW() WHERE id = ?', [$id]);
    }

    public function renameDevice(int $id, string $name): void
    {
        $this->run('UPDATE user_devices SET device_name = ? WHERE id = ? AND revoked_at IS NULL', [$name, $id]);
    }

    public function revokeDevice(int $id): void
    {
        $this->run('UPDATE user_devices SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL', [$id]);
    }

    public function devicesForUser(int $userId): array
    {
        return $this->rows('SELECT * FROM user_devices WHERE user_id = ? ORDER BY last_seen_at DESC, id DESC', [$userId]);
    }

    public function allDevices(): array
    {
        return $this->rows(
            'SELECT d.*, u.name AS user_name, u.email AS user_email
             FROM user_devices d INNER JOIN users u ON u.id = d.user_id
             ORDER BY d.last_seen_at DESC, d.id DESC LIMIT 200'
        );
    }

    public function device(int $id): ?array
    {
        return $this->one('SELECT * FROM user_devices WHERE id = ?', [$id]);
    }

    public function operationByUuid(string $uuid): ?array
    {
        return $this->one('SELECT * FROM sync_operations WHERE operation_uuid = ? LIMIT 1', [$uuid]);
    }

    public function syncedByLocal(string $localUuid): ?array
    {
        return $this->one(
            "SELECT * FROM sync_operations WHERE local_uuid = ? AND status = 'SYNCED' ORDER BY id DESC LIMIT 1",
            [$localUuid]
        );
    }

    public function insertOperation(array $data): int
    {
        $this->run(
            'INSERT INTO sync_operations
                (operation_uuid, local_uuid, user_id, device_id, entity_type, operation_type, status, processed_at, server_entity_id, error_code, result_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)',
            [
                $data['operation_uuid'], $data['local_uuid'], $data['user_id'], $data['device_id'],
                $data['entity_type'], $data['operation_type'], $data['status'], $data['server_entity_id'],
                $data['error_code'], $data['result_json'],
            ]
        );

        return $this->insertId();
    }

    public function updateOperation(int $id, array $data): void
    {
        $this->run(
            'UPDATE sync_operations SET status = ?, processed_at = NOW(), server_entity_id = ?, error_code = ?, result_json = ? WHERE id = ?',
            [$data['status'], $data['server_entity_id'], $data['error_code'], $data['result_json'], $id]
        );
    }

    public function insertConflict(array $data): int
    {
        $this->run(
            'INSERT INTO sync_conflicts
                (operation_uuid, user_id, entity_type, entity_id, conflict_type, server_text, client_text, field_label)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['operation_uuid'], $data['user_id'], $data['entity_type'], $data['entity_id'],
                $data['conflict_type'], $data['server_text'], $data['client_text'], $data['field_label'],
            ]
        );

        return $this->insertId();
    }

    public function conflict(int $id): ?array
    {
        return $this->one('SELECT * FROM sync_conflicts WHERE id = ?', [$id]);
    }

    public function pendingConflicts(): array
    {
        return $this->rows("SELECT * FROM sync_conflicts WHERE status = 'PENDING' ORDER BY created_at ASC LIMIT 100");
    }

    public function resolveConflict(int $id, string $resolution, int $userId): void
    {
        $this->run(
            "UPDATE sync_conflicts SET status = 'RESOLVED', resolution = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ? AND status = 'PENDING'",
            [$resolution, $userId, $id]
        );
    }

    public function insertPack(array $data): int
    {
        $this->run(
            'INSERT INTO field_packs
                (pack_uuid, user_id, device_id, pack_type, entity_type, entity_id, artwork_revision, critical_hash, payload_json, expires_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'ACTIVE\')',
            [
                $data['pack_uuid'], $data['user_id'], $data['device_id'], $data['pack_type'], $data['entity_type'],
                $data['entity_id'], $data['artwork_revision'], $data['critical_hash'], $data['payload_json'], $data['expires_at'],
            ]
        );

        return $this->insertId();
    }

    public function pack(int $id): ?array
    {
        return $this->one('SELECT * FROM field_packs WHERE id = ?', [$id]);
    }

    public function latestPack(int $userId, string $entityType, int $entityId): ?array
    {
        return $this->one(
            'SELECT * FROM field_packs WHERE user_id = ? AND entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
            [$userId, $entityType, $entityId]
        );
    }

    public function packsForUser(int $userId): array
    {
        return $this->rows(
            "SELECT * FROM field_packs WHERE user_id = ? AND status <> 'REMOVED' ORDER BY downloaded_at DESC LIMIT 50",
            [$userId]
        );
    }

    public function setPackStatus(int $id, string $status): void
    {
        $this->run('UPDATE field_packs SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function closeOldPacks(int $days): int
    {
        $this->run(
            "UPDATE field_packs SET status = 'REMOVED'
             WHERE status IN ('ACTIVE', 'STALE') AND downloaded_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );

        return $this->affected();
    }

    public function jobFacts(int $id): ?array
    {
        return $this->one(
            'SELECT j.id, j.job_number, j.title, j.status, j.version_number, j.target_date, j.installation_date,
                    j.site_address, j.site_contact_name, j.site_contact_phone, j.description, j.customer_notes,
                    j.installation_notes, j.customer_id,
                    c.company_name, c.first_name, c.last_name, c.phone, c.mobile, c.email
             FROM jobs j INNER JOIN customers c ON c.id = j.customer_id WHERE j.id = ? AND j.archived = 0',
            [$id]
        );
    }

    public function approvedArtwork(int $jobId): ?array
    {
        return $this->one(
            'SELECT id, title, revision_number, customer_approved_at, status
             FROM job_artworks WHERE job_id = ? AND customer_approved = 1
             ORDER BY revision_number DESC, id DESC LIMIT 1',
            [$jobId]
        );
    }

    public function installation(int $id): ?array
    {
        return $this->one('SELECT * FROM job_installations WHERE id = ?', [$id]);
    }

    public function saveInstallationNotes(int $id, int $jobId, string $notes, int $version): int
    {
        $this->run(
            'UPDATE job_installations SET installation_notes = ?, version_number = version_number + 1
             WHERE id = ? AND job_id = ? AND version_number = ?',
            [$notes, $id, $jobId, $version]
        );

        return $this->affected();
    }

    public function checklist(int $installationId): array
    {
        return $this->rows(
            'SELECT id, label, checked, sort_order FROM installation_checklist_items WHERE installation_id = ? ORDER BY sort_order, id',
            [$installationId]
        );
    }

    public function survey(int $id): ?array
    {
        return $this->one(
            'SELECT s.*, c.company_name, c.first_name, c.last_name, c.phone, c.mobile
             FROM site_surveys s INNER JOIN customers c ON c.id = s.customer_id WHERE s.id = ?',
            [$id]
        );
    }

    public function surveyMeasurements(int $id): array
    {
        return $this->rows(
            'SELECT id, reference, measurement_type, width_mm, height_mm, quantity, notes FROM site_survey_measurements WHERE site_survey_id = ? ORDER BY id',
            [$id]
        );
    }

    public function countMeasurements(int $surveyId): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM site_survey_measurements WHERE site_survey_id = ?', [$surveyId]);

        return (int) ($row['n'] ?? 0);
    }

    public function setSurveyStatus(int $id, string $status): void
    {
        $this->run('UPDATE site_surveys SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function dispatchRow(int $id): ?array
    {
        return $this->one(
            'SELECT d.*, j.job_number, j.title, j.site_address, c.company_name, c.first_name, c.last_name, c.phone, c.mobile
             FROM dispatches d
             INNER JOIN jobs j ON j.id = d.job_id
             INNER JOIN customers c ON c.id = d.customer_id
             WHERE d.id = ?',
            [$id]
        );
    }

    public function dispatchItems(int $id): array
    {
        return $this->rows('SELECT id, description, quantity, status FROM dispatch_items WHERE dispatch_id = ? ORDER BY id', [$id]);
    }

    public function insertPhoto(array $data): int
    {
        $this->run(
            'INSERT INTO field_photos
                (local_uuid, entity_type, entity_id, category, caption, original_path, display_path, thumb_path, annotated_path,
                 file_size, captured_at, latitude, longitude, sort_order, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['local_uuid'], $data['entity_type'], $data['entity_id'], $data['category'], $data['caption'],
                $data['original_path'], $data['display_path'], $data['thumb_path'], $data['annotated_path'],
                $data['file_size'], $data['captured_at'], $data['latitude'], $data['longitude'], $data['sort_order'],
                $data['uploaded_by'],
            ]
        );

        return $this->insertId();
    }

    public function photo(int $id): ?array
    {
        return $this->one('SELECT * FROM field_photos WHERE id = ?', [$id]);
    }

    public function photoByLocal(string $uuid): ?array
    {
        return $this->one('SELECT * FROM field_photos WHERE local_uuid = ?', [$uuid]);
    }

    public function photosFor(string $entityType, int $entityId): array
    {
        return $this->rows(
            'SELECT id, local_uuid, category, caption, sort_order, file_size, captured_at, annotated_path IS NOT NULL AS has_annotation
             FROM field_photos WHERE entity_type = ? AND entity_id = ? ORDER BY sort_order, id',
            [$entityType, $entityId]
        );
    }

    public function countPhotos(string $entityType, int $entityId): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM field_photos WHERE entity_type = ? AND entity_id = ?', [$entityType, $entityId]);

        return (int) ($row['n'] ?? 0);
    }

    public function setPhotoOrder(int $id, string $entityType, int $entityId, int $order): void
    {
        $this->run(
            'UPDATE field_photos SET sort_order = ? WHERE id = ? AND entity_type = ? AND entity_id = ?',
            [$order, $id, $entityType, $entityId]
        );
    }

    public function storageBytes(int $userId): int
    {
        $row = $this->one('SELECT COALESCE(SUM(file_size), 0) AS n FROM field_photos WHERE uploaded_by = ?', [$userId]);

        return (int) ($row['n'] ?? 0);
    }

    public function insertNote(array $data): int
    {
        $this->run(
            'INSERT INTO field_notes (local_uuid, entity_type, entity_id, note_type, body, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['local_uuid'], $data['entity_type'], $data['entity_id'], $data['note_type'], $data['body'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function notesFor(string $entityType, int $entityId): array
    {
        return $this->rows(
            'SELECT id, note_type, body, created_at FROM field_notes WHERE entity_type = ? AND entity_id = ? ORDER BY id',
            [$entityType, $entityId]
        );
    }

    public function countNotes(string $entityType, int $entityId, string $type): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM field_notes WHERE entity_type = ? AND entity_id = ? AND note_type = ?',
            [$entityType, $entityId, $type]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function insertCheck(array $data): int
    {
        $this->run(
            'INSERT INTO field_checklist_answers (local_uuid, installation_id, checklist_item_id, answer, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$data['local_uuid'], $data['installation_id'], $data['checklist_item_id'], $data['answer'], $data['note'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function insertTime(array $data): int
    {
        $this->run(
            'INSERT INTO field_time_entries
                (local_uuid, user_id, entity_type, entity_id, started_at_local, ended_at_local, timezone_name, server_received_at, clock_flag)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                $data['local_uuid'], $data['user_id'], $data['entity_type'], $data['entity_id'],
                $data['started_at_local'], $data['ended_at_local'], $data['timezone_name'], $data['clock_flag'],
            ]
        );

        return $this->insertId();
    }

    public function timeEntry(int $id): ?array
    {
        return $this->one('SELECT * FROM field_time_entries WHERE id = ?', [$id]);
    }

    public function insertTravel(array $data): int
    {
        $this->run(
            'INSERT INTO field_travel
                (local_uuid, user_id, job_id, installation_id, vehicle_resource_id, event_type, start_odometer, end_odometer,
                 manual_km, latitude, longitude, occurred_at_local, timezone_name, vehicle_usage_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['local_uuid'], $data['user_id'], $data['job_id'], $data['installation_id'], $data['vehicle_resource_id'],
                $data['event_type'], $data['start_odometer'], $data['end_odometer'], $data['manual_km'],
                $data['latitude'], $data['longitude'], $data['occurred_at_local'], $data['timezone_name'], $data['vehicle_usage_id'],
            ]
        );

        return $this->insertId();
    }

    public function insertVehicleUsage(array $data): int
    {
        $this->run(
            'INSERT INTO vehicle_usage
                (vehicle_resource_id, job_id, user_id, start_odometer, end_odometer, distance_km, rate_per_km_snapshot, travel_cost, usage_date, notes)
             VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?)',
            [
                $data['vehicle_resource_id'], $data['job_id'], $data['user_id'], $data['start_odometer'],
                $data['end_odometer'], $data['distance_km'], $data['usage_date'], $data['notes'],
            ]
        );

        return $this->insertId();
    }

    public function insertScan(array $data): int
    {
        $this->run(
            'INSERT INTO field_scans (local_uuid, user_id, pack_id, code, status) VALUES (?, ?, ?, ?, \'PENDING_VALIDATION\')',
            [$data['local_uuid'], $data['user_id'], $data['pack_id'], $data['code']]
        );

        return $this->insertId();
    }

    public function insertSubscription(array $data): int
    {
        $this->run(
            'INSERT INTO push_subscriptions (user_id, device_id, endpoint, public_key, auth_token, active)
             VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), device_id = VALUES(device_id), public_key = VALUES(public_key),
                auth_token = VALUES(auth_token), active = 1',
            [$data['user_id'], $data['device_id'], $data['endpoint'], $data['public_key'], $data['auth_token']]
        );

        return $this->insertId();
    }

    public function deactivateSubscriptions(int $deviceId): void
    {
        $this->run('UPDATE push_subscriptions SET active = 0 WHERE device_id = ?', [$deviceId]);
    }

    public function insertPushMessage(array $data): int
    {
        $this->run(
            'INSERT INTO push_messages (user_id, title, body, status) VALUES (?, ?, ?, ?)',
            [$data['user_id'], $data['title'], $data['body'], $data['status']]
        );

        return $this->insertId();
    }

    public function latestPush(int $userId): ?array
    {
        return $this->one('SELECT * FROM push_messages WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
    }

    public function insertReport(array $data): int
    {
        $this->run(
            'INSERT INTO mobile_problem_reports (user_id, app_version, sw_version, platform, module_name, online_flag, error_code)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$data['user_id'], $data['app_version'], $data['sw_version'], $data['platform'], $data['module_name'], $data['online_flag'], $data['error_code']]
        );

        return $this->insertId();
    }

    public function todayInstallations(int $userId, bool $everyone): array
    {
        $sql = 'SELECT i.id, i.scheduled_start_time, i.status, i.site_address, j.id AS job_id, j.job_number, j.title,
                       c.company_name, c.first_name, c.last_name
                FROM job_installations i
                INNER JOIN jobs j ON j.id = i.job_id
                INNER JOIN customers c ON c.id = j.customer_id
                WHERE i.scheduled_date = CURDATE() AND j.archived = 0 AND j.status NOT IN (\'CANCELLED\')';
        $params = [];
        if (!$everyone) {
            $sql .= ' AND (i.assigned_user_id = ? OR j.assigned_to = ?)';
            $params = [$userId, $userId];
        }
        $sql .= ' ORDER BY i.scheduled_start_time, i.id LIMIT 20';

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function laterInstallations(int $userId, bool $everyone, string $when): array
    {
        $sql = 'SELECT i.id, i.scheduled_date, i.scheduled_start_time, i.status, i.site_address, j.job_number,
                       c.company_name, c.first_name, c.last_name
                FROM job_installations i
                INNER JOIN jobs j ON j.id = i.job_id
                INNER JOIN customers c ON c.id = j.customer_id
                WHERE j.archived = 0 AND j.status <> \'CANCELLED\' AND i.status NOT IN (\'COMPLETE\', \'CANCELLED\')';
        if ($when === 'overdue') {
            $sql .= ' AND i.scheduled_date < CURDATE()';
        } elseif ($when === 'return') {
            $sql .= ' AND i.status = \'RETURN_REQUIRED\'';
        } else {
            $sql .= ' AND i.scheduled_date > CURDATE() AND i.scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
        }
        $params = [];
        if (!$everyone) {
            $sql .= ' AND (i.assigned_user_id = ? OR j.assigned_to = ?)';
            $params = [$userId, $userId];
        }
        $sql .= ' ORDER BY i.scheduled_date, i.id LIMIT 20';

        return $this->rows($sql, $params);
    }

    public function surveyQueue(int $userId, bool $everyone): array
    {
        $sql = "SELECT s.id, s.survey_number, s.site_name, s.status, s.survey_date, s.address_line_1, s.city
                FROM site_surveys s WHERE s.status IN ('DRAFT', 'SCHEDULED', 'IN_PROGRESS')";
        $params = [];
        if (!$everyone) {
            $sql .= ' AND (s.surveyed_by = ? OR s.created_by = ?)';
            $params = [$userId, $userId];
        }
        $sql .= ' ORDER BY s.survey_date, s.id LIMIT 20';

        return $this->rows($sql, $params);
    }

    public function deliveryQueue(int $userId, bool $everyone): array
    {
        $sql = "SELECT d.id, d.dispatch_number, d.status, d.scheduled_at, j.job_number, c.company_name, c.first_name, c.last_name
                FROM dispatches d
                INNER JOIN jobs j ON j.id = d.job_id
                INNER JOIN customers c ON c.id = d.customer_id
                WHERE d.status NOT IN ('DELIVERED', 'CANCELLED')";
        $params = [];
        if (!$everyone) {
            $sql .= ' AND d.driver_user_id = ?';
            $params = [$userId];
        }
        $sql .= ' ORDER BY d.scheduled_at, d.id LIMIT 20';

        return $this->rows($sql, $params);
    }

    public function workshopQueue(): array
    {
        return $this->rows(
            "SELECT id, job_number, title, status FROM jobs
             WHERE archived = 0 AND status IN ('IN_PRODUCTION', 'QUALITY_CONTROL', 'ON_HOLD', 'READY_FOR_PRODUCTION', 'READY_FOR_INSTALLATION')
             ORDER BY target_date, id LIMIT 30"
        );
    }

    public function customerCard(int $id): ?array
    {
        return $this->one(
            'SELECT id, company_name, first_name, last_name, phone, mobile, email, physical_address FROM customers WHERE id = ? AND active = 1',
            [$id]
        );
    }

    public function openQuotes(int $customerId): array
    {
        return $this->rows(
            "SELECT id, quote_number, status, total, expiry_date FROM quotes
             WHERE customer_id = ? AND status NOT IN ('DECLINED', 'EXPIRED') ORDER BY id DESC LIMIT 8",
            [$customerId]
        );
    }

    public function activeJobs(int $customerId): array
    {
        return $this->rows(
            "SELECT id, job_number, title, status FROM jobs
             WHERE customer_id = ? AND archived = 0 AND status NOT IN ('COMPLETED', 'CANCELLED') ORDER BY id DESC LIMIT 8",
            [$customerId]
        );
    }

    public function quoteSummary(int $id): ?array
    {
        return $this->one(
            'SELECT q.id, q.quote_number, q.status, q.total, q.expiry_date, q.version_number, c.company_name, c.first_name, c.last_name, c.phone, c.mobile, c.email
             FROM quotes q INNER JOIN customers c ON c.id = q.customer_id WHERE q.id = ?',
            [$id]
        );
    }

    public function leadCard(int $id): ?array
    {
        return $this->one('SELECT id, lead_number, name, company_name, phone, email, status FROM leads WHERE id = ?', [$id]);
    }

    public function failedOperations(int $userId): array
    {
        return $this->rows(
            "SELECT operation_uuid, operation_type, entity_type, error_code, processed_at FROM sync_operations
             WHERE user_id = ? AND status IN ('FAILED', 'CONFLICT', 'REJECTED') ORDER BY id DESC LIMIT 30",
            [$userId]
        );
    }

    public function lastSuccess(int $userId): ?string
    {
        $row = $this->one(
            "SELECT MAX(processed_at) AS at FROM sync_operations WHERE user_id = ? AND status = 'SYNCED'",
            [$userId]
        );

        return $row['at'] ?? null;
    }
}

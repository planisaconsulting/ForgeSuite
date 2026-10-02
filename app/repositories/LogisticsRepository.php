<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Shipments, packages on a shipment, tracking, deliveries, and exceptions.
 * Fulfilment rows and workshop packages stay in their own tables.
 */
final class LogisticsRepository extends Repository
{
    /**
     * @param array<string, mixed> $data
     */
    public function insertCourier(array $data): int
    {
        $this->run(
            'INSERT INTO couriers (name, account_reference, contact_name, contact_email, contact_phone, tracking_url_template, integration_type, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['account_reference'], $data['contact_name'], $data['contact_email'],
                $data['contact_phone'], $data['tracking_url_template'], $data['integration_type'], $data['active'],
            ]
        );

        return $this->insertId();
    }

    public function courier(int $id): ?array
    {
        return $this->one('SELECT * FROM couriers WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function couriers(bool $activeOnly = true): array
    {
        $sql = 'SELECT id, name, account_reference, contact_name, contact_phone, tracking_url_template, integration_type, active FROM couriers';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }

        return $this->rows($sql . ' ORDER BY name');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertShipment(array $data): int
    {
        $this->run(
            'INSERT INTO shipments (
                shipment_number, shipment_type, status, customer_id, job_id, project_id, project_site_id, dispatch_id,
                destination_address, contact_name, contact_phone, required_date, internal_notes, customer_note, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['shipment_number'], $data['shipment_type'], $data['status'], $data['customer_id'],
                $data['job_id'], $data['project_id'], $data['project_site_id'], $data['dispatch_id'],
                $data['destination_address'], $data['contact_name'], $data['contact_phone'], $data['required_date'],
                $data['internal_notes'], $data['customer_note'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function shipment(int $id): ?array
    {
        return $this->one('SELECT * FROM shipments WHERE id = ?', [$id]);
    }

    public function lockShipment(int $id): ?array
    {
        return $this->one('SELECT * FROM shipments WHERE id = ? FOR UPDATE', [$id]);
    }

    public function lockItem(int $id): ?array
    {
        return $this->one('SELECT id, job_id, quantity, good_quantity FROM job_items WHERE id = ? FOR UPDATE', [$id]);
    }

    public function committedQuantity(int $jobItemId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(si.quantity), 0) AS qty
             FROM shipment_items si
             INNER JOIN shipments s ON s.id = si.shipment_id
             WHERE si.job_item_id = ? AND s.status NOT IN ('CANCELLED', 'RETURNED')",
            [$jobItemId]
        );

        return (string) ($row['qty'] ?? '0');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertItem(array $data): int
    {
        $this->run(
            'INSERT INTO shipment_items (shipment_id, job_id, job_item_id, fulfilment_requirement_id, package_id, quantity, description, tracking_code)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['shipment_id'], $data['job_id'], $data['job_item_id'], $data['fulfilment_requirement_id'],
                $data['package_id'], $data['quantity'], $data['description'], $data['tracking_code'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $shipmentId): array
    {
        return $this->rows('SELECT * FROM shipment_items WHERE shipment_id = ? ORDER BY id', [$shipmentId]);
    }

    public function fulfilment(int $id): ?array
    {
        return $this->one('SELECT * FROM fulfilment_requirements WHERE id = ? FOR UPDATE', [$id]);
    }

    public function addDispatched(int $fulfilmentId, string $quantity): void
    {
        $this->run(
            'UPDATE fulfilment_requirements
             SET dispatched_quantity = dispatched_quantity + ?,
                 status = CASE
                    WHEN status = \'FULFILLED\' THEN status
                    WHEN dispatched_quantity >= quantity THEN \'DISPATCHED\'
                    ELSE \'PARTIALLY_DISPATCHED\'
                 END
             WHERE id = ?',
            [$quantity, $fulfilmentId]
        );
    }

    public function addDelivered(int $fulfilmentId, string $quantity): void
    {
        $this->run(
            'UPDATE fulfilment_requirements
             SET delivered_quantity = delivered_quantity + ?,
                 fulfilled_quantity = delivered_quantity,
                 status = CASE WHEN delivered_quantity >= quantity THEN \'FULFILLED\' ELSE \'PARTIALLY_FULFILLED\' END
             WHERE id = ?',
            [$quantity, $fulfilmentId]
        );
    }

    public function addInstalled(int $fulfilmentId, string $quantity): void
    {
        $this->run(
            'UPDATE fulfilment_requirements
             SET installed_quantity = installed_quantity + ?,
                 fulfilled_quantity = GREATEST(fulfilled_quantity, installed_quantity),
                 status = CASE WHEN installed_quantity >= quantity THEN \'FULFILLED\' ELSE status END
             WHERE id = ?',
            [$quantity, $fulfilmentId]
        );
    }

    public function setShipmentStatus(int $id, string $status, array $extra = []): void
    {
        $sets = ['status = ?'];
        $params = [$status];
        foreach ($extra as $column => $value) {
            if (!preg_match('/^[a-z_]+$/', (string) $column)) {
                continue;
            }
            $sets[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $id;
        $this->run('UPDATE shipments SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertEvent(array $data): int
    {
        $this->run(
            'INSERT INTO shipment_tracking_events (shipment_id, status, event_time, location_text, description, source, external_event_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['shipment_id'], $data['status'], $data['event_time'], $data['location_text'],
                $data['description'], $data['source'], $data['external_event_id'],
            ]
        );

        return $this->insertId();
    }

    public function eventByExternal(int $shipmentId, string $externalId): ?array
    {
        return $this->one(
            'SELECT * FROM shipment_tracking_events WHERE shipment_id = ? AND external_event_id = ?',
            [$shipmentId, $externalId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function events(int $shipmentId): array
    {
        return $this->rows(
            'SELECT id, status, event_time, location_text, description, source FROM shipment_tracking_events WHERE shipment_id = ? ORDER BY event_time, id',
            [$shipmentId]
        );
    }

    public function openQcFail(int $jobId): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM quality_checks WHERE job_id = ? AND status = 'FAIL' AND resolved_at IS NULL",
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updatePackage(int $id, array $data): void
    {
        $this->run(
            'UPDATE packages SET shipment_id = ?, project_site_id = ?, package_type = ?, length_mm = ?, width_mm = ?, height_mm = ?,
             weight_kg = ?, fragile = ?, special_handling = ?, sequence_no = ?, sequence_total = ? WHERE id = ?',
            [
                $data['shipment_id'], $data['project_site_id'], $data['package_type'], $data['length_mm'], $data['width_mm'],
                $data['height_mm'], $data['weight_kg'], $data['fragile'], $data['special_handling'],
                $data['sequence_no'], $data['sequence_total'], $id,
            ]
        );
    }

    public function package(int $id): ?array
    {
        return $this->one('SELECT * FROM packages WHERE id = ?', [$id]);
    }

    public function packageByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM packages WHERE package_code = ?', [$code]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packages(int $shipmentId): array
    {
        return $this->rows('SELECT * FROM packages WHERE shipment_id = ? ORDER BY sequence_no, id', [$shipmentId]);
    }

    public function verifyPackage(int $id, int $userId): void
    {
        $this->run('UPDATE packages SET status = \'VERIFIED\', verified_at = NOW(), verified_by = ? WHERE id = ?', [$userId, $id]);
    }

    public function saveToken(int $shipmentId, string $hash, string $expires, int $userId): int
    {
        $this->run(
            'INSERT INTO shipment_access_tokens (shipment_id, token_hash, expires_at, created_by) VALUES (?, ?, ?, ?)',
            [$shipmentId, $hash, $expires, $userId]
        );

        return $this->insertId();
    }

    public function token(string $hash): ?array
    {
        return $this->one('SELECT * FROM shipment_access_tokens WHERE token_hash = ?', [$hash]);
    }

    public function revokeTokens(int $shipmentId): void
    {
        $this->run('UPDATE shipment_access_tokens SET revoked_at = NOW() WHERE shipment_id = ? AND revoked_at IS NULL', [$shipmentId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertPod(array $data): int
    {
        $this->run(
            'INSERT INTO shipment_pods (shipment_id, recipient_name, recipient_role, recipient_contact, statement_version, signature_id, photo_path, notes, latitude, longitude, device_signed_at, signed_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['shipment_id'], $data['recipient_name'], $data['recipient_role'], $data['recipient_contact'],
                $data['statement_version'], $data['signature_id'], $data['photo_path'], $data['notes'],
                $data['latitude'], $data['longitude'], $data['device_signed_at'], $data['signed_at'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function pod(int $shipmentId): ?array
    {
        return $this->one('SELECT * FROM shipment_pods WHERE shipment_id = ?', [$shipmentId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertHandover(array $data): int
    {
        $this->run(
            'INSERT INTO collection_handovers (shipment_id, collected_by, contact_detail, vehicle_registration, signature_id, device_signed_at, collected_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['shipment_id'], $data['collected_by'], $data['contact_detail'], $data['vehicle_registration'],
                $data['signature_id'], $data['device_signed_at'], $data['collected_at'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function handover(int $shipmentId): ?array
    {
        return $this->one('SELECT * FROM collection_handovers WHERE shipment_id = ?', [$shipmentId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertRun(array $data): int
    {
        $this->run(
            'INSERT INTO delivery_runs (run_date, vehicle_resource_id, driver_user_id, team_id, status, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['run_date'], $data['vehicle_resource_id'], $data['driver_user_id'], $data['team_id'], $data['status'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function deliveryRun(int $id): ?array
    {
        return $this->one('SELECT * FROM delivery_runs WHERE id = ?', [$id]);
    }

    public function insertStop(array $data): int
    {
        $this->run(
            'INSERT INTO delivery_stops (run_id, shipment_id, sequence_no, status) VALUES (?, ?, ?, \'PLANNED\')',
            [$data['run_id'], $data['shipment_id'], $data['sequence_no']]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stops(int $runId): array
    {
        return $this->rows(
            'SELECT st.*, s.shipment_number, s.destination_address, s.contact_name, s.contact_phone, s.customer_note, s.customer_id
             FROM delivery_stops st
             INNER JOIN shipments s ON s.id = st.shipment_id
             WHERE st.run_id = ? ORDER BY st.sequence_no, st.id',
            [$runId]
        );
    }

    public function stop(int $id): ?array
    {
        return $this->one('SELECT * FROM delivery_stops WHERE id = ?', [$id]);
    }

    public function updateStop(int $id, string $status, ?string $reason, ?string $notes): void
    {
        $this->run(
            'UPDATE delivery_stops SET status = ?, fail_reason = ?, notes = ?, completed_at = CASE WHEN ? IN (\'DELIVERED\', \'FAILED\', \'SKIPPED\') THEN NOW() ELSE completed_at END WHERE id = ?',
            [$status, $reason, $notes, $status, $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertException(array $data): int
    {
        $this->run(
            'INSERT INTO logistics_exceptions (shipment_id, job_id, project_id, project_site_id, installation_id, contractor_work_order_id, exception_type, status, description, photo_path, quantity, cause_text, disposition, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['shipment_id'], $data['job_id'], $data['project_id'], $data['project_site_id'],
                $data['installation_id'], $data['contractor_work_order_id'], $data['exception_type'], $data['status'],
                $data['description'], $data['photo_path'], $data['quantity'], $data['cause_text'], $data['disposition'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exceptions(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        $params[] = $limit;
        $params[] = $offset;

        return $this->rows(
            'SELECT * FROM logistics_exceptions WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            array_slice($params, 0, count($params) - 2)
        );
    }

    public function setException(int $id, string $status, ?string $disposition): void
    {
        $this->run('UPDATE logistics_exceptions SET status = ?, disposition = COALESCE(?, disposition) WHERE id = ?', [$status, $disposition, $id]);
    }

    public function costBySource(string $type, int $sourceId): ?array
    {
        return $this->one('SELECT * FROM logistics_costs WHERE source_type = ? AND source_id = ?', [$type, $sourceId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCost(array $data): int
    {
        $this->run(
            'INSERT INTO logistics_costs (job_id, project_id, shipment_id, source_type, source_id, amount, customer_charge, description, other_cost_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['project_id'], $data['shipment_id'], $data['source_type'], $data['source_id'],
                $data['amount'], $data['customer_charge'], $data['description'], $data['other_cost_id'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertTravel(array $data): int
    {
        $this->run(
            'INSERT INTO travel_logs (entity_type, entity_id, start_odometer, end_odometer, distance_km, cost_per_km, internal_cost, customer_charge, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['entity_type'], $data['entity_id'], $data['start_odometer'], $data['end_odometer'], $data['distance_km'],
                $data['cost_per_km'], $data['internal_cost'], $data['customer_charge'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertExpense(array $data): int
    {
        $this->run(
            'INSERT INTO field_expenses (entity_type, entity_id, expense_type, amount, receipt_path, note, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, \'CAPTURED\', ?)',
            [$data['entity_type'], $data['entity_id'], $data['expense_type'], $data['amount'], $data['receipt_path'], $data['note'], $data['created_by']]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function shipmentsPage(array $filters, int $limit, int $offset): array
    {
        $where = ['1=1'];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        if ((int) ($filters['project_id'] ?? 0) > 0) {
            $where[] = 's.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        if ((int) ($filters['customer_id'] ?? 0) > 0) {
            $where[] = 's.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }

        return $this->rows(
            'SELECT s.id, s.shipment_number, s.shipment_type, s.status, s.customer_id, s.job_id, s.project_id, s.project_site_id,
                    s.required_date, s.tracking_number, s.waybill_number, s.created_at
             FROM shipments s WHERE ' . implode(' AND ', $where) . ' ORDER BY s.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * @return array<string, int>
     */
    public function dashboardCounts(string $today): array
    {
        $row = $this->one(
            "SELECT
                SUM(status = 'READY') AS ready_to_dispatch,
                SUM(status = 'BOOKED') AS awaiting_courier,
                SUM(status IN ('IN_TRANSIT','OUT_FOR_DELIVERY','COLLECTED')) AS in_transit,
                SUM(required_date = ? AND status NOT IN ('DELIVERED','CANCELLED','RETURNED')) AS due_today
             FROM shipments",
            [$today]
        );
        $ex = $this->one("SELECT COUNT(*) AS n FROM logistics_exceptions WHERE status IN ('OPEN','INVESTIGATING','ACTION_REQUIRED')");
        $blocked = $this->one("SELECT COUNT(*) AS n FROM job_installations WHERE status = 'BLOCKED' AND scheduled_date = ?", [$today]);
        $installs = $this->one("SELECT COUNT(*) AS n FROM job_installations WHERE scheduled_date = ? AND status NOT IN ('CANCELLED')", [$today]);
        $overdue = $this->one(
            "SELECT COUNT(*) AS n FROM contractor_work_orders WHERE required_date < ? AND status NOT IN ('APPROVED_COMPLETE','CANCELLED','DECLINED')",
            [$today]
        );
        $snags = $this->one("SELECT COUNT(*) AS n FROM job_snags WHERE status IN ('OPEN','IN_PROGRESS')");

        return [
            'ready_to_dispatch' => (int) ($row['ready_to_dispatch'] ?? 0),
            'awaiting_courier' => (int) ($row['awaiting_courier'] ?? 0),
            'in_transit' => (int) ($row['in_transit'] ?? 0),
            'due_today' => (int) ($row['due_today'] ?? 0),
            'delivery_exceptions' => (int) ($ex['n'] ?? 0),
            'installations_today' => (int) ($installs['n'] ?? 0),
            'installations_blocked' => (int) ($blocked['n'] ?? 0),
            'contractor_overdue' => (int) ($overdue['n'] ?? 0),
            'open_snags' => (int) ($snags['n'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function projectRollup(int $projectId): array
    {
        $sites = $this->one('SELECT COUNT(*) AS n FROM project_sites WHERE project_id = ?', [$projectId]);
        $ready = $this->one(
            "SELECT COUNT(DISTINCT project_site_id) AS n FROM fulfilment_requirements
             WHERE project_site_id IN (SELECT id FROM project_sites WHERE project_id = ?)
               AND status IN ('READY','NOT_READY','PARTIALLY_DISPATCHED') AND dispatched_quantity < quantity",
            [$projectId]
        );
        $transit = $this->one(
            "SELECT COUNT(DISTINCT project_site_id) AS n FROM shipments
             WHERE project_id = ? AND status IN ('COLLECTED','IN_TRANSIT','OUT_FOR_DELIVERY','AWAITING_COLLECTION')",
            [$projectId]
        );
        $installed = $this->one(
            "SELECT COUNT(DISTINCT project_site_id) AS n FROM job_installations
             WHERE project_id = ? AND status IN ('SIGNED_OFF','COMPLETE')",
            [$projectId]
        );
        $snagged = $this->one(
            "SELECT COUNT(DISTINCT project_site_id) AS n FROM job_snags
             WHERE project_site_id IN (SELECT id FROM project_sites WHERE project_id = ?) AND status IN ('OPEN','IN_PROGRESS')",
            [$projectId]
        );
        $blocked = $this->one(
            "SELECT COUNT(DISTINCT project_site_id) AS n FROM job_installations WHERE project_id = ? AND status = 'BLOCKED'",
            [$projectId]
        );

        return [
            'sites' => (int) ($sites['n'] ?? 0),
            'ready_to_dispatch' => (int) ($ready['n'] ?? 0),
            'in_transit' => (int) ($transit['n'] ?? 0),
            'installed' => (int) ($installed['n'] ?? 0),
            'snagged' => (int) ($snagged['n'] ?? 0),
            'blocked' => (int) ($blocked['n'] ?? 0),
        ];
    }

    public function customerName(int $customerId): string
    {
        $row = $this->one('SELECT company_name, first_name, last_name FROM customers WHERE id = ?', [$customerId]);
        if ($row === null) {
            return '';
        }
        $company = trim((string) ($row['company_name'] ?? ''));
        if ($company !== '') {
            return $company;
        }

        return trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function courierPerformance(): array
    {
        return $this->rows(
            "SELECT c.id, c.name,
                COUNT(s.id) AS shipments,
                SUM(s.status = 'DELIVERED' AND (s.expected_delivery IS NULL OR DATE(s.actual_delivery) <= s.expected_delivery)) AS on_time,
                SUM(s.status = 'DELIVERY_FAILED') AS failed,
                AVG(CASE WHEN s.actual_delivery IS NOT NULL AND s.collection_date IS NOT NULL
                    THEN DATEDIFF(DATE(s.actual_delivery), s.collection_date) END) AS average_transit_days,
                AVG(s.actual_courier_cost) AS average_actual_cost
             FROM couriers c
             LEFT JOIN shipments s ON s.courier_id = c.id
             GROUP BY c.id, c.name
             ORDER BY c.name"
        );
    }
}

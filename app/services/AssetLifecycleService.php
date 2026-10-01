<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\AssetRepository;
use App\Repositories\OperationsRepository;
use PDOException;

/**
 * Parts, costs, history, and reports for an asset after it is installed.
 * Warranty cost stays on the service job. It is not sales revenue.
 */
final class AssetLifecycleService
{
    public function __construct(
        private readonly AssetRepository $assets = new AssetRepository(),
        private readonly NumberingService $numbers = new NumberingService()
    ) {
    }

    /**
     * Consume one stock movement for a replacement part. The same operation
     * uuid does not consume again.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, duplicate: bool}
     */
    public function consumeReplacement(array $input, int $userId): array
    {
        if (!can('assets.manage_components')) {
            return ['errors' => ['_form' => 'You cannot fit a replacement part.'], 'id' => null, 'duplicate' => false];
        }
        $uuid = trim((string) ($input['operation_uuid'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $uuid)) {
            return ['errors' => ['operation_uuid' => 'A stable operation id is required.'], 'id' => null, 'duplicate' => false];
        }
        $existing = $this->assets->partByUuid($uuid);
        if ($existing !== null) {
            return ['errors' => [], 'id' => (int) $existing['id'], 'duplicate' => true];
        }
        $jobId = (int) ($input['job_id'] ?? 0);
        $assetId = (int) ($input['asset_id'] ?? 0);
        $productId = (int) ($input['product_id'] ?? 0);
        if ($jobId < 1 || $assetId < 1 || $productId < 1) {
            return ['errors' => ['_form' => 'Job, asset, and product are required.'], 'id' => null, 'duplicate' => false];
        }
        $quantity = trim((string) ($input['quantity'] ?? '1'));
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
            return ['errors' => ['quantity' => 'Quantity must be greater than zero.'], 'id' => null, 'duplicate' => false];
        }
        $usageId = 0;
        try {
            Database::transaction(function () use ($input, $userId, $uuid, $jobId, $assetId, $productId, $quantity, &$usageId): void {
                $usageId = $this->assets->insertPart([
                    'operation_uuid' => $uuid,
                    'job_id' => $jobId,
                    'asset_id' => $assetId,
                    'old_component_id' => (int) ($input['old_component_id'] ?? 0) > 0 ? (int) $input['old_component_id'] : null,
                    'product_id' => $productId,
                    'quantity' => Decimal::round($quantity, 4),
                ]);
                $recorded = (new MaterialUsageService(stock: new JobStockHook()))->record($jobId, [
                    'usage_type' => 'INSTALLATION',
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'stock_location_id' => (int) ($input['stock_location_id'] ?? 0),
                    'notes' => 'Service replacement ' . $uuid,
                ], $userId);
                if ($recorded['errors'] !== []) {
                    throw new \RuntimeException((string) (reset($recorded['errors']) ?: 'Stock was not consumed.'));
                }
                $movement = $this->assets->rowsForReport(
                    'SELECT id FROM stock_movements WHERE job_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1',
                    [$jobId, $productId]
                );
                $newComponent = null;
                $oldId = (int) ($input['old_component_id'] ?? 0);
                if ($oldId > 0) {
                    $replaced = (new AssetComponentService())->replace($oldId, [
                        'description' => (string) ($input['description'] ?? 'Replacement part'),
                        'serial_number' => (string) ($input['serial_number'] ?? ''),
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'installed_date' => date('Y-m-d'),
                        'manufacturer' => (string) ($input['manufacturer'] ?? ''),
                        'model' => (string) ($input['model'] ?? ''),
                    ], $userId, $jobId);
                    if ($replaced['id'] === null) {
                        throw new \RuntimeException((string) (reset($replaced['errors']) ?: 'Component was not replaced.'));
                    }
                    $newComponent = $replaced['id'];
                }
                $this->assets->finishPart($usageId, $newComponent, isset($movement[0]['id']) ? (int) $movement[0]['id'] : null);
            });
        } catch (PDOException $e) {
            $again = $this->assets->partByUuid($uuid);
            if ($again !== null) {
                return ['errors' => [], 'id' => (int) $again['id'], 'duplicate' => true];
            }

            return ['errors' => ['_form' => 'The part usage could not be saved.'], 'id' => null, 'duplicate' => false];
        } catch (\RuntimeException $e) {
            return ['errors' => ['_form' => $e->getMessage()], 'id' => null, 'duplicate' => false];
        }

        return ['errors' => [], 'id' => $usageId, 'duplicate' => false];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function recordCosts(int $jobId, array $input, int $userId): array
    {
        if (!can('service.view_costs') && !can('materials.record_usage')) {
            return ['_form' => 'You cannot record service cost.'];
        }
        $materialProduct = (int) ($input['product_id'] ?? 0);
        $materialQty = trim((string) ($input['material_quantity'] ?? ''));
        if ($materialProduct > 0 && $materialQty !== '') {
            $recorded = (new MaterialUsageService(stock: new JobStockHook()))->record($jobId, [
                'usage_type' => 'INSTALLATION',
                'product_id' => $materialProduct,
                'quantity' => $materialQty,
                'stock_location_id' => (int) ($input['stock_location_id'] ?? 0),
                'notes' => 'Service material',
            ], $userId);
            if ($recorded['errors'] !== []) {
                return $recorded['errors'];
            }
        }
        $labour = trim((string) ($input['labour_cost'] ?? ''));
        $minutes = (int) ($input['labour_minutes'] ?? 0);
        if ($labour !== '' && $minutes > 0) {
            if (!Decimal::isNumeric($labour)) {
                return ['labour_cost' => 'Labour cost must be an amount.'];
            }
            (new OperationsRepository())->insertTime([
                'job_id' => $jobId,
                'job_item_id' => null,
                'task_id' => null,
                'user_id' => $userId,
                'work_type' => 'INSTALLATION',
                'started_at' => date('Y-m-d H:i:s', time() - ($minutes * 60)),
                'ended_at' => date('Y-m-d H:i:s'),
                'minutes' => $minutes,
                'hourly_cost_snapshot' => Decimal::div(Decimal::money($labour), Decimal::div((string) $minutes, '60', 4), 4),
                'total_cost' => Decimal::money($labour),
                'description' => 'Service labour',
            ]);
        }
        $travel = trim((string) ($input['travel_cost'] ?? ''));
        if ($travel !== '') {
            if (!Decimal::isNumeric($travel) || Decimal::cmp($travel, '0') < 0) {
                return ['travel_cost' => 'Travel cost must be zero or more.'];
            }
            (new OperationsRepository())->insertOther([
                'job_id' => $jobId,
                'cost_type' => 'TRAVEL',
                'description' => 'Service travel',
                'supplier_id' => null,
                'quantity' => '1.0000',
                'unit_cost' => Decimal::money($travel),
                'total_cost' => Decimal::money($travel),
                'reference' => 'service-travel-' . $jobId,
                'created_by' => $userId,
            ]);
        }
        (new JobCostingService())->refresh($jobId);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function lifetime(int $assetId): array
    {
        $asset = $this->assets->find($assetId);
        if ($asset === null) {
            return [];
        }
        $originalCommercial = Decimal::money((string) ($asset['original_commercial_value'] ?? '0'));
        $internal = Decimal::money((string) ($asset['original_internal_cost'] ?? '0'));
        $paid = '0.00';
        $warranty = '0.00';
        foreach ($this->assets->serviceJobs($assetId) as $job) {
            $actual = Decimal::money((string) $job['actual_total_cost']);
            $internal = Decimal::money(Decimal::add($internal, $actual));
            $class = (string) ($job['service_classification'] ?? '');
            if (in_array($class, ['PAID', 'MAINTENANCE_CONTRACT'], true)) {
                $paid = Decimal::money(Decimal::add($paid, (string) $job['quoted_revenue_snapshot']));
            }
            if ($class === 'WARRANTY') {
                $warranty = Decimal::money(Decimal::add($warranty, $actual));
            }
        }

        return [
            'original_commercial_value' => $originalCommercial,
            'paid_service_commercial_value' => $paid,
            'lifetime_commercial_value' => Decimal::money(Decimal::add($originalCommercial, $paid)),
            'internal_lifetime_cost' => $internal,
            'warranty_internal_cost' => $warranty,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldPack(int $assetId): array
    {
        $asset = $this->assets->find($assetId);
        if ($asset === null) {
            return [];
        }
        $requests = [];
        foreach ($this->assets->requestsForAsset($assetId) as $request) {
            $requests[] = [
                'request_number' => (string) $request['request_number'],
                'problem' => (string) $request['description'],
                'priority' => (string) $request['priority'],
                'status' => (string) $request['status'],
            ];
        }
        $warranties = [];
        foreach ($this->assets->warranties($assetId) as $warranty) {
            $warranties[] = [
                'type' => (string) $warranty['warranty_type'],
                'start' => (string) $warranty['start_date'],
                'end' => (string) $warranty['end_date'],
                'status' => (string) $warranty['status'],
            ];
        }

        return [
            'asset_number' => (string) $asset['asset_number'],
            'name' => (string) $asset['name'],
            'customer' => (string) $asset['company_name'],
            'site' => (string) ($asset['site_name'] ?? ''),
            'location' => (string) ($asset['location_description'] ?? ''),
            'latitude' => $asset['latitude'],
            'longitude' => $asset['longitude'],
            'problem' => $requests[0]['problem'] ?? '',
            'requests' => $requests,
            'warranties' => $warranties,
            'history' => array_map(static fn (array $event): array => [
                'type' => (string) $event['event_type'],
                'summary' => (string) $event['summary'],
                'at' => (string) $event['happened_at'],
            ], $this->assets->events($assetId, 30)),
            'components' => array_map(static fn (array $row): array => [
                'description' => (string) $row['description'],
                'status' => (string) $row['status'],
                'serial_number' => (string) ($row['serial_number'] ?? ''),
            ], $this->assets->components($assetId)),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, duplicate: bool, error: string}
     */
    public function sync(int $userId, string $uuid, int $assetId, string $kind, array $payload): array
    {
        if (!can('inspections.perform')) {
            return ['ok' => false, 'duplicate' => false, 'error' => 'Permission was checked again and refused.'];
        }
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $uuid)) {
            return ['ok' => false, 'duplicate' => false, 'error' => 'The sync id is not valid.'];
        }
        $existing = $this->assets->syncByUuid($uuid);
        if ($existing !== null) {
            return ['ok' => true, 'duplicate' => true, 'error' => ''];
        }
        try {
            Database::transaction(function () use ($userId, $uuid, $assetId, $kind, $payload): void {
                $this->assets->insertSync($uuid, $assetId, $userId, $kind);
                if ($kind === 'inspection') {
                    $recorded = (new InspectionService())->record($assetId, $payload, $userId);
                    if ($recorded['id'] === null) {
                        throw new \RuntimeException((string) (reset($recorded['errors']) ?: 'Inspection was not saved.'));
                    }
                }
            });
        } catch (PDOException) {
            $again = $this->assets->syncByUuid($uuid);
            if ($again !== null) {
                return ['ok' => true, 'duplicate' => true, 'error' => ''];
            }

            return ['ok' => false, 'duplicate' => false, 'error' => 'Sync was not saved.'];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'duplicate' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'duplicate' => false, 'error' => ''];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function agreement(array $input, int $userId): array
    {
        unset($userId);
        if (!can('maintenance.manage')) {
            return ['errors' => ['_form' => 'You cannot create a service agreement.'], 'id' => null];
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        $start = trim((string) ($input['start_date'] ?? ''));
        $end = trim((string) ($input['end_date'] ?? ''));
        if ($customerId < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return ['errors' => ['_form' => 'Customer and dates are required.'], 'id' => null];
        }
        $hours = trim((string) ($input['response_target_hours'] ?? ''));
        $id = $this->assets->insertAgreement([
            'agreement_number' => $this->numbers->agreement(),
            'customer_id' => $customerId,
            'project_id' => (int) ($input['project_id'] ?? 0) > 0 ? (int) $input['project_id'] : null,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'ACTIVE',
            'description' => blank_to_null($input['description'] ?? null),
            'included_services' => blank_to_null($input['included_services'] ?? null),
            'response_target_hours' => $hours !== '' ? (int) $hours : null,
            'billing_notes' => blank_to_null($input['billing_notes'] ?? null),
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<array<string, mixed>>}
     */
    public function report(string $slug): array
    {
        $slug = strtolower($slug);
        return match ($slug) {
            'register' => $this->table('Asset register', ['asset_number', 'name', 'company_name', 'site_name', 'status', 'installation_date'],
                'SELECT asset_number, name, company_name, site_name, status, installation_date FROM (' . $this->base() . ') listed ORDER BY asset_number'),
            'by-customer' => $this->table('Assets by customer', ['company_name', 'total'],
                'SELECT c.company_name, COUNT(*) AS total FROM customer_assets a JOIN customers c ON c.id = a.customer_id WHERE a.archived_at IS NULL GROUP BY c.id, c.company_name ORDER BY total DESC'),
            'by-site' => $this->table('Assets by site', ['site_name', 'total'],
                'SELECT COALESCE(ps.site_name, \'No site\') AS site_name, COUNT(*) AS total FROM customer_assets a LEFT JOIN project_sites ps ON ps.id = a.project_site_id WHERE a.archived_at IS NULL GROUP BY ps.id, ps.site_name ORDER BY total DESC'),
            'by-type' => $this->table('Assets by type', ['name', 'total'],
                'SELECT t.name, COUNT(*) AS total FROM customer_assets a JOIN asset_types t ON t.id = a.asset_type_id WHERE a.archived_at IS NULL GROUP BY t.id, t.name ORDER BY total DESC'),
            'age' => $this->table('Asset age', ['asset_number', 'name', 'installation_date', 'age_years'],
                'SELECT asset_number, name, installation_date, TIMESTAMPDIFF(YEAR, installation_date, CURDATE()) AS age_years FROM customer_assets WHERE archived_at IS NULL AND installation_date IS NOT NULL ORDER BY installation_date'),
            'warranty-expiry' => $this->table('Warranty expiry', ['asset_number', 'warranty_type', 'end_date', 'status'],
                'SELECT a.asset_number, w.warranty_type, w.end_date, w.status FROM asset_warranties w JOIN customer_assets a ON a.id = w.asset_id ORDER BY w.end_date'),
            'service-due' => $this->table('Service due', ['asset_number', 'plan_name', 'next_due_on'],
                'SELECT a.asset_number, p.name AS plan_name, s.next_due_on FROM asset_plan_assignments s JOIN customer_assets a ON a.id = s.asset_id JOIN maintenance_plans p ON p.id = s.plan_id WHERE s.next_due_on >= CURDATE() ORDER BY s.next_due_on'),
            'service-overdue' => $this->table('Service overdue', ['asset_number', 'plan_name', 'next_due_on'],
                'SELECT a.asset_number, p.name AS plan_name, s.next_due_on FROM asset_plan_assignments s JOIN customer_assets a ON a.id = s.asset_id JOIN maintenance_plans p ON p.id = s.plan_id WHERE s.next_due_on < CURDATE() ORDER BY s.next_due_on'),
            'request-performance' => $this->table('Service request performance', ['problem_category', 'priority', 'total', 'avg_open_days'],
                'SELECT problem_category, priority, COUNT(*) AS total, ROUND(AVG(DATEDIFF(COALESCE(DATE(resolved_at), CURDATE()), DATE(reported_at))), 1) AS avg_open_days FROM service_requests GROUP BY problem_category, priority'),
            'profitability' => $this->table('Service job profitability', ['job_number', 'service_classification', 'quoted_revenue_snapshot', 'actual_total_cost'],
                'SELECT job_number, service_classification, quoted_revenue_snapshot, actual_total_cost FROM jobs WHERE job_type <> \'STANDARD\' AND archived = 0 ORDER BY id DESC'),
            'warranty-cost' => $this->table('Warranty cost', ['job_number', 'quoted_revenue_snapshot', 'actual_total_cost'],
                'SELECT job_number, quoted_revenue_snapshot, actual_total_cost FROM jobs WHERE service_classification = \'WARRANTY\' AND archived = 0'),
            'supplier-recovery' => $this->table('Supplier warranty recovery', ['claim_number', 'cost_recovery_amount', 'recovered_amount'],
                'SELECT claim_number, cost_recovery_amount, recovered_amount FROM warranty_claims ORDER BY id DESC'),
            'component-failure' => $this->table('Component failure', ['description', 'installed', 'failed'],
                'SELECT description, SUM(status IN (\'ACTIVE\',\'FAILED\',\'REPLACED\',\'REMOVED\')) AS installed, SUM(status IN (\'FAILED\',\'REPLACED\')) AS failed FROM asset_components GROUP BY description HAVING installed > 0 ORDER BY failed DESC'),
            'maintenance-compliance' => $this->table('Maintenance compliance', ['name', 'assignments', 'overdue'],
                'SELECT p.name, COUNT(*) AS assignments, SUM(s.next_due_on < CURDATE()) AS overdue FROM asset_plan_assignments s JOIN maintenance_plans p ON p.id = s.plan_id GROUP BY p.id, p.name'),
            'lifetime-cost' => $this->table('Asset lifetime cost', ['asset_number', 'original_internal_cost', 'service_cost'],
                'SELECT a.asset_number, a.original_internal_cost, COALESCE(SUM(j.actual_total_cost), 0) AS service_cost FROM customer_assets a LEFT JOIN jobs j ON j.customer_asset_id = a.id AND j.job_type <> \'STANDARD\' GROUP BY a.id ORDER BY a.asset_number'),
            'fleet' => $this->table('Fleet branding register', ['asset_number', 'company_name', 'registration', 'vehicle_make', 'vehicle_model', 'installation_date'],
                'SELECT a.asset_number, c.company_name, a.registration, a.vehicle_make, a.vehicle_model, a.installation_date FROM customer_assets a JOIN customers c ON c.id = a.customer_id JOIN asset_types t ON t.id = a.asset_type_id WHERE t.code = \'VEHICLE_BRANDING\' AND a.archived_at IS NULL'),
            'forecast' => $this->table('Maintenance forecast', ['asset_number', 'plan_name', 'next_due_on'],
                'SELECT a.asset_number, p.name AS plan_name, s.next_due_on FROM asset_plan_assignments s JOIN customer_assets a ON a.id = s.asset_id JOIN maintenance_plans p ON p.id = s.plan_id WHERE s.next_due_on <= DATE_ADD(CURDATE(), INTERVAL 180 DAY) ORDER BY s.next_due_on'),
            default => ['title' => 'Unknown report', 'columns' => [], 'rows' => []],
        };
    }

    /**
     * @param list<string> $columns
     * @return array{title: string, columns: list<string>, rows: list<array<string, mixed>>}
     */
    private function table(string $title, array $columns, string $sql): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => $this->assets->rowsForReport($sql)];
    }

    private function base(): string
    {
        return 'SELECT a.asset_number, a.name, c.company_name, ps.site_name, a.status, a.installation_date
            FROM customer_assets a
            JOIN customers c ON c.id = a.customer_id
            LEFT JOIN project_sites ps ON ps.id = a.project_site_id
            WHERE a.archived_at IS NULL';
    }
}

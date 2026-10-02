<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\InventoryRepository;
use App\Repositories\ForecastRepository;
use App\Services\ApiClientService;
use App\Services\LeadService;
use App\Services\ProjectFinancialService;
use App\Services\ProjectService;
use App\Services\RateLimiter;
use App\Repositories\ProjectRepository;

/**
 * Versioned API. A client needs a matching scope. Errors do not include a stack trace.
 */
final class ApiV1Controller
{
    public function customer(string $id): void
    {
        $this->read('customers.read', 'customer', (int) $id, static function (int $id): ?array {
            $row = (new ForecastRepository())->customer($id);

            return $row === null ? null : [
                'id' => (int) $row['id'],
                'name' => trim((string) ($row['company_name'] ?: ($row['first_name'] . ' ' . $row['last_name']))),
            ];
        });
    }

    public function quote(string $id): void
    {
        $this->read('quotes.read', 'quote', (int) $id, static fn (int $id): ?array => (new ForecastRepository())->quoteStatus($id));
    }

    public function job(string $id): void
    {
        $this->read('jobs.read', 'job', (int) $id, static fn (int $id): ?array => (new ForecastRepository())->jobStatus($id));
    }

    public function invoice(string $id): void
    {
        $this->read('invoices.read', 'invoice', (int) $id, static function (int $id): ?array {
            $status = (new ForecastRepository())->invoiceStatus($id);

            return $status === null ? null : ['id' => $id, 'status' => $status];
        });
    }

    public function stock(string $id): void
    {
        $this->read('inventory.read', 'product', (int) $id, static function (int $id): ?array {
            $product = (new ForecastRepository())->product($id);
            if ($product === null) {
                return null;
            }

            return [
                'id' => $id,
                'name' => (string) $product['name'],
                'on_hand' => (new InventoryRepository())->onHand($id),
            ];
        });
    }

    public function assets(): void
    {
        $auth = $this->gate('assets.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\AssetRepository())->search([
            'q' => trim((string) ($_GET['q'] ?? '')),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
        ], 50, max(0, (int) ($_GET['offset'] ?? 0)));
        $money = (new ApiClientService())->allows($auth['scopes'], 'assets.financials');
        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->assetPayload($row, $money);
        }
        $this->finish($auth, 200, true, ['assets' => $data], [], 'customer_asset', null);
    }

    public function assetRecord(string $id): void
    {
        $this->read('assets.read', 'customer_asset', (int) $id, function (int $id): ?array {
            $row = (new \App\Repositories\AssetRepository())->find($id);

            return $row === null ? null : $this->assetPayload($row, false);
        });
    }

    public function assetComponents(string $id): void
    {
        $this->read('assets.read', 'customer_asset', (int) $id, static function (int $id): ?array {
            $repo = new \App\Repositories\AssetRepository();
            if ($repo->find($id) === null) {
                return null;
            }

            return ['components' => $repo->components($id)];
        });
    }

    public function assetWarranties(string $id): void
    {
        $this->read('assets.read', 'customer_asset', (int) $id, static function (int $id): ?array {
            $repo = new \App\Repositories\AssetRepository();
            if ($repo->find($id) === null) {
                return null;
            }

            return ['warranties' => $repo->warranties($id)];
        });
    }

    public function assetHistory(string $id): void
    {
        $this->read('assets.read', 'customer_asset', (int) $id, static function (int $id): ?array {
            $repo = new \App\Repositories\AssetRepository();
            if ($repo->find($id) === null) {
                return null;
            }

            return ['history' => $repo->events($id)];
        });
    }

    public function serviceRequests(): void
    {
        $auth = $this->gate('service.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\AssetRepository())->requests([
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
        ]);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => (int) $row['id'],
                'request_number' => (string) $row['request_number'],
                'status' => (string) $row['status'],
                'priority' => (string) $row['priority'],
                'asset_number' => (string) ($row['asset_number'] ?? ''),
            ];
        }
        $this->finish($auth, 200, true, ['service_requests' => $data], [], 'service_request', null);
    }

    public function serviceRequest(string $id): void
    {
        $this->read('service.read', 'service_request', (int) $id, static function (int $id): ?array {
            $row = (new \App\Repositories\AssetRepository())->request($id);
            if ($row === null) {
                return null;
            }

            return [
                'id' => (int) $row['id'],
                'request_number' => (string) $row['request_number'],
                'status' => (string) $row['status'],
                'description' => (string) $row['description'],
                'warranty_candidate' => (int) $row['warranty_candidate'],
            ];
        });
    }

    public function warrantyClaims(): void
    {
        $this->read('service.read', 'warranty_claim', 1, static function (int $id): array {
            unset($id);

            return ['claims' => (new \App\Repositories\AssetRepository())->rowsForReport(
                'SELECT id, claim_number, asset_id, status, reported_date FROM warranty_claims ORDER BY id DESC LIMIT 50'
            )];
        });
    }

    public function maintenanceDue(): void
    {
        $auth = $this->gate('service.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Services\AssetMaintenanceService())->forecast(30);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'asset_number' => (string) $row['asset_number'],
                'plan' => (string) $row['plan_name'],
                'next_due_on' => (string) $row['next_due_on'],
            ];
        }
        $this->finish($auth, 200, true, ['due' => $data], [], 'maintenance', null);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function assetPayload(array $row, bool $money): array
    {
        $payload = [
            'id' => (int) $row['id'],
            'asset_number' => (string) $row['asset_number'],
            'name' => (string) $row['name'],
            'status' => (string) $row['status'],
            'customer_id' => (int) $row['customer_id'],
            'site' => (string) ($row['site_name'] ?? ''),
        ];
        if ($money) {
            $payload['original_commercial_value'] = $row['original_commercial_value'];
            $payload['original_internal_cost'] = $row['original_internal_cost'];
        }

        return $payload;
    }

    public function createLead(): void
    {
        $auth = $this->gate('leads.write');
        if ($auth === null) {
            return;
        }
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            $this->finish($auth, 422, false, null, ['body' => 'Send a JSON object.'], 'lead', null);

            return;
        }
        $created = (new LeadService())->create($body, (int) ($auth['client']['created_by'] ?? 0));
        if ($created['id'] === null) {
            $this->finish($auth, 422, false, null, $created['errors'], 'lead', null);

            return;
        }
        $this->finish($auth, 201, true, ['id' => $created['id']], [], 'lead', $created['id']);
    }

    /**
     * @param callable(int): ?array<string, mixed> $loader
     */
    public function project(string $id): void
    {
        $this->read('projects.read', 'project', (int) $id, static function (int $id): ?array {
            $row = (new ProjectRepository())->find($id);

            return $row === null ? null : [
                'id' => (int) $row['id'],
                'project_number' => (string) $row['project_number'],
                'name' => (string) $row['name'],
                'status' => (string) $row['status'],
                'health' => (string) $row['project_health'],
            ];
        });
    }

    public function projectSites(string $id): void
    {
        $this->read('projects.read', 'project', (int) $id, static function (int $id): ?array {
            if ((new ProjectRepository())->find($id) === null) {
                return null;
            }

            return ['sites' => (new ProjectRepository())->sites($id)];
        });
    }

    public function projectMilestones(string $id): void
    {
        $this->read('projects.read', 'project', (int) $id, static function (int $id): ?array {
            if ((new ProjectRepository())->find($id) === null) {
                return null;
            }

            return ['milestones' => (new ProjectRepository())->milestones($id)];
        });
    }

    public function projectJobs(string $id): void
    {
        $this->read('projects.read', 'project', (int) $id, static function (int $id): ?array {
            if ((new ProjectRepository())->find($id) === null) {
                return null;
            }

            return ['jobs' => (new ProjectRepository())->jobs($id, null)];
        });
    }

    public function projectSummary(string $id): void
    {
        $auth = $this->gate('projects.read');
        if ($auth === null) {
            return;
        }
        $projectId = (int) $id;
        $project = (new ProjectRepository())->find($projectId);
        if ($project === null) {
            $this->finish($auth, 404, false, null, ['record' => 'That record was not found.'], 'project', $projectId);

            return;
        }
        $workspace = (new ProjectService())->workspace($projectId);
        $data = [
            'id' => $projectId,
            'project_number' => (string) $project['project_number'],
            'status' => (string) $project['status'],
            'health' => $workspace['health']['health'],
            'reasons' => $workspace['health']['reasons'],
            'sites' => $workspace['counts']['sites'],
            'jobs' => $workspace['counts']['jobs'],
            'progress' => $workspace['progress']['percent'],
        ];
        if ((new ApiClientService())->allows($auth['scopes'], 'projects.financials')) {
            $money = (new ProjectFinancialService())->statement($projectId);
            $data['commercial_value'] = $money['commercial_value'];
            $data['actual_cost'] = $money['actual_cost'];
            $data['gross_profit'] = $money['gross_profit'];
            $data['margin_percent'] = $money['margin_percent'];
            $data['invoiced'] = $money['invoiced'];
            $data['paid'] = $money['paid'];
        }
        $this->finish($auth, 200, true, $data, [], 'project', $projectId);
    }

    public function projectSite(string $id): void
    {
        $this->read('projects.read', 'project_site', (int) $id, static function (int $id): ?array {
            $site = (new ProjectRepository())->site($id);
            if ($site === null) {
                return null;
            }

            return [
                'id' => (int) $site['id'],
                'project_id' => (int) $site['project_id'],
                'site_code' => (string) $site['site_code'],
                'site_name' => (string) $site['site_name'],
                'status' => (string) $site['status'],
            ];
        });
    }

    public function specifications(): void
    {
        $auth = $this->gate('specifications.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\SignageRepository())->specifications(['approved_only' => true], 50, 0);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'version' => (int) $row['version'],
                'name' => (string) $row['name'],
                'estimator_type' => (string) $row['estimator_type'],
                'status' => (string) $row['status'],
            ];
        }
        $this->finish($auth, 200, true, ['specifications' => $data], [], 'specification', null);
    }

    public function vehicleTemplates(): void
    {
        $auth = $this->gate('specifications.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\SignageRepository())->templates(true);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => (int) $row['id'],
                'make' => (string) $row['make_name'],
                'model' => (string) $row['model_name'],
                'year_from' => (int) $row['year_from'],
                'verified' => (int) $row['verified'] === 1,
            ];
        }
        $this->finish($auth, 200, true, ['templates' => $data], [], 'vehicle_template', null);
    }

    public function estimateSign(string $type): void
    {
        $auth = $this->gate('estimators.use');
        if ($auth === null) {
            return;
        }
        $raw = file_get_contents('php://input');
        $body = json_decode($raw === false ? '' : $raw, true);
        $input = is_array($body) ? $body : $_GET;
        $userId = (int) ($auth['client']['created_by'] ?? 0);
        if ($userId < 1) {
            $this->finish($auth, 403, false, null, ['auth' => 'This API client has no user for estimator permissions.'], 'sign_calculation', null);

            return;
        }
        $_SESSION['user_id'] = $userId;
        forget_auth_user();
        $ran = (new \App\Services\SignEstimateService())->run(strtoupper($type), $input, $userId, false);
        if ($ran['errors'] !== []) {
            $this->finish($auth, 422, false, null, $ran['errors'], 'sign_calculation', null);

            return;
        }
        $result = $ran['result'];
        if (!(new ApiClientService())->allows($auth['scopes'], 'estimators.financials')) {
            unset($result['costs'], $result['installation_detail']);
        }
        $this->finish($auth, 200, true, $result, [], 'sign_calculation', null);
    }

    public function portalCatalogues(): void
    {
        $user = (new \App\Services\PortalAuthService())->user();
        if ($user === null) {
            $this->send(401, false, null, ['auth' => 'Sign in to the customer hub.']);

            return;
        }
        $rows = (new \App\Repositories\CustomerHubRepository())->catalogues((int) $user['customer_id'], 50, 0);
        $this->send(200, true, ['catalogues' => $rows], []);
    }

    public function artworkIndex(): void
    {
        $auth = $this->gate('artwork.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\ArtworkProofingRepository())->queue('CUSTOMER_REVIEW', 50);
        $this->finish($auth, 200, true, ['artwork' => $rows], [], 'artwork', null);
    }

    public function artworkShow(string $id): void
    {
        $auth = $this->gate('artwork.read');
        if ($auth === null) {
            return;
        }
        $row = (new \App\Repositories\ArtworkProofingRepository())->artwork((int) $id);
        if ($row === null) {
            $this->finish($auth, 404, false, null, ['record' => 'That artwork was not found.'], 'artwork', (int) $id);

            return;
        }
        $this->finish($auth, 200, true, ['artwork' => $row, 'revisions' => (new \App\Repositories\ArtworkProofingRepository())->revisions((int) $id)], [], 'artwork', (int) $id);
    }

    public function portalArtwork(): void
    {
        $user = (new \App\Services\PortalAuthService())->user();
        if ($user === null) {
            $this->send(401, false, null, ['auth' => 'Sign in to the customer hub.']);

            return;
        }
        $rows = (new \App\Services\ArtworkProofingService())->library((int) $user['customer_id']);
        $this->send(200, true, ['artwork' => $rows], []);
    }

    public function portalOrders(): void
    {
        $user = (new \App\Services\PortalAuthService())->user();
        if ($user === null) {
            $this->send(401, false, null, ['auth' => 'Sign in to the customer hub.']);

            return;
        }
        $rows = (new \App\Repositories\CustomerHubRepository())->searchOrders((int) $user['customer_id'], '', 1);
        $this->send(200, true, ['orders' => $rows], []);
    }

    public function procurementRfqs(): void
    {
        $auth = $this->gate('procurement.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\ProcurementRepository())->rfqPage(50, 0);
        $this->finish($auth, 200, true, ['rfqs' => $rows], [], null, null);
    }

    public function procurementRfq(string $id): void
    {
        $auth = $this->gate('procurement.read');
        if ($auth === null) {
            return;
        }
        $rfq = (new \App\Repositories\ProcurementRepository())->rfq((int) $id);
        $this->finish($auth, $rfq === null ? 404 : 200, $rfq !== null, $rfq, $rfq === null ? ['record' => 'That RFQ was not found.'] : [], 'supplier_rfq', (int) $id);
    }

    public function procurementResponses(string $id): void
    {
        $auth = $this->gate('procurement.read');
        if ($auth === null) {
            return;
        }
        $compared = (new \App\Services\SupplierRfqService())->compare((int) $id);
        $this->finish($auth, 200, true, $compared, [], 'supplier_rfq', (int) $id);
    }

    public function purchaseRecommendations(): void
    {
        $auth = $this->gate('procurement.read');
        if ($auth === null) {
            return;
        }
        $sample = (new \App\Services\SupplierRfqService())->suggestOrderQuantity('13', '5');
        $this->finish($auth, 200, true, $sample, [], null, null);
    }

    public function productionReadiness(string $id): void
    {
        $auth = $this->gate('production.read');
        if ($auth === null) {
            return;
        }
        $this->actAsClient($auth);
        $evaluated = (new \App\Services\ReleaseReadinessService())->evaluate((int) $id);
        $this->finish($auth, $evaluated['found'] ? 200 : 404, $evaluated['found'], $evaluated, $evaluated['found'] ? [] : ['record' => 'That job was not found.'], 'job', (int) $id);
    }

    public function productionReleases(string $id): void
    {
        $auth = $this->gate('production.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\ProductionControlRepository())->releaseQueue(['project_id' => 0]);
        $mine = array_values(array_filter($rows, static fn (array $row): bool => (int) $row['id'] === (int) $id));
        $current = (new \App\Repositories\ProductionControlRepository())->currentRelease((int) $id);
        $this->finish($auth, 200, true, ['current' => $current, 'listed' => $mine !== []], [], 'job', (int) $id);
    }

    public function productionRelease(string $id): void
    {
        $auth = $this->gate('production.read');
        if ($auth === null) {
            return;
        }
        $row = (new \App\Repositories\ProductionControlRepository())->release((int) $id);
        if ($row === null) {
            $this->finish($auth, 404, false, null, ['record' => 'That release was not found.'], 'production_release', (int) $id);

            return;
        }
        unset($row['snapshot_json']);
        $this->finish($auth, 200, true, $row, [], 'production_release', (int) $id);
    }

    public function productionQueue(): void
    {
        $auth = $this->gate('production.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\ProductionControlRepository())->queue([], 50, 0);
        $this->finish($auth, 200, true, ['stages' => $rows], [], 'production_stage', null);
    }

    public function productionStage(string $id, string $action): void
    {
        $auth = $this->gate('production.execute');
        if ($auth === null) {
            return;
        }
        $this->actAsClient($auth);
        $raw = file_get_contents('php://input');
        $body = json_decode($raw === false ? '' : $raw, true);
        $input = is_array($body) ? $body : [];
        $userId = (int) ($auth['client']['created_by'] ?? 0);
        $service = new \App\Services\ProductionStageControlService();
        $key = (string) ($input['idempotency_key'] ?? '');
        $errors = match ($action) {
            'start' => $service->start((int) $id, $userId, $key !== '' ? $key : null),
            'pause' => $service->pause((int) $id, (string) ($input['reason'] ?? ''), $userId, $key !== '' ? $key : null),
            default => $service->complete((int) $id, $input, $userId, $key !== '' ? $key : null),
        };
        $this->finish($auth, $errors === [] ? 200 : 422, $errors === [], ['stage_id' => (int) $id], $errors, 'production_stage', (int) $id);
    }

    public function fulfilmentList(): void
    {
        $auth = $this->gate('production.read');
        if ($auth === null) {
            return;
        }
        $jobId = (int) ($_GET['job_id'] ?? 0);
        $rows = $jobId > 0 ? (new \App\Repositories\ProductionControlRepository())->fulfilments($jobId) : [];
        $this->finish($auth, 200, true, ['fulfilment' => $rows], [], 'fulfilment', null);
    }

    public function fulfilmentRecord(string $id): void
    {
        $auth = $this->gate('production.read');
        if ($auth === null) {
            return;
        }
        $row = (new \App\Repositories\ProductionControlRepository())->fulfilment((int) $id);
        if ($row === null) {
            $this->finish($auth, 404, false, null, ['record' => 'That fulfilment was not found.'], 'fulfilment', (int) $id);

            return;
        }
        $this->finish($auth, 200, true, $row, [], 'fulfilment', (int) $id);
    }

    /**
     * @param array{client: array<string, mixed>, scopes: list<string>} $auth
     */
    private function actAsClient(array $auth): void
    {
        $userId = (int) ($auth['client']['created_by'] ?? 0);
        if ($userId > 0) {
            $_SESSION['user_id'] = $userId;
            forget_auth_user();
        }
    }

    private function read(string $scope, string $entity, int $id, callable $loader): void
    {
        $auth = $this->gate($scope);
        if ($auth === null) {
            return;
        }
        $row = $id > 0 ? $loader($id) : null;
        if ($row === null) {
            $this->finish($auth, 404, false, null, ['record' => 'That record was not found.'], $entity, $id);

            return;
        }
        $this->finish($auth, 200, true, $row, [], $entity, $id);
    }

    public function shipments(): void
    {
        $auth = $this->gate('logistics.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Services\LogisticsService())->page([], 50, 0);
        $this->finish($auth, 200, true, ['shipments' => $rows], [], 'shipment', null);
    }

    public function shipment(string $id): void
    {
        $auth = $this->gate('logistics.read');
        if ($auth === null) {
            return;
        }
        $row = (new \App\Repositories\LogisticsRepository())->shipment((int) $id);
        if ($row === null) {
            $this->finish($auth, 404, false, null, ['shipment' => 'That shipment was not found.'], 'shipment', (int) $id);

            return;
        }
        unset($row['internal_notes'], $row['estimated_courier_cost'], $row['actual_courier_cost']);
        $this->finish($auth, 200, true, ['shipment' => $row], [], 'shipment', (int) $id);
    }

    public function shipmentTracking(): void
    {
        $auth = $this->gate('logistics.read');
        if ($auth === null) {
            return;
        }
        $id = (int) ($_GET['shipment_id'] ?? 0);
        $events = $id > 0 ? (new \App\Repositories\LogisticsRepository())->events($id) : [];
        $this->finish($auth, 200, true, ['events' => $events], [], 'shipment', $id > 0 ? $id : null);
    }

    public function deliveries(): void
    {
        $auth = $this->gate('logistics.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Services\LogisticsService())->page(['status' => 'IN_TRANSIT'], 50, 0);
        $this->finish($auth, 200, true, ['deliveries' => $rows], [], 'shipment', null);
    }

    public function logisticsExceptions(): void
    {
        $auth = $this->gate('logistics.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\LogisticsRepository())->exceptions([], 50, 0);
        $this->finish($auth, 200, true, ['exceptions' => $rows], [], 'logistics_exception', null);
    }

    public function installations(): void
    {
        $auth = $this->gate('installations.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\OperationsRepository())->installationBoard();
        $this->finish($auth, 200, true, ['installations' => $rows], [], 'installation', null);
    }

    public function contractors(): void
    {
        $auth = $this->gate('contractors.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\ContractorRepository())->list(50, 0);
        $this->finish($auth, 200, true, ['contractors' => $rows], [], 'contractor', null);
    }

    public function contractorWorkOrders(): void
    {
        $auth = $this->gate('contractors.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Repositories\ContractorRepository())->recentWork(50);
        $this->finish($auth, 200, true, ['work_orders' => $rows], [], 'contractor_work', null);
    }

    public function contractorPortalWork(): void
    {
        $user = (new \App\Services\ContractorWorkService())->currentUser();
        if ($user === null) {
            $this->send(401, false, null, ['auth' => 'Sign in to the contractor portal.']);

            return;
        }
        $rows = (new \App\Services\ContractorWorkService())->home((int) $user['id']);
        $this->send(200, true, ['work' => $rows], []);
    }

    public function expenses(): void
    {
        $auth = $this->gate('expenses.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Services\ExpenseService())->page(25, 0, (int) $auth['client']['created_by']);
        $this->finish($auth, 200, true, ['expenses' => $rows], [], 'expense', null);
    }

    public function expense(string $id): void
    {
        $auth = $this->gate('expenses.read');
        if ($auth === null) {
            return;
        }
        $row = (new \App\Services\ExpenseService())->open((int) $id, (int) $auth['client']['created_by']);
        if ($row === null) {
            $this->finish($auth, 404, false, null, ['expense' => 'That expense was not found.'], 'expense', (int) $id);

            return;
        }
        unset($row['extraction_json']);
        $this->finish($auth, 200, true, ['expense' => $row], [], 'expense', (int) $id);
    }

    public function expenseAction(string $id, string $action): void
    {
        $auth = $this->gate('expenses.write');
        if ($auth === null) {
            return;
        }
        $service = new \App\Services\ExpenseService();
        $userId = (int) $auth['client']['created_by'];
        $result = match ($action) {
            'submit' => $service->submit((int) $id, $userId),
            'approve' => $service->approve((int) $id, $userId),
            'reject' => $service->reject((int) $id, (string) ($_POST['reason'] ?? ''), $userId),
            default => ['errors' => ['action' => 'That action was not found.']],
        };
        $ok = $result['errors'] === [];
        $this->finish($auth, $ok ? 200 : 422, $ok, [], $result['errors'], 'expense', (int) $id);
    }

    public function mileage(): void
    {
        $auth = $this->gate('expenses.read');
        if ($auth === null) {
            return;
        }
        $this->finish($auth, 200, true, ['mileage' => 'Record mileage from the expense service. The list stays on the job cost trace.'], [], 'mileage', null);
    }

    public function trips(): void
    {
        $auth = $this->gate('expenses.read');
        if ($auth === null) {
            return;
        }
        $this->finish($auth, 200, true, ['trips' => 'Trips are created by the trip service and allocated once.'], [], 'trip', null);
    }

    public function calendar(): void
    {
        $auth = $this->gate('expenses.read');
        if ($auth === null) {
            return;
        }
        $events = (new \App\Services\CalendarFeedService())->events(date('Y-m-d'), date('Y-m-d', strtotime('+14 days')), (int) $auth['client']['created_by'], 'BASIC');
        $this->finish($auth, 200, true, ['events' => $events], [], 'calendar', null);
    }

    public function calendarFeed(): void
    {
        $auth = $this->gate('expenses.read');
        if ($auth === null) {
            return;
        }
        $this->finish($auth, 200, true, ['feed' => 'Create a revocable token from CalendarFeedService. The public feed does not use this credential.'], [], 'calendar_feed', null);
    }

    public function dataQuality(): void
    {
        $auth = $this->gate('expenses.read');
        if ($auth === null) {
            return;
        }
        $scan = (new \App\Services\OperationalWorkspaceService())->scanDataQuality((int) $auth['client']['created_by']);
        $this->finish($auth, 200, true, $scan, [], 'data_quality', null);
    }

    public function salesIntakes(): void
    {
        $auth = $this->gate('sales_intake.read');
        if ($auth === null) {
            return;
        }
        $rows = (new \App\Services\SalesIntakeService())->page(25, 0);
        $this->finish($auth, 200, true, ['intakes' => $rows], [], 'sales_intake', null);
    }

    public function salesIntake(string $id): void
    {
        $auth = $this->gate('sales_intake.read');
        if ($auth === null) {
            return;
        }
        $opened = (new \App\Services\SalesIntakeService())->workspace((int) $id, (int) $auth['client']['created_by']);
        if ($opened['intake'] === null) {
            $this->finish($auth, 404, false, null, ['intake' => 'That enquiry was not found.'], 'sales_intake', (int) $id);

            return;
        }
        unset($opened['intake']['customer_budget']);
        $this->finish($auth, 200, true, ['intake' => $opened['intake']], [], 'sales_intake', (int) $id);
    }

    public function salesIntakeAnalyse(string $id): void
    {
        $auth = $this->gate('sales_intake.write');
        if ($auth === null) {
            return;
        }
        $result = (new \App\Services\SalesIntakeService())->analyse((int) $id, (int) $auth['client']['created_by']);
        $ok = $result['errors'] === [];
        $this->finish($auth, $ok ? 200 : 422, $ok, ['cached' => $result['cached']], $result['errors'], 'sales_intake', (int) $id);
    }

    public function salesIntakeSection(string $id, string $section): void
    {
        $auth = $this->gate('sales_intake.read');
        if ($auth === null) {
            return;
        }
        $opened = (new \App\Services\SalesIntakeService())->workspace((int) $id, (int) $auth['client']['created_by']);
        if ($opened['intake'] === null) {
            $this->finish($auth, 404, false, null, ['intake' => 'That enquiry was not found.'], 'sales_intake', (int) $id);

            return;
        }
        $allowed = ['requirements' => 'fields', 'matches' => 'matches', 'questions' => 'questions', 'estimate' => 'estimate_id', 'quote' => 'quote_id'];
        if (!isset($allowed[$section])) {
            $this->finish($auth, 404, false, null, ['section' => 'That section was not found.'], 'sales_intake', (int) $id);

            return;
        }
        $key = $allowed[$section];
        $payload = in_array($key, ['estimate_id', 'quote_id'], true)
            ? [$key => $opened['intake'][$key] ?? null]
            : [$section => $opened['intake'][$key] ?? []];
        $this->finish($auth, 200, true, $payload, [], 'sales_intake', (int) $id);
    }

    /**
     * @return array{client: array<string, mixed>, scopes: list<string>}|null
     */
    private function gate(string $scope): ?array
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $auth = (new ApiClientService())->authenticate($header);
        if ($auth === null) {
            $this->send(401, false, null, ['auth' => 'The API credential was not accepted.']);

            return null;
        }
        $limit = (int) \App\Services\SettingsService::get('api_rate_per_minute', '60');
        if (!(new RateLimiter())->allow('api:' . $auth['client']['id'], max(1, $limit), 60)) {
            $this->finish($auth, 429, false, null, ['rate' => 'Too many requests.'], null, null);

            return null;
        }
        if (!(new ApiClientService())->allows($auth['scopes'], $scope)) {
            $this->finish($auth, 403, false, null, ['scope' => 'This client cannot call that resource.'], null, null);

            return null;
        }

        return $auth;
    }

    /**
     * @param array{client: array<string, mixed>, scopes: list<string>} $auth
     * @param array<string, string> $errors
     */
    private function finish(array $auth, int $status, bool $success, ?array $data, array $errors, ?string $entity, ?int $entityId): void
    {
        (new ForecastRepository())->logApi([
            'api_client_id' => (int) $auth['client']['id'],
            'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'action' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'status_code' => $status,
            'entity_type' => $entity,
            'entity_id' => $entityId,
        ]);
        $this->send($status, $success, $data, $errors);
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, string> $errors
     */
    private function send(int $status, bool $success, ?array $data, array $errors): void
    {
        json_response([
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
            'meta' => ['version' => 'v1'],
        ], $status);
    }
}

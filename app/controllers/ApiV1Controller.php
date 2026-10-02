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

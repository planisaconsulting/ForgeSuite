<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\View;
use App\Repositories\AssetRepository;
use App\Services\AssetAccess;
use App\Services\AssetComponentService;
use App\Services\AssetLifecycleService;
use App\Services\AssetService;
use App\Services\InspectionService;
use App\Services\AssetMaintenanceService;
use App\Services\RateLimiter;
use App\Services\ServiceRequestService;
use App\Services\SettingsService;
use App\Services\WarrantyService;

/**
 * Staff asset register, service inbox, and the public QR page.
 */
final class AssetController
{
    public function index(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            'customer_id' => (int) ($_GET['customer_id'] ?? 0),
            'project_id' => (int) ($_GET['project_id'] ?? 0),
            'type_id' => (int) ($_GET['type'] ?? 0),
        ];
        $repo = new AssetRepository();
        View::render('assets/index', [
            'title' => 'Assets',
            'activeNav' => 'assets',
            'rows' => $repo->search($filters, 50, max(0, (int) ($_GET['page'] ?? 0)) * 50),
            'filters' => $filters,
            'page' => max(0, (int) ($_GET['page'] ?? 0)),
            'types' => $repo->types(),
            'dashboard' => $repo->assetDashboard(),
            'byType' => $repo->countsByType(),
            'showCosts' => AssetAccess::canSeeCosts(),
            'canCreate' => can('assets.create'),
        ]);
    }

    public function createForm(): void
    {
        View::render('assets/form', [
            'title' => 'New asset',
            'activeNav' => 'assets',
            'types' => (new AssetRepository())->types(),
            'old' => [
                'customer_id' => (int) ($_GET['customer_id'] ?? 0),
                'project_id' => (int) ($_GET['project_id'] ?? 0),
                'project_site_id' => (int) ($_GET['project_site_id'] ?? 0),
                'status' => 'ACTIVE',
                'source' => 'SIGN_FORGE',
                'quantity' => '1',
                'track_mode' => 'INDIVIDUAL',
            ],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $result = (new AssetService())->create($_POST, (int) auth_user()['id']);
        if ($result['errors'] !== []) {
            View::render('assets/form', [
                'title' => 'New asset',
                'activeNav' => 'assets',
                'types' => (new AssetRepository())->types(),
                'old' => $_POST,
                'errors' => $result['errors'],
            ]);

            return;
        }
        if ($result['warnings'] !== []) {
            flash('error', implode(' ', $result['warnings']));
        }
        flash('success', 'Asset created.');
        redirect('/assets/' . $result['id']);
    }

    public function show(string $id): void
    {
        $asset = $this->asset($id);
        $repo = new AssetRepository();
        $assetId = (int) $asset['id'];
        $open = false;
        foreach ($repo->requestsForAsset($assetId) as $request) {
            if (!in_array((string) $request['status'], ['RESOLVED', 'CLOSED', 'CANCELLED'], true)) {
                $open = true;
            }
        }
        View::render('assets/show', [
            'title' => (string) $asset['asset_number'],
            'activeNav' => 'assets',
            'asset' => $asset,
            'health' => (new AssetService())->healthFor($asset, $open),
            'components' => $repo->components($assetId),
            'warranties' => (new WarrantyService())->withStatus($assetId, date('Y-m-d')),
            'claims' => $repo->claimsForAsset($assetId),
            'requests' => $repo->requestsForAsset($assetId),
            'inspections' => $repo->inspections($assetId),
            'events' => $repo->events($assetId),
            'locations' => $repo->locations($assetId),
            'plans' => $repo->assignments($assetId),
            'planOptions' => $repo->plans(),
            'jobs' => $repo->serviceJobs($assetId),
            'lifetime' => AssetAccess::canSeeCosts() ? (new AssetLifecycleService())->lifetime($assetId) : [],
            'showCosts' => AssetAccess::canSeeCosts(),
            'suggestions' => $asset['original_job_id'] !== null ? (new AssetService())->suggestionsForJob((int) $asset['original_job_id']) : [],
            'categories' => $repo->problemCategories(),
            'contact' => SettingsService::get('asset_label_contact', 'Sign-Forge service'),
        ]);
    }

    public function update(string $id): void
    {
        $asset = $this->asset($id);
        $errors = (new AssetService())->update((int) $asset['id'], $_POST, (int) ($_POST['version'] ?? 0), (int) auth_user()['id']);
        $this->back($errors, '/assets/' . $asset['id'], 'Asset saved.');
    }

    public function addComponent(string $id): void
    {
        $asset = $this->asset($id);
        $result = (new AssetComponentService())->add((int) $asset['id'], $_POST, (int) auth_user()['id']);
        $this->back($result['errors'], '/assets/' . $asset['id'], 'Component added.');
    }

    public function replaceComponent(string $id): void
    {
        $asset = $this->asset($id);
        $result = (new AssetComponentService())->replace((int) ($_POST['component_id'] ?? 0), $_POST, (int) auth_user()['id']);
        $this->back($result['errors'], '/assets/' . $asset['id'], 'Component replaced. The old part is still on the history.');
    }

    public function addWarranty(string $id): void
    {
        $asset = $this->asset($id);
        $result = (new WarrantyService())->add((int) $asset['id'], $_POST, (int) auth_user()['id']);
        $this->back($result['errors'], '/assets/' . $asset['id'], 'Warranty recorded.');
    }

    public function addClaim(string $id): void
    {
        $asset = $this->asset($id);
        $result = (new WarrantyService())->openClaim((int) $asset['id'], $_POST, (int) auth_user()['id']);
        $this->back($result['errors'], '/assets/' . $asset['id'], 'Warranty claim opened. It is not approved yet.');
    }

    public function label(string $id): void
    {
        if (!can('assets.generate_labels')) {
            deny_access('You cannot print asset labels.');
        }
        $asset = $this->asset($id);
        View::render('assets/label', [
            'title' => 'Asset label',
            'asset' => $asset,
            'svg' => (new AssetService())->labelSvg((int) $asset['id']),
            'contact' => SettingsService::get('asset_label_contact', 'Sign-Forge service'),
        ], 'layouts/auth');
    }

    public function transfer(string $id): void
    {
        $asset = $this->asset($id);
        $errors = (new AssetService())->transfer((int) $asset['id'], $_POST, (int) auth_user()['id']);
        $this->back($errors, '/assets/' . $asset['id'], 'Asset location updated. The previous location stays in the history.');
    }

    public function remove(string $id): void
    {
        $asset = $this->asset($id);
        $errors = (new AssetService())->remove((int) $asset['id'], $_POST, (int) auth_user()['id']);
        $this->back($errors, '/assets/' . $asset['id'], 'Asset marked removed. It was not deleted.');
    }

    public function importForm(): void
    {
        View::render('assets/import', [
            'title' => 'Import assets',
            'activeNav' => 'assets',
            'result' => null,
        ]);
    }

    public function import(): void
    {
        $csv = (string) ($_POST['csv'] ?? '');
        if (!empty($_FILES['file']['tmp_name'])) {
            $csv = (string) file_get_contents((string) $_FILES['file']['tmp_name']);
        }
        $result = (new AssetService())->importCsv($csv, (int) auth_user()['id']);
        View::render('assets/import', [
            'title' => 'Import assets',
            'activeNav' => 'assets',
            'result' => $result,
        ]);
    }

    public function serviceIndex(): void
    {
        $repo = new AssetRepository();
        View::render('service/index', [
            'title' => 'Service',
            'activeNav' => 'service',
            'rows' => $repo->requests([
                'q' => trim((string) ($_GET['q'] ?? '')),
                'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            ]),
            'cards' => $repo->requestCards(),
            'filters' => [
                'q' => trim((string) ($_GET['q'] ?? '')),
                'status' => strtoupper(trim((string) ($_GET['status'] ?? ''))),
            ],
        ]);
    }

    public function serviceCreate(): void
    {
        $result = (new ServiceRequestService())->create($_POST, (int) auth_user()['id']);
        if ($result['id'] === null) {
            flash('error', implode(' ', $result['errors']));
            redirect('/service');
        }
        flash('success', 'Service request logged. Warranty is only a candidate until someone confirms it.');
        redirect('/service/' . $result['id']);
    }

    public function serviceShow(string $id): void
    {
        $request = (new AssetRepository())->request(route_id($id));
        if ($request === null) {
            abort_not_found('That service request was not found.');
        }
        $asset = $request['asset_id'] !== null ? (new AssetRepository())->find((int) $request['asset_id']) : null;
        View::render('service/show', [
            'title' => (string) $request['request_number'],
            'activeNav' => 'service',
            'request' => $request,
            'asset' => $asset,
            'warranties' => $asset !== null ? (new WarrantyService())->withStatus((int) $asset['id'], date('Y-m-d')) : [],
            'history' => $asset !== null ? (new AssetRepository())->events((int) $asset['id'], 30) : [],
            'components' => $asset !== null ? (new AssetRepository())->components((int) $asset['id']) : [],
            'times' => (new ServiceRequestService())->responseTimes((int) $request['id']),
            'showCosts' => AssetAccess::canSeeCosts(),
        ]);
    }

    public function serviceUpdate(string $id): void
    {
        $request = (new AssetRepository())->request(route_id($id));
        if ($request === null) {
            abort_not_found('That service request was not found.');
        }
        $errors = (new ServiceRequestService())->update((int) $request['id'], $_POST, (int) ($_POST['version'] ?? 0), (int) auth_user()['id']);
        $this->back($errors, '/service/' . $request['id'], 'Service request updated.');
    }

    public function serviceJob(string $id): void
    {
        $request = (new AssetRepository())->request(route_id($id));
        if ($request === null) {
            abort_not_found('That service request was not found.');
        }
        $result = (new ServiceRequestService())->openJob((int) $request['id'], $_POST, (int) auth_user()['id']);
        if ($result['id'] === null) {
            flash('error', implode(' ', $result['errors']));
            redirect('/service/' . $request['id']);
        }
        flash('success', 'Service job opened.');
        redirect('/jobs/' . $result['id']);
    }

    public function serviceSign(string $id): void
    {
        $request = (new AssetRepository())->request(route_id($id));
        if ($request === null) {
            abort_not_found('That service request was not found.');
        }
        $errors = (new ServiceRequestService())->signOff((int) $request['id'], $_POST, (int) ($_POST['version'] ?? $request['version']), (int) auth_user()['id']);
        $this->back($errors, '/service/' . $request['id'] . '/report', 'Service signed off.');
    }

    public function serviceReport(string $id): void
    {
        if (!can('service_reports.generate') && !can('service_requests.view')) {
            deny_access('You cannot open this service report.');
        }
        $report = (new ServiceRequestService())->report(route_id($id), false);
        if ($report === null) {
            abort_not_found('That service report was not found.');
        }
        View::render('service/report', [
            'title' => 'Service report',
            'report' => $report,
        ], 'layouts/auth');
    }

    public function maintenance(): void
    {
        $repo = new AssetRepository();
        $days = (int) ($_GET['days'] ?? 30);
        if (!in_array($days, [30, 60, 90, 180], true)) {
            $days = 30;
        }
        View::render('service/maintenance', [
            'title' => 'Maintenance',
            'activeNav' => 'service',
            'plans' => $repo->plans(),
            'due' => (new AssetMaintenanceService())->forecast($days),
            'days' => $days,
            'types' => $repo->types(),
        ]);
    }

    public function savePlan(): void
    {
        $result = (new AssetMaintenanceService())->createPlan($_POST, (int) auth_user()['id']);
        $this->back($result['errors'], '/service/maintenance', 'Maintenance plan saved.');
    }

    public function warranties(): void
    {
        View::render('service/warranties', [
            'title' => 'Warranties',
            'activeNav' => 'service',
            'dashboard' => (new AssetRepository())->warrantyDashboard(),
            'expiring' => (new AssetRepository())->expiringWarranties(date('Y-m-d', strtotime('+90 days'))),
            'showCosts' => AssetAccess::canSeeCosts(),
        ]);
    }

    public function report(string $slug): void
    {
        $report = (new AssetLifecycleService())->report($slug);
        if ($report['columns'] === [] && $slug !== 'register') {
            abort_not_found('That report was not found.');
        }
        View::render('service/table', [
            'title' => (string) $report['title'],
            'activeNav' => 'service',
            'report' => $report,
        ]);
    }

    public function publicShow(string $token): void
    {
        if (!(new RateLimiter())->allow('qr:' . substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), 30, 60)) {
            http_response_code(429);
            View::render('assets/public', [
                'title' => 'Service',
                'card' => null,
                'error' => 'Too many scans from this address. Wait a minute and try again.',
                'token' => '',
            ], 'layouts/auth');

            return;
        }
        $card = (new AssetService())->publicCard($token);
        View::render('assets/public', [
            'title' => $card === null ? 'Asset not found' : 'Report a problem',
            'card' => $card,
            'error' => $card === null ? 'That code is not a Sign-Forge asset.' : '',
            'token' => $card === null ? '' : $token,
            'contact' => SettingsService::get('asset_label_contact', 'Sign-Forge service'),
        ], 'layouts/auth');
    }

    public function publicReport(string $token): void
    {
        if (!(new RateLimiter())->allow('qr-post:' . substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), 10, 3600)) {
            flash('error', 'Too many reports from this address.');
            redirect('/service/asset/' . rawurlencode($token));
        }
        if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
            flash('success', 'Report received.');
            redirect('/service/asset/' . rawurlencode($token));
        }
        $asset = (new AssetRepository())->findByToken($token);
        if ($asset === null) {
            flash('error', 'That code is not a Sign-Forge asset.');
            redirect('/service/asset/' . rawurlencode($token));
        }
        $result = (new ServiceRequestService())->create([
            'asset_id' => (int) $asset['id'],
            'customer_id' => (int) $asset['customer_id'],
            'reported_by' => (string) ($_POST['reported_by'] ?? ''),
            'contact_detail' => (string) ($_POST['contact_detail'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
            'problem_category' => (string) ($_POST['problem_category'] ?? 'OTHER'),
            'source' => 'QR',
            'priority' => 'NORMAL',
        ], 0, true);
        if ($result['id'] === null) {
            flash('error', implode(' ', $result['errors']));
            redirect('/service/asset/' . rawurlencode($token));
        }
        flash('success', 'Report received. A person will confirm any warranty.');
        redirect('/service/asset/' . rawurlencode($token));
    }

    public function scan(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if (str_starts_with($token, 'SFASSET:')) {
            $token = substr($token, 8);
        }
        $asset = (new AssetRepository())->findByToken($token);
        if ($asset === null) {
            flash('error', 'That code is not a Sign-Forge asset.');
            redirect('/assets');
        }
        redirect('/assets/' . $asset['id']);
    }

    public function mobile(): void
    {
        $repo = new AssetRepository();
        View::render('mobile/service', [
            'title' => 'My service jobs',
            'rows' => $repo->requests(['status' => 'IN_PROGRESS']),
        ]);
    }

    public function mobileAsset(string $id): void
    {
        $pack = (new AssetLifecycleService())->fieldPack(route_id($id));
        if ($pack === []) {
            abort_not_found('That asset was not found.');
        }
        View::render('mobile/service_asset', [
            'title' => (string) $pack['asset_number'],
            'pack' => $pack,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function asset(string $id): array
    {
        $asset = (new AssetRepository())->find(route_id($id));
        if ($asset === null || $asset['archived_at'] !== null && !can('assets.archive')) {
            abort_not_found('That asset was not found.');
        }

        return $asset;
    }

    /**
     * @param array<string, string> $errors
     */
    private function back(array $errors, string $path, string $ok): void
    {
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
        } else {
            flash('success', $ok);
        }
        redirect($path);
    }
}

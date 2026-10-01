<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Decimal;
use App\Helpers\View;
use App\Repositories\EstimatingRepository;
use App\Services\CostStateService;
use App\Services\EstimateService;
use App\Services\InstallationEstimateService;
use App\Services\MachineEstimateService;
use App\Services\MaterialOptimisationService;
use App\Services\PricingIntelligenceService;
use App\Services\PricingMathService;
use App\Services\PricingRecommendationService;
use App\Services\QuantityBreakService;
use App\Services\SettingsService;
use App\Services\VehicleBrandingService;

/**
 * Internal estimating screens. Customer quotes are not changed here.
 */
final class EstimatingController
{
    public function index(): void
    {
        $repo = new EstimatingRepository();
        View::render('estimating/index', [
            'title' => 'Estimates',
            'rows' => $repo->search(trim((string) ($_GET['q'] ?? ''))),
            'term' => trim((string) ($_GET['q'] ?? '')),
        ]);
    }

    public function create(): void
    {
        View::render('estimating/form', ['title' => 'New estimate']);
    }

    public function show(string $id): void
    {
        $repo = new EstimatingRepository();
        $estimate = $repo->find((int) $id);
        if ($estimate === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Estimate not found']);

            return;
        }
        $userId = (int) auth_user()['id'];
        $intelligence = new PricingIntelligenceService();
        $similar = $estimate['recipe_id'] ? $intelligence->similar((int) $estimate['recipe_id'], $userId) : [];
        View::render('estimating/show', [
            'title' => (string) $estimate['estimate_number'],
            'estimate' => $estimate,
            'components' => $repo->components((int) $estimate['id']),
            'similar' => $similar,
        ]);
    }

    public function store(): void
    {
        $userId = (int) auth_user()['id'];
        $lines = [];
        $descriptions = $_POST['description'] ?? [];
        $quantities = $_POST['estimated_quantity'] ?? [];
        $costs = $_POST['unit_cost_snapshot'] ?? [];
        $types = $_POST['component_type'] ?? [];
        $formulas = $_POST['quantity_formula'] ?? [];
        if (is_array($descriptions)) {
            foreach ($descriptions as $index => $description) {
                if (trim((string) $description) === '' && trim((string) ($quantities[$index] ?? '')) === '') {
                    continue;
                }
                $lines[] = [
                    'description' => (string) $description,
                    'estimated_quantity' => (string) ($quantities[$index] ?? '0'),
                    'unit_cost_snapshot' => (string) ($costs[$index] ?? '0'),
                    'component_type' => (string) ($types[$index] ?? 'OTHER'),
                    'quantity_formula' => (string) ($formulas[$index] ?? ''),
                    'billable_quantity' => (string) ($quantities[$index] ?? '0'),
                    'estimated_cost' => (string) ($_POST['estimated_cost'][$index] ?? ''),
                ];
            }
        }
        $result = (new EstimateService())->save(null, $_POST, $lines, $userId);
        if (!$result['ok'] || $result['id'] === null) {
            flash('error', $result['error'] ?? 'The estimate was not saved.');
            redirect('/estimates/new');
        }
        flash('success', 'Estimate saved. Selling prices on quotes were not changed.');
        redirect('/estimates/' . $result['id']);
    }

    public function approve(string $id): void
    {
        $result = (new EstimateService())->approve((int) $id, (int) auth_user()['id']);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Estimate approved.' : (string) $result['error']);
        redirect('/estimates/' . $id);
    }

    public function sheets(): void
    {
        $input = $this->sheetInput();
        $result = null;
        $breaks = [];
        $machine = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $options = (new MaterialOptimisationService())->options($input, $this->offcuts(), []);
            $result = $options['sheet'];
            $manual = trim((string) ($_POST['manual_sheets'] ?? ''));
            if ($manual !== '' && is_array($result)) {
                $override = (new PricingIntelligenceService())->overrideYield((string) $result['sheets'], $manual, trim((string) ($_POST['override_note'] ?? '')), (int) auth_user()['id']);
                $result['override'] = $override;
            }
            $breaks = (new QuantityBreakService())->sheetBreaks(
                $input['sheet_width_mm'],
                $input['sheet_height_mm'],
                $input['part_width_mm'],
                $input['part_height_mm'],
                ['10', '25', '50', '100'],
                !empty($input['allow_rotation']),
                $input['kerf_mm'],
                $input['edge_margin_mm'],
                (string) ($_POST['sheet_cost'] ?? '0'),
                (string) ($_POST['setup_cost'] ?? '0'),
                (string) ($_POST['markup_percent'] ?? '50')
            );
            $machine = (new MachineEstimateService())->estimate(
                (string) ($_POST['setup_minutes'] ?? '0'),
                (string) ($_POST['run_minutes'] ?? '0'),
                $input['quantity'],
                (string) ($_POST['batch_size'] ?? ''),
                (string) ($_POST['machine_rate'] ?? '0')
            );
            if (isset($_POST['save_estimate']) && is_array($result) && !empty($result['fits'])) {
                $this->saveYieldEstimate('SHEET', $result, (string) ($_POST['sheet_cost'] ?? '0'), $machine);
            }
        }
        View::render('estimating/sheets', [
            'title' => 'Sheet optimiser',
            'input' => $input,
            'result' => $result,
            'breaks' => $breaks,
            'machine' => $machine,
            'offcuts' => $_SERVER['REQUEST_METHOD'] === 'POST' ? (new MaterialOptimisationService())->options($input, $this->offcuts(), [])['offcuts'] : [],
        ]);
    }

    public function rolls(): void
    {
        $input = $this->sheetInput();
        $input['kind'] = 'ROLL';
        $result = null;
        $options = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $widths = array_filter(array_map('trim', explode(',', (string) ($_POST['roll_widths'] ?? '1050,1370,1520'))));
            $rolls = [];
            foreach ($widths as $width) {
                $rolls[] = ['width_mm' => $width, 'unit_cost' => (string) ($_POST['roll_cost'] ?? '0'), 'label' => $width . ' mm'];
            }
            $built = (new MaterialOptimisationService())->options($input, [], $rolls);
            $result = $built['roll'];
            $options = $built['roll_options'];
        }
        View::render('estimating/rolls', [
            'title' => 'Roll optimiser',
            'input' => $input,
            'result' => $result,
            'options' => $options,
        ]);
    }

    public function installation(): void
    {
        $repo = new EstimatingRepository();
        $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $access = $repo->accessLevel(strtoupper((string) ($_POST['access_category'] ?? 'STANDARD')));
            $height = $repo->heightCategory(strtoupper((string) ($_POST['height_category'] ?? 'GROUND')));
            $posted = $_POST;
            $posted['access_multiplier'] = (string) ($access['multiplier'] ?? '1');
            $posted['access_category'] = (string) ($access['code'] ?? 'STANDARD');
            $posted['equipment_cost'] = (string) ($height['equipment_cost'] ?? ($_POST['equipment_cost'] ?? '0'));
            $posted['height_category'] = (string) ($height['name'] ?? 'Ground level');
            if (trim((string) ($_POST['rate_per_km'] ?? '')) === '') {
                $posted['rate_per_km'] = (string) SettingsService::get('travel_rate_per_km', '0');
            }
            if (trim((string) ($_POST['hourly_rate'] ?? '')) === '') {
                $posted['hourly_rate'] = (string) SettingsService::get('default_labour_hourly_cost', '0');
            }
            $result = (new InstallationEstimateService())->estimate($posted);
            unset($posted['total_cost']);
        }
        View::render('estimating/installation', [
            'title' => 'Installation estimator',
            'access' => $repo->accessLevels(),
            'heights' => $repo->heightCategories(),
            'result' => $result,
        ]);
    }

    public function vehicles(): void
    {
        $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $posted = $_POST;
            if (trim((string) ($posted['hourly_rate'] ?? '')) === '') {
                $posted['hourly_rate'] = (string) SettingsService::get('default_labour_hourly_cost', '0');
            }
            $result = (new VehicleBrandingService())->estimate($posted);
        }
        View::render('estimating/vehicles', [
            'title' => 'Vehicle branding estimator',
            'categories' => VehicleBrandingService::CATEGORIES,
            'areas' => VehicleBrandingService::AREAS,
            'removal' => VehicleBrandingService::REMOVAL_HOURS,
            'result' => $result,
        ]);
    }

    public function simulator(): void
    {
        $math = new PricingMathService();
        $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['apply'])) {
            $cost = (string) ($_POST['cost'] ?? '0');
            $waste = (string) ($_POST['waste_percent'] ?? '0');
            $costed = Decimal::mul($cost, Decimal::add('1', Decimal::div(Decimal::isNumeric($waste) ? $waste : '0', '100', Decimal::CALC_SCALE), Decimal::CALC_SCALE), Decimal::CALC_SCALE);
            $mode = (string) ($_POST['price_mode'] ?? 'MARGIN');
            $rate = (string) ($_POST['rate_percent'] ?? '0');
            $priced = $mode === 'MARKUP' ? $math->sellFromMarkup($costed, $rate) : $math->sellFromMargin($costed, $rate);
            $sell = $priced['ok'] ? $priced['sell'] : '0.00';
            $discount = $math->discountImpact($costed, $sell, (string) ($_POST['discount'] ?? '0'));
            $result = [
                'cost' => Decimal::money($costed),
                'sell' => $sell,
                'mode' => $mode,
                'error' => $priced['error'],
                'margin' => $math->marginPercent($sell, Decimal::money($costed)),
                'discount' => $discount,
                'saved' => false,
            ];
        }
        View::render('estimating/simulator', ['title' => 'What-if pricing', 'result' => $result]);
    }

    public function intelligence(): void
    {
        View::render('estimating/intelligence', [
            'title' => 'Pricing intelligence',
            'health' => (new PricingIntelligenceService())->health((int) auth_user()['id']),
        ]);
    }

    public function recommendations(): void
    {
        View::render('estimating/recommendations', [
            'title' => 'Pricing recommendations',
            'rows' => (new EstimatingRepository())->recommendations(''),
        ]);
    }

    public function recommendationAct(string $id): void
    {
        $service = new PricingRecommendationService();
        $userId = (int) auth_user()['id'];
        $action = (string) ($_POST['action'] ?? 'reject');
        $result = $action === 'accept'
            ? $service->accept((int) $id, (string) ($_POST['value'] ?? ''), $userId)
            : $service->reject((int) $id, $userId);
        flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Recommendation updated. Historical quotes were not changed.' : (string) $result['error']);
        redirect('/estimating/recommendations');
    }

    public function variance(): void
    {
        $report = (new PricingIntelligenceService())->estimateVersusActual((int) auth_user()['id']);
        View::render('estimating/variance', ['title' => 'Estimate vs actual', 'report' => $report]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sheetInput(): array
    {
        return [
            'kind' => 'SHEET',
            'sheet_width_mm' => (string) ($_POST['sheet_width_mm'] ?? '2440'),
            'sheet_height_mm' => (string) ($_POST['sheet_height_mm'] ?? '1220'),
            'part_width_mm' => (string) ($_POST['part_width_mm'] ?? '600'),
            'part_height_mm' => (string) ($_POST['part_height_mm'] ?? '400'),
            'quantity' => (string) ($_POST['quantity'] ?? '10'),
            'kerf_mm' => (string) ($_POST['kerf_mm'] ?? '0'),
            'edge_margin_mm' => (string) ($_POST['edge_margin_mm'] ?? '0'),
            'allow_rotation' => isset($_POST['allow_rotation']),
            'direction_sensitive' => isset($_POST['direction_sensitive']),
            'roll_width_mm' => (string) ($_POST['roll_width_mm'] ?? '1370'),
            'horizontal_spacing_mm' => (string) ($_POST['horizontal_spacing_mm'] ?? '0'),
            'vertical_spacing_mm' => (string) ($_POST['vertical_spacing_mm'] ?? '0'),
            'product_id' => (int) ($_POST['product_id'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offcuts(): array
    {
        $productId = (int) ($_POST['product_id'] ?? 0);
        if ($productId < 1) {
            return [];
        }

        return (new EstimatingRepository())->availableOffcuts($productId);
    }

    /**
     * @param array<string, mixed> $layout
     * @param array<string, mixed> $machine
     */
    private function saveYieldEstimate(string $type, array $layout, string $unitCost, array $machine): void
    {
        $sheets = (string) ($layout['override']['manual'] ?? $layout['sheets']);
        if (isset($layout['override']) && empty($layout['override']['ok'])) {
            flash('error', (string) $layout['override']['error']);

            return;
        }
        $components = [[
            'description' => 'Sheet material',
            'component_type' => 'MATERIAL',
            'estimated_quantity' => $sheets,
            'billable_quantity' => (string) ($layout['used_area_m2'] ?? '0'),
            'unit_cost_snapshot' => $unitCost,
            'unit' => 'sheet',
            'calculation_method' => 'SHEET_YIELD',
            'manual_override' => !empty($layout['override']['manual']),
            'override_reason' => (string) ($_POST['override_note'] ?? ''),
            'details' => [
                'calculated_sheets' => $layout['sheets'],
                'manual_sheets' => $layout['override']['manual'] ?? null,
                'waste_percent' => $layout['waste_percent'],
                'label' => $layout['label'],
            ],
        ]];
        if (Decimal::cmp((string) $machine['estimated_cost'], '0') > 0) {
            $components[] = [
                'description' => 'Machine time',
                'component_type' => 'MACHINE',
                'estimated_quantity' => '1',
                'unit_cost_snapshot' => (string) $machine['estimated_cost'],
                'server_cost' => (string) $machine['estimated_cost'],
                'unit' => 'job',
                'calculation_method' => 'MACHINE',
                'details' => $machine,
            ];
        }
        $saved = (new EstimateService())->save(null, ['estimate_type' => $type, 'notes' => 'Sheet optimiser'], $components, (int) auth_user()['id']);
        if ($saved['ok'] && $saved['id'] !== null) {
            flash('success', 'Estimate stored from the calculated yield.');
            redirect('/estimates/' . $saved['id']);
        }
        flash('error', $saved['error'] ?? 'The estimate was not saved.');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\InventoryRepository;
use App\Repositories\SignageRepository;

/**
 * Turns an estimator result into a priced snapshot.
 * Selling price uses the same margin rule as other internal estimates.
 * The browser does not supply the cost.
 */
final class SignEstimateService
{
    public function __construct(
        private readonly SignageRepository $repo = new SignageRepository(),
        private readonly VehicleWrapEstimator $wrap = new VehicleWrapEstimator(),
        private readonly ChannelLetterEstimator $letters = new ChannelLetterEstimator(),
        private readonly LightboxEstimator $lightbox = new LightboxEstimator(),
        private readonly PylonEstimator $pylon = new PylonEstimator(),
        private readonly PanelFrameEstimator $panel = new PanelFrameEstimator(),
        private readonly CompatibilityRuleService $rules = new CompatibilityRuleService(),
        private readonly FormulaService $formulas = new FormulaService(),
        private readonly InstallationEstimateService $installation = new InstallationEstimateService(),
        private readonly MachineEstimateService $machines = new MachineEstimateService(),
        private readonly PricingMathService $pricing = new PricingMathService(),
        private readonly EstimateService $estimates = new EstimateService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, result: array<string, mixed>}
     */
    public function run(string $type, array $input, int $userId, bool $save): array
    {
        if (!can('estimators.use')) {
            return ['errors' => ['_form' => 'You cannot run an estimator.'], 'id' => null, 'result' => []];
        }
        $type = strtoupper($type);
        if (array_key_exists('illuminated', $input)) {
            $input['illuminated'] = SignMath::yes($input['illuminated']) ? 'YES' : 'NO';
        }
        if (($input['illuminated'] ?? '') === 'YES' && (int) ($input['led_profile_id'] ?? 0) > 0 && trim((string) ($input['electrical_components'] ?? '')) === '') {
            $input['electrical_components'] = 'YES';
        }
        $spec = null;
        $specId = (int) ($input['specification_id'] ?? 0);
        if ($specId > 0) {
            $spec = $this->repo->specification($specId);
            if ($spec === null) {
                return ['errors' => ['specification_id' => 'That specification was not found.'], 'id' => null, 'result' => []];
            }
            if ((string) $spec['status'] !== 'APPROVED' && !can('specifications.edit')) {
                return ['errors' => ['specification_id' => 'Only an approved specification is offered for a normal estimate.'], 'id' => null, 'result' => []];
            }
        }
        $led = (int) ($input['led_profile_id'] ?? 0) > 0 ? $this->repo->profile((int) $input['led_profile_id']) : null;
        $psu = (int) ($input['psu_profile_id'] ?? 0) > 0 ? $this->repo->profile((int) $input['psu_profile_id']) : null;
        $checked = null;
        if ($spec !== null) {
            $checked = $this->rules->evaluate($this->repo->rules($specId), $input);
            if ($checked['blocked']) {
                $reason = trim((string) ($input['override_reason'] ?? ''));
                if ($reason === '' || !can('estimators.override')) {
                    return ['errors' => ['_form' => $checked['messages'][0] ?? 'That combination is not allowed on this specification.'], 'id' => null, 'result' => []];
                }
            }
        }
        try {
            $result = $this->dispatch($type, $input, $spec, $led, $psu);
        } catch (\InvalidArgumentException $e) {
            return ['errors' => ['_form' => $e->getMessage()], 'id' => null, 'result' => []];
        }
        if (($result['blocked'] ?? null) !== null) {
            return ['errors' => ['_form' => (string) $result['blocked']], 'id' => null, 'result' => $result];
        }
        if ($spec !== null && $checked !== null) {
            if ($checked['blocked']) {
                $result['warnings'][] = 'Hard rule overridden: ' . ($checked['messages'][0] ?? 'That combination is not allowed on this specification.');
                $result['override_reason'] = trim((string) ($input['override_reason'] ?? ''));
            }
            $result['warnings'] = array_merge($result['warnings'], $checked['warnings']);
            $result['reviews']['engineering'] = $result['reviews']['engineering'] || $checked['engineering'] || (int) $spec['engineering_review_required'] === 1;
            $result['reviews']['electrical'] = $result['reviews']['electrical'] || $checked['electrical'] || (int) $spec['electrical_review_required'] === 1;
            $this->applySpecificationLabour($result, $spec, $input);
        }
        $this->price($result, $input);
        $result['disclaimers'] = [EngineeringLimits::STRUCTURAL, EngineeringLimits::ELECTRICAL];
        if (!$save) {
            return ['errors' => [], 'id' => null, 'result' => $result];
        }
        $review = $result['reviews']['engineering'] || $result['reviews']['technical'] || $result['outside'];
        $id = $this->persist($type, $input, $spec, $result, $userId, $review);
        BusinessEventDispatcher::emit('ESTIMATE_CALCULATED', 'SIGN_CALCULATION', $id, $userId, ['type' => $type]);
        if ($review) {
            (new ReviewQueueService())->add('TECHNICAL_ESTIMATE', 'SIGN_CALCULATION', $id, 'Technical estimate review', 'ESTIMATOR', 'Review the manufacturing estimate before it is quoted.', null);
            BusinessEventDispatcher::emit('TECHNICAL_REVIEW_REQUIRED', 'SIGN_CALCULATION', $id, $userId, ['type' => $type]);
        }

        return ['errors' => [], 'id' => $id, 'result' => $result];
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     * @return array{errors: array<string, string>, a: array<string, mixed>, b: array<string, mixed>}
     */
    public function compare(string $type, array $left, array $right, int $userId): array
    {
        $a = $this->run($type, $left, $userId, false);
        $b = $this->run($type, $right, $userId, false);

        return [
            'errors' => $a['errors'] + $b['errors'],
            'a' => $a['result'],
            'b' => $b['result'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function override(int $id, string $field, string $value, string $reason, int $userId): array
    {
        if (!can('estimators.override')) {
            return ['_form' => 'You cannot override a calculation.'];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['reason' => 'A reason is required.'];
        }
        if (!Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
            return ['value' => 'Enter a quantity of zero or more.'];
        }
        $row = $this->repo->calculation($id);
        if ($row === null || in_array((string) $row['status'], ['QUOTED', 'CONVERTED'], true)) {
            return ['_form' => 'A quoted or converted estimate is not changed. Start a new calculation.'];
        }
        $output = json_decode((string) $row['output_json'], true);
        if (!is_array($output)) {
            return ['_form' => 'That calculation could not be read.'];
        }
        $found = false;
        foreach ($output['materials'] as &$material) {
            if ((string) ($material['role'] ?? '') !== $field) {
                continue;
            }
            $original = (string) $material['quantity'];
            $material['original_quantity'] = $material['original_quantity'] ?? $original;
            $material['quantity'] = Decimal::round($value, 4);
            $material['overridden'] = true;
            $found = true;
            $this->repo->insertOverride([
                'calculation_id' => $id,
                'field_key' => $field,
                'calculated_value' => (string) $material['original_quantity'],
                'overridden_value' => Decimal::round($value, 4),
                'reason' => mb_substr($reason, 0, 255),
                'created_by' => $userId,
            ]);
            break;
        }
        unset($material);
        if (!$found) {
            return ['field' => 'That material line was not on the calculation.'];
        }
        $this->price($output, json_decode((string) $row['input_json'], true) ?: []);
        $this->repo->replaceOutput(
            $id,
            json_encode($output, JSON_THROW_ON_ERROR),
            (string) $output['costs']['material'],
            (string) $output['costs']['total'],
            (string) $output['costs']['sell'],
            (string) $output['costs']['profit'],
            $output['costs']['margin']
        );
        $this->audit->record('sign_calculation', $id, 'ESTIMATE_OVERRIDE', null, ['field' => $field, 'reason' => $reason], $userId);
        BusinessEventDispatcher::emit('ESTIMATE_OVERRIDE', 'SIGN_CALCULATION', $id, $userId, ['field' => $field]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function addToQuote(int $id, int $quoteId, int $version, int $userId): array
    {
        $row = $this->repo->calculation($id);
        if ($row === null) {
            return ['_form' => 'That calculation was not found.'];
        }
        if ((int) $row['technical_review_required'] === 1 && (string) $row['status'] === 'REVIEW') {
            return ['_form' => 'This estimate is waiting for technical review.'];
        }
        $output = json_decode((string) $row['output_json'], true);
        $text = is_array($output) ? (string) ($output['customer_text'] ?? 'Signage') : 'Signage';
        $added = (new QuoteService())->addCustomLine($quoteId, [
            'customer_description' => $text,
            'quantity' => '1',
            'unit_cost' => (string) $row['total_cost'],
            'final_sell_price' => (string) $row['sell_price'],
            'override_reason' => 'Estimator selling price from the company target margin',
        ], $version, $userId);
        if ($added['errors'] !== []) {
            return ['_form' => (string) (is_array($added['errors']) ? ($added['errors'][0] ?? 'The quote line was not added.') : $added['errors'])];
        }
        $this->repo->setQuote($id, $quoteId);

        return [];
    }

    public function stampJobFromQuote(int $jobId, int $quoteId): void
    {
        $row = $this->repo->calculationForQuote($quoteId);
        if ($row === null) {
            return;
        }
        $this->repo->stampJob($jobId, $this->jobSnapshot($row));
        $this->repo->setJob((int) $row['id'], $jobId);
    }

    /**
     * @return array<string, string>
     */
    public function completeReview(int $id, int $userId): array
    {
        if (!can('estimators.technical_review')) {
            return ['_form' => 'You cannot complete a technical review.'];
        }
        $row = $this->repo->calculation($id);
        if ($row === null) {
            return ['_form' => 'That calculation was not found.'];
        }
        $this->repo->setStatus($id, 'CALCULATED');
        $this->audit->record('sign_calculation', $id, 'TECHNICAL_REVIEW_COMPLETED', null, ['status' => 'CALCULATED'], $userId);
        BusinessEventDispatcher::emit('TECHNICAL_REVIEW_COMPLETED', 'SIGN_CALCULATION', $id, $userId, []);

        return [];
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array{standard: list<int>, exceptions: list<array<string, mixed>>}
     */
    public function rollout(int $specificationId, array $sites, int $userId): array
    {
        $standard = [];
        $exceptions = [];
        foreach ($sites as $site) {
            $input = $site;
            $input['specification_id'] = $specificationId;
            $ran = $this->run('LIGHTBOX', $input, $userId, true);
            $label = (string) ($site['label'] ?? '');
            if ($ran['errors'] !== [] || !empty($ran['result']['outside'])) {
                $exceptions[] = ['label' => $label, 'message' => (string) ($ran['errors']['_form'] ?? 'Outside the specification.')];
                continue;
            }
            $standard[] = (int) $ran['id'];
        }

        return ['standard' => $standard, 'exceptions' => $exceptions];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $spec
     * @param array<string, mixed>|null $led
     * @param array<string, mixed>|null $psu
     * @return array<string, mixed>
     */
    private function dispatch(string $type, array $input, ?array $spec, ?array $led, ?array $psu): array
    {
        if ($type === 'VEHICLE_WRAP') {
            $templateId = (int) ($input['template_id'] ?? 0);
            if ($templateId > 0) {
                $template = $this->repo->template($templateId);
                if ($template !== null && (int) $template['verified'] === 1 && trim((string) ($input['measurement_source'] ?? '')) === '') {
                    $input['measurement_source'] = 'VERIFIED_TEMPLATE';
                }
                if ($template !== null && (int) $template['verified'] !== 1) {
                    $input['measurement_source'] = (string) ($input['measurement_source'] ?? 'SUPPLIER_TEMPLATE');
                }
            }

            return $this->wrap->calculate($input, $this->panelsFor($input));
        }
        if ($type === 'CHANNEL_LETTER') {
            if ($spec !== null && ($input['allowance_percent'] ?? '') === '') {
                $input['allowance_percent'] = (string) $spec['manufacturing_allowance_percent'];
            }

            return $this->letters->calculate($input, $led, $psu);
        }
        if ($type === 'LIGHTBOX') {
            return $this->lightbox->calculate($input, $spec, $led, $psu);
        }
        if ($type === 'PYLON') {
            return $this->pylon->calculate($input, $spec, $led, $psu);
        }
        if ($type === 'PANEL_FRAME') {
            $product = (int) ($input['panel_product_id'] ?? 0) > 0 ? $this->repo->product((int) $input['panel_product_id']) : null;

            return $this->panel->calculate($input, $spec, $product);
        }

        throw new \InvalidArgumentException('Choose an estimator.');
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array<string, mixed>>
     */
    private function panelsFor(array $input): array
    {
        $templateId = (int) ($input['template_id'] ?? 0);
        if ($templateId > 0) {
            $template = $this->repo->template($templateId);
            if ($template !== null && (int) $template['verified'] === 1 && ($input['measurement_source'] ?? '') === '') {
                $input['measurement_source'] = 'VERIFIED_TEMPLATE';
            }
            $panels = $this->repo->panels($templateId);
            if ((int) ($template['verified'] ?? 0) !== 1) {
                foreach ($panels as &$panel) {
                    $panel['notes'] = 'Unverified template';
                }
                unset($panel);
            }

            return $panels;
        }
        $manual = [];
        foreach ((array) ($input['manual_panels'] ?? []) as $panel) {
            if (!is_array($panel)) {
                continue;
            }
            $width = (string) ($panel['width_mm'] ?? '0');
            $height = (string) ($panel['height_mm'] ?? '0');
            $manual[] = [
                'panel_code' => strtoupper((string) ($panel['panel_code'] ?? 'CUSTOM')),
                'width_mm' => $width,
                'height_mm' => $height,
                'area_m2' => SignMath::areaM2($width, $height),
                'bleed_mm' => (string) ($panel['bleed_mm'] ?? '0'),
                'overlap_mm' => (string) ($panel['overlap_mm'] ?? '0'),
            ];
        }

        return $manual;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $input
     */
    private function applySpecificationLabour(array &$result, array $spec, array $input): void
    {
        $vars = SignMath::formulaVariables([
            'W' => (string) ($result['geometry']['width_mm'] ?? $input['width'] ?? '0'),
            'H' => (string) ($result['geometry']['height_mm'] ?? $input['height'] ?? '0'),
            'D' => (string) ($result['geometry']['depth_mm'] ?? $input['depth'] ?? '0'),
            'Q' => (string) ($input['quantity'] ?? '1'),
            'AREA' => (string) ($result['geometry']['face_m2'] ?? $result['geometry']['graphic_area_m2'] ?? '0'),
            'PERIMETER' => (string) ($result['geometry']['perimeter_m'] ?? $result['geometry']['frame_m'] ?? '0'),
            'FACE_AREA' => (string) ($result['geometry']['face_m2'] ?? '0'),
            'RETURN_DEPTH' => (string) ($input['return_depth'] ?? $input['depth'] ?? '0'),
            'VECTOR_AREA' => (string) ($result['geometry']['area_m2'] ?? '0'),
            'VECTOR_PERIMETER' => (string) ($result['geometry']['perimeter_m'] ?? '0'),
            'SIDES' => (string) ($input['sides'] ?? '1'),
            'MODULE_WATTS' => '0',
        ]);
        foreach ($this->repo->labour((int) $spec['id']) as $row) {
            try {
                $minutes = $this->formulas->evaluate((string) $row['minutes_formula'], $vars);
            } catch (FormulaRejected $e) {
                $result['warnings'][] = $e->getMessage();
                continue;
            }
            $result['labour'][] = [
                'description' => (string) $row['description'],
                'minutes' => Decimal::round($minutes, 2),
                'hourly_rate' => (string) $row['hourly_rate'],
                'source' => 'SPECIFICATION',
            ];
            $result['trace'][] = SignMath::trace((string) $row['description'], 'Specification formula ' . $row['minutes_formula'], 'Specification version ' . $spec['version'], Decimal::round($minutes, 2) . ' min');
        }
        $qty = (string) ($input['quantity'] ?? '1');
        foreach ($this->repo->operations((int) $spec['id']) as $row) {
            $machine = $this->machines->estimate((string) $row['setup_minutes'], (string) $row['run_minutes'], $qty, null, (string) $row['hourly_rate']);
            $result['machines'][] = $machine + ['description' => (string) $row['description'], 'code' => (string) $row['operation_code']];
            if ($result['route'] === []) {
                $result['route'][] = (string) $row['operation_code'];
            }
        }
        if ($result['route'] === []) {
            foreach ($this->repo->operations((int) $spec['id']) as $row) {
                $result['route'][] = (string) $row['operation_code'];
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $input
     */
    private function price(array &$result, array $input): void
    {
        $material = '0';
        $labour = '0';
        $machine = '0';
        foreach ($result['materials'] as &$line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $product = $productId > 0 ? $this->repo->product($productId) : null;
            $unitCost = $product !== null ? (string) $product['cost_price'] : (string) ($line['unit_cost'] ?? '0');
            $line['unit_cost'] = Decimal::round($unitCost, 4);
            $line['cost'] = Decimal::money(Decimal::mul((string) $line['quantity'], $unitCost, Decimal::CALC_SCALE));
            if (($line['unit'] ?? '') === 'allowance' && isset($line['unit_cost'])) {
                $line['cost'] = Decimal::money((string) $line['unit_cost']);
            }
            $material = Decimal::add($material, (string) $line['cost'], Decimal::CALC_SCALE);
            if ($product !== null) {
                $onHand = (new InventoryRepository())->onHand($productId);
                $line['available'] = $onHand;
                if (Decimal::cmp($onHand, (string) $line['quantity']) < 0) {
                    $result['warnings'][] = $line['description'] . ' needs ' . $line['quantity'] . '. Available now: ' . $onHand . '. This does not change the estimate or raise a purchase.';
                }
                $this->weight($result, $product, (string) ($result['geometry']['face_m2'] ?? $result['geometry']['graphic_area_m2'] ?? '0'));
            }
        }
        unset($line);
        foreach ($result['components'] as &$line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $product = $productId > 0 ? $this->repo->product($productId) : null;
            $unitCost = $product !== null ? (string) $product['cost_price'] : '0';
            $line['unit_cost'] = Decimal::round($unitCost, 4);
            $line['cost'] = Decimal::money(Decimal::mul((string) $line['quantity'], $unitCost, Decimal::CALC_SCALE));
            $material = Decimal::add($material, (string) $line['cost'], Decimal::CALC_SCALE);
        }
        unset($line);
        foreach ($result['labour'] as $line) {
            $hours = Decimal::div((string) $line['minutes'], '60', Decimal::CALC_SCALE);
            $labour = Decimal::add($labour, Decimal::mul($hours, (string) $line['hourly_rate'], Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        }
        foreach ($result['machines'] as $line) {
            $machine = Decimal::add($machine, (string) ($line['estimated_cost'] ?? '0'), Decimal::CALC_SCALE);
        }
        $install = ['total_cost' => '0.00', 'components' => []];
        if (($result['installation'] ?? []) !== []) {
            $install = $this->installation->estimate($result['installation']);
            $result['trace'][] = SignMath::trace('Installation', 'InstallationEstimateService', 'Access factor ' . (string) ($result['installation']['access_multiplier'] ?? '1'), (string) $install['total_cost']);
        }
        if (($result['estimator_type'] ?? '') === 'PANEL_FRAME') {
            $productId = (int) ($input['panel_product_id'] ?? 0);
            if ($productId > 0) {
                $offcuts = (new InventoryRepository())->offcuts($productId, (string) ($result['geometry']['width_mm'] ?? '0'), (string) ($result['geometry']['height_mm'] ?? '0'), true);
                $result['offcut_suggestions'] = (new OffcutMatchingService())->rank(
                    (string) ($result['geometry']['width_mm'] ?? '0'),
                    (string) ($result['geometry']['height_mm'] ?? '0'),
                    $offcuts,
                    true
                );
                $result['warnings'][] = 'Offcuts are suggestions. None were consumed.';
            }
        }
        $total = Decimal::add(Decimal::add(Decimal::add($material, $labour, Decimal::CALC_SCALE), $machine, Decimal::CALC_SCALE), (string) $install['total_cost'], Decimal::CALC_SCALE);
        $margin = SettingsService::get('target_margin_percent', '35');
        $sell = $this->pricing->sellFromMargin(Decimal::money($total), $margin);
        $sellPrice = $sell['ok'] ? $sell['sell'] : Decimal::money($total);
        $profit = Decimal::money(Decimal::sub($sellPrice, Decimal::money($total), Decimal::CALC_SCALE));
        $result['costs'] = [
            'material' => Decimal::money($material),
            'labour' => Decimal::money($labour),
            'machine' => Decimal::money($machine),
            'installation' => (string) $install['total_cost'],
            'total' => Decimal::money($total),
            'sell' => $sellPrice,
            'profit' => $profit,
            'margin' => $this->pricing->marginPercent($sellPrice, Decimal::money($total)),
            'margin_rule' => 'Company target margin ' . $margin . ' percent, the same rule as other internal estimates.',
        ];
        $result['installation_detail'] = $install;
        $result['bom_status'] = 'DRAFT';
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $product
     */
    private function weight(array &$result, array $product, string $areaM2): void
    {
        if ($result['weight_kg'] !== null) {
            return;
        }
        $thickness = (string) ($product['thickness_mm'] ?? '');
        $density = (string) ($product['density_kg_m3'] ?? '');
        if ($thickness === '' || $density === '' || Decimal::cmp($areaM2, '0') <= 0) {
            return;
        }
        $volume = Decimal::mul($areaM2, Decimal::div($thickness, '1000', Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $result['weight_kg'] = Decimal::round(Decimal::mul($volume, $density, Decimal::CALC_SCALE), 2);
        $result['trace'][] = SignMath::trace('Estimated weight', 'Area x thickness x density. Not a structural rating.', $areaM2 . ' m2 x ' . $thickness . ' mm x ' . $density . ' kg/m3', $result['weight_kg'] . ' kg');
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $spec
     * @param array<string, mixed> $result
     */
    private function persist(string $type, array $input, ?array $spec, array $result, int $userId, bool $review): int
    {
        $components = [];
        foreach (array_merge($result['materials'], $result['components']) as $line) {
            $components[] = [
                'component_type' => 'MATERIAL',
                'product_id' => $line['product_id'] ?? null,
                'description' => (string) $line['description'],
                'estimated_quantity' => (string) $line['quantity'],
                'unit' => (string) ($line['unit'] ?? 'unit'),
                'unit_cost_snapshot' => (string) ($line['unit_cost'] ?? '0'),
                'server_cost' => (string) ($line['cost'] ?? '0'),
                'calculation_method' => 'ESTIMATOR',
            ];
        }
        if ($components === []) {
            $components[] = [
                'component_type' => 'OTHER',
                'description' => 'Estimator produced no material line',
                'estimated_quantity' => '0',
                'unit' => 'unit',
                'unit_cost_snapshot' => '0',
            ];
        }
        $saved = $this->estimates->save(null, [
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'estimate_type' => $type,
            'notes' => 'Advanced estimator ' . $type,
        ], $components, $userId);
        if (!$saved['ok']) {
            throw new \RuntimeException((string) ($saved['error'] ?? 'The internal estimate was not saved.'));
        }
        $id = 0;
        Database::transaction(function () use ($type, $input, $spec, $result, $userId, $review, $saved, &$id): void {
            $id = $this->repo->insertCalculation([
                'estimate_id' => $saved['id'],
                'parent_calculation_id' => (int) ($input['parent_calculation_id'] ?? 0) > 0 ? (int) $input['parent_calculation_id'] : null,
                'estimator_type' => $type,
                'specification_id' => $spec['id'] ?? null,
                'specification_code' => $spec['code'] ?? null,
                'specification_version' => $spec['version'] ?? null,
                'customer_id' => (int) ($input['customer_id'] ?? 0) > 0 ? (int) $input['customer_id'] : null,
                'project_id' => (int) ($input['project_id'] ?? 0) > 0 ? (int) $input['project_id'] : null,
                'project_site_id' => (int) ($input['project_site_id'] ?? 0) > 0 ? (int) $input['project_site_id'] : null,
                'quote_id' => null,
                'job_id' => null,
                'status' => $review ? 'REVIEW' : 'CALCULATED',
                'outside_specification' => !empty($result['outside']) ? 1 : 0,
                'technical_review_required' => $review ? 1 : 0,
                'input_json' => json_encode($this->storedInput($input), JSON_THROW_ON_ERROR),
                'output_json' => json_encode($result, JSON_THROW_ON_ERROR),
                'input_hash' => hash('sha256', json_encode($this->storedInput($input)) . '|' . (string) ($spec['id'] ?? '0') . '|' . SettingsService::get('estimator_version', '1')),
                'material_cost' => $result['costs']['material'],
                'labour_cost' => $result['costs']['labour'],
                'machine_cost' => $result['costs']['machine'],
                'installation_cost' => $result['costs']['installation'],
                'total_cost' => $result['costs']['total'],
                'sell_price' => $result['costs']['sell'],
                'gross_profit' => $result['costs']['profit'],
                'margin_percent' => $result['costs']['margin'],
                'created_by' => $userId,
            ]);
            $this->audit->record('sign_calculation', $id, 'ESTIMATE_CALCULATED', null, ['type' => $type], $userId);
            if ($type === 'CHANNEL_LETTER' && is_array($result['geometry'] ?? null) && (string) ($result['geometry']['preview_svg'] ?? '') !== '') {
                $svg = is_string($input['svg'] ?? null) ? $input['svg'] : '';
                $geo = $result['geometry'];
                $this->repo->insertGeometry([
                    'calculation_id' => $id,
                    'original_name' => substr((string) ($input['geometry_name'] ?? 'upload.svg'), 0, 180),
                    'byte_size' => strlen($svg),
                    'sha256' => hash('sha256', $svg),
                    'open_paths' => (int) ($geo['open_paths'] ?? 0),
                    'duplicate_paths' => (int) ($geo['duplicate_paths'] ?? 0),
                    'area_mm2' => Decimal::round(Decimal::mul((string) ($geo['area_m2'] ?? '0'), '1000000', Decimal::CALC_SCALE), 4),
                    'perimeter_mm' => Decimal::round(Decimal::mul((string) ($geo['perimeter_m'] ?? '0'), '1000', Decimal::CALC_SCALE), 4),
                    'sanitized_svg' => (string) $geo['preview_svg'],
                    'warnings_json' => json_encode($result['warnings'] ?? [], JSON_THROW_ON_ERROR),
                    'created_by' => $userId,
                ]);
            }
        });
        if ($type === 'CHANNEL_LETTER' && is_array($result['geometry'] ?? null) && (string) ($result['geometry']['preview_svg'] ?? '') !== '') {
            BusinessEventDispatcher::emit('GEOMETRY_ANALYSED', 'SIGN_CALCULATION', $id, $userId, ['type' => $type]);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function storedInput(array $input): array
    {
        if (isset($input['svg']) && is_string($input['svg'])) {
            $input['svg_sha'] = hash('sha256', $input['svg']);
            $input['svg_bytes'] = strlen($input['svg']);
            unset($input['svg']);
            $input['svg_omitted'] = true;
        }

        return $input;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function jobSnapshot(array $row): string
    {
        $output = json_decode((string) $row['output_json'], true);
        $snapshot = [
            'calculation_id' => (int) $row['id'],
            'specification_id' => $row['specification_id'] !== null ? (int) $row['specification_id'] : null,
            'specification_code' => $row['specification_code'],
            'specification_version' => $row['specification_version'] !== null ? (int) $row['specification_version'] : null,
            'estimator_type' => (string) $row['estimator_type'],
            'inputs' => json_decode((string) $row['input_json'], true),
            'bom' => is_array($output) ? ($output['materials'] ?? []) : [],
            'components' => is_array($output) ? ($output['components'] ?? []) : [],
            'trace' => is_array($output) ? ($output['trace'] ?? []) : [],
            'route' => is_array($output) ? ($output['route'] ?? []) : [],
            'warnings' => is_array($output) ? ($output['warnings'] ?? []) : [],
            'geometry' => is_array($output) ? ($output['geometry'] ?? []) : [],
        ];

        return json_encode($snapshot, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function report(string $slug): array
    {
        $rows = $this->repo->varianceRows();
        $sample = count($rows);
        $materialEstimated = '0';
        $materialActual = '0';
        $labourEstimated = '0';
        $labourActual = '0';
        foreach ($rows as $row) {
            $materialEstimated = Decimal::add($materialEstimated, (string) $row['material_cost'], Decimal::CALC_SCALE);
            $materialActual = Decimal::add($materialActual, (string) $row['actual_material_cost'], Decimal::CALC_SCALE);
            $labourEstimated = Decimal::add($labourEstimated, (string) $row['labour_cost'], Decimal::CALC_SCALE);
            $labourActual = Decimal::add($labourActual, (string) $row['actual_labour_cost'], Decimal::CALC_SCALE);
        }

        return [
            'slug' => $slug,
            'sample_size' => $sample,
            'material_estimated' => Decimal::money($materialEstimated),
            'material_actual' => Decimal::money($materialActual),
            'labour_estimated' => Decimal::money($labourEstimated),
            'labour_actual' => Decimal::money($labourActual),
            'note' => $sample < 3 ? 'Fewer than three completed jobs. No labour formula is changed from this report.' : 'Review the variance before changing a specification.',
            'usage' => $this->repo->dashboard(),
        ];
    }
}

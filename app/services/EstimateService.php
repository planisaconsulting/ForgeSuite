<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\EstimatingRepository;
use App\Repositories\UserRepository;

/**
 * Internal estimates.
 *
 * Posted totals, margins, and sheet counts are not stored. The server
 * multiplies quantity by the unit cost it calculated, or by a unit cost the
 * user is allowed to type as an input. A quote is not repriced from here.
 */
final class EstimateService
{
    /** @var list<string> */
    public const TYPES = ['GENERAL', 'SHEET', 'ROLL', 'INSTALLATION', 'VEHICLE', 'RECIPE', 'REPRINT', 'VEHICLE_WRAP', 'CHANNEL_LETTER', 'LIGHTBOX', 'PYLON', 'PANEL_FRAME'];

    /** @var list<string> */
    public const STATUSES = ['DRAFT', 'CALCULATED', 'APPROVED', 'SUPERSEDED', 'CONVERTED', 'ARCHIVED'];

    public function __construct(
        private readonly EstimatingRepository $estimates = new EstimatingRepository(),
        private readonly PricingMathService $pricing = new PricingMathService(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly FormulaService $formulas = new FormulaService(),
    ) {
    }

    /**
     * @param list<array<string, mixed>> $components
     * @param array<string, mixed> $header
     * @return array{ok: bool, id: int|null, error: string|null}
     */
    public function save(?int $id, array $header, array $components, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        $needed = $id === null ? 'estimates.create' : 'estimates.edit';
        if (!AuthorizationService::allows($actor, $needed)) {
            return ['ok' => false, 'id' => null, 'error' => 'You cannot save an estimate.'];
        }
        $built = $this->components($components);
        if ($built['error'] !== null) {
            return ['ok' => false, 'id' => null, 'error' => $built['error']];
        }
        $totals = $this->totals($built['rows']);
        $marginTarget = (string) SettingsService::get('target_margin_percent', '35');
        $sell = $this->pricing->sellFromMargin($totals['subtotal_cost'], $marginTarget);
        $sellPrice = $sell['ok'] ? $sell['sell'] : $totals['subtotal_cost'];
        $profit = Decimal::money(Decimal::sub($sellPrice, $totals['subtotal_cost'], Decimal::CALC_SCALE));
        $row = [
            'revision_number' => 1,
            'customer_id' => $this->optionalId($header['customer_id'] ?? null),
            'opportunity_id' => $this->optionalId($header['opportunity_id'] ?? null),
            'quote_id' => $this->optionalId($header['quote_id'] ?? null),
            'job_id' => $this->optionalId($header['job_id'] ?? null),
            'recipe_id' => $this->optionalId($header['recipe_id'] ?? null),
            'recipe_version' => $this->optionalId($header['recipe_version'] ?? null),
            'estimate_type' => in_array(strtoupper((string) ($header['estimate_type'] ?? 'GENERAL')), self::TYPES, true)
                ? strtoupper((string) $header['estimate_type']) : 'GENERAL',
            'status' => 'CALCULATED',
            'subtotal_cost' => $totals['subtotal_cost'],
            'material_cost' => $totals['material_cost'],
            'labour_cost' => $totals['labour_cost'],
            'machine_cost' => $totals['machine_cost'],
            'installation_cost' => $totals['installation_cost'],
            'travel_cost' => $totals['travel_cost'],
            'subcontract_cost' => $totals['subcontract_cost'],
            'other_cost' => $totals['other_cost'],
            'recommended_sell_price' => $sellPrice,
            'expected_gross_profit' => $profit,
            'expected_margin' => $this->pricing->marginPercent($sellPrice, $totals['subtotal_cost']),
            'confidence_basis' => (string) ($header['confidence_basis'] ?? 'NO_HISTORY'),
            'snapshot_json' => json_encode(['components' => $built['rows'], 'ignored_browser_total' => $header['subtotal_cost'] ?? null], JSON_THROW_ON_ERROR),
            'notes' => trim((string) ($header['notes'] ?? '')) ?: null,
            'created_by' => $userId,
        ];
        $saved = 0;
        try {
        Database::transaction(function () use ($id, $row, $built, $userId, &$saved): void {
            if ($id === null) {
                $row['estimate_number'] = $this->numbers->estimate();
                $saved = $this->estimates->insertEstimate($row);
                $this->audit->record('estimate', $saved, 'ESTIMATE_CREATED', null, ['number' => $row['estimate_number']], $userId);
            } else {
                $existing = $this->estimates->find($id);
                if ($existing === null) {
                    throw new \RuntimeException('That estimate was not found.');
                }
                $row['revision_number'] = (int) $existing['revision_number'];
                $row['quote_id'] = $existing['quote_id'];
                $row['status'] = (string) $existing['status'] === 'APPROVED' ? 'CALCULATED' : (string) $existing['status'];
                if (in_array((string) $existing['status'], ['APPROVED', 'CONVERTED'], true)) {
                    $row['revision_number'] = (int) $existing['revision_number'] + 1;
                    $this->estimates->insertRevision($id, (int) $existing['revision_number'], [
                        'estimate' => $existing,
                        'components' => $this->estimates->components($id),
                    ], $userId);
                    $row['status'] = 'CALCULATED';
                    $this->audit->record('estimate', $id, 'ESTIMATE_REVISED', ['revision' => (int) $existing['revision_number']], ['revision' => $row['revision_number']], $userId);
                }
                $this->estimates->updateEstimate($id, $row);
                $this->estimates->deleteComponents($id);
                $saved = $id;
            }
            foreach ($built['rows'] as $component) {
                $component['estimate_id'] = $saved;
                $this->estimates->insertComponent($component);
            }
        });
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'id' => null, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'id' => $saved, 'error' => null];
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    public function approve(int $id, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'estimates.approve')) {
            return ['ok' => false, 'error' => 'You cannot approve an estimate.'];
        }
        $existing = $this->estimates->find($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'That estimate was not found.'];
        }
        $costLimit = (string) SettingsService::get('estimate_approval_cost', '50000');
        $marginFloor = (string) SettingsService::get('estimate_approval_margin_percent', '25');
        $needs = Decimal::cmp((string) $existing['subtotal_cost'], $costLimit) > 0
            || ($existing['expected_margin'] !== null && Decimal::cmp((string) $existing['expected_margin'], $marginFloor) < 0);
        if (!$needs && !AuthorizationService::allows($actor, 'estimates.approve')) {
            return ['ok' => false, 'error' => 'You cannot approve an estimate.'];
        }
        Database::transaction(function () use ($id, $existing, $userId): void {
            $existing['status'] = 'APPROVED';
            $this->estimates->updateEstimate($id, $this->headerFrom($existing));
            $this->audit->record('estimate', $id, 'ESTIMATE_APPROVED', null, ['number' => $existing['estimate_number']], $userId);
        });

        return ['ok' => true, 'error' => null];
    }

    /**
     * Links an estimate to a quote and stores a snapshot.
     * Quote selling prices are not changed.
     *
     * @return array{ok: bool, error: string|null}
     */
    public function linkQuote(int $id, int $quoteId, int $userId): array
    {
        $existing = $this->estimates->find($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'That estimate was not found.'];
        }
        $marginFloor = (string) SettingsService::get('estimate_approval_margin_percent', '25');
        $costLimit = (string) SettingsService::get('estimate_approval_cost', '50000');
        $low = $existing['expected_margin'] !== null && Decimal::cmp((string) $existing['expected_margin'], $marginFloor) < 0;
        $large = Decimal::cmp((string) $existing['subtotal_cost'], $costLimit) > 0;
        if (($low || $large) && (string) $existing['status'] !== 'APPROVED') {
            return ['ok' => false, 'error' => 'This estimate needs approval before it can feed a quote.'];
        }
        Database::transaction(function () use ($id, $quoteId, $existing, $userId): void {
            $this->estimates->insertRevision($id, (int) $existing['revision_number'], [
                'estimate' => $existing,
                'components' => $this->estimates->components($id),
                'quote_id' => $quoteId,
                'note' => 'Snapshot taken when the estimate was linked. Quote prices were not changed.',
            ], $userId);
            $existing['quote_id'] = $quoteId;
            $existing['status'] = 'CONVERTED';
            $this->estimates->updateEstimate($id, $this->headerFrom($existing));
        });

        return ['ok' => true, 'error' => null];
    }

    /**
     * @param list<array<string, mixed>> $components
     * @return array{rows: list<array<string, mixed>>, error: string|null}
     */
    private function components(array $components): array
    {
        $rows = [];
        foreach ($components as $component) {
            $formula = trim((string) ($component['quantity_formula'] ?? ''));
            if ($formula !== '') {
                try {
                    $quantity = $this->formulas->evaluate($formula, []);
                } catch (FormulaRejected $e) {
                    return ['rows' => [], 'error' => $e->getMessage()];
                }
            } else {
                $quantity = (string) ($component['estimated_quantity'] ?? '0');
            }
            if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') < 0) {
                return ['rows' => [], 'error' => 'A quantity is not a number.'];
            }
            $unitCost = (string) ($component['unit_cost_snapshot'] ?? '0');
            if (!Decimal::isNumeric($unitCost) || Decimal::cmp($unitCost, '0') < 0) {
                return ['rows' => [], 'error' => 'A unit cost is not a number.'];
            }
            $type = strtoupper((string) ($component['component_type'] ?? 'OTHER'));
            if (!in_array($type, ['MATERIAL', 'LABOUR', 'MACHINE', 'TRAVEL', 'INSTALLATION', 'SUBCONTRACT', 'OTHER'], true)) {
                $type = 'OTHER';
            }
            $cost = Decimal::money(Decimal::mul($quantity, $unitCost, Decimal::CALC_SCALE));
            if (isset($component['server_cost']) && Decimal::isNumeric((string) $component['server_cost'])) {
                $cost = Decimal::money((string) $component['server_cost']);
            }
            $rows[] = [
                'component_type' => $type,
                'product_id' => $this->optionalId($component['product_id'] ?? null),
                'recipe_item_id' => $this->optionalId($component['recipe_item_id'] ?? null),
                'description' => trim((string) ($component['description'] ?? 'Component')) ?: 'Component',
                'billable_quantity' => isset($component['billable_quantity']) ? (string) $component['billable_quantity'] : null,
                'estimated_quantity' => Decimal::round($quantity, 4),
                'actual_quantity' => null,
                'unit' => (string) ($component['unit'] ?? 'unit'),
                'unit_cost_snapshot' => Decimal::round($unitCost, 4),
                'estimated_cost' => $cost,
                'calculation_method' => (string) ($component['calculation_method'] ?? 'QUANTITY'),
                'calculation_details_json' => json_encode($component['details'] ?? ['source' => 'server'], JSON_THROW_ON_ERROR),
                'manual_override' => !empty($component['manual_override']) ? 1 : 0,
                'override_reason' => trim((string) ($component['override_reason'] ?? '')) ?: null,
            ];
        }
        if ($rows === []) {
            return ['rows' => [], 'error' => 'Add at least one component.'];
        }

        return ['rows' => $rows, 'error' => null];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, string>
     */
    private function totals(array $rows): array
    {
        $buckets = [
            'material_cost' => '0',
            'labour_cost' => '0',
            'machine_cost' => '0',
            'installation_cost' => '0',
            'travel_cost' => '0',
            'subcontract_cost' => '0',
            'other_cost' => '0',
        ];
        $map = [
            'MATERIAL' => 'material_cost',
            'LABOUR' => 'labour_cost',
            'MACHINE' => 'machine_cost',
            'INSTALLATION' => 'installation_cost',
            'TRAVEL' => 'travel_cost',
            'SUBCONTRACT' => 'subcontract_cost',
            'OTHER' => 'other_cost',
        ];
        foreach ($rows as $row) {
            $key = $map[$row['component_type']] ?? 'other_cost';
            $buckets[$key] = Decimal::add($buckets[$key], (string) $row['estimated_cost'], Decimal::CALC_SCALE);
        }
        $subtotal = '0';
        foreach ($buckets as $amount) {
            $subtotal = Decimal::add($subtotal, $amount, Decimal::CALC_SCALE);
        }
        $buckets['subtotal_cost'] = Decimal::money($subtotal);
        foreach ($buckets as $key => $amount) {
            if ($key !== 'subtotal_cost') {
                $buckets[$key] = Decimal::money($amount);
            }
        }

        return $buckets;
    }

    /**
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function headerFrom(array $existing): array
    {
        return [
            'revision_number' => (int) $existing['revision_number'],
            'customer_id' => $existing['customer_id'],
            'opportunity_id' => $existing['opportunity_id'],
            'quote_id' => $existing['quote_id'],
            'job_id' => $existing['job_id'],
            'recipe_id' => $existing['recipe_id'],
            'recipe_version' => $existing['recipe_version'],
            'estimate_type' => $existing['estimate_type'],
            'status' => $existing['status'],
            'subtotal_cost' => $existing['subtotal_cost'],
            'material_cost' => $existing['material_cost'],
            'labour_cost' => $existing['labour_cost'],
            'machine_cost' => $existing['machine_cost'],
            'installation_cost' => $existing['installation_cost'],
            'travel_cost' => $existing['travel_cost'],
            'subcontract_cost' => $existing['subcontract_cost'],
            'other_cost' => $existing['other_cost'],
            'recommended_sell_price' => $existing['recommended_sell_price'],
            'expected_gross_profit' => $existing['expected_gross_profit'],
            'expected_margin' => $existing['expected_margin'],
            'confidence_basis' => $existing['confidence_basis'],
            'snapshot_json' => $existing['snapshot_json'],
            'notes' => $existing['notes'],
        ];
    }

    private function optionalId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}

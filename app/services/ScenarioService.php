<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ForecastRepository;

/**
 * What-if figures are calculated on a copy of the numbers.
 * Live product costs, quotes, resources, and jobs are not written.
 */
final class ScenarioService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, result: array<string, string>}
     */
    public function run(array $input, int $userId): array
    {
        if (!can('scenarios.manage') && !can('scenarios.view')) {
            return ['errors' => ['_form' => 'You cannot run a scenario.'], 'id' => null, 'result' => []];
        }
        $type = strtoupper(trim((string) ($input['scenario_type'] ?? 'CUSTOM')));
        $allowed = ['BASE', 'HIGH_SALES', 'LOW_SALES', 'MATERIAL_PRICE', 'CAPACITY_CHANGE', 'CUSTOM'];
        if (!in_array($type, $allowed, true)) {
            return ['errors' => ['scenario_type' => 'Choose a scenario type.'], 'id' => null, 'result' => []];
        }
        $percent = Decimal::round((string) ($input['percent'] ?? '0'), 2);
        $productId = (int) ($input['product_id'] ?? 0);
        $product = $productId > 0 ? $this->planning->product($productId) : null;
        $baseCost = $product === null ? '0.0000' : (string) $product['cost_price'];
        $scenarioCost = $baseCost;
        if ($type === 'MATERIAL_PRICE') {
            $scenarioCost = Decimal::round(Decimal::mul($baseCost, Decimal::add('1', Decimal::div($percent, '100'))), 4);
        }
        $quoteId = (int) ($input['quote_id'] ?? 0);
        $quoteTotal = $quoteId > 0 ? $this->planning->quoteTotal($quoteId) : null;
        $result = [
            'base_cost' => $baseCost,
            'scenario_cost' => $scenarioCost,
            'percent' => $percent,
            'quote_total_unchanged' => $quoteTotal ?? '',
            'note' => 'Live product cost and issued quotes are not changed.',
        ];
        $id = $this->planning->insertScenario([
            'name' => mb_substr(trim((string) ($input['name'] ?? 'Scenario')), 0, 180),
            'description' => blank_to_null($input['description'] ?? null),
            'scenario_type' => $type,
            'parameters_json' => json_encode(['percent' => $percent, 'product_id' => $productId, 'result' => $result], JSON_THROW_ON_ERROR),
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id, 'result' => $result];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\EstimatingRepository;
use App\Repositories\UserRepository;

/**
 * Compares estimates with completed jobs and lists quote warnings.
 *
 * Reports use a limited sample. Comparable jobs are the same recipe only.
 */
final class PricingIntelligenceService
{
    public function __construct(
        private readonly EstimatingRepository $estimates = new EstimatingRepository(),
        private readonly VarianceService $variance = new VarianceService(),
        private readonly QuoteRiskService $risk = new QuoteRiskService(),
        private readonly HistoricalStatsService $stats = new HistoricalStatsService(),
        private readonly CostStateService $states = new CostStateService(),
        private readonly AuditService $audit = new AuditService(),
    ) {
    }

    /**
     * @return array{ok: bool, rows: list<array<string, mixed>>, error: string|null}
     */
    public function estimateVersusActual(int $userId): array
    {
        if (!$this->allowed($userId, 'historical_costing.view')) {
            return ['ok' => false, 'rows' => [], 'error' => 'You cannot view historical costing.'];
        }
        $rows = [];
        foreach ($this->estimates->completedComparisons(60) as $job) {
            $money = $this->estimates->jobMoney((int) $job['id']);
            $estimatedCost = (string) $job['quoted_cost_snapshot'];
            $actualCost = (string) $job['actual_total_cost'];
            $material = $this->variance->compare($money['estimated_material'], $money['actual_material']);
            $labour = $this->variance->compare($money['estimated_labour'], $money['actual_labour']);
            $cost = $this->variance->compare($estimatedCost, $actualCost);
            $estimatedMargin = $this->margin((string) $job['quoted_revenue_snapshot'], $estimatedCost);
            $actualMargin = $this->margin((string) $job['quoted_revenue_snapshot'], $actualCost);
            $rows[] = [
                'job_number' => (string) $job['job_number'],
                'recipe' => (string) ($job['recipe_name'] ?? ''),
                'estimated_material' => $money['estimated_material'],
                'actual_material' => $money['actual_material'],
                'material_variance_percent' => $material['variance_percent'],
                'material_meaning' => $material['meaning'],
                'estimated_labour' => $money['estimated_labour'],
                'actual_labour' => $money['actual_labour'],
                'labour_variance_percent' => $labour['variance_percent'],
                'labour_meaning' => $labour['meaning'],
                'estimated_cost' => $estimatedCost,
                'actual_cost' => $actualCost,
                'cost_variance_percent' => $cost['variance_percent'],
                'cost_meaning' => $cost['meaning'],
                'estimated_margin' => $estimatedMargin,
                'actual_margin' => $actualMargin,
            ];
        }

        return ['ok' => true, 'rows' => $rows, 'error' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public function health(int $userId): array
    {
        if (!$this->allowed($userId, 'pricing_intelligence.view')) {
            return ['ok' => false, 'error' => 'You cannot view pricing intelligence.'];
        }
        $comparison = $this->estimateVersusActual($userId);
        $under = [];
        $over = [];
        $impact = [];
        $byRecipe = [];
        foreach ($comparison['rows'] as $row) {
            $percent = $row['cost_variance_percent'];
            if ($percent !== null && Decimal::cmp((string) $percent, '10') > 0) {
                $under[] = $row;
            }
            if ($percent !== null && Decimal::cmp((string) $percent, '-10') < 0) {
                $over[] = $row;
            }
            $impact[] = ['estimated' => (string) $row['estimated_cost'], 'actual' => (string) $row['actual_cost']];
            $name = $row['recipe'] !== '' ? $row['recipe'] : 'No recipe';
            $byRecipe[$name][] = (string) ($percent ?? '0');
        }
        $recipes = [];
        foreach ($byRecipe as $name => $values) {
            $summary = $this->stats->summarise($values);
            $recipes[] = ['recipe' => $name, 'sample' => $summary['count'], 'median_variance_percent' => $summary['median'], 'mean_variance_percent' => $summary['mean']];
        }

        return [
            'ok' => true,
            'underestimated' => $under,
            'overestimated' => $over,
            'recipes' => $recipes,
            'variance' => $this->states->estimateVariance($impact),
            'pending' => $this->estimates->recommendations('NEW'),
            'sample_note' => 'The latest 60 completed jobs. A recipe with fewer than the minimum sample is marked insufficient on its recommendation.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function similar(int $recipeId, int $userId): array
    {
        if (!$this->allowed($userId, 'historical_costing.view') || $recipeId < 1) {
            return [];
        }
        $rows = [];
        foreach ($this->estimates->similarJobs($recipeId, 12) as $job) {
            $money = $this->estimates->jobMoney((int) $job['id']);
            $rows[] = [
                'job_number' => (string) $job['job_number'],
                'quoted_value' => (string) $job['quoted_revenue_snapshot'],
                'actual_cost' => (string) $job['actual_total_cost'],
                'margin' => $this->margin((string) $job['quoted_revenue_snapshot'], (string) $job['actual_total_cost']),
                'material_variance' => $this->variance->compare($money['estimated_material'], $money['actual_material']),
                'labour_variance' => $this->variance->compare($money['estimated_labour_minutes'], $money['actual_labour_minutes']),
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $historicalMinutes
     * @return array<string, mixed>|null
     */
    public function labourAnomaly(string $currentMinutes, array $historicalMinutes): ?array
    {
        $minimum = (int) SettingsService::get('minimum_sample_size', '5');
        $summary = $this->stats->summarise($historicalMinutes);
        if ($summary['count'] < $minimum || $summary['median'] === null || !Decimal::isNumeric($currentMinutes)) {
            return null;
        }
        $high = Decimal::mul((string) $summary['median'], '1.5', Decimal::CALC_SCALE);
        $low = Decimal::mul((string) $summary['median'], '0.6', Decimal::CALC_SCALE);
        if (Decimal::cmp($currentMinutes, $high) <= 0 && Decimal::cmp($currentMinutes, $low) >= 0) {
            return null;
        }

        return [
            'code' => 'REVIEW_LABOUR_ESTIMATE',
            'message' => 'Current estimated labour is ' . Decimal::round($currentMinutes, 2) . '. Historical median is ' . $summary['median'] . ' across ' . $summary['count'] . ' values. The estimate was not changed.',
            'median' => $summary['median'],
            'current' => Decimal::round($currentMinutes, 2),
        ];
    }

    /**
     * @param array<string, mixed> $facts
     * @return array<string, mixed>
     */
    public function quoteReview(array $facts, int $userId, ?int $estimateId): array
    {
        $facts['target_margin'] = $facts['target_margin'] ?? SettingsService::get('target_margin_percent', '35');
        $facts['cost_age_limit'] = (int) SettingsService::get('cost_age_warning_days', '90');
        $facts['price_move_limit'] = SettingsService::get('price_volatility_percent', '5');
        $facts['minimum_sample'] = (int) SettingsService::get('minimum_sample_size', '5');
        $facts['block_on_review'] = SettingsService::get('quote_risk_block', '0') === '1';
        $result = $this->risk->assess($facts);
        if ($result['blocked']) {
            $actor = (new UserRepository())->find($userId);
            $reason = trim((string) ($facts['override_reason'] ?? ''));
            if ($reason !== '' && AuthorizationService::allows($actor, 'quote_risk.override')) {
                $result['blocked'] = false;
                $this->estimates->insertRisk($result['warnings'], $result['level'], null, $estimateId, $reason, $userId);
                $this->audit->record('estimate', $estimateId, 'QUOTE_RISK_OVERRIDDEN', null, ['reason' => $reason], $userId);
            }
        }

        return $result;
    }

    /**
     * @return array{ok: bool, error: string|null, calculated: string, manual: string|null}
     */
    public function overrideYield(string $calculated, string $manual, string $note, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'yield.override')) {
            return ['ok' => false, 'error' => 'You cannot override a yield.', 'calculated' => $calculated, 'manual' => null];
        }
        if (!Decimal::isNumeric($calculated) || !Decimal::isNumeric($manual)) {
            return ['ok' => false, 'error' => 'Yield values must be numbers.', 'calculated' => $calculated, 'manual' => null];
        }
        $gap = Decimal::sub($manual, $calculated, Decimal::CALC_SCALE);
        $absolute = Decimal::cmp($gap, '0') < 0 ? Decimal::mul($gap, '-1', Decimal::CALC_SCALE) : $gap;
        $percent = Decimal::cmp($calculated, '0') > 0
            ? Decimal::mul(Decimal::div($absolute, $calculated, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE)
            : '0';
        $material = Decimal::cmp($percent, '5') >= 0;
        if ($material && trim($note) === '') {
            return ['ok' => false, 'error' => 'A note is required when the override differs by 5% or more.', 'calculated' => $calculated, 'manual' => null];
        }
        $this->audit->record('estimate', null, 'YIELD_OVERRIDDEN', ['calculated' => $calculated], ['manual' => $manual, 'note' => $note], $userId);

        return ['ok' => true, 'error' => null, 'calculated' => $calculated, 'manual' => $manual];
    }

    private function margin(string $revenue, string $cost): ?string
    {
        if (!Decimal::isNumeric($revenue) || Decimal::cmp($revenue, '0') <= 0) {
            return null;
        }
        $profit = Decimal::sub($revenue, $cost, Decimal::CALC_SCALE);

        return Decimal::round(Decimal::mul(Decimal::div($profit, $revenue, Decimal::CALC_SCALE), '100', Decimal::CALC_SCALE), 2);
    }

    private function allowed(int $userId, string $permission): bool
    {
        return AuthorizationService::allows((new UserRepository())->find($userId), $permission);
    }
}

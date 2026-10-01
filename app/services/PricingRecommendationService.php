<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\EstimatingRepository;
use App\Repositories\RecipeRepository;
use App\Repositories\UserRepository;

/**
 * Suggests a recipe change from completed jobs.
 *
 * Nothing is written onto the live recipe until a person accepts it.
 * Acceptance creates the next recipe version. Quotes and jobs keep the
 * snapshot they already stored.
 */
final class PricingRecommendationService
{
    public function __construct(
        private readonly EstimatingRepository $estimates = new EstimatingRepository(),
        private readonly RecipeRepository $recipes = new RecipeRepository(),
        private readonly HistoricalStatsService $stats = new HistoricalStatsService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly FormulaService $formulas = new FormulaService(),
    ) {
    }

    /**
     * @param list<string> $actuals
     * @return array<string, mixed>
     */
    public function propose(string $type, string $current, array $actuals, ?int $recipeId, ?int $itemId, ?int $productId): array
    {
        $minimum = (int) SettingsService::get('minimum_sample_size', '5');
        $summary = $this->stats->summarise($actuals);
        $summary['confidence'] = $this->stats->confidence($summary['count'], $minimum);
        $suggested = $summary['recommendation_value'];
        $ready = $summary['count'] >= $minimum && $suggested !== null && Decimal::isNumeric($current);
        if ($summary['count'] < $minimum) {
            $summary['explanation'] = 'INSUFFICIENT HISTORY. ' . $summary['explanation'];
        }

        return [
            'ready' => $ready,
            'type' => $type,
            'current_value' => $current,
            'suggested_value' => $suggested,
            'recipe_id' => $recipeId,
            'recipe_item_id' => $itemId,
            'product_id' => $productId,
            'statistics' => $summary,
        ];
    }

    /**
     * @param list<string> $actuals
     * @return array{ok: bool, id: int|null, error: string|null}
     */
    public function create(string $type, string $current, array $actuals, ?int $recipeId, ?int $itemId, ?int $productId, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'pricing_intelligence.view')) {
            return ['ok' => false, 'id' => null, 'error' => 'You cannot create a pricing recommendation.'];
        }
        $proposal = $this->propose($type, $current, $actuals, $recipeId, $itemId, $productId);
        if (!$proposal['ready']) {
            return ['ok' => false, 'id' => null, 'error' => 'INSUFFICIENT HISTORY'];
        }
        $id = $this->estimates->insertRecommendation([
            'recommendation_type' => $type,
            'recipe_id' => $recipeId,
            'recipe_item_id' => $itemId,
            'product_id' => $productId,
            'current_value' => $current,
            'suggested_value' => (string) $proposal['suggested_value'],
            'sample_size' => $proposal['statistics']['count'],
            'confidence_basis' => $proposal['statistics']['confidence'],
            'reason' => (string) $proposal['statistics']['explanation'],
            'statistic_json' => json_encode($proposal['statistics'], JSON_THROW_ON_ERROR),
            'status' => 'NEW',
        ]);
        $this->audit->record('pricing_recommendation', $id, 'PRICING_RECOMMENDATION_CREATED', null, [
            'type' => $type,
            'current' => $current,
            'suggested' => $proposal['suggested_value'],
        ], $userId);

        return ['ok' => true, 'id' => $id, 'error' => null];
    }

    /**
     * @return array{ok: bool, error: string|null, version: int|null}
     */
    public function accept(int $id, string $value, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'pricing_recommendations.apply')) {
            return ['ok' => false, 'error' => 'You cannot apply a pricing recommendation.', 'version' => null];
        }
        $row = $this->estimates->recommendation($id);
        if ($row === null || (string) $row['status'] !== 'NEW') {
            return ['ok' => false, 'error' => 'That recommendation is not open.', 'version' => null];
        }
        $value = trim($value);
        if ($value === '' || !Decimal::isNumeric($value)) {
            return ['ok' => false, 'error' => 'Enter the value to store.', 'version' => null];
        }
        $recipeId = (int) ($row['recipe_id'] ?? 0);
        $itemId = (int) ($row['recipe_item_id'] ?? 0);
        if ($recipeId < 1 || $itemId < 1) {
            return ['ok' => false, 'error' => 'This recommendation is not tied to a recipe line.', 'version' => null];
        }
        $recipe = $this->recipes->find($recipeId);
        if ($recipe === null) {
            return ['ok' => false, 'error' => 'That recipe was not found.', 'version' => null];
        }
        $version = 0;
        try {
        Database::transaction(function () use ($row, $recipe, $recipeId, $itemId, $value, $userId, $id, &$version): void {
            $type = (string) $row['recommendation_type'];
            if ($type === 'LABOUR_TIME') {
                $formula = $value;
                $this->formulas->evaluate($formula, ['W' => '1', 'H' => '1', 'Q' => '1', 'AREA_M2' => '1', 'L' => '1', 'D' => '1', 'PERIMETER_M' => '1']);
                $this->recipes->setItemFormula($recipeId, $itemId, $formula);
            } else {
                $this->recipes->setItemWaste($recipeId, $itemId, Decimal::round($value, 2));
            }
            $version = (int) $recipe['version_number'] + 1;
            $this->recipes->setVersion($recipeId, $version);
            $this->recipes->insertVersion($recipeId, $version, [
                'recipe' => $this->recipes->find($recipeId),
                'items' => $this->recipes->items($recipeId),
                'inputs' => $this->recipes->inputs($recipeId),
                'recommendation_id' => $id,
            ], $userId);
            $this->estimates->reviewRecommendation($id, 'ACCEPTED', $userId);
            $this->audit->record('recipe', $recipeId, 'RECIPE_UPDATED_FROM_RECOMMENDATION', [
                'version' => (int) $recipe['version_number'],
                'value' => $row['current_value'],
            ], [
                'version' => $version,
                'value' => $value,
                'recommendation_id' => $id,
            ], $userId);
            $this->audit->record('pricing_recommendation', $id, 'PRICING_RECOMMENDATION_ACCEPTED', null, ['value' => $value], $userId);
        });
        } catch (FormulaRejected $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'version' => null];
        }

        return ['ok' => true, 'error' => null, 'version' => $version];
    }

    /**
     * @return array{ok: bool, error: string|null}
     */
    public function reject(int $id, int $userId): array
    {
        $actor = (new UserRepository())->find($userId);
        if (!AuthorizationService::allows($actor, 'pricing_recommendations.review') && !AuthorizationService::allows($actor, 'pricing_recommendations.apply')) {
            return ['ok' => false, 'error' => 'You cannot review a pricing recommendation.'];
        }
        $row = $this->estimates->recommendation($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'That recommendation was not found.'];
        }
        $this->estimates->reviewRecommendation($id, 'REJECTED', $userId);
        $this->audit->record('pricing_recommendation', $id, 'PRICING_RECOMMENDATION_REJECTED', null, ['status' => 'REJECTED'], $userId);

        return ['ok' => true, 'error' => null];
    }
}

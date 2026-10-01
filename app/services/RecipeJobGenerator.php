<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\MaterialSource;
use App\Helpers\Decimal;
use App\Repositories\OperationsRepository;

/**
 * Builds job requirements, expected labour, and production stages from the
 * quote recipe snapshot. It does not read the current recipe.
 */
final class RecipeJobGenerator
{
    public function __construct(private readonly OperationsRepository $ops = new OperationsRepository())
    {
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function apply(int $jobId, int $jobItemId, array $snapshot, int $userId): void
    {
        $components = $snapshot['component_snapshot_json'] ?? [];
        if (!is_array($components)) {
            $components = [];
        }
        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $type = strtoupper((string) ($component['component_type'] ?? ''));
            if ($type === 'LABOUR') {
                $this->ops->insertExpectedLabour([
                    'job_id' => $jobId,
                    'job_item_id' => $jobItemId,
                    'description' => (string) ($component['description'] ?? 'Labour'),
                    'expected_minutes' => Decimal::round((string) ($component['costed_quantity'] ?? $component['quantity'] ?? '0'), 2),
                    'hourly_cost_snapshot' => Decimal::round((string) ($component['unit_cost'] ?? '0'), 4),
                ]);
                continue;
            }
            if (!in_array($type, ['MATERIAL', 'HARDWARE', 'SUBCONTRACT', 'SERVICE', 'OTHER'], true)) {
                continue;
            }
            $productId = (int) ($component['product_id'] ?? 0);
            $qty = Decimal::round((string) ($component['costed_quantity'] ?? $component['quantity'] ?? '0'), 4);
            if (Decimal::cmp($qty, '0') <= 0) {
                continue;
            }
            $this->ops->insertRequirement([
                'job_id' => $jobId,
                'job_item_id' => $jobItemId,
                'product_id' => $productId > 0 ? $productId : null,
                'required_quantity' => $qty,
                'unit' => substr((string) ($component['unit'] ?? 'unit'), 0, 20),
                'calculated_quantity' => $qty,
                'manual_adjustment' => '0.0000',
                'final_required_quantity' => $qty,
                'purchase_quantity' => isset($component['purchase_quantity']) ? Decimal::round((string) $component['purchase_quantity'], 4) : null,
                'pack_size' => $component['pack_size'] ?? null,
                'source' => MaterialSource::Recipe->value,
                'notes' => 'From the accepted recipe snapshot',
                'created_by' => $userId,
            ]);
        }
        $route = $snapshot['production_route_snapshot_json'] ?? [];
        if (!is_array($route)) {
            return;
        }
        foreach ($route as $stage) {
            if (!is_array($stage)) {
                continue;
            }
            $stageId = (int) ($stage['production_stage_id'] ?? 0);
            if ($stageId < 1) {
                continue;
            }
            $this->ops->insertStage($jobId, $jobItemId, $stageId, (int) ($stage['sort_order'] ?? 0));
        }
    }
}

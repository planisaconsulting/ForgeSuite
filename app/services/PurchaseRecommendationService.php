<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\InventoryRepository;
use App\Repositories\ForecastRepository;

/**
 * MRP may recommend an order. It does not send a purchase order.
 * A person reviews demand, stock, supplier, MOQ, and pack size first.
 */
final class PurchaseRecommendationService
{
    public function __construct(
        private readonly ForecastRepository $planning = new ForecastRepository(),
        private readonly MaterialPlanningService $materials = new MaterialPlanningService(),
        private readonly InventoryRepository $inventory = new InventoryRepository()
    ) {
    }

    /**
     * @return list<int>
     */
    public function refresh(int $userId): array
    {
        if (!can('mrp.manage') && !can('purchase_recommendations.review')) {
            return [];
        }
        $grouped = [];
        foreach ($this->planning->firmDemand() as $row) {
            $productId = (int) $row['product_id'];
            $grouped[$productId][] = $row;
        }
        $incoming = [];
        foreach ($this->planning->incomingPurchaseLines() as $line) {
            $incoming[(int) $line['product_id']][] = [
                'quantity' => (string) $line['quantity'],
                'arrives' => (string) $line['arrives'],
            ];
        }
        $ids = [];
        $buffer = (int) SettingsService::get('planning_order_buffer_days', '2');
        $skip = SettingsService::get('planning_skip_weekends', '1') === '1';
        foreach ($grouped as $productId => $rows) {
            $demand = [];
            foreach ($rows as $row) {
                $demand[] = [
                    'quantity' => (string) $row['quantity'],
                    'required_by' => (string) $row['required_by'],
                    'category' => 'FIRM',
                    'label' => (string) $row['job_number'],
                    'job_id' => (int) $row['job_id'],
                ];
            }
            $safety = (string) ($rows[0]['minimum_stock_level'] ?? '0');
            $plan = $this->materials->plan(
                $this->inventory->onHand($productId),
                $this->inventory->reserved($productId),
                $demand,
                $incoming[$productId] ?? [],
                $safety
            );
            if (Decimal::cmp($plan['net'], '0') <= 0) {
                continue;
            }
            $suppliers = $this->planning->supplierOptions($productId);
            $requiredBy = $demand[0]['required_by'];
            $pack = null;
            foreach ($demand as $line) {
                if ($line['required_by'] < $requiredBy) {
                    $requiredBy = $line['required_by'];
                }
            }
            foreach ($rows as $row) {
                if ($row['pack_size'] !== null && Decimal::cmp((string) $row['pack_size'], '0') > 0) {
                    $pack = (string) $row['pack_size'];
                    break;
                }
            }
            $chosen = null;
            $today = date('Y-m-d');
            foreach ($suppliers as $supplier) {
                $candidateLead = (int) ($supplier['lead_time_days'] ?? 0);
                $orderBy = PlanningMath::orderByDate($requiredBy, $candidateLead, $buffer, $skip);
                if ($orderBy >= $today) {
                    $chosen = $supplier;
                    break;
                }
            }
            if ($chosen === null) {
                $chosen = $suppliers[0] ?? null;
            }
            $lead = $chosen === null ? 0 : (int) ($chosen['lead_time_days'] ?? 0);
            $moq = $chosen['minimum_order_quantity'] ?? null;
            $order = PlanningMath::orderQuantity($plan['net'], $moq !== null ? (string) $moq : null, $pack);
            $id = $this->planning->insertRecommendation([
                'product_id' => $productId,
                'supplier_id' => $chosen === null ? null : (int) $chosen['supplier_id'],
                'required_quantity' => $plan['net'],
                'order_quantity' => $order,
                'required_by_date' => $requiredBy,
                'recommended_order_date' => PlanningMath::orderByDate($requiredBy, $lead, $buffer, $skip),
                'source_type' => 'FIRM',
                'source_summary' => count($demand) . ' job requirement(s)',
                'status' => 'NEW',
            ]);
            foreach ($demand as $line) {
                $this->planning->insertRecommendationSource([
                    'recommendation_id' => $id,
                    'job_id' => $line['job_id'],
                    'demand_category' => 'FIRM',
                    'quantity' => $line['quantity'],
                    'required_by_date' => $line['required_by'],
                    'source_label' => $line['label'],
                ]);
            }
            $ids[] = $id;
        }
        (new ForecastSnapshotService($this->planning))->store('MRP', date('Y-m-d'), date('Y-m-d'), date('Y-m-d', strtotime('+90 days')), ['count' => count($ids)], ['recommendations' => count($ids)], $userId);

        return $ids;
    }

    /**
     * @return array<string, string>
     */
    public function review(int $id, string $status, int $userId): array
    {
        if (!can('purchase_recommendations.review')) {
            return ['_form' => 'You cannot review a purchase recommendation.'];
        }
        $status = strtoupper($status);
        if (!in_array($status, ['REVIEWED', 'ACCEPTED', 'REJECTED', 'EXPIRED'], true)) {
            return ['status' => 'Choose a review decision.'];
        }
        $this->planning->setRecommendationStatus($id, $status, $userId);

        return [];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createRequest(int $id, int $userId): array
    {
        $row = $this->planning->recommendation($id);
        if ($row === null) {
            return ['errors' => ['_form' => 'That recommendation was not found.'], 'id' => null];
        }
        $sources = $this->planning->recommendationSources($id);
        $jobId = $sources[0]['job_id'] ?? 0;
        $created = (new PurchasingService())->request([
            'product_id' => (int) $row['product_id'],
            'quantity' => (string) $row['order_quantity'],
            'required_by' => (string) ($row['required_by_date'] ?? ''),
            'job_id' => (int) $jobId,
            'reason' => 'Material planning recommendation ' . $id,
        ], $userId);
        if ($created['id'] === null) {
            return $created;
        }
        $this->planning->setRecommendationStatus($id, 'CONVERTED', $userId);

        return $created;
    }
}

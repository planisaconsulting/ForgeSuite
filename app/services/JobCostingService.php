<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\JobRepository;

/**
 * Actual job cost from material usage, labour, and other costs.
 *
 * Quoted revenue and quoted cost stay on the job as snapshots from the
 * accepted quotation. They are quoted figures, not money received.
 * Gross profit is quoted revenue minus actual cost.
 * Gross margin is gross profit divided by quoted revenue.
 * Markup is not used here.
 */
final class JobCostingService
{
    public function __construct(private readonly JobRepository $jobs = new JobRepository())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(int $jobId): array
    {
        $summary = $this->report($jobId);
        if ($summary !== []) {
            $this->jobs->cacheActuals($jobId, $summary);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(int $jobId): array
    {
        $job = $this->jobs->find($jobId);
        if ($job === null) {
            return [];
        }

        return $this->summarise(
            (string) $job['quoted_revenue_snapshot'],
            (string) $job['quoted_cost_snapshot'],
            $this->jobs->materialTotal($jobId),
            $this->jobs->labourTotal($jobId),
            $this->jobs->otherTotal($jobId),
            $this->jobs->materialLines($jobId),
            $this->jobs->labourLines($jobId)
        );
    }

    /**
     * @param list<array<string, mixed>> $materialLines
     * @param list<array<string, mixed>> $labourLines
     * @return array<string, mixed>
     */
    public function summarise(
        string $quotedRevenue,
        string $quotedCost,
        string $material,
        string $labour,
        string $other,
        array $materialLines = [],
        array $labourLines = []
    ): array {
        $revenue = Decimal::money($quotedRevenue);
        $quoted = Decimal::money($quotedCost);
        $materialCost = Decimal::money($material);
        $labourCost = Decimal::money($labour);
        $otherCost = Decimal::money($other);
        $actual = Decimal::money(Decimal::add(Decimal::add($materialCost, $labourCost), $otherCost));
        $quotedProfit = Decimal::money(Decimal::sub($revenue, $quoted));
        $actualProfit = Decimal::money(Decimal::sub($revenue, $actual));
        $variance = Decimal::money(Decimal::sub($actual, $quoted));
        $variancePercent = Decimal::cmp($quoted, '0') === 0
            ? null
            : Decimal::round(Decimal::mul(Decimal::div($variance, $quoted), '100'), 2);
        $quotedMargin = $this->margin($quotedProfit, $revenue);
        $actualMargin = $this->margin($actualProfit, $revenue);

        return [
            'quoted_revenue' => $revenue,
            'quoted_cost' => $quoted,
            'quoted_profit' => $quotedProfit,
            'quoted_margin_percent' => $quotedMargin,
            'actual_material_cost' => $materialCost,
            'actual_labour_cost' => $labourCost,
            'actual_other_cost' => $otherCost,
            'actual_total_cost' => $actual,
            'actual_profit' => $actualProfit,
            'actual_margin_percent' => $actualMargin,
            'cost_variance' => $variance,
            'cost_variance_percent' => $variancePercent,
            'overrun' => Decimal::cmp($variance, '0') > 0,
            'materials' => $this->materialVariance($materialLines),
            'labour' => $this->labourVariance($labourLines),
        ];
    }

    private function margin(string $profit, string $revenue): ?string
    {
        if (Decimal::cmp($revenue, '0') === 0) {
            return null;
        }

        return Decimal::round(Decimal::mul(Decimal::div($profit, $revenue), '100'), 2);
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<array<string, mixed>>
     */
    private function materialVariance(array $lines): array
    {
        $groups = [];
        foreach ($lines as $line) {
            $key = (string) ($line['product_id'] ?? '0') . '|' . (string) ($line['unit'] ?? '');
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'name' => (string) ($line['name'] ?? 'Material'),
                    'unit' => (string) ($line['unit'] ?? ''),
                    'estimated' => '0',
                    'production' => '0',
                    'waste' => '0',
                ];
            }
            $qty = (string) ($line['quantity'] ?? '0');
            $kind = (string) ($line['kind'] ?? '');
            if ($kind === 'estimated') {
                $groups[$key]['estimated'] = Decimal::add($groups[$key]['estimated'], $qty);
            } elseif ($kind === 'waste') {
                $groups[$key]['waste'] = Decimal::add($groups[$key]['waste'], $qty);
            } else {
                $groups[$key]['production'] = Decimal::add($groups[$key]['production'], $qty);
            }
        }
        $out = [];
        foreach ($groups as $group) {
            $actual = Decimal::add($group['production'], $group['waste']);
            $variance = Decimal::sub($actual, $group['estimated']);
            $out[] = [
                'name' => $group['name'],
                'unit' => $group['unit'],
                'estimated' => Decimal::round($group['estimated'], 4),
                'production' => Decimal::round($group['production'], 4),
                'waste' => Decimal::round($group['waste'], 4),
                'actual' => Decimal::round($actual, 4),
                'variance' => Decimal::round($variance, 4),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array{estimated_minutes: int, actual_minutes: int, variance_minutes: int}
     */
    private function labourVariance(array $lines): array
    {
        $estimated = 0;
        $actual = 0;
        foreach ($lines as $line) {
            $estimated += (int) ($line['estimated_minutes'] ?? 0);
            $actual += (int) ($line['actual_minutes'] ?? 0);
        }

        return [
            'estimated_minutes' => $estimated,
            'actual_minutes' => $actual,
            'variance_minutes' => $actual - $estimated,
        ];
    }
}

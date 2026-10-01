<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ProjectRepository;

/**
 * Project commercial value, cost, and margin.
 *
 * A contract line counts once. Site or job allocations of that contract are
 * stored for profitability splits and do not add to the project total.
 * Payments are cash collected. They are not revenue.
 */
final class ProjectFinancialService
{
    public function __construct(private readonly ProjectRepository $projects = new ProjectRepository())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function statement(int $projectId): array
    {
        $commercial = $this->projects->sumCommercial($projectId);
        $commercial = Decimal::add($commercial, $this->projects->additionalJobRevenue($projectId), 2);
        $commercial = Decimal::add($commercial, $this->projects->approvedVariationTotal($projectId), 2);
        $commercial = Decimal::round($commercial, 2);
        $jobCost = $this->projects->jobCostSum($projectId);
        $direct = $this->projects->directCostSum($projectId);
        $actual = Decimal::round(Decimal::add($jobCost, $direct, 2), 2);
        $estimated = Decimal::round($this->projects->estimatedJobCost($projectId), 2);
        $budget = Decimal::round($this->projects->budgetSum($projectId), 2);
        $gp = Decimal::round(Decimal::sub($commercial, $actual, 2), 2);
        $margin = Decimal::cmp($commercial, '0') === 0 ? null : Decimal::round(Decimal::mul(Decimal::div($gp, $commercial, 8), '100', 8), 2);
        $invoices = $this->projects->invoiceSummary($projectId);
        $outstanding = Decimal::round(Decimal::sub($invoices['invoiced'], $invoices['paid'], 2), 2);
        if (Decimal::cmp($outstanding, '0') < 0) {
            $outstanding = '0.00';
        }
        $variance = Decimal::round(Decimal::sub($budget, $actual, 2), 2);

        return [
            'commercial_value' => $commercial,
            'estimated_cost' => $estimated,
            'actual_cost' => $actual,
            'job_cost' => Decimal::round($jobCost, 2),
            'direct_cost' => Decimal::round($direct, 2),
            'gross_profit' => $gp,
            'margin_percent' => $margin,
            'budget' => $budget,
            'budget_variance' => $variance,
            'invoiced' => $invoices['invoiced'],
            'paid' => $invoices['paid'],
            'outstanding' => $outstanding,
            'lines' => $this->projects->commercialLines($projectId, false),
            'allocations' => array_values(array_filter(
                $this->projects->commercialLines($projectId, false),
                static fn (array $line): bool => (int) $line['counts_as_value'] === 0 && (string) $line['source_type'] === 'ALLOCATION'
            )),
        ];
    }

    /**
     * Record an accepted project contract. Allocations explain the split and do not add value.
     *
     * @param list<array{site_id: int|null, job_id: int|null, amount: string, label: string}> $allocations
     */
    public function recordContract(int $projectId, string $amount, string $label, ?int $quoteId, array $allocations, ?int $supersedeLineId = null): int
    {
        if ($supersedeLineId !== null && $supersedeLineId > 0) {
            $this->projects->supersedeCommercial($supersedeLineId);
        }
        $lineId = $this->projects->insertCommercial([
            'project_id' => $projectId,
            'source_type' => 'CONTRACT_QUOTE',
            'source_id' => $quoteId,
            'project_site_id' => null,
            'job_id' => null,
            'label' => $label,
            'amount' => Decimal::round($amount, 2),
            'counts_as_value' => 1,
            'superseded' => 0,
        ]);
        foreach ($allocations as $allocation) {
            $this->projects->insertCommercial([
                'project_id' => $projectId,
                'source_type' => 'ALLOCATION',
                'source_id' => $lineId,
                'project_site_id' => $allocation['site_id'],
                'job_id' => $allocation['job_id'],
                'label' => $allocation['label'],
                'amount' => Decimal::round($allocation['amount'], 2),
                'counts_as_value' => 0,
                'superseded' => 0,
            ]);
        }

        return $lineId;
    }

    public function recordChangeValue(int $projectId, int $changeId, string $amount, string $label): void
    {
        if (Decimal::cmp($amount, '0') === 0) {
            return;
        }
        $this->projects->insertCommercial([
            'project_id' => $projectId,
            'source_type' => 'CHANGE',
            'source_id' => $changeId,
            'project_site_id' => null,
            'job_id' => null,
            'label' => $label,
            'amount' => Decimal::round($amount, 2),
            'counts_as_value' => 1,
            'superseded' => 0,
        ]);
    }
}

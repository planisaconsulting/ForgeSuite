<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\FinanceRepository;
use App\Repositories\JobRepository;

/**
 * Commercial value, invoiced value, cash received, and actual cost
 * are four different measurements. This report keeps them labelled apart.
 */
final class JobFinancialService
{
    public function __construct(
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly JobRepository $jobs = new JobRepository(),
        private readonly InvoiceService $invoices = new InvoiceService(),
        private readonly JobCostingService $costing = new JobCostingService()
    ) {
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
        $position = $this->invoices->position((int) $job['quote_id'], $jobId);
        $invoiced = $position['invoiced'];
        $paid = $this->finance->paidForJob($jobId);
        $outstanding = '0.00';
        foreach ($this->finance->invoices(['job_id' => $jobId], 100) as $invoice) {
            if (!in_array((string) $invoice['status'], ['DRAFT', 'CANCELLED'], true)) {
                $outstanding = Decimal::money(Decimal::add($outstanding, (string) $invoice['balance_due']));
            }
        }
        $cost = $this->costing->report($jobId);
        $actual = (string) ($cost['actual_total_cost'] ?? '0');
        $commercialProfit = Decimal::money(Decimal::sub($position['commercial'], $actual));
        $invoicedProfit = Decimal::money(Decimal::sub($invoiced, $actual));

        return [
            'quote_total' => $position['quote_total'],
            'variations' => $position['variations'],
            'commercial' => $position['commercial'],
            'invoiced' => $invoiced,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'remaining' => $position['remaining'],
            'actual_material_cost' => (string) ($cost['actual_material_cost'] ?? '0'),
            'actual_labour_cost' => (string) ($cost['actual_labour_cost'] ?? '0'),
            'actual_other_cost' => (string) ($cost['actual_other_cost'] ?? '0'),
            'actual_total_cost' => $actual,
            'commercial_profit' => $commercialProfit,
            'commercial_margin' => $this->margin($commercialProfit, $position['commercial']),
            'invoiced_profit' => $invoicedProfit,
            'invoiced_margin' => $this->margin($invoicedProfit, $invoiced),
            'invoices' => $this->finance->invoices(['job_id' => $jobId], 50),
            'variations_rows' => $this->finance->variations($jobId),
        ];
    }

    private function margin(string $profit, string $base): ?string
    {
        if (Decimal::cmp($base, '0') === 0) {
            return null;
        }

        return Decimal::round(Decimal::mul(Decimal::div($profit, $base), '100'), 2);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Estimated, committed, and actual stay separate.
 *
 * Estimated is the figure used to price the work.
 * Committed is reserved or purchased material.
 * Actual is what was consumed or timed.
 * A lower actual than estimate is favourable. It is not an accounting loss.
 */
final class CostStateService
{
    public function __construct(private readonly VarianceService $variance = new VarianceService())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function line(string $estimated, string $committed, string $actual): array
    {
        return [
            'estimated' => Decimal::money($this->number($estimated)),
            'committed' => Decimal::money($this->number($committed)),
            'actual' => Decimal::money($this->number($actual)),
            'actual_versus_estimated' => $this->variance->compare($this->number($estimated), $this->number($actual)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function quantities(string $billable, string $estimatedPhysical, ?string $actualPhysical): array
    {
        return [
            'billable' => Decimal::round($this->number($billable), 4),
            'estimated_physical' => Decimal::round($this->number($estimatedPhysical), 4),
            'actual_physical' => $actualPhysical === null || $actualPhysical === '' ? null : Decimal::round($this->number($actualPhysical), 4),
        ];
    }

    /**
     * Cost estimate variance is actual cost minus estimated cost.
     * It is not described as an accounting loss.
     *
     * @param list<array{estimated: string, actual: string}> $jobs
     * @return array<string, mixed>
     */
    public function estimateVariance(array $jobs): array
    {
        $difference = '0';
        $count = 0;
        foreach ($jobs as $job) {
            $row = $this->variance->compare((string) $job['estimated'], (string) $job['actual']);
            $difference = Decimal::add($difference, $row['variance'], Decimal::CALC_SCALE);
            $count++;
        }

        return [
            'label' => 'COST ESTIMATE VARIANCE',
            'jobs' => $count,
            'amount' => Decimal::money($difference),
        ];
    }

    private function number(string $value): string
    {
        return Decimal::isNumeric($value) ? $value : '0';
    }
}

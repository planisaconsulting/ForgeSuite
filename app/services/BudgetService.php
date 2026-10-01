<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ForecastRepository;

/**
 * Operational budget. Variance is actual minus target.
 * A negative variance means actual is below the target.
 */
final class BudgetService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        if (!can('budgets.manage')) {
            return ['errors' => ['_form' => 'You cannot edit the operational budget.'], 'id' => null];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['errors' => ['name' => 'Name the budget.'], 'id' => null];
        }
        $id = $this->planning->insertBudget([
            'name' => mb_substr($name, 0, 180),
            'financial_year' => mb_substr(trim((string) ($input['financial_year'] ?? date('Y'))), 0, 20),
            'status' => 'DRAFT',
            'created_by' => $userId,
        ]);
        $amount = Decimal::money((string) ($input['target_amount'] ?? '0'));
        $this->planning->insertBudgetLine([
            'budget_id' => $id,
            'period' => $this->period((string) ($input['period'] ?? date('Y-m-01'))),
            'metric_code' => strtoupper(trim((string) ($input['metric_code'] ?? 'INVOICED_VALUE'))),
            'target_amount' => $amount,
            'notes' => blank_to_null($input['notes'] ?? null),
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array{target: string, actual: string, variance: string, variance_percent: ?string, label: string}
     */
    public function compare(string $target, string $actual): array
    {
        $variance = PlanningMath::variance($actual, $target);

        return [
            'target' => Decimal::money($target),
            'actual' => Decimal::money($actual),
            'variance' => $variance['variance'],
            'variance_percent' => $variance['variance_percent'],
            'label' => 'OPERATIONAL BUDGET',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(int $budgetId): array
    {
        $rows = [];
        foreach ($this->planning->budgetLines($budgetId) as $line) {
            $period = (string) $line['period'];
            $end = (new \DateTimeImmutable($period))->modify('last day of this month')->format('Y-m-d');
            $actual = '0.00';
            if ((string) $line['metric_code'] === 'INVOICED_VALUE') {
                $actual = Decimal::money($this->planning->invoicedBetween($period, $end));
            }
            $compared = $this->compare((string) $line['target_amount'], $actual);
            $rows[] = $compared + [
                'period' => $period,
                'metric_code' => (string) $line['metric_code'],
            ];
        }

        return $rows;
    }

    private function period(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value . '-01';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return substr($value, 0, 7) . '-01';
        }

        return date('Y-m-01');
    }
}

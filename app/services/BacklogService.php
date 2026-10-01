<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ForecastRepository;

/**
 * Backlog is accepted commercial work that is not yet complete.
 *
 * For each job that is not COMPLETED or CANCELLED:
 *   commercial value = quoted_revenue_snapshot
 *   completed scope = commercial value × completed item quantity / total item quantity
 *   an item counts as complete only when its production_status is COMPLETE
 *   remaining backlog = commercial value − completed scope
 *
 * A job with no items keeps its full commercial value in the backlog.
 * Invoice payments and amounts invoiced do not change this figure.
 * Invoiced value is shown beside it and is not subtracted.
 */
final class BacklogService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @return array{total: string, rows: list<array<string, mixed>>, formula: string, generated_at: string}
     */
    public function report(): array
    {
        $rows = [];
        $total = '0.00';
        foreach ($this->planning->openJobs() as $job) {
            $commercial = Decimal::money((string) $job['quoted_revenue_snapshot']);
            $completed = $this->completedValue($commercial, (string) $job['item_quantity'], (string) $job['completed_quantity']);
            $remaining = Decimal::money(Decimal::sub($commercial, $completed));
            if (Decimal::cmp($remaining, '0') < 0) {
                $remaining = '0.00';
            }
            $total = Decimal::money(Decimal::add($total, $remaining));
            $rows[] = [
                'job_id' => (int) $job['id'],
                'job_number' => (string) $job['job_number'],
                'customer' => (string) ($job['company_name'] ?? ''),
                'status' => (string) $job['status'],
                'target_date' => (string) ($job['target_date'] ?? ''),
                'commercial' => $commercial,
                'completed_scope' => $completed,
                'backlog' => $remaining,
                'invoiced' => Decimal::money((string) $job['invoiced']),
                'bucket' => $this->bucket((string) ($job['target_date'] ?? '')),
            ];
        }

        return [
            'total' => $total,
            'rows' => $rows,
            'formula' => 'Remaining backlog = quoted commercial value × open item quantity / total item quantity. Payments are not used.',
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function completedValue(string $commercial, string $totalQuantity, string $completedQuantity): string
    {
        if (Decimal::cmp($totalQuantity, '0') <= 0) {
            return '0.00';
        }
        $ratio = Decimal::div($completedQuantity, $totalQuantity);

        return Decimal::money(Decimal::mul($commercial, $ratio));
    }

    public function bucket(string $target): string
    {
        if ($target === '') {
            return 'Unscheduled';
        }
        $today = new \DateTimeImmutable('today');
        $date = new \DateTimeImmutable($target);
        $weekEnd = $today->modify('sunday this week');
        $nextWeekEnd = $weekEnd->modify('+7 days');
        $monthEnd = $today->modify('last day of this month');
        $nextMonthEnd = $monthEnd->modify('last day of next month');
        if ($date <= $weekEnd) {
            return 'This week';
        }
        if ($date <= $nextWeekEnd) {
            return 'Next week';
        }
        if ($date <= $monthEnd) {
            return 'This month';
        }
        if ($date <= $nextMonthEnd) {
            return 'Next month';
        }

        return 'Later';
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\FinanceRepository;

/**
 * Ageing is based on the invoice due date, not the invoice date.
 * Current means a balance that is not yet due.
 */
final class DebtorAgeingService
{
    public function __construct(private readonly FinanceRepository $finance = new FinanceRepository())
    {
    }

    /**
     * @return array{buckets: array<string, string>, customers: list<array<string, mixed>>, lines: list<array<string, mixed>>}
     */
    public function report(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');
        $buckets = [
            'CURRENT' => '0.00',
            'DAYS_1_30' => '0.00',
            'DAYS_31_60' => '0.00',
            'DAYS_61_90' => '0.00',
            'DAYS_90_PLUS' => '0.00',
        ];
        $customers = [];
        $lines = [];
        foreach ($this->finance->ageingLines() as $invoice) {
            $due = (string) ($invoice['due_date'] ?? $today);
            $bucket = FinanceMath::ageingBucket($due, $today);
            $amount = Decimal::money((string) $invoice['balance_due']);
            $buckets[$bucket] = Decimal::money(Decimal::add($buckets[$bucket], $amount));
            $customerId = (int) $invoice['customer_id'];
            if (!isset($customers[$customerId])) {
                $customers[$customerId] = [
                    'customer_id' => $customerId,
                    'company_name' => $invoice['company_name'],
                    'first_name' => $invoice['first_name'],
                    'last_name' => $invoice['last_name'],
                    'customer_type' => $invoice['customer_type'],
                    'total' => '0.00',
                ];
            }
            $customers[$customerId]['total'] = Decimal::money(Decimal::add($customers[$customerId]['total'], $amount));
            $invoice['bucket'] = $bucket;
            $lines[] = $invoice;
        }
        usort($customers, static fn (array $a, array $b): int => Decimal::cmp((string) $b['total'], (string) $a['total']));

        return [
            'buckets' => $buckets,
            'customers' => array_values($customers),
            'lines' => $lines,
        ];
    }
}

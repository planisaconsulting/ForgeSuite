<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ForecastRepository;

/**
 * A potential invoice schedule. It does not create invoices.
 *
 * DEPOSIT is an unpaid deposit on the accepted quote.
 * JOB COMPLETION is the remaining commercial value dated by the job target date.
 * Issued balances stay on the cash screen as contractual receivables.
 */
final class InvoiceForecastService
{
    public function __construct(
        private readonly ForecastRepository $planning = new ForecastRepository(),
        private readonly InvoiceService $invoices = new InvoiceService()
    ) {
    }

    /**
     * @return array{lines: list<array<string, string>>, generated_at: string, assumptions: list<string>}
     */
    public function schedule(): array
    {
        $lines = [];
        foreach ($this->planning->openJobs() as $job) {
            $position = $this->invoices->position((int) ($job['quote_id'] ?? 0), (int) $job['id']);
            $remaining = (string) $position['remaining'];
            if (Decimal::cmp($remaining, '0') <= 0) {
                continue;
            }
            $invoiced = (string) $position['invoiced'];
            $deposit = (string) $position['deposit'];
            $depositLeft = '0.00';
            if (Decimal::cmp($deposit, $invoiced) > 0) {
                $depositLeft = Decimal::money(Decimal::sub($deposit, $invoiced));
                if (Decimal::cmp($depositLeft, $remaining) > 0) {
                    $depositLeft = $remaining;
                }
            }
            $date = (string) ($job['target_date'] ?? '');
            if (Decimal::cmp($depositLeft, '0') > 0) {
                $lines[] = $this->line((string) $job['job_number'], $depositLeft, $date, 'DEPOSIT');
                $rest = Decimal::money(Decimal::sub($remaining, $depositLeft));
                if (Decimal::cmp($rest, '0') > 0) {
                    $lines[] = $this->line((string) $job['job_number'], $rest, $date, 'JOB COMPLETION');
                }
                continue;
            }
            $lines[] = $this->line((string) $job['job_number'], $remaining, $date, 'JOB COMPLETION');
        }

        return [
            'lines' => $lines,
            'generated_at' => date('Y-m-d H:i:s'),
            'assumptions' => [
                'This is a potential invoice schedule. No invoice is created.',
                'A deposit line is the unpaid deposit on the accepted quote.',
                'The rest is dated by the job target date and labelled job completion.',
                'An issued invoice is a contractual receivable, not a line on this schedule.',
            ],
        ];
    }

    /**
     * @return array{job_number: string, amount: string, expected_date: string, basis: string}
     */
    private function line(string $jobNumber, string $amount, string $date, string $basis): array
    {
        return [
            'job_number' => $jobNumber,
            'amount' => Decimal::money($amount),
            'expected_date' => $date,
            'basis' => $basis,
        ];
    }
}

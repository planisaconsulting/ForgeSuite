<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;

/**
 * A statement is a running balance of issued invoices (debits),
 * credit notes (credits), and recorded payments (credits).
 */
final class StatementService
{
    public function __construct(
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(int $customerId, string $from, string $to): array
    {
        $customer = $this->customers->find($customerId);
        $rows = [];
        foreach ($this->finance->invoices(['customer_id' => $customerId], 500) as $invoice) {
            if (in_array((string) $invoice['status'], ['DRAFT', 'CANCELLED'], true) || $invoice['invoice_number'] === null) {
                continue;
            }
            $rows[] = [
                'date' => (string) $invoice['invoice_date'],
                'reference' => (string) $invoice['invoice_number'],
                'description' => 'Invoice',
                'debit' => Decimal::money((string) $invoice['total']),
                'credit' => '0.00',
            ];
        }
        foreach ($this->finance->credits(['customer_id' => $customerId], 500) as $note) {
            if (!in_array((string) $note['status'], ['ISSUED', 'APPLIED'], true)) {
                continue;
            }
            $rows[] = [
                'date' => (string) $note['credit_date'],
                'reference' => (string) ($note['credit_note_number'] ?? ''),
                'description' => 'Credit note',
                'debit' => '0.00',
                'credit' => Decimal::money((string) $note['total']),
            ];
        }
        foreach ($this->finance->payments(['customer_id' => $customerId], 500) as $payment) {
            if ((string) $payment['status'] !== 'RECORDED') {
                continue;
            }
            $rows[] = [
                'date' => (string) $payment['payment_date'],
                'reference' => (string) $payment['payment_reference'],
                'description' => 'Payment',
                'debit' => '0.00',
                'credit' => Decimal::money((string) $payment['amount']),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$a['date'], $a['reference']] <=> [$b['date'], $b['reference']]);
        $opening = '0.00';
        $period = [];
        foreach ($rows as $row) {
            $delta = Decimal::sub((string) $row['debit'], (string) $row['credit']);
            if ($row['date'] < $from) {
                $opening = Decimal::money(Decimal::add($opening, $delta));
                continue;
            }
            if ($row['date'] > $to) {
                continue;
            }
            $period[] = $row;
        }
        $running = $opening;
        foreach ($period as $index => $row) {
            $running = Decimal::money(Decimal::add($running, Decimal::sub((string) $row['debit'], (string) $row['credit'])));
            $period[$index]['balance'] = $running;
        }
        $account = $this->finance->account($customerId);

        return [
            'customer' => $customer,
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'closing' => $running,
            'rows' => $period,
            'overdue' => $account['overdue'],
        ];
    }
}

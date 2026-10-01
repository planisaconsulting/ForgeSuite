<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ForecastRepository;

/**
 * Contractual receivables are issued invoices.
 * Future invoices that have not been issued are a forecast and are not added to them.
 * This is an operational forecast, not a bank reconciliation.
 */
final class CashForecastService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(string $scenario = 'DUE_DATE'): array
    {
        $scenario = strtoupper($scenario);
        if (!in_array($scenario, ['DUE_DATE', 'HISTORICAL', 'MANUAL'], true)) {
            $scenario = 'DUE_DATE';
        }
        $receivables = '0.00';
        $lines = [];
        foreach ($this->planning->issuedReceivables() as $invoice) {
            $amount = Decimal::money((string) $invoice['balance_due']);
            $receivables = Decimal::money(Decimal::add($receivables, $amount));
            $due = (string) ($invoice['due_date'] ?? '');
            $shown = $due;
            if ($scenario === 'HISTORICAL' && $due !== '') {
                $days = $this->medianDays((int) $invoice['customer_id']);
                if ($days !== null) {
                    $shown = (new \DateTimeImmutable($due))->modify('+' . $days . ' days')->format('Y-m-d');
                }
            }
            $lines[] = [
                'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                'amount' => $amount,
                'due_date' => $due,
                'shown_date' => $shown,
                'basis' => 'CONTRACTUAL RECEIVABLE',
            ];
        }
        $purchasing = '0.00';
        $purchaseLines = [];
        foreach ($this->planning->openPurchaseOrders() as $order) {
            $amount = Decimal::money((string) $order['total']);
            $purchasing = Decimal::money(Decimal::add($purchasing, $amount));
            $terms = (string) ($order['payment_terms'] ?: $order['supplier_terms'] ?: '30 DAYS');
            $purchaseLines[] = [
                'po_number' => (string) $order['po_number'],
                'supplier' => (string) $order['supplier_name'],
                'amount' => $amount,
                'expected_date' => (string) ($order['expected_date'] ?? ''),
                'terms' => $terms,
                'label' => 'PURCHASING CASH REQUIREMENT',
            ];
        }
        $opening = $this->planning->latestCashPosition();
        $backlog = (new BacklogService($this->planning))->report();

        return [
            'warning' => 'OPERATIONAL FORECAST — NOT BANK RECONCILIATION',
            'scenario' => $scenario,
            'contractual_receivables' => $receivables,
            'receivable_lines' => $lines,
            'purchasing_cash' => $purchasing,
            'purchase_lines' => $purchaseLines,
            'forecast_future_invoices' => $backlog['total'],
            'forecast_note' => 'Remaining backlog is unissued commercial value. It is not an issued receivable.',
            'opening' => $opening,
            'assumptions' => [
                'Issued invoice balances use the due date stored on the invoice.',
                'Historical payment behaviour shifts the displayed date only in the historical scenario.',
                'It does not change the amount owed.',
                'Unissued work is not counted as cash already due.',
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function medianDays(int $customerId): ?int
    {
        $samples = [];
        foreach ($this->planning->paymentDays($customerId) as $row) {
            if ($row['days'] !== null) {
                $samples[] = (int) $row['days'];
            }
        }
        if ($samples === []) {
            return null;
        }
        sort($samples);
        $mid = intdiv(count($samples), 2);
        if (count($samples) % 2 === 1) {
            return $samples[$mid];
        }

        return (int) round(($samples[$mid - 1] + $samples[$mid]) / 2);
    }
}

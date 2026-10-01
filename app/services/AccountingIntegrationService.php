<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForecastRepository;

/**
 * Accounting sync sits beside the invoice.
 * A provider failure is logged. The issued invoice is left as it is.
 */
final class AccountingIntegrationService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    public function afterInvoiceIssued(int $invoiceId): void
    {
        $provider = trim((string) SettingsService::get('accounting_provider', ''));
        if ($provider === '' || strtolower($provider) === 'none') {
            return;
        }
        try {
            if (strtolower($provider) === 'unavailable') {
                throw new \RuntimeException('Accounting provider is unavailable');
            }
            $this->planning->upsertMapping([
                'provider' => $provider,
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'external_id' => null,
                'sync_status' => 'PENDING',
                'last_synced_at' => null,
            ]);
            $this->planning->insertSyncLog([
                'provider' => $provider,
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'direction' => 'OUTBOUND',
                'status' => 'PENDING',
                'message' => 'No live provider is connected. Export the invoice when a mapping is configured.',
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $this->planning->upsertMapping([
                'provider' => $provider,
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'external_id' => null,
                'sync_status' => 'FAILED',
                'last_synced_at' => null,
            ]);
            $this->planning->insertSyncLog([
                'provider' => $provider,
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'direction' => 'OUTBOUND',
                'status' => 'FAILED',
                'message' => mb_substr($e->getMessage(), 0, 255),
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            try {
                (new \App\Repositories\PlatformRepository())->insertIssue([
                    'provider' => 'accounting',
                    'entity_type' => 'INVOICE',
                    'entity_id' => $invoiceId,
                    'failure' => mb_substr($e->getMessage(), 0, 255),
                    'attempts' => 1,
                    'max_attempts' => 3,
                    'safe_retry' => 0,
                    'idempotency_key' => 'accounting:INVOICE:' . $invoiceId,
                    'status' => 'OPEN',
                    'next_retry_at' => null,
                ]);
            } catch (\Throwable) {
                // The invoice stays issued even when the issue queue cannot be written.
            }
        }
    }

    /**
     * @return list<string>
     */
    public function exportColumns(): array
    {
        return [
            'customer_name',
            'invoice_number',
            'invoice_date',
            'due_date',
            'subtotal',
            'vat_amount',
            'total',
            'amount_paid',
            'balance_due',
            'reference',
        ];
    }
}

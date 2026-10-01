<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\QuoteRepository;
use App\Repositories\WorkflowRepository;

/**
 * Facts come from fixed queries. Workflow configuration cannot add SQL.
 */
final class WorkflowFactReader
{
    /** @var list<string> */
    public const ENTITIES = [
        'CUSTOMER', 'CONTACT', 'LEAD', 'OPPORTUNITY', 'QUOTE', 'JOB', 'SITE_SURVEY',
        'PRODUCT', 'SUPPLIER', 'INVENTORY_ITEM', 'INSTALLATION', 'INVOICE', 'PURCHASE_ORDER', 'PAYMENT',
    ];

    public function __construct(
        private readonly QuoteRepository $quotes = new QuoteRepository(),
        private readonly WorkflowRepository $workflows = new WorkflowRepository()
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function read(string $entityType, int $entityId): array
    {
        $entityType = strtoupper($entityType);
        if ($entityId < 1) {
            return [];
        }

        return match ($entityType) {
            'QUOTE' => $this->quote($entityId),
            'JOB' => $this->workflows->scalarFacts('jobs', $entityId, ['status', 'priority', 'customer_id']),
            'INVOICE' => $this->workflows->scalarFacts('invoices', $entityId, ['status', 'total', 'balance_due', 'customer_id']),
            'CUSTOMER' => $this->workflows->scalarFacts('customers', $entityId, ['customer_type', 'email']),
            'LEAD' => $this->workflows->scalarFacts('leads', $entityId, ['status']),
            'OPPORTUNITY' => $this->workflows->scalarFacts('sales_opportunities', $entityId, ['status', 'estimated_value']),
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function quote(int $id): array
    {
        $quote = $this->quotes->find($id);
        if ($quote === null) {
            return [];
        }
        $summary = (new QuoteTotals())->summarise($this->quotes->items($id), $quote);
        $margin = $summary['gross_margin_percent'];

        return [
            'status' => (string) $quote['status'],
            'total' => Decimal::money((string) $quote['total']),
            'customer_id' => (string) $quote['customer_id'],
            'discount_amount' => Decimal::money((string) $quote['discount_amount']),
            'deposit_amount' => Decimal::money((string) $quote['deposit_amount']),
            'margin_percent' => $margin === null ? '' : (string) $margin,
        ];
    }

}

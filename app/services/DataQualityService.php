<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForecastRepository;

/**
 * Data-quality issues are listed with a link. Nothing is corrected automatically.
 */
final class DataQualityService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @return list<array{issue: string, action: string, href: string, label: string}>
     */
    public function issues(): array
    {
        $rows = [];
        foreach ($this->planning->dataQualityCustomers() as $row) {
            $rows[] = $this->item('Customer has no email or phone', 'Add a contact detail', '/customers/' . $row['id'], (string) ($row['company_name'] ?: 'Customer ' . $row['id']));
        }
        foreach ($this->planning->dataQualityProducts() as $row) {
            $rows[] = $this->item('Product has no supplier cost', 'Enter a cost price', '/products/' . $row['id'], (string) $row['name']);
        }
        foreach ($this->planning->dataQualityJobs() as $row) {
            $rows[] = $this->item('Job has no target date', 'Set a target date', '/jobs/' . $row['id'], (string) $row['job_number']);
        }
        foreach ($this->planning->dataQualityInvoices() as $row) {
            $rows[] = $this->item('Issued invoice has no due date', 'Set a due date', '/invoices/' . $row['id'], (string) ($row['invoice_number'] ?: 'Invoice ' . $row['id']));
        }
        foreach ($this->planning->dataQualityOpportunities() as $row) {
            $rows[] = $this->item('Opportunity has no next action', 'Set a follow-up date', '/opportunities/' . $row['id'], (string) $row['opportunity_number']);
        }
        foreach ($this->planning->negativeStock() as $row) {
            $rows[] = $this->item('Inventory remaining quantity is negative', 'Review the stock ledger', '/inventory/' . $row['id'], (string) $row['inventory_code']);
        }

        return $rows;
    }

    /**
     * @return array{issue: string, action: string, href: string, label: string}
     */
    private function item(string $issue, string $action, string $href, string $label): array
    {
        return ['issue' => $issue, 'action' => $action, 'href' => $href, 'label' => $label];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Repository;

/**
 * First-touch attribution.
 *
 * The lead source and campaign are copied forward once.
 * Later touches do not replace them. This is not multi-touch attribution.
 */
final class AttributionService extends Repository
{
    public function stampOpportunity(int $opportunityId, int $leadId, ?int $campaignId): void
    {
        $this->run(
            'UPDATE sales_opportunities
             SET lead_id = ?, campaign_id = ?, attribution_model = \'FIRST_TOUCH\'
             WHERE id = ? AND lead_id IS NULL',
            [$leadId, $campaignId, $opportunityId]
        );
    }

    public function copyToQuote(int $quoteId): void
    {
        $this->run(
            'UPDATE quotes q
             INNER JOIN sales_opportunities o ON o.id = q.opportunity_id
             SET q.lead_id = o.lead_id,
                 q.campaign_id = o.campaign_id,
                 q.attribution_source = o.source,
                 q.attribution_model = \'FIRST_TOUCH\'
             WHERE q.id = ? AND q.lead_id IS NULL AND o.lead_id IS NOT NULL',
            [$quoteId]
        );
    }

    public function copyToJob(int $jobId): void
    {
        $this->run(
            'UPDATE jobs j
             INNER JOIN quotes q ON q.id = j.quote_id
             SET j.lead_id = q.lead_id,
                 j.campaign_id = q.campaign_id,
                 j.attribution_source = q.attribution_source,
                 j.attribution_model = \'FIRST_TOUCH\'
             WHERE j.id = ? AND j.lead_id IS NULL AND q.lead_id IS NOT NULL',
            [$jobId]
        );
    }

    public function copyToInvoice(int $invoiceId): void
    {
        $this->run(
            'UPDATE invoices i
             INNER JOIN jobs j ON j.id = i.job_id
             SET i.lead_id = j.lead_id,
                 i.campaign_id = j.campaign_id,
                 i.attribution_source = j.attribution_source,
                 i.attribution_model = \'FIRST_TOUCH\'
             WHERE i.id = ? AND i.lead_id IS NULL AND j.lead_id IS NOT NULL',
            [$invoiceId]
        );
    }
}

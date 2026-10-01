<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Shared words for the business. The figures are not interchangeable.
 */
final class DataDictionary
{
    /**
     * @return list<array{term: string, meaning: string}>
     */
    public static function financial(): array
    {
        return [
            ['term' => 'REVENUE / INVOICED VALUE', 'meaning' => 'The total on issued invoices. A quotation total is not revenue.'],
            ['term' => 'PAYMENTS / CASH RECEIVED', 'meaning' => 'Money recorded against a customer. It is not the same as invoiced value.'],
            ['term' => 'COMMERCIAL VALUE', 'meaning' => 'The quoted value of the job, kept as a snapshot. It is not cash received.'],
            ['term' => 'COST', 'meaning' => 'What the work costs the business. It is not the selling price.'],
            ['term' => 'GROSS PROFIT', 'meaning' => 'Commercial value or revenue minus cost, as labelled on the screen.'],
            ['term' => 'GROSS MARGIN', 'meaning' => 'Gross profit divided by the selling base, as a percentage. It is not markup.'],
            ['term' => 'MARKUP', 'meaning' => 'The percentage added to cost to reach a selling price. It is not margin.'],
            ['term' => 'WEIGHTED PIPELINE', 'meaning' => 'Open opportunity value times the probability someone entered. It is not expected revenue.'],
        ];
    }

    /**
     * @return list<array{term: string, meaning: string}>
     */
    public static function operations(): array
    {
        return [
            ['term' => 'Job status', 'meaning' => 'The operational state. A workflow can request a change only through the job service.'],
            ['term' => 'Inventory unit', 'meaning' => 'Stock is a ledger of movements. There is no editable quantity column on the product.'],
            ['term' => 'Forecast', 'meaning' => 'A projection. It does not reserve stock or create an invoice.'],
            ['term' => 'Approval', 'meaning' => 'A held decision. Escalation sends a reminder. It does not approve the request.'],
        ];
    }
}

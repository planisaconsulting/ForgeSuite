<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ForecastRepository;

/**
 * Pipeline figures stay in separate buckets.
 * Weighted pipeline is estimated value times the salesperson probability.
 * It is not expected revenue and it is not accepted work.
 */
final class SalesForecastService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(string $horizonCode): array
    {
        $horizon = PlanningMath::horizon($horizonCode);
        $raw = '0.00';
        $weighted = '0.00';
        $rows = [];
        foreach ($this->planning->openOpportunities() as $row) {
            $value = Decimal::money((string) ($row['estimated_value'] ?? '0'));
            $weight = PlanningMath::weighted($value, $row['probability_percent'] !== null ? (string) $row['probability_percent'] : null);
            $raw = Decimal::money(Decimal::add($raw, $value));
            $weighted = Decimal::money(Decimal::add($weighted, $weight));
            $rows[] = [
                'number' => (string) $row['opportunity_number'],
                'title' => (string) $row['title'],
                'status' => (string) $row['status'],
                'value' => $value,
                'probability' => $row['probability_percent'],
                'weighted' => $weight,
                'close' => (string) ($row['expected_close_date'] ?? ''),
                'salesperson' => (string) ($row['salesperson'] ?? ''),
                'customer' => (string) ($row['company_name'] ?? ''),
                'bucket' => (string) $row['status'] === 'QUOTED' ? 'Quoted pipeline' : 'Open pipeline',
            ];
        }
        $quotes = [];
        $quoted = '0.00';
        foreach ($this->planning->openQuotes() as $quote) {
            $total = Decimal::money((string) $quote['total']);
            $quoted = Decimal::money(Decimal::add($quoted, $total));
            $quotes[] = [
                'number' => (string) $quote['quote_number'],
                'total' => $total,
                'status' => (string) $quote['status'],
                'sent' => (string) ($quote['quote_date'] ?? ''),
                'expiry' => (string) ($quote['expiry_date'] ?? ''),
                'decision' => (string) ($quote['expected_decision_date'] ?? ''),
            ];
        }
        $context = $this->planning->conversionContext();
        $rate = $context['decided'] > 0
            ? Decimal::round(Decimal::mul(Decimal::div((string) $context['won'], (string) $context['decided']), '100'), 2)
            : null;

        return [
            'label' => 'WEIGHTED PIPELINE',
            'raw_pipeline' => $raw,
            'weighted_pipeline' => $weighted,
            'quoted_pipeline' => $quoted,
            'rows' => $rows,
            'quotes' => $quotes,
            'historical_conversion_percent' => $rate,
            'historical_sample' => $context['decided'],
            'assumptions' => [
                'Probability is the figure entered on the opportunity. It is not generated.',
                'Quote age does not assign a probability.',
                'Historical conversion is context from decided quotes. It is not applied to the weighted total.',
            ],
            'horizon' => $horizon,
            'generated_at' => date('Y-m-d H:i:s'),
            'source' => 'Open opportunities and open quotes',
        ];
    }
}

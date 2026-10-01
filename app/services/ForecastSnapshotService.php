<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForecastRepository;

/**
 * A snapshot is kept. A later forecast does not replace it.
 */
final class ForecastSnapshotService
{
    public function __construct(private readonly ForecastRepository $planning = new ForecastRepository())
    {
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $summary
     */
    public function store(string $type, string $asOf, string $start, string $end, array $parameters, array $summary, ?int $userId): int
    {
        return $this->planning->insertSnapshot([
            'forecast_type' => $type,
            'as_of_date' => $asOf,
            'horizon_start' => $start,
            'horizon_end' => $end,
            'parameters_json' => json_encode($parameters, JSON_THROW_ON_ERROR),
            'result_summary_json' => json_encode($summary, JSON_THROW_ON_ERROR),
            'generated_by' => $userId,
        ]);
    }

    /**
     * @return array{forecast: string, actual: string, difference: string}|null
     */
    public function accuracy(string $forecast, string $actual): array
    {
        $variance = PlanningMath::variance($actual, $forecast);

        return [
            'forecast' => $forecast,
            'actual' => $actual,
            'difference' => $variance['variance'],
        ];
    }
}

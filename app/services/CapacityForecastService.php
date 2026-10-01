<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Capacity stays in four labelled piles: available, scheduled, unscheduled committed, and forecast.
 * Forecast pipeline hours are not part of committed workload.
 */
final class CapacityForecastService
{
    /**
     * @return array<string, string|null>
     */
    public function summarise(string $availableHours, string $scheduledHours, string $unscheduledHours, string $forecastHours): array
    {
        $committed = PlanningMath::committedCapacity($availableHours, $scheduledHours, $unscheduledHours);

        return [
            'available_hours' => $availableHours,
            'scheduled_hours' => $scheduledHours,
            'unscheduled_committed_hours' => $unscheduledHours,
            'forecast_hours' => $forecastHours,
            'committed_hours' => $committed['committed_hours'],
            'remaining_committed_hours' => $committed['remaining_hours'],
            'shortfall_hours' => $committed['shortfall_hours'],
            'bottleneck' => $committed['shortfall_hours'] !== '0.00' ? 'PROJECTED BOTTLENECK' : '',
            'generated_at' => date('Y-m-d H:i:s'),
            'assumptions' => 'Forecast hours are open pipeline labour and are not subtracted from remaining committed capacity.',
        ];
    }
}

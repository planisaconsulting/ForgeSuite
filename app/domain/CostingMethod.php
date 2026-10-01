<?php

declare(strict_types=1);

namespace App\Domain;

enum CostingMethod: string
{
    case LastCost = 'LAST_COST';
    case WeightedAverage = 'WEIGHTED_AVERAGE_COST';

    public function label(): string
    {
        return match ($this) {
            self::LastCost => 'Last cost',
            self::WeightedAverage => 'Weighted average cost',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain;

enum OffcutValuation: string
{
    case FullCost = 'FULL_COST';
    case ReducedCost = 'REDUCED_COST';
    case ZeroCost = 'ZERO_COST';

    public function label(): string
    {
        return match ($this) {
            self::FullCost => 'Full acquisition cost',
            self::ReducedCost => 'Reduced cost',
            self::ZeroCost => 'Zero cost',
        };
    }
}

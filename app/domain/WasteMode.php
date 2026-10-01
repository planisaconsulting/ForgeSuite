<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * What the operator decided to charge for unused roll width.
 *
 * This is not manufacturing waste. Manufacturing waste is standard_waste_percent.
 * ACTUAL is "don't charge wastage". CONSUMED_WIDTH is "charge wastage".
 */
enum WasteMode: string
{
    case Actual = 'ACTUAL';
    case ConsumedWidth = 'CONSUMED_WIDTH';
    case Manual = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::Actual => 'Actual material only',
            self::ConsumedWidth => 'Charge consumed roll width',
            self::Manual => 'Manual override',
        };
    }
}

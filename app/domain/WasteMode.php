<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * What the operator decided to charge for unused material.
 *
 * This is not manufacturing waste. Manufacturing waste is standard_waste_percent
 * and is applied after this choice.
 *
 * ACTUAL          charge only the material the art needs
 * CONSUMED_WIDTH  charge the full roll width for the print length
 * FULL_SHEET      charge every sheet the pieces occupy
 * MANUAL          charge a width or area the operator typed
 *
 * The waste threshold never selects one of these. It only warns.
 */
enum WasteMode: string
{
    case Actual = 'ACTUAL';
    case ConsumedWidth = 'CONSUMED_WIDTH';
    case FullSheet = 'FULL_SHEET';
    case Manual = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::Actual => 'Charge actual material',
            self::ConsumedWidth => 'Charge consumed roll width',
            self::FullSheet => 'Charge full sheet',
            self::Manual => 'Manual measure',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

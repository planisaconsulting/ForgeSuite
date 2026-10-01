<?php

declare(strict_types=1);

namespace App\Domain;

enum MaterialSource: string
{
    case Quote = 'QUOTE';
    case Calculated = 'CALCULATED';
    case Manual = 'MANUAL';
    case Recipe = 'RECIPE';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'From the accepted quote',
            self::Calculated => 'Calculated',
            self::Manual => 'Manual',
            self::Recipe => 'Recipe',
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

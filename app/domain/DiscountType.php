<?php

declare(strict_types=1);

namespace App\Domain;

enum DiscountType: string
{
    case None = 'NONE';
    case Percentage = 'PERCENTAGE';
    case Fixed = 'FIXED_AMOUNT';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No discount',
            self::Percentage => 'Percentage',
            self::Fixed => 'Fixed amount',
        };
    }

    public static function normalise(string $value): string
    {
        $value = strtoupper(trim($value));

        return in_array($value, ['PERCENTAGE', 'FIXED_AMOUNT'], true) ? $value : self::None->value;
    }
}

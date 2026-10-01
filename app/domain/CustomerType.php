<?php

declare(strict_types=1);

namespace App\Domain;

enum CustomerType: string
{
    case Business = 'BUSINESS';
    case Individual = 'INDIVIDUAL';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Business',
            self::Individual => 'Individual',
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

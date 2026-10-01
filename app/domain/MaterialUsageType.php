<?php

declare(strict_types=1);

namespace App\Domain;

enum MaterialUsageType: string
{
    case Production = 'PRODUCTION';
    case Waste = 'WASTE';
    case Rework = 'REWORK';
    case TestPrint = 'TEST_PRINT';
    case Damage = 'DAMAGE';
    case Installation = 'INSTALLATION';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Waste => 'Waste',
            self::Rework => 'Rework',
            self::TestPrint => 'Test print',
            self::Damage => 'Damage',
            self::Installation => 'Installation',
            self::Other => 'Other',
        };
    }

    public function needsReason(): bool
    {
        return in_array($this, [self::Waste, self::Rework, self::TestPrint, self::Damage], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}

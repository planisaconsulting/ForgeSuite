<?php

declare(strict_types=1);

namespace App\Domain;

enum WasteReason: string
{
    case Trim = 'TRIM';
    case PrintError = 'PRINT_ERROR';
    case Damage = 'DAMAGE';
    case ColourTest = 'COLOUR_TEST';
    case Offcut = 'OFFCUT';
    case Rework = 'REWORK';
    case Normal = 'NORMAL';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Trim => 'Trim',
            self::PrintError => 'Print error',
            self::Damage => 'Damage',
            self::ColourTest => 'Colour test',
            self::Offcut => 'Offcut',
            self::Rework => 'Rework',
            self::Normal => 'Normal production',
            self::Other => 'Other',
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

<?php

declare(strict_types=1);

namespace App\Domain;

enum WorkType: string
{
    case Design = 'DESIGN';
    case Printing = 'PRINTING';
    case Fabrication = 'FABRICATION';
    case Cnc = 'CNC';
    case Assembly = 'ASSEMBLY';
    case Electrical = 'ELECTRICAL';
    case Installation = 'INSTALLATION';
    case Admin = 'ADMIN';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Design => 'Design',
            self::Printing => 'Printing',
            self::Fabrication => 'Fabrication',
            self::Cnc => 'CNC',
            self::Assembly => 'Assembly',
            self::Electrical => 'Electrical',
            self::Installation => 'Installation',
            self::Admin => 'Admin',
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

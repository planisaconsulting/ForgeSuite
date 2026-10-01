<?php

declare(strict_types=1);

namespace App\Domain;

enum TaskType: string
{
    case Design = 'DESIGN';
    case Print = 'PRINT';
    case Laminate = 'LAMINATE';
    case Cut = 'CUT';
    case Cnc = 'CNC';
    case Fabrication = 'FABRICATION';
    case Welding = 'WELDING';
    case Painting = 'PAINTING';
    case Assembly = 'ASSEMBLY';
    case Electrical = 'ELECTRICAL';
    case Packing = 'PACKING';
    case Installation = 'INSTALLATION';
    case Delivery = 'DELIVERY';
    case Admin = 'ADMIN';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Design => 'Design',
            self::Print => 'Print',
            self::Laminate => 'Laminate',
            self::Cut => 'Cut',
            self::Cnc => 'CNC',
            self::Fabrication => 'Fabrication',
            self::Welding => 'Welding',
            self::Painting => 'Painting',
            self::Assembly => 'Assembly',
            self::Electrical => 'Electrical',
            self::Packing => 'Packing',
            self::Installation => 'Installation',
            self::Delivery => 'Delivery',
            self::Admin => 'Admin',
            self::Other => 'Other',
        };
    }

    public static function fromStageName(string $name): string
    {
        $key = strtoupper(trim($name));

        return match ($key) {
            'ARTWORK' => self::Design->value,
            'PRINTING' => self::Print->value,
            'LAMINATION' => self::Laminate->value,
            'CUTTING' => self::Cut->value,
            'CNC' => self::Cnc->value,
            'FABRICATION' => self::Fabrication->value,
            'PAINTING' => self::Painting->value,
            'ASSEMBLY' => self::Assembly->value,
            'ELECTRICAL' => self::Electrical->value,
            'QUALITY CONTROL' => self::Admin->value,
            'PACKING' => self::Packing->value,
            'INSTALLATION' => self::Installation->value,
            default => self::Other->value,
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

<?php

declare(strict_types=1);

namespace App\Domain;

enum OtherCostType: string
{
    case Courier = 'COURIER';
    case EquipmentHire = 'EQUIPMENT_HIRE';
    case Subcontractor = 'SUBCONTRACTOR';
    case Travel = 'TRAVEL';
    case Accommodation = 'ACCOMMODATION';
    case SpecialHardware = 'SPECIAL_HARDWARE';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Courier => 'Courier',
            self::EquipmentHire => 'Equipment hire',
            self::Subcontractor => 'Subcontractor',
            self::Travel => 'Travel',
            self::Accommodation => 'Accommodation',
            self::SpecialHardware => 'Special hardware',
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

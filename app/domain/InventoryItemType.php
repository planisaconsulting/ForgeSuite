<?php

declare(strict_types=1);

namespace App\Domain;

enum InventoryItemType: string
{
    case Roll = 'ROLL';
    case Sheet = 'SHEET';
    case Offcut = 'OFFCUT';
    case Batch = 'BATCH';
    case UnitGroup = 'UNIT_GROUP';

    public function label(): string
    {
        return match ($this) {
            self::Roll => 'Roll',
            self::Sheet => 'Sheet',
            self::Offcut => 'Offcut',
            self::Batch => 'Batch',
            self::UnitGroup => 'Unit group',
        };
    }
}

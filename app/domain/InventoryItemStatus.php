<?php

declare(strict_types=1);

namespace App\Domain;

enum InventoryItemStatus: string
{
    case Available = 'AVAILABLE';
    case Reserved = 'RESERVED';
    case Consumed = 'CONSUMED';
    case Discarded = 'DISCARDED';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::Consumed => 'Consumed',
            self::Discarded => 'Discarded',
        };
    }
}

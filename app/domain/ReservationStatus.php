<?php

declare(strict_types=1);

namespace App\Domain;

enum ReservationStatus: string
{
    case Reserved = 'RESERVED';
    case Consumed = 'CONSUMED';
    case Released = 'RELEASED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Reserved',
            self::Consumed => 'Consumed',
            self::Released => 'Released',
            self::Cancelled => 'Cancelled',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Domain;

enum PaymentStatus: string
{
    case Recorded = 'RECORDED';
    case Reversed = 'REVERSED';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::Reversed => 'Reversed',
        };
    }
}

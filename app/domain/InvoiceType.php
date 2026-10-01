<?php

declare(strict_types=1);

namespace App\Domain;

enum InvoiceType: string
{
    case Deposit = 'DEPOSIT';
    case Progress = 'PROGRESS';
    case Final = 'FINAL';
    case Standard = 'STANDARD';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Progress => 'Progress',
            self::Final => 'Final',
            self::Standard => 'Standard',
        };
    }
}

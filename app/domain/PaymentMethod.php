<?php

declare(strict_types=1);

namespace App\Domain;

enum PaymentMethod: string
{
    case Eft = 'EFT';
    case Cash = 'CASH';
    case Card = 'CARD';
    case BankDeposit = 'BANK_DEPOSIT';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Eft => 'EFT',
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::BankDeposit => 'Bank deposit',
            self::Other => 'Other',
        };
    }
}

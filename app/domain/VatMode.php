<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * How a quote presents VAT.
 *
 * Line prices are always stored excluding VAT. EXCLUSIVE adds VAT on the
 * quote total. INCLUSIVE shows the customer a gross total, but the stored
 * line totals stay net so tax is never applied twice.
 */
enum VatMode: string
{
    case Exclusive = 'EXCLUSIVE';
    case Inclusive = 'INCLUSIVE';

    public function label(): string
    {
        return match ($this) {
            self::Exclusive => 'VAT exclusive',
            self::Inclusive => 'VAT inclusive',
        };
    }
}

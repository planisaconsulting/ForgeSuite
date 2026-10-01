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
    case NoVat = 'NO_VAT';

    public function label(): string
    {
        return match ($this) {
            self::Exclusive => 'VAT exclusive',
            self::Inclusive => 'VAT inclusive',
            self::NoVat => 'No VAT',
        };
    }

    /**
     * Accepts the Phase 1 codes and the longer names from the sales spec.
     */
    public static function normalise(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'VAT_INCLUSIVE', 'INCLUSIVE' => self::Inclusive->value,
            'NO_VAT', 'NONE' => self::NoVat->value,
            default => self::Exclusive->value,
        };
    }
}

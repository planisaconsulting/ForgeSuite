<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Stock maths that does not touch the database.
 *
 * On hand is the sum of signed movements. Reserved stock is still on hand.
 * Available is on hand minus open reservations. Consuming a reservation
 * reduces on hand and removes the reservation, so available is not reduced twice.
 *
 * Weighted average applies to bulk quantity, unit, and area stock:
 * (existing quantity × existing unit cost + receipt quantity × receipt unit cost)
 * / (existing quantity + receipt quantity).
 * Rolls, sheets, and lengths keep the acquisition cost on the inventory item.
 *
 * Waste rate is waste / (production + waste). It is blank when nothing was used.
 */
final class StockValuation
{
    public static function available(string $onHand, string $reserved): string
    {
        $available = Decimal::sub($onHand, $reserved, 4);
        if (Decimal::cmp($available, '0') < 0) {
            return '0.0000';
        }

        return $available;
    }

    public static function weightedAverage(string $existingQty, string $existingUnitCost, string $receiptQty, string $receiptUnitCost): string
    {
        $totalQty = Decimal::add($existingQty, $receiptQty, 4);
        if (Decimal::cmp($totalQty, '0') === 0) {
            return Decimal::round($receiptUnitCost, 4);
        }
        $value = Decimal::add(
            Decimal::mul($existingQty, $existingUnitCost),
            Decimal::mul($receiptQty, $receiptUnitCost)
        );

        return Decimal::round(Decimal::div($value, $totalQty), 4);
    }

    public static function wasteRate(string $production, string $waste): ?string
    {
        $base = Decimal::add($production, $waste, 4);
        if (Decimal::cmp($base, '0') === 0) {
            return null;
        }

        return Decimal::round(Decimal::mul(Decimal::div($waste, $base), '100'), 2);
    }

    public static function offcutUnitCost(string $acquisition, string $treatment, string $percent): string
    {
        if ($treatment === 'ZERO_COST') {
            return '0.0000';
        }
        if ($treatment === 'REDUCED_COST') {
            $rate = Decimal::div($percent, '100', 8);

            return Decimal::round(Decimal::mul($acquisition, $rate), 4);
        }

        return Decimal::round($acquisition, 4);
    }
}

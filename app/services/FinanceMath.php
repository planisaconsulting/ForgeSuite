<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\InvoiceStatus;
use App\Domain\VatMode;
use App\Helpers\Decimal;

/**
 * Invoice arithmetic.
 *
 * Money is DECIMAL rounded to cents with Decimal::money (half away from zero
 * at the stored scale). VAT is calculated once on the discounted document
 * subtotal, using the same rules as quotations:
 * exclusive adds VAT on top, inclusive treats the subtotal as the amount
 * the customer pays and lifts VAT out of it, and no VAT leaves that subtotal
 * as the total.
 *
 * Line VAT shares are portions of that single VAT amount. The last line
 * takes the remainder so the lines add back to the document VAT and total.
 */
final class FinanceMath
{
    /**
     * @param list<array<string, mixed>> $lines Each line needs line_subtotal, or quantity and unit_price.
     * @return array<string, mixed>
     */
    public static function document(array $lines, string $discountType, string $discountValue, string $vatMode, string $vatRate): array
    {
        $prepared = [];
        foreach ($lines as $line) {
            $subtotal = isset($line['line_subtotal']) && (string) $line['line_subtotal'] !== ''
                ? Decimal::money((string) $line['line_subtotal'])
                : Decimal::money(Decimal::mul((string) ($line['quantity'] ?? '0'), (string) ($line['unit_price'] ?? '0')));
            $discount = Decimal::money((string) ($line['discount_amount'] ?? '0'));
            if (Decimal::cmp($discount, $subtotal) > 0) {
                $discount = $subtotal;
            }
            $net = Decimal::money(Decimal::sub($subtotal, $discount));
            $prepared[] = array_merge($line, [
                'line_subtotal' => $net,
                'line_total' => $net,
                'discount_amount' => $discount,
            ]);
        }

        $summary = (new QuoteTotals())->summarise($prepared, [
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'vat_mode' => $vatMode,
            'vat_rate' => $vatRate,
        ]);
        $mode = (string) $summary['vat_mode'];
        $shares = self::shares($prepared, (string) $summary['vat_amount'], (string) $summary['total'], $mode);
        foreach ($prepared as $index => $line) {
            $prepared[$index]['vat_rate_snapshot'] = (string) $summary['vat_rate'];
            $prepared[$index]['vat_amount'] = $shares[$index]['vat'];
            $prepared[$index]['line_total'] = $shares[$index]['total'];
        }

        return [
            'subtotal' => (string) $summary['subtotal'],
            'discount_type' => (string) $summary['discount_type'],
            'discount_value' => (string) $summary['discount_value'],
            'discount_amount' => (string) $summary['discount_amount'],
            'subtotal_after_discount' => (string) $summary['subtotal_after_discount'],
            'vat_mode' => $mode,
            'vat_rate' => (string) $summary['vat_rate'],
            'vat_amount' => (string) $summary['vat_amount'],
            'total' => (string) $summary['total'],
            'lines' => $prepared,
        ];
    }

    /**
     * Net line amount that produces a customer total of $target under the VAT mode.
     */
    public static function netForCustomerTotal(string $target, string $vatMode, string $vatRate): string
    {
        $target = Decimal::money($target);
        $mode = VatMode::normalise($vatMode);
        if ($mode !== VatMode::Exclusive->value || Decimal::cmp($vatRate, '0') === 0) {
            return $target;
        }
        $factor = Decimal::add('1', Decimal::div($vatRate, '100', 8));
        $guess = Decimal::money(Decimal::div($target, $factor, 8));
        for ($step = 0; $step < 6; $step++) {
            $document = self::document([['line_subtotal' => $guess]], 'NONE', '0', $mode, $vatRate);
            $cmp = Decimal::cmp((string) $document['total'], $target);
            if ($cmp === 0) {
                return $guess;
            }
            $guess = Decimal::money(Decimal::add($guess, $cmp < 0 ? '0.01' : '-0.01'));
        }

        return $guess;
    }

    public static function balance(string $total, string $paid, string $credited): string
    {
        $left = Decimal::sub(Decimal::money($total), Decimal::money($paid));
        $left = Decimal::sub($left, Decimal::money($credited));
        if (Decimal::cmp($left, '0') < 0) {
            return '0.00';
        }

        return Decimal::money($left);
    }

    public static function storedStatus(string $total, string $paid, string $credited): string
    {
        $balance = self::balance($total, $paid, $credited);
        if (Decimal::cmp($balance, '0') === 0) {
            if (Decimal::cmp($paid, '0') === 0 && Decimal::cmp($credited, '0') > 0) {
                return InvoiceStatus::Credited->value;
            }

            return InvoiceStatus::Paid->value;
        }
        if (Decimal::cmp($paid, '0') > 0 || Decimal::cmp($credited, '0') > 0) {
            return InvoiceStatus::PartiallyPaid->value;
        }

        return InvoiceStatus::Issued->value;
    }

    /**
     * Ageing uses the due date. Current means not yet due.
     *
     * @return 'CURRENT'|'DAYS_1_30'|'DAYS_31_60'|'DAYS_61_90'|'DAYS_90_PLUS'
     */
    public static function ageingBucket(string $dueDate, string $today): string
    {
        if ($dueDate >= $today) {
            return 'CURRENT';
        }
        $due = new \DateTimeImmutable($dueDate);
        $now = new \DateTimeImmutable($today);
        $days = (int) $due->diff($now)->days;
        if ($days <= 30) {
            return 'DAYS_1_30';
        }
        if ($days <= 60) {
            return 'DAYS_31_60';
        }
        if ($days <= 90) {
            return 'DAYS_61_90';
        }

        return 'DAYS_90_PLUS';
    }

    public static function remaining(string $commercial, string $invoicedNet): string
    {
        $left = Decimal::sub(Decimal::money($commercial), Decimal::money($invoicedNet));
        if (Decimal::cmp($left, '0') < 0) {
            return '0.00';
        }

        return Decimal::money($left);
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return list<array{vat: string, total: string}>
     */
    private static function shares(array $lines, string $vat, string $grand, string $mode): array
    {
        $count = count($lines);
        if ($count === 0) {
            return [];
        }
        $weights = [];
        $weightTotal = '0.00';
        foreach ($lines as $line) {
            $weight = Decimal::money((string) $line['line_subtotal']);
            $weights[] = $weight;
            $weightTotal = Decimal::money(Decimal::add($weightTotal, $weight));
        }
        $vatLeft = Decimal::money($vat);
        $grandLeft = Decimal::money($grand);
        $out = [];
        foreach ($weights as $index => $weight) {
            $last = $index === $count - 1;
            if ($last) {
                $lineVat = $vatLeft;
                $lineTotal = $grandLeft;
            } elseif (Decimal::cmp($weightTotal, '0') === 0) {
                $lineVat = '0.00';
                $lineTotal = '0.00';
            } else {
                $portion = Decimal::div($weight, $weightTotal, 8);
                $lineVat = Decimal::money(Decimal::mul($vat, $portion));
                $lineTotal = Decimal::money(Decimal::mul($grand, $portion));
                $vatLeft = Decimal::money(Decimal::sub($vatLeft, $lineVat));
                $grandLeft = Decimal::money(Decimal::sub($grandLeft, $lineTotal));
            }
            if ($mode === VatMode::Inclusive->value || $mode === VatMode::NoVat->value) {
                // line_total is the customer's share of the amount payable.
            }
            $out[] = ['vat' => $lineVat, 'total' => $lineTotal];
        }

        return $out;
    }
}

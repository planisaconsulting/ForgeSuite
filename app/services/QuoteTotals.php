<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DiscountType;
use App\Domain\VatMode;
use App\Helpers\Decimal;

/**
 * Quote totals from saved line amounts.
 *
 * Line prices are the commercial amounts already stored on each line.
 * This class applies the quote discount, VAT mode, and deposit.
 * It does not look up products or markup.
 *
 * VAT exclusive adds tax on top of the discounted subtotal.
 * VAT inclusive treats that subtotal as the amount the customer pays,
 * and lifts the VAT portion out of it.
 * No VAT leaves the discounted subtotal as the total.
 */
final class QuoteTotals
{
    /**
     * @param list<array<string, mixed>> $lines
     * @param array<string, mixed> $header
     * @return array<string, mixed>
     */
    public function summarise(array $lines, array $header): array
    {
        $subtotal = '0';
        $cost = '0';
        $optionalTotal = '0';
        foreach ($lines as $line) {
            $amount = $this->money((string) ($line['line_total'] ?? '0'));
            $lineCost = $this->money((string) ($line['total_cost'] ?? '0'));
            if ($this->included($line)) {
                $subtotal = Decimal::add($subtotal, $amount);
                $cost = Decimal::add($cost, $lineCost);
            } elseif ((int) ($line['is_optional'] ?? 0) === 1) {
                $optionalTotal = Decimal::add($optionalTotal, $amount);
            }
        }

        $discountType = DiscountType::normalise((string) ($header['discount_type'] ?? 'NONE'));
        $discountValue = $this->nonNegative((string) ($header['discount_value'] ?? '0'));
        $discountAmount = '0.00';
        if ($discountType === DiscountType::Percentage->value) {
            $percent = Decimal::cmp($discountValue, '100') > 0 ? '100' : $discountValue;
            $discountAmount = Decimal::money(Decimal::mul($subtotal, Decimal::div($percent, '100')));
        } elseif ($discountType === DiscountType::Fixed->value) {
            $discountAmount = Decimal::cmp($discountValue, $subtotal) > 0
                ? Decimal::money($subtotal)
                : Decimal::money($discountValue);
        }

        $after = Decimal::sub($subtotal, $discountAmount);
        if (Decimal::cmp($after, '0') < 0) {
            $after = '0';
        }

        $rate = $this->nonNegative((string) ($header['vat_rate'] ?? '0'));
        $mode = VatMode::normalise((string) ($header['vat_mode'] ?? 'EXCLUSIVE'));
        if (Decimal::cmp($rate, '0') === 0 || $mode === VatMode::NoVat->value) {
            $vat = '0.00';
            $total = Decimal::money($after);
            $revenue = Decimal::money($after);
            $mode = $mode === VatMode::NoVat->value ? VatMode::NoVat->value : $mode;
        } elseif ($mode === VatMode::Inclusive->value) {
            $divisor = Decimal::add('100', $rate);
            $vat = Decimal::money(Decimal::mul($after, Decimal::div($rate, $divisor)));
            $total = Decimal::money($after);
            $revenue = Decimal::money(Decimal::sub($after, $vat));
        } else {
            $mode = VatMode::Exclusive->value;
            $vat = Decimal::money(Decimal::mul($after, Decimal::div($rate, '100')));
            $total = Decimal::money(Decimal::add($after, $vat));
            $revenue = Decimal::money($after);
        }

        $depositType = strtoupper(trim((string) ($header['deposit_type'] ?? 'NONE')));
        if (!in_array($depositType, ['PERCENTAGE', 'FIXED_AMOUNT'], true)) {
            $depositType = 'NONE';
        }
        $depositValue = $this->nonNegative((string) ($header['deposit_value'] ?? '0'));
        $deposit = '0.00';
        if ($depositType === 'PERCENTAGE') {
            $percent = Decimal::cmp($depositValue, '100') > 0 ? '100' : $depositValue;
            $deposit = Decimal::money(Decimal::mul($total, Decimal::div($percent, '100')));
        } elseif ($depositType === 'FIXED_AMOUNT') {
            $deposit = Decimal::cmp($depositValue, $total) > 0
                ? Decimal::money($total)
                : Decimal::money($depositValue);
        }

        $profit = Decimal::money(Decimal::sub($revenue, $cost));
        $margin = Decimal::cmp($revenue, '0') === 0
            ? null
            : Decimal::round(Decimal::mul(Decimal::div($profit, $revenue), '100'), 2);

        return [
            'subtotal' => Decimal::money($subtotal),
            'discount_type' => $discountType,
            'discount_value' => Decimal::round($discountValue, 4),
            'discount_amount' => $discountAmount,
            'subtotal_after_discount' => Decimal::money($after),
            'vat_mode' => $mode,
            'vat_rate' => Decimal::round($rate, 4),
            'vat_amount' => $vat,
            'total' => $total,
            'deposit_type' => $depositType,
            'deposit_value' => Decimal::round($depositValue, 4),
            'deposit_amount' => $deposit,
            'cost' => Decimal::money($cost),
            'revenue' => $revenue,
            'gross_profit' => $profit,
            'gross_margin_percent' => $margin,
            'below_cost' => Decimal::cmp($revenue, Decimal::money($cost)) < 0,
            'optional_total' => Decimal::money($optionalTotal),
        ];
    }

    /**
     * @param array<string, mixed> $line
     */
    public function included(array $line): bool
    {
        if ((int) ($line['is_optional'] ?? 0) !== 1) {
            return true;
        }

        return (int) ($line['include_optional'] ?? 0) === 1;
    }

    private function money(string $value): string
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || !Decimal::isNumeric($value)) {
            return '0.00';
        }

        return Decimal::money($value);
    }

    private function nonNegative(string $value): string
    {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || !Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
            return '0';
        }

        return $value;
    }
}

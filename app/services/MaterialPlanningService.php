<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Time-phased material planning.
 *
 * Firm demand is accepted work. Planned demand is a scheduled recurring job.
 * Forecast demand is an open opportunity or quote and is never treated as firm.
 * A purchase order that arrives after the required date does not cover that date.
 * Safety stock is products.minimum_stock_level and is applied once, after dated demand.
 */
final class MaterialPlanningService
{
    /**
     * @param list<array{quantity: string, required_by: string, category: string, label: string, job_id?: int|null}> $demand
     * @param list<array{quantity: string, arrives: string}> $incoming
     * @return array{net: string, weeks: list<array<string, string>>, available: string, assumptions: list<string>}
     */
    public function plan(string $onHand, string $reserved, array $demand, array $incoming, string $safety): array
    {
        $available = Decimal::sub(Decimal::round($onHand, 4), Decimal::round($reserved, 4));
        if (Decimal::cmp($available, '0') < 0) {
            $available = '0.0000';
        }
        $stock = $available;
        $firm = array_values(array_filter($demand, static fn (array $row): bool => ($row['category'] ?? '') === 'FIRM' || ($row['category'] ?? '') === 'PLANNED'));
        usort($firm, static fn (array $a, array $b): int => strcmp($a['required_by'], $b['required_by']));
        $receipts = $incoming;
        usort($receipts, static fn (array $a, array $b): int => strcmp($a['arrives'], $b['arrives']));
        $used = [];
        $weeks = [];
        $net = '0.0000';
        foreach ($firm as $row) {
            foreach ($receipts as $index => $receipt) {
                if (isset($used[$index]) || strcmp($receipt['arrives'], $row['required_by']) > 0) {
                    continue;
                }
                $stock = Decimal::add($stock, Decimal::round($receipt['quantity'], 4));
                $used[$index] = true;
            }
            $need = Decimal::round($row['quantity'], 4);
            $short = '0.0000';
            if (Decimal::cmp($stock, $need) >= 0) {
                $stock = Decimal::sub($stock, $need);
            } else {
                $short = Decimal::sub($need, $stock);
                $stock = '0.0000';
                $net = Decimal::add($net, $short);
            }
            $weeks[] = [
                'required_by' => $row['required_by'],
                'category' => $row['category'],
                'label' => $row['label'],
                'gross' => Decimal::round($need, 4),
                'net' => Decimal::round($short, 4),
            ];
        }
        $safetyGap = '0.0000';
        if (Decimal::cmp($stock, Decimal::round($safety, 4)) < 0) {
            $safetyGap = Decimal::sub(Decimal::round($safety, 4), $stock);
            $net = Decimal::add($net, $safetyGap);
        }

        return [
            'net' => Decimal::round($net, 4),
            'weeks' => $weeks,
            'available' => Decimal::round($available, 4),
            'safety_gap' => Decimal::round($safetyGap, 4),
            'assumptions' => [
                'Available stock is on hand minus quantity reserved for other work.',
                'Incoming stock counts only when it arrives on or before the demand date.',
                'Safety stock is the product minimum level and is added once.',
                'Forecast demand is listed separately and is not in the net requirement.',
            ],
        ];
    }
}

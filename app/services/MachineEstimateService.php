<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Internal machine time.
 *
 * Time is setup minutes, repeated once per batch, plus run minutes per unit.
 * Cost uses the internal machine cost rate. That rate may cover electricity,
 * a maintenance allowance, consumables, or a depreciation allowance only when
 * the company has put those into the rate. It is not accounting depreciation.
 */
final class MachineEstimateService
{
    /**
     * @return array<string, mixed>
     */
    public function estimate(string $setupMinutes, string $runMinutesEach, string $quantity, ?string $batchSize, string $hourlyRate): array
    {
        $setup = $this->number($setupMinutes);
        $run = $this->number($runMinutesEach);
        $qty = $this->number($quantity);
        $batches = '1';
        if ($batchSize !== null && $batchSize !== '' && Decimal::isNumeric($batchSize) && Decimal::cmp($batchSize, '0') > 0 && Decimal::cmp($qty, '0') > 0) {
            $raw = Decimal::div($qty, $batchSize, Decimal::CALC_SCALE);
            $whole = explode('.', Decimal::round($raw, 4), 2)[0];
            $batches = Decimal::cmp($raw, $whole === '' ? '0' : $whole) > 0 ? Decimal::add($whole === '' ? '0' : $whole, '1', 0) : ($whole === '' ? '0' : $whole);
        }
        $minutes = Decimal::add(Decimal::mul($batches, $setup, Decimal::CALC_SCALE), Decimal::mul($qty, $run, Decimal::CALC_SCALE), Decimal::CALC_SCALE);
        $hours = Decimal::div($minutes, '60', Decimal::CALC_SCALE);
        $cost = Decimal::mul($hours, $this->number($hourlyRate), Decimal::CALC_SCALE);

        return [
            'label' => 'INTERNAL MACHINE COST RATE',
            'setup_minutes' => Decimal::round($setup, 2),
            'run_minutes_each' => Decimal::round($run, 2),
            'batches' => $batches,
            'estimated_minutes' => Decimal::round($minutes, 2),
            'estimated_cost' => Decimal::money($cost),
            'detail' => $batches . ' setup x ' . Decimal::round($setup, 2) . ' min + ' . Decimal::round($qty, 2) . ' x ' . Decimal::round($run, 2) . ' min',
        ];
    }

    private function number(string $value): string
    {
        $text = str_replace(',', '.', trim($value));
        if ($text === '' || !Decimal::isNumeric($text) || Decimal::cmp($text, '0') < 0) {
            return '0';
        }

        return $text;
    }
}

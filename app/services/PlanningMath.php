<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;

/**
 * Labelled planning arithmetic. Forecast figures are never added to actuals.
 */
final class PlanningMath
{
    public static function weighted(string $value, ?string $probabilityPercent): string
    {
        if ($probabilityPercent === null || trim($probabilityPercent) === '') {
            return '0.00';
        }
        $ratio = Decimal::div(Decimal::round($probabilityPercent, 4), '100');

        return Decimal::money(Decimal::mul(Decimal::round($value, 2), $ratio));
    }

    /**
     * Net requirement at one date.
     * Incoming stock is included only when it arrives on or before that date.
     * Net = gross + safety − available − on-time incoming, and never below zero.
     */
    public static function netRequirement(string $gross, string $available, string $incomingOnTime, string $safety): string
    {
        $need = Decimal::add(Decimal::round($gross, 4), Decimal::round($safety, 4));
        $cover = Decimal::add(Decimal::round($available, 4), Decimal::round($incomingOnTime, 4));
        $net = Decimal::sub($need, $cover);

        return Decimal::cmp($net, '0') > 0 ? Decimal::round($net, 4) : '0.0000';
    }

    public static function orderQuantity(string $demand, ?string $minimumOrder, ?string $packSize): string
    {
        $qty = Decimal::round($demand, 4);
        if (Decimal::cmp($qty, '0') < 0) {
            $qty = '0.0000';
        }
        if ($minimumOrder !== null && Decimal::cmp($minimumOrder, '0') > 0 && Decimal::cmp($qty, $minimumOrder) < 0) {
            $qty = Decimal::round($minimumOrder, 4);
        }
        if ($packSize !== null && Decimal::cmp($packSize, '0') > 0 && Decimal::cmp($qty, '0') > 0) {
            $packs = Decimal::div($qty, $packSize);
            $whole = Decimal::round($packs, 0);
            if (Decimal::cmp($packs, $whole) > 0) {
                $whole = Decimal::add($whole, '1');
            }
            $qty = Decimal::round(Decimal::mul($whole, $packSize), 4);
        }

        return $qty;
    }

    /**
     * Remaining capacity after committed work. Forecast hours are not subtracted.
     *
     * @return array{committed_hours: string, remaining_hours: string, shortfall_hours: string}
     */
    public static function committedCapacity(string $availableHours, string $scheduledHours, string $unscheduledHours): array
    {
        $committed = Decimal::add(Decimal::round($scheduledHours, 2), Decimal::round($unscheduledHours, 2));
        $remaining = Decimal::sub(Decimal::round($availableHours, 2), $committed);
        $shortfall = Decimal::cmp($remaining, '0') < 0 ? Decimal::mul($remaining, '-1') : '0.00';
        if (Decimal::cmp($remaining, '0') < 0) {
            $remaining = '0.00';
        }

        return [
            'committed_hours' => Decimal::round($committed, 2),
            'remaining_hours' => Decimal::round($remaining, 2),
            'shortfall_hours' => Decimal::round($shortfall, 2),
        ];
    }

    /**
     * @return array{variance: string, variance_percent: ?string}
     */
    public static function variance(string $actual, string $target): array
    {
        $difference = Decimal::sub(Decimal::round($actual, 2), Decimal::round($target, 2));
        $percent = null;
        if (Decimal::cmp($target, '0') !== 0) {
            $percent = Decimal::round(Decimal::mul(Decimal::div($difference, $target), '100'), 2);
        }

        return [
            'variance' => Decimal::money($difference),
            'variance_percent' => $percent,
        ];
    }

    public static function orderByDate(string $requiredBy, int $leadDays, int $bufferDays, bool $skipWeekends): string
    {
        $date = new \DateTimeImmutable($requiredBy);
        $date = $date->modify('-' . max(0, $leadDays + $bufferDays) . ' days');
        if ($skipWeekends) {
            while (in_array($date->format('N'), ['6', '7'], true)) {
                $date = $date->modify('-1 day');
            }
        }

        return $date->format('Y-m-d');
    }

    /**
     * @return array{start: string, end: string, label: string}
     */
    public static function horizon(string $code, ?string $customStart = null, ?string $customEnd = null): array
    {
        $start = date('Y-m-d');
        $days = match ($code) {
            '7' => 7,
            '14' => 14,
            '30' => 30,
            '60' => 60,
            '90' => 90,
            '180' => 182,
            '365' => 365,
            'custom' => 0,
            default => 30,
        };
        if ($code === 'custom' && $customStart !== null && $customEnd !== null && $customStart !== '' && $customEnd !== '') {
            return ['start' => $customStart, 'end' => $customEnd, 'label' => 'Custom'];
        }
        $end = (new \DateTimeImmutable($start))->modify('+' . $days . ' days')->format('Y-m-d');
        $label = match ($code) {
            '7' => '7 days',
            '14' => '14 days',
            '60' => '60 days',
            '90' => '90 days',
            '180' => '6 months',
            '365' => '12 months',
            default => '30 days',
        };

        return ['start' => $start, 'end' => $end, 'label' => $label];
    }
}

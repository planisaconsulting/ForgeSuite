<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Reporting periods in the application timezone.
 * Weeks start on Monday. A comparison period is the same length
 * immediately before the selected range, or the same dates one year earlier.
 */
final class DateRangeService
{
    /**
     * @return array{from: string, to: string, label: string, preset: string, compare_from: string, compare_to: string, compare_label: string}
     */
    public function resolve(string $preset, ?string $from = null, ?string $to = null, string $compare = 'previous'): array
    {
        $zone = new \DateTimeZone(SettingsService::get('timezone', 'Africa/Johannesburg') ?: 'Africa/Johannesburg');
        $today = new \DateTimeImmutable('today', $zone);
        $preset = strtolower(trim($preset));
        [$start, $end, $label] = match ($preset) {
            'today' => [$today, $today, 'Today'],
            'yesterday' => [$today->modify('-1 day'), $today->modify('-1 day'), 'Yesterday'],
            'this_week' => [$today->modify('monday this week'), $today->modify('sunday this week'), 'This week'],
            'last_week' => [$today->modify('monday last week'), $today->modify('sunday last week'), 'Last week'],
            'last_month' => [$today->modify('first day of last month'), $today->modify('last day of last month'), 'Last month'],
            'this_quarter' => $this->quarter($today, 0),
            'last_quarter' => $this->quarter($today, -1),
            'this_year' => [$today->modify('first day of january this year'), $today->modify('last day of december this year'), 'This year'],
            'last_year' => [$today->modify('first day of january last year'), $today->modify('last day of december last year'), 'Last year'],
            'custom' => $this->custom($from, $to, $today),
            default => [$today->modify('first day of this month'), $today->modify('last day of this month'), 'This month'],
        };
        if ($preset === '') {
            $preset = 'this_month';
        }
        $days = (int) $start->diff($end)->days;
        if ($compare === 'year') {
            $compareStart = $start->modify('-1 year');
            $compareEnd = $end->modify('-1 year');
            $compareLabel = 'Same dates last year';
        } else {
            $compareEnd = $start->modify('-1 day');
            $compareStart = $compareEnd->modify('-' . $days . ' days');
            $compareLabel = 'Previous period';
        }

        return [
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'label' => $label,
            'preset' => $preset === 'default' ? 'this_month' : $preset,
            'compare_from' => $compareStart->format('Y-m-d'),
            'compare_to' => $compareEnd->format('Y-m-d'),
            'compare_label' => $compareLabel,
        ];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: string}
     */
    private function quarter(\DateTimeImmutable $today, int $shift): array
    {
        $month = (int) $today->format('n');
        $quarter = (int) floor(($month - 1) / 3);
        $year = (int) $today->format('Y');
        $quarter += $shift;
        while ($quarter < 0) {
            $quarter += 4;
            $year--;
        }
        while ($quarter > 3) {
            $quarter -= 4;
            $year++;
        }
        $startMonth = $quarter * 3 + 1;
        $start = $today->setDate($year, $startMonth, 1);
        $end = $start->modify('+2 months')->modify('last day of this month');
        $label = $shift === 0 ? 'This quarter' : 'Last quarter';

        return [$start, $end, $label];
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: string}
     */
    private function custom(?string $from, ?string $to, \DateTimeImmutable $today): array
    {
        $start = $this->date($from) ?? $today->modify('first day of this month');
        $end = $this->date($to) ?? $today;
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end, 'Custom range'];
    }

    private function date(?string $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $zone = new \DateTimeZone(SettingsService::get('timezone', 'Africa/Johannesburg') ?: 'Africa/Johannesburg');

        return new \DateTimeImmutable($value, $zone);
    }
}

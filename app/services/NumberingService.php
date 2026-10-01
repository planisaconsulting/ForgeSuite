<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\NumberSequenceRepository;

/**
 * Document numbers such as SFQ-2026-0001.
 *
 * The prefix comes from settings. The sequence is a locked counter, not
 * COUNT(*) + 1, so two people saving at once cannot get the same number.
 * Invoice and purchase-order prefixes can use the same method later.
 */
final class NumberingService
{
    public function __construct(private readonly NumberSequenceRepository $sequences = new NumberSequenceRepository())
    {
    }

    public function opportunity(): string
    {
        return $this->next('opportunity', 'opportunity_prefix', 'SFO');
    }

    public function quote(): string
    {
        return $this->next('quote', 'quote_prefix', 'SFQ');
    }

    public function job(): string
    {
        return $this->next('job', 'job_prefix', 'SFJ');
    }

    private function next(string $document, string $setting, string $fallback): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) SettingsService::get($setting, $fallback)) ?? '');
        if ($prefix === '') {
            $prefix = $fallback;
        }
        $year = date('Y');
        $key = $document . ':' . $year;

        $number = Database::transaction(fn (): int => $this->sequences->allocate($key));

        return $prefix . '-' . $year . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}

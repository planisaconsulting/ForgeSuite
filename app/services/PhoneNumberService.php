<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Turns a typed phone number into digits for matching and WhatsApp links.
 * The original text is kept on the lead or contact. This does not replace it.
 */
final class PhoneNumberService
{
    public function normalise(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '27') && strlen($digits) === 11) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '27' . substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '27' . $digits;
        }

        return $digits === '' ? null : $digits;
    }

    public function international(?string $raw): ?string
    {
        $digits = $this->normalise($raw);

        return $digits === null ? null : '+' . $digits;
    }

    public function matchKey(?string $raw): ?string
    {
        $digits = $this->normalise($raw);
        if ($digits === null || strlen($digits) < 9) {
            return null;
        }

        return substr($digits, -9);
    }
}

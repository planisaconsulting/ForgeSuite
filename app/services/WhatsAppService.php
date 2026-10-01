<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Click-to-WhatsApp and a place for a future official provider.
 * Opening a link is not delivery. Nothing here scrapes WhatsApp Web.
 */
final class WhatsAppService
{
    public function __construct(private readonly PhoneNumberService $phones = new PhoneNumberService())
    {
    }

    public function mode(): string
    {
        return 'manual';
    }

    /**
     * @return array{ok: bool, url: string, phone: string, text: string, status: string, reason: string}
     */
    public function prepare(string $phone, string $message): array
    {
        $digits = $this->phones->normalise($phone);
        if ($digits === null || strlen($digits) < 8) {
            return [
                'ok' => false,
                'url' => '',
                'phone' => '',
                'text' => $message,
                'status' => 'FAILED',
                'reason' => 'That phone number cannot be opened in WhatsApp.',
            ];
        }

        return [
            'ok' => true,
            'url' => 'https://wa.me/' . $digits . '?text=' . rawurlencode($message),
            'phone' => '+' . $digits,
            'text' => $message,
            'status' => 'PREPARED',
            'reason' => 'Message prepared. Delivery is not confirmed.',
        ];
    }
}

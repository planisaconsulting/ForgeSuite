<?php

declare(strict_types=1);

namespace App\Services;

/**
 * A local provider for tests and for a site that has not connected a live gateway.
 * A browser return from this provider is not a payment.
 */
final class TestPaymentProvider implements PaymentProviderInterface
{
    public function name(): string
    {
        return 'test';
    }

    public function createLink(int $invoiceId, string $amount, string $currency, string $reference): array
    {
        return [
            'ok' => true,
            'url' => '/pay/return/' . $reference,
            'reference' => $reference,
            'message' => 'Test link created. Payment still requires a verified webhook.',
        ];
    }

    public function verifySignature(string $body, string $signature): bool
    {
        $secret = (string) SettingsService::get('payment_webhook_secret', '');
        if ($secret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected, $signature);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Invoice code asks this interface for a link. It does not know PayFast, Yoco, or Peach.
 */
interface PaymentProviderInterface
{
    public function name(): string;

    /**
     * @return array{ok: bool, url: string|null, reference: string|null, message: string}
     */
    public function createLink(int $invoiceId, string $amount, string $currency, string $reference): array;

    public function verifySignature(string $body, string $signature): bool;
}

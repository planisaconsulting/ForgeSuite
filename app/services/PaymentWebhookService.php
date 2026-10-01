<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\FinanceRepository;
use App\Repositories\PlatformRepository;

/**
 * Payment is recorded only after the provider signature, amount, currency, and reference match.
 */
final class PaymentWebhookService
{
    public function __construct(
        private readonly PlatformRepository $platform = new PlatformRepository(),
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly PaymentService $payments = new PaymentService()
    ) {
    }

    /**
     * @return array{ok: bool, payment_id: int|null, message: string}
     */
    public function handle(string $provider, string $body, string $signature): array
    {
        $adapter = (new PaymentLinkService($this->platform, $this->finance))->provider();
        if ($adapter === null || $adapter->name() !== strtolower($provider)) {
            return ['ok' => false, 'payment_id' => null, 'message' => 'Provider is not configured.'];
        }
        if (!$adapter->verifySignature($body, $signature)) {
            return ['ok' => false, 'payment_id' => null, 'message' => 'Signature was not accepted.'];
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'payment_id' => null, 'message' => 'Payload was not accepted.'];
        }
        $reference = trim((string) ($payload['external_reference'] ?? ''));
        $eventId = trim((string) ($payload['event_id'] ?? ''));
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $amount = trim((string) ($payload['amount'] ?? ''));
        if ($eventId === '' || $reference === '' || !Decimal::isNumeric($amount)) {
            return ['ok' => false, 'payment_id' => null, 'message' => 'Provider reference was incomplete.'];
        }
        if ($currency !== (string) SettingsService::get('payment_currency', 'ZAR')) {
            return ['ok' => false, 'payment_id' => null, 'message' => 'Currency did not match.'];
        }
        $seen = $this->platform->paymentRequestByEvent($eventId);
        if ($seen !== null && (string) $seen['status'] === 'PAID' && $seen['payment_id'] !== null) {
            return ['ok' => true, 'payment_id' => (int) $seen['payment_id'], 'message' => 'Already recorded.'];
        }

        return Database::transaction(function () use ($reference, $eventId, $amount): array {
            $request = $this->platform->paymentRequestByReference($reference);
            if ($request === null) {
                return ['ok' => false, 'payment_id' => null, 'message' => 'Payment request was not found.'];
            }
            $locked = $this->platform->lockPaymentRequest((int) $request['id']);
            if ($locked === null) {
                return ['ok' => false, 'payment_id' => null, 'message' => 'Payment request was not found.'];
            }
            if ((string) $locked['status'] === 'PAID' && $locked['payment_id'] !== null) {
                return ['ok' => true, 'payment_id' => (int) $locked['payment_id'], 'message' => 'Already recorded.'];
            }
            if (Decimal::cmp(Decimal::money($amount), (string) $locked['amount']) !== 0) {
                return ['ok' => false, 'payment_id' => null, 'message' => 'Amount did not match the payment request.'];
            }
            $invoice = $this->finance->invoice((int) $locked['invoice_id']);
            if ($invoice === null) {
                return ['ok' => false, 'payment_id' => null, 'message' => 'Invoice was not found.'];
            }
            $recorded = $this->payments->recordVerified([
                'customer_id' => (int) $invoice['customer_id'],
                'amount' => (string) $locked['amount'],
                'external_reference' => $eventId,
                'payment_method' => 'EFT',
                'allocations' => [(int) $invoice['id'] => (string) $locked['amount']],
            ], (int) ($invoice['created_by'] ?? 0) > 0 ? (int) $invoice['created_by'] : (int) (auth_user()['id'] ?? 0));
            if ($recorded['id'] === null) {
                $this->platform->markPaymentRequest((int) $locked['id'], 'FAILED', null, null);

                return ['ok' => false, 'payment_id' => null, 'message' => 'Payment was not recorded.'];
            }
            $this->platform->markPaymentRequest((int) $locked['id'], 'PAID', $eventId, (int) $recorded['id']);

            return ['ok' => true, 'payment_id' => (int) $recorded['id'], 'message' => 'Payment recorded.'];
        });
    }
}

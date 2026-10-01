<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\FinanceRepository;
use App\Repositories\PlatformRepository;

/**
 * The amount is taken from the invoice balance on the server.
 */
final class PaymentLinkService
{
    public function __construct(
        private readonly PlatformRepository $platform = new PlatformRepository(),
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function provider(): ?PaymentProviderInterface
    {
        $name = strtolower(trim((string) SettingsService::get('payment_provider', '')));
        if ($name === 'test') {
            return new TestPaymentProvider();
        }

        return null;
    }

    /**
     * @return array{errors: array<string, string>, id: int|null, amount: string|null}
     */
    public function create(int $invoiceId, string $basis, ?string $requestedAmount, int $userId): array
    {
        if (!can('payment_links.create') || !(new FeatureFlagService($this->platform))->enabled('PAYMENT_LINKS')) {
            return ['errors' => ['_form' => 'Payment links are not available.'], 'id' => null, 'amount' => null];
        }
        $provider = $this->provider();
        if ($provider === null) {
            return ['errors' => ['_form' => 'No payment provider is configured.'], 'id' => null, 'amount' => null];
        }
        $invoice = $this->finance->invoice($invoiceId);
        if ($invoice === null || !in_array((string) $invoice['status'], ['ISSUED', 'PARTIALLY_PAID'], true)) {
            return ['errors' => ['invoice' => 'Choose an open invoice.'], 'id' => null, 'amount' => null];
        }
        $balance = Decimal::money((string) $invoice['balance_due']);
        $basis = strtoupper($basis);
        if ($basis === 'FULL') {
            $amount = $balance;
        } elseif ($basis === 'DEPOSIT') {
            $percent = (new BusinessRuleService($this->platform))->resolve('deposit_requirement_percent') ?? '0';
            if (Decimal::cmp($percent, '0') <= 0) {
                $percent = '50';
            }
            $amount = Decimal::money(Decimal::mul($balance, Decimal::div($percent, '100')));
        } elseif ($basis === 'CUSTOM' && SettingsService::get('payment_allow_partial', '1') !== '0') {
            $custom = str_replace(',', '.', trim((string) $requestedAmount));
            if (!Decimal::isNumeric($custom) || Decimal::cmp($custom, '0') <= 0 || Decimal::cmp($custom, $balance) > 0) {
                return ['errors' => ['amount' => 'The amount must be within the outstanding balance.'], 'id' => null, 'amount' => null];
            }
            $amount = Decimal::money($custom);
        } else {
            return ['errors' => ['basis' => 'Choose a full balance, a deposit, or an authorised amount.'], 'id' => null, 'amount' => null];
        }
        if (Decimal::cmp($amount, '0') <= 0) {
            return ['errors' => ['amount' => 'There is nothing left to collect.'], 'id' => null, 'amount' => null];
        }
        $token = bin2hex(random_bytes(16));
        $reference = 'sfpay-' . $token;
        $link = $provider->createLink($invoiceId, $amount, (string) SettingsService::get('payment_currency', 'ZAR'), $token);
        if (!$link['ok']) {
            return ['errors' => ['_form' => $link['message']], 'id' => null, 'amount' => null];
        }
        $id = $this->platform->insertPaymentRequest([
            'invoice_id' => $invoiceId,
            'provider' => $provider->name(),
            'public_token' => $token,
            'external_reference' => $reference,
            'amount' => $amount,
            'currency' => (string) SettingsService::get('payment_currency', 'ZAR'),
            'amount_basis' => $basis,
            'status' => 'CREATED',
            'payment_url' => '/pay/return/' . $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
        $this->audit->record('payment_request', $id, 'PAYMENT_LINK_CREATED', null, [
            'invoice_id' => $invoiceId,
            'amount' => $amount,
        ], $userId);

        return ['errors' => [], 'id' => $id, 'amount' => $amount];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function acknowledge(string $token): ?array
    {
        return $this->platform->paymentRequestByToken($token);
    }
}

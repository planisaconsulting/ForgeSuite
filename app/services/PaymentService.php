<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\PaymentMethod;
use App\Domain\PaymentStatus;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;

/**
 * Payments are never deleted. A mistake is reversed, and the invoice
 * balance is recalculated from the remaining allocations.
 */
final class PaymentService
{
    public function __construct(
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly InvoiceService $invoices = new InvoiceService(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function record(array $input, int $userId): array
    {
        if (!can('payments.record')) {
            return ['errors' => ['_form' => 'You cannot record a payment.'], 'id' => null];
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        if ($this->customers->find($customerId) === null) {
            return ['errors' => ['customer_id' => 'Choose a customer.'], 'id' => null];
        }
        $amount = $this->money($input['amount'] ?? '');
        if ($amount === null || Decimal::cmp($amount, '0') <= 0) {
            return ['errors' => ['amount' => 'Enter the amount received.'], 'id' => null];
        }
        $method = PaymentMethod::tryFrom(strtoupper(trim((string) ($input['payment_method'] ?? 'EFT'))));
        if ($method === null) {
            return ['errors' => ['payment_method' => 'Choose a payment method.'], 'id' => null];
        }
        $date = trim((string) ($input['payment_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        try {
            $id = Database::transaction(function () use ($customerId, $amount, $method, $date, $input, $userId): int {
                $id = $this->finance->insertPayment([
                    'payment_reference' => $this->numbers->payment(),
                    'customer_id' => $customerId,
                    'payment_date' => $date,
                    'amount' => Decimal::money($amount),
                    'payment_method' => $method->value,
                    'external_reference' => blank_to_null($input['external_reference'] ?? null),
                    'bank_reference' => blank_to_null($input['bank_reference'] ?? null),
                    'notes' => blank_to_null($input['notes'] ?? null),
                    'status' => PaymentStatus::Recorded->value,
                    'recorded_by' => $userId,
                ]);
                $this->audit->record('payment', $id, 'PAYMENT_RECORDED', null, [
                    'customer_id' => $customerId,
                    'amount' => Decimal::money($amount),
                ], $userId);
                $allocations = $input['allocations'] ?? [];
                if (is_array($allocations) && $allocations !== []) {
                    $this->allocateInside($id, $allocations, $userId);
                }

                return $id;
            });
        } catch (FinanceRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        BusinessEventDispatcher::emit('PAYMENT_RECEIVED', 'PAYMENT', $id, $userId, []);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * A verified provider webhook is the caller. Browser redirects must not use this.
     *
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, duplicate: bool}
     */
    public function recordVerified(array $input, int $userId): array
    {
        $reference = trim((string) ($input['external_reference'] ?? ''));
        if ($reference === '') {
            return ['errors' => ['external_reference' => 'The provider reference is missing.'], 'id' => null, 'duplicate' => false];
        }
        $existing = $this->finance->findPaymentByExternal($reference);
        if ($existing !== null) {
            return ['errors' => [], 'id' => (int) $existing['id'], 'duplicate' => true];
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        if ($this->customers->find($customerId) === null) {
            return ['errors' => ['customer_id' => 'Choose a customer.'], 'id' => null, 'duplicate' => false];
        }
        $amount = $this->money($input['amount'] ?? '');
        if ($amount === null || Decimal::cmp($amount, '0') <= 0) {
            return ['errors' => ['amount' => 'Enter the amount received.'], 'id' => null, 'duplicate' => false];
        }
        $method = PaymentMethod::tryFrom(strtoupper(trim((string) ($input['payment_method'] ?? 'EFT'))));
        if ($method === null) {
            $method = PaymentMethod::Eft;
        }
        $date = date('Y-m-d');
        try {
            $id = Database::transaction(function () use ($customerId, $amount, $method, $date, $input, $userId, $reference): int {
                $again = $this->finance->findPaymentByExternal($reference);
                if ($again !== null) {
                    return (int) $again['id'];
                }
                $id = $this->finance->insertPayment([
                    'payment_reference' => $this->numbers->payment(),
                    'customer_id' => $customerId,
                    'payment_date' => $date,
                    'amount' => Decimal::money($amount),
                    'payment_method' => $method->value,
                    'external_reference' => $reference,
                    'bank_reference' => blank_to_null($input['bank_reference'] ?? null),
                    'notes' => 'Confirmed by a verified payment provider webhook.',
                    'status' => PaymentStatus::Recorded->value,
                    'recorded_by' => $userId,
                ]);
                $this->audit->record('payment', $id, 'PAYMENT_RECORDED', null, [
                    'customer_id' => $customerId,
                    'amount' => Decimal::money($amount),
                    'source' => 'provider_webhook',
                ], $userId);
                $allocations = $input['allocations'] ?? [];
                if (is_array($allocations) && $allocations !== []) {
                    $this->allocateInside($id, $allocations, $userId);
                }

                return $id;
            });
        } catch (FinanceRejected $e) {
            return ['errors' => $e->errors, 'id' => null, 'duplicate' => false];
        }

        return ['errors' => [], 'id' => $id, 'duplicate' => false];
    }

    /**
     * @param array<int|string, mixed> $allocations invoice id => amount
     * @return array<string, string>
     */
    public function allocate(int $paymentId, array $allocations, int $userId): array
    {
        if (!can('payments.allocate')) {
            return ['_form' => 'You cannot allocate a payment.'];
        }
        try {
            Database::transaction(function () use ($paymentId, $allocations, $userId): void {
                $this->allocateInside($paymentId, $allocations, $userId);
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function reverse(int $paymentId, int $userId, string $reason): array
    {
        if (!can('payments.reverse')) {
            return ['_form' => 'You cannot reverse a payment.'];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['reason' => 'A reversal needs a reason.'];
        }
        try {
            Database::transaction(function () use ($paymentId, $userId, $reason): void {
                $payment = $this->finance->lockPayment($paymentId);
                if ($payment === null) {
                    throw new FinanceRejected(['_form' => 'That payment was not found.']);
                }
                if ((string) $payment['status'] !== PaymentStatus::Recorded->value) {
                    throw new FinanceRejected(['_form' => 'This payment is already reversed.']);
                }
                $invoiceIds = [];
                foreach ($this->finance->allocations($paymentId) as $row) {
                    if ($row['reversed_at'] === null) {
                        $invoiceIds[] = (int) $row['invoice_id'];
                    }
                }
                $this->finance->reverseAllocations($paymentId);
                $this->finance->reversePayment($paymentId, $userId, substr($reason, 0, 255));
                foreach ($invoiceIds as $invoiceId) {
                    $this->invoices->refresh($invoiceId);
                }
                $this->audit->record('payment', $paymentId, 'PAYMENT_REVERSED', null, [
                    'reason' => $reason,
                ], $userId);
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param array<int|string, mixed> $allocations
     */
    private function allocateInside(int $paymentId, array $allocations, int $userId): void
    {
        $payment = $this->finance->lockPayment($paymentId);
        if ($payment === null || (string) $payment['status'] !== PaymentStatus::Recorded->value) {
            throw new FinanceRejected(['_form' => 'That payment cannot be allocated.']);
        }
        $available = Decimal::sub((string) $payment['amount'], $this->finance->allocated($paymentId));
        foreach ($allocations as $invoiceId => $raw) {
            $amount = $this->money($raw);
            if ($amount === null || Decimal::cmp($amount, '0') === 0) {
                continue;
            }
            if (Decimal::cmp($amount, '0') < 0) {
                throw new FinanceRejected(['_form' => 'An allocation cannot be negative.']);
            }
            if (Decimal::cmp($amount, $available) > 0) {
                throw new FinanceRejected(['_form' => 'The allocations are more than the payment still available.']);
            }
            $invoice = $this->finance->lockInvoice((int) $invoiceId);
            if ($invoice === null || (int) $invoice['customer_id'] !== (int) $payment['customer_id']) {
                throw new FinanceRejected(['_form' => 'That invoice is not on this customer account.']);
            }
            if (!in_array((string) $invoice['status'], ['ISSUED', 'PARTIALLY_PAID'], true)) {
                throw new FinanceRejected(['_form' => 'Only an open invoice can receive a payment.']);
            }
            if (Decimal::cmp($amount, (string) $invoice['balance_due']) > 0) {
                throw new FinanceRejected(['_form' => 'That allocation is more than the invoice balance.']);
            }
            $this->finance->insertAllocation([
                'payment_id' => $paymentId,
                'invoice_id' => (int) $invoiceId,
                'amount' => Decimal::money($amount),
                'allocated_by' => $userId,
            ]);
            $this->invoices->refresh((int) $invoiceId);
            $available = Decimal::money(Decimal::sub($available, $amount));
            $this->audit->record('payment', $paymentId, 'PAYMENT_ALLOCATED', null, [
                'invoice_id' => (int) $invoiceId,
                'amount' => Decimal::money($amount),
            ], $userId);
        }
    }

    private function money(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '' || !Decimal::isNumeric($value)) {
            return null;
        }

        return Decimal::money($value);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\CreditNoteStatus;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;

/**
 * An issued credit note reduces what the customer owes.
 * It does not rewrite the original invoice total.
 */
final class CreditNoteService
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
    public function create(array $input, int $userId): array
    {
        if (!can('credit_notes.create')) {
            return ['errors' => ['_form' => 'You cannot create a credit note.'], 'id' => null];
        }
        $customerId = (int) ($input['customer_id'] ?? 0);
        if ($this->customers->find($customerId) === null) {
            return ['errors' => ['customer_id' => 'Choose a customer.'], 'id' => null];
        }
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            return ['errors' => ['reason' => 'A credit note needs a reason.'], 'id' => null];
        }
        $description = trim((string) ($input['description'] ?? ''));
        $amount = str_replace(',', '.', trim((string) ($input['amount'] ?? '')));
        if ($description === '' || !Decimal::isNumeric($amount) || Decimal::cmp($amount, '0') <= 0) {
            return ['errors' => ['amount' => 'Enter what is being credited.'], 'id' => null];
        }
        $invoiceId = (int) ($input['invoice_id'] ?? 0);
        $vatMode = 'EXCLUSIVE';
        $vatRate = (string) SettingsService::get('default_vat_percent', '15');
        if ($invoiceId > 0) {
            $invoice = $this->finance->invoice($invoiceId);
            if ($invoice === null || (int) $invoice['customer_id'] !== $customerId) {
                return ['errors' => ['invoice_id' => 'That invoice is not on this customer.'], 'id' => null];
            }
            $vatMode = (string) $invoice['vat_mode'];
            $vatRate = (string) $invoice['vat_rate'];
        }
        $net = FinanceMath::netForCustomerTotal(Decimal::money($amount), $vatMode, $vatRate);
        $document = FinanceMath::document([
            ['description' => $description, 'quantity' => '1', 'unit_price' => $net, 'line_subtotal' => $net],
        ], 'NONE', '0', $vatMode, $vatRate);
        try {
            $id = Database::transaction(function () use ($customerId, $invoiceId, $input, $reason, $document, $description, $net, $userId): int {
                $id = $this->finance->insertCredit([
                    'credit_note_number' => null,
                    'customer_id' => $customerId,
                    'invoice_id' => $invoiceId > 0 ? $invoiceId : null,
                    'job_id' => ((int) ($input['job_id'] ?? 0)) > 0 ? (int) $input['job_id'] : null,
                    'credit_date' => $this->date($input['credit_date'] ?? null),
                    'status' => CreditNoteStatus::Draft->value,
                    'reason' => substr($reason, 0, 255),
                    'vat_mode' => $document['vat_mode'],
                    'vat_rate' => $document['vat_rate'],
                    'subtotal' => $document['subtotal'],
                    'vat_amount' => $document['vat_amount'],
                    'total' => $document['total'],
                    'created_by' => $userId,
                ]);
                $line = $document['lines'][0];
                $this->finance->insertCreditItem([
                    'credit_note_id' => $id,
                    'description' => substr($description, 0, 255),
                    'quantity' => '1.0000',
                    'unit_price' => Decimal::round($net, 4),
                    'subtotal' => $line['line_subtotal'],
                    'vat_rate_snapshot' => $line['vat_rate_snapshot'],
                    'vat_amount' => $line['vat_amount'],
                    'total' => $line['line_total'],
                ]);
                $this->audit->record('credit_note', $id, 'CREDIT_NOTE_CREATED', null, [
                    'customer_id' => $customerId,
                    'total' => $document['total'],
                ], $userId);

                return $id;
            });
        } catch (FinanceRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @return array<string, string>
     */
    public function issue(int $id, int $userId): array
    {
        if (!can('credit_notes.issue')) {
            return ['_form' => 'You cannot issue a credit note.'];
        }
        try {
            Database::transaction(function () use ($id, $userId): void {
                $note = $this->finance->lockCredit($id);
                if ($note === null) {
                    throw new FinanceRejected(['_form' => 'That credit note was not found.']);
                }
                if ((string) $note['status'] !== CreditNoteStatus::Draft->value) {
                    throw new FinanceRejected(['_form' => 'This credit note has already been issued.']);
                }
                $customer = $this->customers->find((int) $note['customer_id']);
                $status = CreditNoteStatus::Issued->value;
                if ((int) ($note['invoice_id'] ?? 0) > 0) {
                    $invoice = $this->finance->lockInvoice((int) $note['invoice_id']);
                    if ($invoice === null) {
                        throw new FinanceRejected(['_form' => 'The invoice for this credit note was not found.']);
                    }
                    if (Decimal::cmp((string) $note['total'], (string) $invoice['balance_due']) > 0) {
                        throw new FinanceRejected(['_form' => 'The credit is more than the invoice balance.']);
                    }
                    $status = CreditNoteStatus::Applied->value;
                }
                $number = $this->numbers->creditNote();
                $this->finance->issueCredit($id, $number, $status, $customer !== null ? customer_label($customer) : '', $userId);
                if ((int) ($note['invoice_id'] ?? 0) > 0) {
                    $this->invoices->refresh((int) $note['invoice_id']);
                    $this->audit->record('credit_note', $id, 'CREDIT_APPLIED', null, [
                        'invoice_id' => (int) $note['invoice_id'],
                        'total' => (string) $note['total'],
                    ], $userId);
                }
                $this->audit->record('credit_note', $id, 'CREDIT_NOTE_ISSUED', null, [
                    'credit_note_number' => $number,
                    'total' => (string) $note['total'],
                ], $userId);
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    private function date(mixed $value): string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }
}

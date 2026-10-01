<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\InvoiceStatus;
use App\Domain\InvoiceType;
use App\Domain\VatMode;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CustomerRepository;
use App\Repositories\FinanceRepository;
use App\Repositories\JobRepository;
use App\Repositories\QuoteRepository;

/**
 * Drafts can change. Issued invoices keep their snapshots.
 * A correction is a credit note or a new invoice.
 */
final class InvoiceService
{
    public function __construct(
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly CustomerRepository $customers = new CustomerRepository(),
        private readonly QuoteRepository $quotes = new QuoteRepository(),
        private readonly JobRepository $jobs = new JobRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array{commercial: string, invoiced: string, remaining: string, deposit: string, quote_total: string, variations: string}
     */
    public function position(?int $quoteId, ?int $jobId): array
    {
        $quoteTotal = '0.00';
        $deposit = '0.00';
        if ($quoteId !== null && $quoteId > 0) {
            $quote = $this->quotes->find($quoteId);
            if ($quote !== null) {
                $quoteTotal = Decimal::money((string) $quote['total']);
                $deposit = Decimal::money((string) $quote['deposit_amount']);
            }
        }
        $variations = $jobId !== null && $jobId > 0 ? $this->finance->approvedVariationTotal($jobId) : '0.00';
        $commercial = Decimal::money(Decimal::add($quoteTotal, $variations));
        $invoiced = $this->finance->invoicedNet($quoteId, $jobId);

        return [
            'commercial' => $commercial,
            'invoiced' => $invoiced,
            'remaining' => FinanceMath::remaining($commercial, $invoiced),
            'deposit' => $deposit,
            'quote_total' => $quoteTotal,
            'variations' => $variations,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        if (!can('invoices.create')) {
            return ['errors' => ['_form' => 'You cannot create an invoice.'], 'id' => null];
        }
        try {
            $id = Database::transaction(function () use ($input, $userId): int {
                $built = $this->buildDraft($input, $userId);
                $id = $this->finance->insertInvoice($built['header']);
                foreach ($built['lines'] as $index => $line) {
                    $line['invoice_id'] = $id;
                    $line['sort_order'] = $index + 1;
                    $this->finance->insertItem($line);
                }
                $this->retotal($id);
                $this->finance->addHistory($id, null, InvoiceStatus::Draft->value, $userId, 'Draft created');
                $this->audit->record('invoice', $id, 'INVOICE_CREATED', null, [
                    'customer_id' => $built['header']['customer_id'],
                    'invoice_type' => $built['header']['invoice_type'],
                ], $userId);
                (new AttributionService())->copyToInvoice($id);

                return $id;
            });
        } catch (FinanceRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function save(int $id, array $input, int $version, int $userId): array
    {
        if (!can('invoices.edit_draft') && !can('invoices.create')) {
            return ['_form' => 'You cannot edit this invoice.'];
        }
        try {
            Database::transaction(function () use ($id, $input, $version, $userId): void {
                $invoice = $this->locked($id, $version);
                if ((string) $invoice['status'] !== InvoiceStatus::Draft->value) {
                    throw new FinanceRejected(['_form' => 'An issued invoice cannot be edited. Raise a credit note.']);
                }
                $term = $this->termFrom($input, $invoice);
                $this->finance->updateDraft($id, [
                    'invoice_type' => $this->type($input['invoice_type'] ?? $invoice['invoice_type']),
                    'invoice_date' => $this->date($input['invoice_date'] ?? $invoice['invoice_date']),
                    'due_date' => $this->due($input, (string) ($input['invoice_date'] ?? $invoice['invoice_date']), $term),
                    'contact_id' => ((int) ($input['contact_id'] ?? 0)) > 0 ? (int) $input['contact_id'] : null,
                    'customer_po_number' => blank_to_null($input['customer_po_number'] ?? null),
                    'customer_notes' => blank_to_null($input['customer_notes'] ?? null),
                    'internal_notes' => blank_to_null($input['internal_notes'] ?? null),
                    'terms' => blank_to_null($input['terms'] ?? null),
                    'discount_type' => strtoupper(trim((string) ($input['discount_type'] ?? 'NONE'))),
                    'discount_value' => Decimal::round((string) ($this->number($input['discount_value'] ?? '0') ?? '0'), 4),
                    'payment_term_name_snapshot' => $term['name'],
                    'payment_term_days_snapshot' => $term['days'],
                    'collection_flag' => $this->flag($input['collection_flag'] ?? null),
                    'updated_by' => $userId,
                ]);
                $this->retotal($id);
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addLine(int $id, array $input, int $version, int $userId): array
    {
        if (!can('invoices.edit_draft') && !can('invoices.create')) {
            return ['_form' => 'You cannot edit this invoice.'];
        }
        try {
            Database::transaction(function () use ($id, $input, $version, $userId): void {
                $invoice = $this->locked($id, $version);
                if ((string) $invoice['status'] !== InvoiceStatus::Draft->value) {
                    throw new FinanceRejected(['_form' => 'An issued invoice cannot be edited.']);
                }
                $description = trim((string) ($input['description'] ?? ''));
                $qty = $this->positive($input['quantity'] ?? '', 'quantity');
                $price = $this->number($input['unit_price'] ?? '');
                if ($description === '') {
                    throw new FinanceRejected(['description' => 'Describe the line.']);
                }
                if ($price === null || Decimal::cmp($price, '0') < 0) {
                    throw new FinanceRejected(['unit_price' => 'Enter a unit price.']);
                }
                $this->finance->insertItem([
                    'invoice_id' => $id,
                    'sort_order' => $this->finance->nextSort($id),
                    'source_type' => 'MANUAL',
                    'source_id' => null,
                    'description' => substr($description, 0, 255),
                    'quantity' => Decimal::qty($qty),
                    'unit' => substr(trim((string) ($input['unit'] ?? 'unit')) ?: 'unit', 0, 20),
                    'unit_price' => Decimal::round($price, 4),
                    'line_subtotal' => Decimal::money(Decimal::mul($qty, $price)),
                    'discount_amount' => '0.00',
                    'vat_rate_snapshot' => (string) $invoice['vat_rate'],
                    'vat_amount' => '0.00',
                    'line_total' => '0.00',
                ]);
                $this->finance->updateDraft($id, $this->draftKeep($invoice, $userId));
                $this->retotal($id);
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function issue(int $id, int $version, int $userId, string $overrideReason = ''): array
    {
        if (!can('invoices.issue')) {
            return ['_form' => 'You cannot issue an invoice.'];
        }
        try {
            Database::transaction(function () use ($id, $version, $userId, $overrideReason): void {
                $invoice = $this->locked($id, $version);
                if ((string) $invoice['status'] !== InvoiceStatus::Draft->value) {
                    throw new FinanceRejected(['_form' => 'This invoice has already been issued.']);
                }
                $customer = $this->customers->find((int) $invoice['customer_id']);
                if ($customer === null) {
                    throw new FinanceRejected(['_form' => 'Choose a customer.']);
                }
                $items = $this->finance->items($id);
                if ($items === []) {
                    throw new FinanceRejected(['_form' => 'Add at least one line before issuing.']);
                }
                $this->retotal($id);
                $invoice = $this->finance->lockInvoice($id) ?? $invoice;
                if (Decimal::cmp((string) $invoice['total'], '0') <= 0) {
                    throw new FinanceRejected(['_form' => 'The invoice total must be greater than zero.']);
                }
                $this->guardCommercial($invoice, $overrideReason, $userId);
                $this->guardCredit($customer, (string) $invoice['total'], $overrideReason);
                $number = $this->numbers->invoice();
                $this->finance->issue($id, [
                    'invoice_number' => $number,
                    'status' => InvoiceStatus::Issued->value,
                    'issued_by' => $userId,
                    'customer_name_snapshot' => customer_label($customer),
                    'customer_vat_number_snapshot' => $customer['vat_number'] ?? null,
                    'customer_address_snapshot' => $customer['billing_address'] ?? $customer['physical_address'] ?? null,
                    'customer_email_snapshot' => $customer['email'] ?? null,
                    'company_name_snapshot' => SettingsService::get('company_name', 'Sign-Forge'),
                    'company_vat_number_snapshot' => SettingsService::get('vat_number', ''),
                    'company_address_snapshot' => SettingsService::get('address', ''),
                    'bank_name_snapshot' => SettingsService::get('bank_name', ''),
                    'account_name_snapshot' => SettingsService::get('account_name', ''),
                    'account_number_snapshot' => SettingsService::get('account_number', ''),
                    'branch_code_snapshot' => SettingsService::get('branch_code', ''),
                    'account_type_snapshot' => SettingsService::get('account_type', ''),
                    'over_invoice_reason' => trim($overrideReason) !== '' ? substr(trim($overrideReason), 0, 255) : null,
                    'over_invoice_by' => trim($overrideReason) !== '' ? $userId : null,
                    'over_invoice_at' => trim($overrideReason) !== '' ? date('Y-m-d H:i:s') : null,
                ]);
                $this->finance->addHistory($id, InvoiceStatus::Draft->value, InvoiceStatus::Issued->value, $userId, $number);
                $this->audit->record('invoice', $id, 'INVOICE_ISSUED', null, [
                    'invoice_number' => $number,
                    'total' => (string) $invoice['total'],
                ], $userId);
                if ((string) $invoice['invoice_type'] !== InvoiceType::Deposit->value && (int) ($invoice['job_id'] ?? 0) > 0) {
                    $this->finance->markVariationsInvoiced((int) $invoice['job_id']);
                }
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function cancel(int $id, int $version, int $userId, string $reason): array
    {
        if (!can('invoices.cancel')) {
            return ['_form' => 'You cannot cancel an invoice.'];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['cancellation_reason' => 'A cancellation needs a reason.'];
        }
        try {
            Database::transaction(function () use ($id, $version, $userId, $reason): void {
                $invoice = $this->locked($id, $version);
                if ((string) $invoice['status'] === InvoiceStatus::Cancelled->value) {
                    throw new FinanceRejected(['_form' => 'This invoice is already cancelled.']);
                }
                if ((string) $invoice['status'] === InvoiceStatus::Draft->value) {
                    throw new FinanceRejected(['_form' => 'Delete is not used. Leave the draft, or issue it and then cancel it.']);
                }
                if (Decimal::cmp($this->finance->paidOnInvoice($id), '0') > 0 || Decimal::cmp($this->finance->creditedOnInvoice($id), '0') > 0) {
                    throw new FinanceRejected(['_form' => 'Reverse payments and credits before cancelling this invoice.']);
                }
                $this->finance->cancel($id, $userId, substr($reason, 0, 255));
                $this->finance->addHistory($id, (string) $invoice['status'], InvoiceStatus::Cancelled->value, $userId, $reason);
                $this->audit->record('invoice', $id, 'INVOICE_CANCELLED', ['status' => $invoice['status']], [
                    'reason' => $reason,
                ], $userId);
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    public function setCollection(int $id, ?string $flag, int $userId): array
    {
        if (!can('invoices.issue') && !can('debtors.view')) {
            return ['_form' => 'You cannot change the collection note.'];
        }
        $flag = $this->flag($flag);
        $this->finance->setCollection($id, $flag);

        return [];
    }

    public function refresh(int $invoiceId): void
    {
        $invoice = $this->finance->lockInvoice($invoiceId);
        if ($invoice === null || (string) $invoice['status'] === InvoiceStatus::Draft->value || (string) $invoice['status'] === InvoiceStatus::Cancelled->value) {
            return;
        }
        $paid = $this->finance->paidOnInvoice($invoiceId);
        $credited = $this->finance->creditedOnInvoice($invoiceId);
        $balance = FinanceMath::balance((string) $invoice['total'], $paid, $credited);
        $status = FinanceMath::storedStatus((string) $invoice['total'], $paid, $credited);
        $paidAt = $invoice['paid_at'];
        if ($status === InvoiceStatus::Paid->value && $paidAt === null) {
            $paidAt = date('Y-m-d H:i:s');
        }
        if ($status !== InvoiceStatus::Paid->value) {
            $paidAt = null;
        }
        $this->finance->applyAmounts($invoiceId, [
            'amount_paid' => $paid,
            'credit_applied' => $credited,
            'balance_due' => $balance,
            'status' => $status,
            'paid_at' => $paidAt,
        ]);
    }

    private function retotal(int $id): void
    {
        $invoice = $this->finance->lockInvoice($id);
        if ($invoice === null) {
            return;
        }
        $items = $this->finance->items($id);
        $document = FinanceMath::document(
            $items,
            (string) $invoice['discount_type'],
            (string) $invoice['discount_value'],
            (string) $invoice['vat_mode'],
            (string) $invoice['vat_rate']
        );
        foreach ($document['lines'] as $index => $line) {
            if (!isset($items[$index]['id'])) {
                continue;
            }
            $this->finance->updateItemFigures(
                (int) $items[$index]['id'],
                (string) $line['vat_amount'],
                (string) $line['line_total'],
                (string) $line['vat_rate_snapshot']
            );
        }
        $balance = (string) $invoice['status'] === InvoiceStatus::Draft->value
            ? (string) $document['total']
            : FinanceMath::balance((string) $document['total'], (string) $invoice['amount_paid'], (string) $invoice['credit_applied']);
        $this->finance->saveTotals($id, [
            'subtotal' => $document['subtotal'],
            'discount_amount' => $document['discount_amount'],
            'subtotal_after_discount' => $document['subtotal_after_discount'],
            'vat_mode' => $document['vat_mode'],
            'vat_rate' => $document['vat_rate'],
            'vat_amount' => $document['vat_amount'],
            'total' => $document['total'],
            'balance_due' => $balance,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    private function buildDraft(array $input, int $userId): array
    {
        $customerId = (int) ($input['customer_id'] ?? 0);
        $customer = $this->customers->find($customerId);
        if ($customer === null) {
            throw new FinanceRejected(['customer_id' => 'Choose a customer.']);
        }
        $quoteId = (int) ($input['quote_id'] ?? 0);
        $jobId = (int) ($input['job_id'] ?? 0);
        $quote = $quoteId > 0 ? $this->quotes->find($quoteId) : null;
        $job = $jobId > 0 ? $this->jobs->find($jobId) : null;
        if ($quoteId > 0 && $quote === null) {
            throw new FinanceRejected(['quote_id' => 'That quotation was not found.']);
        }
        if ($jobId > 0 && $job === null) {
            throw new FinanceRejected(['job_id' => 'That job was not found.']);
        }
        if ($quote !== null && (int) $quote['customer_id'] !== $customerId) {
            throw new FinanceRejected(['customer_id' => 'The quotation belongs to a different customer.']);
        }
        if ($job !== null && (int) $job['customer_id'] !== $customerId) {
            throw new FinanceRejected(['customer_id' => 'The job belongs to a different customer.']);
        }
        if ($job !== null && $quote === null) {
            $quote = $this->quotes->find((int) $job['quote_id']);
            $quoteId = $quote !== null ? (int) $quote['id'] : 0;
        }
        $type = $this->type($input['invoice_type'] ?? 'STANDARD');
        $vatMode = $quote !== null ? (string) $quote['vat_mode'] : VatMode::normalise((string) ($input['vat_mode'] ?? 'EXCLUSIVE'));
        $vatRate = $quote !== null ? (string) $quote['vat_rate'] : Decimal::round((string) SettingsService::get('default_vat_percent', '15'), 4);
        $discountType = 'NONE';
        $discountValue = '0';
        $lines = [];
        if ($quote !== null && $type !== InvoiceType::Standard->value) {
            $target = $this->targetAmount($type, $quote, $jobId > 0 ? $jobId : null, $input);
            $net = FinanceMath::netForCustomerTotal($target, $vatMode, $vatRate);
            $lines[] = $this->summaryLine($type, $net, $quote, $job);
        } elseif ($quote !== null) {
            if ($this->finance->invoicedNet($quoteId, $jobId > 0 ? $jobId : null) !== '0.00') {
                throw new FinanceRejected(['_form' => 'Part of this job is already invoiced. Use a progress or final invoice for the remainder.']);
            }
            $discountType = (string) $quote['discount_type'];
            $discountValue = (string) $quote['discount_value'];
            foreach ($this->quotes->items($quoteId) as $item) {
                if ((int) ($item['is_optional'] ?? 0) === 1 && (int) ($item['include_optional'] ?? 0) !== 1) {
                    continue;
                }
                $lines[] = [
                    'source_type' => 'QUOTE_ITEM',
                    'source_id' => (int) $item['id'],
                    'description' => substr((string) ($item['customer_description'] ?: $item['product_name_snapshot']), 0, 255),
                    'quantity' => '1.0000',
                    'unit' => 'unit',
                    'unit_price' => Decimal::round((string) $item['line_total'], 4),
                    'line_subtotal' => Decimal::money((string) $item['line_total']),
                    'discount_amount' => '0.00',
                    'vat_rate_snapshot' => $vatRate,
                    'vat_amount' => '0.00',
                    'line_total' => '0.00',
                ];
            }
        }
        if ($lines === [] && $type === InvoiceType::Standard->value) {
            $description = trim((string) ($input['description'] ?? ''));
            $amount = $this->number($input['amount'] ?? '');
            if ($description !== '' && $amount !== null && Decimal::cmp($amount, '0') > 0) {
                $lines[] = [
                    'source_type' => 'MANUAL',
                    'source_id' => null,
                    'description' => substr($description, 0, 255),
                    'quantity' => '1.0000',
                    'unit' => 'unit',
                    'unit_price' => Decimal::round($amount, 4),
                    'line_subtotal' => Decimal::money($amount),
                    'discount_amount' => '0.00',
                    'vat_rate_snapshot' => $vatRate,
                    'vat_amount' => '0.00',
                    'line_total' => '0.00',
                ];
            }
        }
        $date = $this->date($input['invoice_date'] ?? null);
        $term = $this->termForCustomer($customer, $input);
        $document = FinanceMath::document($lines, $discountType, $discountValue, $vatMode, $vatRate);
        $header = [
            'invoice_number' => null,
            'invoice_type' => $type,
            'customer_id' => $customerId,
            'contact_id' => $quote['contact_id'] ?? null,
            'quote_id' => $quoteId > 0 ? $quoteId : null,
            'quote_revision_number' => $quote['revision_number'] ?? ($job['quote_revision_number'] ?? null),
            'job_id' => $jobId > 0 ? $jobId : null,
            'invoice_date' => $date,
            'due_date' => $this->due($input, $date, $term),
            'status' => InvoiceStatus::Draft->value,
            'subtotal' => $document['subtotal'],
            'discount_type' => $discountType,
            'discount_value' => Decimal::round($discountValue, 4),
            'discount_amount' => $document['discount_amount'],
            'subtotal_after_discount' => $document['subtotal_after_discount'],
            'vat_mode' => $document['vat_mode'],
            'vat_rate' => $document['vat_rate'],
            'vat_amount' => $document['vat_amount'],
            'total' => $document['total'],
            'amount_paid' => '0.00',
            'credit_applied' => '0.00',
            'balance_due' => $document['total'],
            'customer_po_number' => $job['customer_po_number'] ?? blank_to_null($input['customer_po_number'] ?? null),
            'customer_notes' => blank_to_null($input['customer_notes'] ?? ($quote['customer_notes'] ?? null)),
            'internal_notes' => blank_to_null($input['internal_notes'] ?? null),
            'terms' => blank_to_null($input['terms'] ?? ($quote['terms'] ?? SettingsService::get('default_quote_terms', ''))),
            'payment_term_name_snapshot' => $term['name'],
            'payment_term_days_snapshot' => $term['days'],
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
        $storedLines = [];
        foreach ($document['lines'] as $line) {
            $storedLines[] = [
                'source_type' => $line['source_type'] ?? 'MANUAL',
                'source_id' => $line['source_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'] ?? '1.0000',
                'unit' => $line['unit'] ?? 'unit',
                'unit_price' => $line['unit_price'] ?? $line['line_subtotal'],
                'line_subtotal' => $line['line_subtotal'],
                'discount_amount' => $line['discount_amount'],
                'vat_rate_snapshot' => $line['vat_rate_snapshot'],
                'vat_amount' => $line['vat_amount'],
                'line_total' => $line['line_total'],
            ];
        }

        return ['header' => $header, 'lines' => $storedLines];
    }

    /**
     * @param array<string, mixed> $quote
     * @param array<string, mixed>|null $job
     * @param array<string, mixed> $input
     */
    private function targetAmount(string $type, array $quote, ?int $jobId, array $input): string
    {
        $position = $this->position((int) $quote['id'], $jobId);
        if ($type === InvoiceType::Deposit->value) {
            if ($this->finance->depositExists((int) $quote['id'], $jobId)) {
                throw new FinanceRejected(['_form' => 'A deposit invoice already exists for this quotation.']);
            }
            $amount = Decimal::cmp($position['deposit'], '0') > 0 ? $position['deposit'] : $position['commercial'];
            if (Decimal::cmp($amount, $position['remaining']) > 0) {
                $amount = $position['remaining'];
            }

            return $amount;
        }
        if ($type === InvoiceType::Progress->value) {
            $fixed = $this->number($input['progress_amount'] ?? '');
            if ($fixed !== null && Decimal::cmp($fixed, '0') > 0) {
                return Decimal::money($fixed);
            }
            $percent = $this->number($input['progress_percent'] ?? '');
            if ($percent === null || Decimal::cmp($percent, '0') <= 0) {
                throw new FinanceRejected(['progress_amount' => 'Enter a progress amount or percent.']);
            }
            if (Decimal::cmp($percent, '100') > 0) {
                $percent = '100';
            }

            return Decimal::money(Decimal::mul($position['commercial'], Decimal::div($percent, '100')));
        }

        return $position['remaining'];
    }

    /**
     * @param array<string, mixed> $quote
     * @param array<string, mixed>|null $job
     * @return array<string, mixed>
     */
    private function summaryLine(string $type, string $net, array $quote, ?array $job): array
    {
        $label = match ($type) {
            InvoiceType::Deposit->value => 'Deposit',
            InvoiceType::Progress->value => 'Progress claim',
            InvoiceType::Final->value => 'Final claim',
            default => 'Invoice',
        };
        $ref = $job['job_number'] ?? $quote['quote_number'];

        return [
            'source_type' => 'SUMMARY',
            'source_id' => (int) $quote['id'],
            'description' => $label . ' for ' . $ref,
            'quantity' => '1.0000',
            'unit' => 'unit',
            'unit_price' => Decimal::round($net, 4),
            'line_subtotal' => Decimal::money($net),
            'discount_amount' => '0.00',
            'vat_rate_snapshot' => (string) $quote['vat_rate'],
            'vat_amount' => '0.00',
            'line_total' => '0.00',
        ];
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private function guardCommercial(array $invoice, string $reason, int $userId): void
    {
        $quoteId = (int) ($invoice['quote_id'] ?? 0);
        $jobId = (int) ($invoice['job_id'] ?? 0);
        if ($quoteId < 1 && $jobId < 1) {
            return;
        }
        $position = $this->position($quoteId > 0 ? $quoteId : null, $jobId > 0 ? $jobId : null);
        $already = $this->finance->invoicedNet($quoteId > 0 ? $quoteId : null, $jobId > 0 ? $jobId : null);
        $proposed = Decimal::money(Decimal::add($already, (string) $invoice['total']));
        if (Decimal::cmp($proposed, $position['commercial']) > 0) {
            if (trim($reason) === '') {
                throw new FinanceRejected(['_form' => 'This invoice would exceed the commercial value. Enter an override reason to continue.']);
            }
            $this->audit->record('invoice', (int) $invoice['id'], 'INVOICE_ISSUED', null, [
                'over_invoice' => $reason,
                'commercial' => $position['commercial'],
                'proposed' => $proposed,
            ], $userId);
        }
    }

    /**
     * @param array<string, mixed> $customer
     */
    private function guardCredit(array $customer, string $total, string $reason): void
    {
        if ((int) ($customer['account_on_hold'] ?? 0) === 1 && trim($reason) === '') {
            throw new FinanceRejected(['_form' => 'This account is on hold. Enter a reason to issue anyway.']);
        }
        if ($customer['credit_limit'] === null || $customer['credit_limit'] === '') {
            return;
        }
        $account = $this->finance->account((int) $customer['id']);
        $next = Decimal::add($account['outstanding'], $total);
        if (Decimal::cmp($next, (string) $customer['credit_limit']) > 0 && trim($reason) === '') {
            throw new FinanceRejected(['_form' => 'This invoice would pass the credit limit. Enter a reason to issue anyway.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function locked(int $id, int $version): array
    {
        $invoice = $this->finance->lockInvoice($id);
        if ($invoice === null) {
            throw new FinanceRejected(['_form' => 'That invoice was not found.']);
        }
        if ((int) $invoice['version_number'] !== $version) {
            throw new FinanceRejected(['_form' => 'Someone else saved this invoice. Reload it and try again.']);
        }

        return $invoice;
    }

    /**
     * @param array<string, mixed> $invoice
     * @return array<string, mixed>
     */
    private function draftKeep(array $invoice, int $userId): array
    {
        return [
            'invoice_type' => $invoice['invoice_type'],
            'invoice_date' => $invoice['invoice_date'],
            'due_date' => $invoice['due_date'],
            'contact_id' => $invoice['contact_id'],
            'customer_po_number' => $invoice['customer_po_number'],
            'customer_notes' => $invoice['customer_notes'],
            'internal_notes' => $invoice['internal_notes'],
            'terms' => $invoice['terms'],
            'discount_type' => $invoice['discount_type'],
            'discount_value' => $invoice['discount_value'],
            'payment_term_name_snapshot' => $invoice['payment_term_name_snapshot'],
            'payment_term_days_snapshot' => $invoice['payment_term_days_snapshot'],
            'collection_flag' => $invoice['collection_flag'],
            'updated_by' => $userId,
        ];
    }

    /**
     * @param array<string, mixed> $customer
     * @param array<string, mixed> $input
     * @return array{name: string, days: int}
     */
    private function termForCustomer(array $customer, array $input): array
    {
        return $this->termFrom($input, ['payment_term_days_snapshot' => null]);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $invoice
     * @return array{name: string, days: int}
     */
    private function termFrom(array $input, array $invoice): array
    {
        $termId = (int) ($input['payment_term_id'] ?? 0);
        if ($termId > 0) {
            $term = $this->finance->term($termId);
            if ($term !== null) {
                return ['name' => (string) $term['name'], 'days' => (int) $term['days_due']];
            }
        }
        if (!empty($invoice['payment_term_name_snapshot'])) {
            return [
                'name' => (string) $invoice['payment_term_name_snapshot'],
                'days' => (int) ($invoice['payment_term_days_snapshot'] ?? 0),
            ];
        }
        $fallback = $this->finance->termByName('14 DAYS');

        return [
            'name' => (string) ($fallback['name'] ?? '14 DAYS'),
            'days' => (int) ($fallback['days_due'] ?? 14),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array{name: string, days: int} $term
     */
    private function due(array $input, string $invoiceDate, array $term): string
    {
        $posted = trim((string) ($input['due_date'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $posted)) {
            return $posted;
        }
        $date = new \DateTimeImmutable($invoiceDate);

        return $date->modify('+' . $term['days'] . ' days')->format('Y-m-d');
    }

    private function date(mixed $value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return date('Y-m-d');
        }

        return $value;
    }

    private function type(mixed $value): string
    {
        $type = InvoiceType::tryFrom(strtoupper(trim((string) $value)));

        return $type === null ? InvoiceType::Standard->value : $type->value;
    }

    private function flag(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return in_array($value, ['DISPUTED', 'COLLECTIONS'], true) ? $value : null;
    }

    private function number(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '' || !Decimal::isNumeric($value)) {
            return null;
        }

        return $value;
    }

    private function positive(mixed $value, string $field): string
    {
        $number = $this->number($value);
        if ($number === null || Decimal::cmp($number, '0') <= 0) {
            throw new FinanceRejected([$field => 'Enter a quantity greater than zero.']);
        }

        return $number;
    }
}

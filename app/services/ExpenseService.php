<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\OtherCostType;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\ExpenseRepository;
use App\Repositories\OperationsRepository;

/**
 * Operational expense records.
 *
 * Capturing a cost does not pay anyone and does not post a ledger.
 * An approved job expense becomes one other-cost row. A later reversal
 * adds a reversing row and leaves the original in place.
 */
final class ExpenseService
{
    /** @var list<string> */
    public const STATUSES = ['DRAFT', 'SUBMITTED', 'REVIEW_REQUIRED', 'APPROVED', 'REJECTED', 'CANCELLED'];

    /** @var list<string> */
    public const METHODS = ['PERSONAL_CARD', 'COMPANY_CARD', 'CASH', 'EFT', 'ACCOUNT', 'OTHER'];

    /** @var list<string> */
    public const SOURCES = ['MANUAL', 'MOBILE', 'CONTRACTOR', 'LOGISTICS', 'IMPORT', 'INTEGRATION', 'OTHER'];

    /** @var list<string> */
    public const ALLOCATIONS = ['JOB', 'JOB_ITEM', 'PROJECT', 'PROJECT_SITE', 'SERVICE_JOB', 'CUSTOMER_ASSET', 'INSTALLATION', 'SITE_SURVEY', 'GENERAL'];

    /** @var list<string> */
    private const BLOCKED_FILES = ['php', 'phtml', 'phar', 'exe', 'sh', 'js', 'html', 'htm', 'svg', 'bat', 'cmd'];

    /** @var list<string> */
    private const ALREADY_COSTED = ['GOODS_RECEIPT', 'PURCHASE_ORDER', 'CONTRACTOR_WORK', 'LOGISTICS', 'STOCK'];

    public function __construct(
        private readonly ExpenseRepository $repo = new ExpenseRepository(),
        private readonly OperationsRepository $ops = new OperationsRepository(),
        private readonly JobCostingService $costing = new JobCostingService(),
        private readonly AuditService $audit = new AuditService(),
        private readonly NumberingService $numbers = new NumberingService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, warning: string|null, number: string|null}
     */
    public function create(array $input, int $userId): array
    {
        if (!can('expenses.create')) {
            return $this->fail(['_form' => 'You cannot capture an expense.']);
        }
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        if ($key !== '') {
            $existing = $this->repo->findByKey('idempotency_key', $key);
            if ($existing !== null) {
                return ['errors' => [], 'id' => (int) $existing['id'], 'warning' => null, 'number' => (string) $existing['expense_number']];
            }
        }
        $local = trim((string) ($input['client_local_id'] ?? ''));
        if ($local !== '') {
            $existing = $this->repo->findByKey('client_local_id', $local);
            if ($existing !== null) {
                return ['errors' => [], 'id' => (int) $existing['id'], 'warning' => null, 'number' => (string) $existing['expense_number']];
            }
        }
        $category = $this->repo->categoryByCode(strtoupper(trim((string) ($input['category'] ?? 'OTHER'))));
        if ($category === null) {
            return $this->fail(['category' => 'Choose an expense category.']);
        }
        $amount = $this->moneyField($input['amount_inc_vat'] ?? null);
        if ($amount === null || Decimal::cmp($amount, '0') <= 0) {
            return $this->fail(['amount_inc_vat' => 'Enter the amount including VAT.']);
        }
        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            return $this->fail(['description' => 'Describe the expense.']);
        }
        $date = trim((string) ($input['expense_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->fail(['expense_date' => 'Enter the expense date.']);
        }
        $method = strtoupper(trim((string) ($input['payment_method'] ?? '')));
        if ($method !== '' && !in_array($method, self::METHODS, true)) {
            return $this->fail(['payment_method' => 'Choose a payment method.']);
        }
        $source = strtoupper(trim((string) ($input['source'] ?? 'MANUAL')));
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'MANUAL';
        }
        $represented = strtoupper(trim((string) ($input['represented_by_type'] ?? '')));
        if ($represented !== '' && !in_array($represented, self::ALREADY_COSTED, true)) {
            return $this->fail(['represented_by_type' => 'That cost source is not recognised.']);
        }
        $merchant = trim((string) ($input['merchant_name'] ?? ''));
        $hash = $this->hash($input['receipt_sha256'] ?? null);
        $duplicate = $this->repo->duplicate($userId, $amount, $date, $merchant, $hash);
        $vat = $this->optionalMoney($input['vat_amount'] ?? null);
        $ex = $this->optionalMoney($input['amount_ex_vat'] ?? null);
        $id = 0;
        $number = '';
        $warning = $duplicate !== null ? 'Possible duplicate. The earlier expense was kept.' : null;
        if ($represented !== '') {
            $warning = 'This purchase is already represented by ' . str_replace('_', ' ', strtolower($represented)) . '. It will not be costed again.';
        }
        Database::transaction(function () use ($input, $userId, $category, $amount, $description, $date, $method, $source, $represented, $merchant, $hash, $duplicate, $vat, $ex, $key, $local, &$id, &$number): void {
            $number = $this->numbers->expense();
            $id = $this->repo->insertExpense([
                'expense_number' => $number,
                'expense_date' => $date,
                'submitted_by' => $userId,
                'incurred_by' => (int) ($input['incurred_by'] ?? 0) > 0 ? (int) $input['incurred_by'] : $userId,
                'expense_category_id' => (int) $category['id'],
                'description' => mb_substr($description, 0, 180),
                'merchant_name' => $merchant !== '' ? mb_substr($merchant, 0, 180) : null,
                'supplier_id' => (int) ($input['supplier_id'] ?? 0) > 0 ? (int) $input['supplier_id'] : null,
                'amount_ex_vat' => $ex,
                'vat_amount' => $vat,
                'amount_inc_vat' => $amount,
                'currency_code' => 'ZAR',
                'payment_method' => $method !== '' ? $method : null,
                'status' => 'DRAFT',
                'reimbursement_status' => $this->reimbursement($category, $method),
                'receipt_required' => (int) $category['receipt_required'],
                'receipt_sha256' => $hash,
                'receipt_name' => $this->short($input['receipt_name'] ?? null, 180),
                'reference_text' => $this->short($input['reference_text'] ?? null, 80),
                'notes' => $this->short($input['notes'] ?? null, 2000),
                'source' => $source,
                'represented_by_type' => $represented !== '' ? $represented : null,
                'represented_by_id' => (int) ($input['represented_by_id'] ?? 0) > 0 ? (int) $input['represented_by_id'] : null,
                'duplicate_of_id' => $duplicate !== null ? (int) $duplicate['id'] : null,
                'idempotency_key' => $key !== '' ? mb_substr($key, 0, 80) : null,
                'client_local_id' => $local !== '' ? mb_substr($local, 0, 80) : null,
                'trip_id' => (int) ($input['trip_id'] ?? 0) > 0 ? (int) $input['trip_id'] : null,
            ]);
            $this->storeAllocations($id, $amount, $input);
        });
        $this->audit->record('expense', $id, 'EXPENSE_CREATED', null, ['number' => $number], $userId);
        BusinessEventDispatcher::emit('EXPENSE_CREATED', 'EXPENSE', $id, $userId, ['number' => strtolower($number)]);

        return ['errors' => [], 'id' => $id, 'warning' => $warning, 'number' => $number];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null, already: bool}
     */
    public function submit(int $id, int $userId): array
    {
        if (!can('expenses.submit') && !can('expenses.create')) {
            return ['errors' => ['_form' => 'You cannot submit an expense.'], 'id' => null, 'already' => false];
        }
        $row = $this->visible($id, $userId);
        if ($row === null) {
            return ['errors' => ['_form' => 'That expense was not found.'], 'id' => null, 'already' => false];
        }
        if ((string) $row['status'] === 'SUBMITTED') {
            return ['errors' => [], 'id' => $id, 'already' => true];
        }
        if (!in_array((string) $row['status'], ['DRAFT', 'REVIEW_REQUIRED'], true)) {
            return ['errors' => ['status' => 'This expense cannot be submitted.'], 'id' => $id, 'already' => false];
        }
        $status = 'SUBMITTED';
        if ((int) $row['receipt_required'] === 1 && ($row['receipt_sha256'] === null || $row['receipt_sha256'] === '') && ($row['receipt_path'] === null || $row['receipt_path'] === '')) {
            $status = 'REVIEW_REQUIRED';
        }
        if ($row['represented_by_type'] !== null) {
            $status = 'REVIEW_REQUIRED';
        }
        $this->repo->updateExpense($id, ['status' => $status, 'submitted_at' => date('Y-m-d H:i:s')]);
        $this->audit->record('expense', $id, 'EXPENSE_SUBMITTED', null, ['status' => $status], $userId);
        BusinessEventDispatcher::emit('EXPENSE_SUBMITTED', 'EXPENSE', $id, $userId, ['status' => strtolower($status)]);
        if ($status === 'SUBMITTED') {
            (new NotificationService())->send(null, null, 'SYSTEM', 'Expense ' . $row['expense_number'] . ' submitted for approval.', 'Review the expense before it is costed.', 'EXPENSE', $id, 'NORMAL', 'expense-submit-' . $id);
        }

        return ['errors' => [], 'id' => $id, 'already' => false];
    }

    /**
     * @return array{errors: array<string, string>, posted: bool, warning: string|null}
     */
    public function approve(int $id, int $userId): array
    {
        if (!can('expenses.approve')) {
            return ['errors' => ['_form' => 'You cannot approve an expense.'], 'posted' => false, 'warning' => null];
        }
        $row = $this->repo->find($id);
        if ($row === null || !$this->canSee($row, $userId)) {
            return ['errors' => ['_form' => 'That expense was not found.'], 'posted' => false, 'warning' => null];
        }
        if ((string) $row['status'] === 'APPROVED') {
            return ['errors' => [], 'posted' => false, 'warning' => null];
        }
        if ((int) $row['prevent_self_approval'] === 1 && (int) $row['submitted_by'] === $userId && in_array((string) $row['status'], ['SUBMITTED', 'REVIEW_REQUIRED'], true)) {
            return ['errors' => ['approval' => 'You cannot approve your own expense.'], 'posted' => false, 'warning' => null];
        }
        if ($row['represented_by_type'] !== null) {
            if ((string) $row['status'] !== 'REVIEW_REQUIRED') {
                $this->repo->updateExpense($id, ['status' => 'REVIEW_REQUIRED']);
            }
            $this->audit->record('expense', $id, 'EXPENSE_REVIEW', null, ['source' => (string) $row['represented_by_type']], $userId);

            return ['errors' => [], 'posted' => false, 'warning' => 'Already costed from ' . (string) $row['represented_by_type'] . '. No job cost was posted.'];
        }
        if ((string) $row['status'] !== 'SUBMITTED') {
            return ['errors' => ['status' => 'Submit the expense before approval.'], 'posted' => false, 'warning' => null];
        }
        $threshold = $row['approval_threshold'];
        if ($threshold !== null && Decimal::cmp((string) $row['amount_inc_vat'], (string) $threshold) > 0 && !can('expenses.view_all')) {
            return ['errors' => ['approval' => 'This amount needs a manager.'], 'posted' => false, 'warning' => null];
        }
        if ((int) $row['receipt_required'] === 1 && ($row['receipt_sha256'] === null || $row['receipt_sha256'] === '')) {
            return ['errors' => ['receipt' => 'Attach the receipt before approval.'], 'posted' => false, 'warning' => null];
        }
        $lines = $this->repo->allocations($id);
        $sum = '0.00';
        foreach ($lines as $line) {
            $sum = Decimal::money(Decimal::add($sum, (string) $line['amount']));
        }
        if ($lines !== [] && Decimal::cmp($sum, (string) $row['amount_inc_vat']) !== 0) {
            return ['errors' => ['allocation' => 'Allocations must equal the expense amount.'], 'posted' => false, 'warning' => null];
        }
        $jobs = [];
        Database::transaction(function () use ($row, $lines, $userId, &$jobs): void {
            foreach ($lines as $line) {
                if ($this->repo->postingForAllocation((int) $line['id']) !== null) {
                    continue;
                }
                $this->postLine($row, $line, $userId, $jobs);
            }
            $this->repo->updateExpense((int) $row['id'], [
                'status' => 'APPROVED',
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
            ]);
            foreach (array_keys($jobs) as $jobId) {
                $this->costing->refresh((int) $jobId);
            }
        });
        $this->audit->record('expense', $id, 'EXPENSE_APPROVED', null, ['amount' => (string) $row['amount_inc_vat']], $userId);
        BusinessEventDispatcher::emit('EXPENSE_APPROVED', 'EXPENSE', $id, $userId, []);

        return ['errors' => [], 'posted' => true, 'warning' => null];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function reject(int $id, string $reason, int $userId): array
    {
        if (!can('expenses.reject') && !can('expenses.approve')) {
            return ['errors' => ['_form' => 'You cannot reject an expense.']];
        }
        $reason = trim($reason);
        if ($reason === '') {
            return ['errors' => ['reason' => 'Give a reason.']];
        }
        $row = $this->repo->find($id);
        if ($row === null) {
            return ['errors' => ['_form' => 'That expense was not found.']];
        }
        if ((string) $row['status'] === 'APPROVED') {
            return ['errors' => ['status' => 'Reverse an approved expense. Do not reject it.']];
        }
        $this->repo->updateExpense($id, [
            'status' => 'REJECTED',
            'rejected_by' => $userId,
            'rejection_reason' => mb_substr($reason, 0, 255),
        ]);
        $this->audit->record('expense', $id, 'EXPENSE_REJECTED', null, ['reason' => $reason], $userId);
        BusinessEventDispatcher::emit('EXPENSE_REJECTED', 'EXPENSE', $id, $userId, []);

        return ['errors' => []];
    }

    /**
     * @return array{errors: array<string, string>}
     */
    public function reverse(int $id, int $userId): array
    {
        if (!can('expenses.reverse')) {
            return ['errors' => ['_form' => 'You cannot reverse an expense.']];
        }
        $row = $this->repo->find($id);
        if ($row === null || (string) $row['status'] !== 'APPROVED') {
            return ['errors' => ['status' => 'Only an approved expense can be reversed.']];
        }
        if ($row['reversed_at'] !== null) {
            return ['errors' => []];
        }
        $jobs = [];
        Database::transaction(function () use ($row, $userId, &$jobs): void {
            foreach ($this->repo->postings((int) $row['id']) as $posting) {
                if ($posting['reversed_at'] !== null) {
                    continue;
                }
                $amount = Decimal::money(Decimal::sub('0', (string) $posting['amount']));
                $otherId = null;
                $directId = null;
                if ($posting['job_id'] !== null && $posting['other_cost_id'] !== null) {
                    $otherId = $this->ops->insertOther([
                        'job_id' => (int) $posting['job_id'],
                        'cost_type' => (string) $row['other_cost_type'],
                        'description' => 'Reversal ' . $row['expense_number'],
                        'supplier_id' => null,
                        'quantity' => '1.0000',
                        'unit_cost' => $amount,
                        'total_cost' => $amount,
                        'reference' => 'REV-' . $row['expense_number'] . '-A' . $posting['allocation_id'],
                        'created_by' => $userId,
                    ]);
                    $jobs[(int) $posting['job_id']] = true;
                }
                if ($posting['project_id'] !== null && $posting['direct_cost_id'] !== null) {
                    $directId = $this->repo->insertDirectCost((int) $posting['project_id'], 'Reversal ' . $row['expense_number'], $amount, (string) $row['expense_date'], $userId);
                }
                $this->repo->markPostingReversed((int) $posting['id'], $otherId, $directId);
            }
            $this->repo->updateExpense((int) $row['id'], ['reversed_at' => date('Y-m-d H:i:s'), 'reversed_by' => $userId]);
            foreach (array_keys($jobs) as $jobId) {
                $this->costing->refresh((int) $jobId);
            }
        });
        $this->audit->record('expense', $id, 'EXPENSE_REVERSED', null, ['number' => (string) $row['expense_number']], $userId);
        BusinessEventDispatcher::emit('EXPENSE_REVERSED', 'EXPENSE', $id, $userId, []);

        return ['errors' => []];
    }

    /**
     * Proposed receipt fields. Confirming them does not approve the expense.
     *
     * @return array{errors: array<string, string>, proposed: array<string, string>}
     */
    public function proposeReceipt(int $id, string $text, int $userId): array
    {
        $row = $this->visible($id, $userId);
        if ($row === null) {
            return ['errors' => ['_form' => 'That expense was not found.'], 'proposed' => []];
        }
        $proposed = [
            'merchant' => $this->labelled($text, 'merchant'),
            'date' => $this->labelled($text, 'date'),
            'total' => $this->labelledMoney($text, 'total'),
            'vat' => $this->labelledMoney($text, 'vat'),
            'reference' => $this->labelled($text, 'invoice'),
        ];
        $this->repo->updateExpense($id, ['extraction_json' => json_encode($proposed, JSON_THROW_ON_ERROR)]);
        $this->audit->record('expense', $id, 'RECEIPT_EXTRACTED', null, ['fields' => implode(',', array_keys(array_filter($proposed)))], $userId);

        return ['errors' => [], 'proposed' => $proposed];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{errors: array<string, string>}
     */
    public function confirmReceipt(int $id, array $fields, int $userId): array
    {
        $row = $this->visible($id, $userId);
        if ($row === null) {
            return ['errors' => ['_form' => 'That expense was not found.']];
        }
        if ((string) $row['status'] === 'APPROVED') {
            return ['errors' => ['status' => 'An approved expense is not edited in place.']];
        }
        $update = ['confirmed_json' => json_encode($this->scalar($fields), JSON_THROW_ON_ERROR)];
        $vat = $this->optionalMoney($fields['vat'] ?? $fields['vat_amount'] ?? null);
        if ($vat !== null) {
            $update['vat_amount'] = $vat;
        }
        $total = $this->optionalMoney($fields['total'] ?? $fields['amount_inc_vat'] ?? null);
        if ($total !== null) {
            $update['amount_inc_vat'] = $total;
        }
        $merchant = trim((string) ($fields['merchant'] ?? ''));
        if ($merchant !== '') {
            $update['merchant_name'] = mb_substr($merchant, 0, 180);
        }
        $this->repo->updateExpense($id, $update);
        $this->audit->record('expense', $id, 'RECEIPT_CONFIRMED', null, [], $userId);

        return ['errors' => []];
    }

    /**
     * @return array{errors: array<string, string>, stored: bool}
     */
    public function attach(int $id, string $filename, string $contents, int $userId): array
    {
        $row = $this->visible($id, $userId);
        if ($row === null) {
            return ['errors' => ['_form' => 'That expense was not found.'], 'stored' => false];
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, self::BLOCKED_FILES, true) || str_contains($contents, '<?php')) {
            return ['errors' => ['file' => 'That file cannot be stored as a receipt.'], 'stored' => false];
        }
        $hash = hash('sha256', $contents);
        $prior = $this->repo->duplicate($userId, (string) $row['amount_inc_vat'], (string) $row['expense_date'], (string) ($row['merchant_name'] ?? ''), $hash);
        $fields = ['receipt_sha256' => $hash, 'receipt_name' => mb_substr($filename, 0, 180)];
        if ($prior !== null && (int) $prior['id'] !== $id) {
            $fields['duplicate_of_id'] = (int) $prior['id'];
        }
        $this->repo->updateExpense($id, $fields);
        $this->audit->record('expense', $id, 'RECEIPT_ADDED', null, ['sha256' => $hash], $userId);

        return ['errors' => [], 'stored' => true];
    }

    /**
     * @param list<int> $expenseIds
     * @return array{errors: array<string, string>, id: int|null, number: string|null}
     */
    public function reimbursementBatch(array $expenseIds, int $userId): array
    {
        if (!can('expenses.reimbursement.manage')) {
            return ['errors' => ['_form' => 'You cannot prepare a reimbursement batch.'], 'id' => null, 'number' => null];
        }
        $rows = [];
        foreach ($expenseIds as $expenseId) {
            $row = $this->repo->find((int) $expenseId);
            if ($row === null || (string) $row['status'] !== 'APPROVED' || $row['reversed_at'] !== null) {
                return ['errors' => ['_form' => 'Only approved expenses can be batched.'], 'id' => null, 'number' => null];
            }
            if (!in_array((string) $row['reimbursement_status'], ['NOT_SUBMITTED', 'PENDING'], true)) {
                return ['errors' => ['_form' => 'That expense is not reimbursable.'], 'id' => null, 'number' => null];
            }
            $rows[] = $row;
        }
        $id = 0;
        $number = '';
        Database::transaction(function () use ($rows, $userId, &$id, &$number): void {
            $number = $this->numbers->reimbursementBatch();
            $id = $this->repo->insertBatch(['batch_number' => $number, 'notes' => null, 'created_by' => $userId]);
            foreach ($rows as $row) {
                $this->repo->addBatchItem($id, (int) $row['id'], (string) $row['amount_inc_vat']);
                $this->repo->updateExpense((int) $row['id'], ['reimbursement_status' => 'PENDING']);
            }
        });
        $this->audit->record('reimbursement_batch', $id, 'REIMBURSEMENT_BATCH_CREATED', null, ['number' => $number], $userId);
        BusinessEventDispatcher::emit('REIMBURSEMENT_BATCH_CREATED', 'REIMBURSEMENT_BATCH', $id, $userId, []);

        return ['errors' => [], 'id' => $id, 'number' => $number];
    }

    /**
     * @return array{errors: array<string, string>, csv: string}
     */
    public function exportBatch(int $id, int $userId): array
    {
        if (!can('expenses.export') && !can('expenses.reimbursement.manage')) {
            return ['errors' => ['_form' => 'You cannot export a reimbursement batch.'], 'csv' => ''];
        }
        $batch = $this->repo->batch($id);
        if ($batch === null) {
            return ['errors' => ['_form' => 'That batch was not found.'], 'csv' => ''];
        }
        $lines = ['expense_number,date,merchant,description,amount'];
        foreach ($this->repo->batchItems($id) as $item) {
            $lines[] = implode(',', [
                $this->csv((string) $item['expense_number']),
                $this->csv((string) $item['expense_date']),
                $this->csv((string) ($item['merchant_name'] ?? '')),
                $this->csv((string) $item['description']),
                $this->csv((string) $item['amount']),
            ]);
            $this->repo->updateExpense((int) $item['expense_id'], ['reimbursement_status' => 'EXPORTED']);
        }
        $this->repo->markBatch($id, 'EXPORTED');
        $this->audit->record('reimbursement_batch', $id, 'REIMBURSEMENT_BATCH_EXPORTED', null, [], $userId);
        BusinessEventDispatcher::emit('REIMBURSEMENT_BATCH_EXPORTED', 'REIMBURSEMENT_BATCH', $id, $userId, []);

        return ['errors' => [], 'csv' => implode("\n", $lines) . "\n"];
    }

    /**
     * Marks an external payment as reported. It does not send money.
     *
     * @return array{errors: array<string, string>}
     */
    public function confirmExternal(int $id, int $userId): array
    {
        if (!can('expenses.reimbursement.manage')) {
            return ['errors' => ['_form' => 'You cannot confirm an external reimbursement.']];
        }
        $batch = $this->repo->batch($id);
        if ($batch === null || (string) $batch['status'] !== 'EXPORTED') {
            return ['errors' => ['status' => 'Export the batch first.']];
        }
        foreach ($this->repo->batchItems($id) as $item) {
            $this->repo->updateExpense((int) $item['expense_id'], ['reimbursement_status' => 'REIMBURSED_EXTERNALLY']);
        }
        $this->repo->markBatch($id, 'CONFIRMED_EXTERNALLY');
        $this->audit->record('reimbursement_batch', $id, 'REIMBURSEMENT_REPORTED', null, ['note' => 'informational'], $userId);

        return ['errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null, distance: string|null, rate: string|null, cost: string|null}
     */
    public function recordMileage(array $input, int $userId): array
    {
        if (!can('mileage.create')) {
            return ['errors' => ['_form' => 'You cannot record mileage.'], 'id' => null, 'distance' => null, 'rate' => null, 'cost' => null];
        }
        $start = $this->optionalDecimal($input['start_odometer'] ?? null, 1);
        $end = $this->optionalDecimal($input['end_odometer'] ?? null, 1);
        if ($start !== null && $end !== null && Decimal::cmp($end, $start) < 0) {
            return ['errors' => ['end_odometer' => 'The end reading is before the start reading.'], 'id' => null, 'distance' => null, 'rate' => null, 'cost' => null];
        }
        $distance = $start !== null && $end !== null
            ? Decimal::round(Decimal::sub($end, $start), 2)
            : $this->optionalDecimal($input['distance_km'] ?? null, 2);
        if ($distance === null || Decimal::cmp($distance, '0') < 0) {
            return ['errors' => ['distance_km' => 'Enter the distance.'], 'id' => null, 'distance' => null, 'rate' => null, 'cost' => null];
        }
        $anomaly = false;
        $note = null;
        if ($start !== null && $end !== null && isset($input['distance_km']) && $input['distance_km'] !== '') {
            $claimed = $this->optionalDecimal($input['distance_km'], 2);
            if ($claimed !== null && Decimal::cmp(Decimal::round(Decimal::sub($claimed, $distance), 2), '1.00') > 0) {
                $anomaly = true;
                $note = 'Claimed distance does not match the odometer.';
            }
        }
        $use = strtoupper(trim((string) ($input['vehicle_use'] ?? 'COMPANY')));
        if (!in_array($use, ['COMPANY', 'PERSONAL'], true)) {
            $use = 'COMPANY';
        }
        $rate = Decimal::round((string) SettingsService::get('mileage_rate_per_km', '4.50'), 4);
        $cost = Decimal::money(Decimal::mul($distance, $rate));
        $purpose = trim((string) ($input['purpose'] ?? ''));
        if ($purpose === '') {
            return ['errors' => ['purpose' => 'Say what the trip was for.'], 'id' => null, 'distance' => null, 'rate' => null, 'cost' => null];
        }
        $date = trim((string) ($input['trip_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['errors' => ['trip_date' => 'Enter the trip date.'], 'id' => null, 'distance' => null, 'rate' => null, 'cost' => null];
        }
        $source = strtoupper(trim((string) ($input['source'] ?? 'ODOMETER')));
        if (!in_array($source, ['MANUAL', 'ODOMETER', 'MAP_ESTIMATE', 'OTHER'], true)) {
            $source = 'MANUAL';
        }
        $id = $this->repo->insertMileage([
            'user_id' => $userId,
            'vehicle_label' => $this->short($input['vehicle_label'] ?? null, 120),
            'vehicle_use' => $use,
            'trip_date' => $date,
            'origin_text' => $this->short($input['origin_text'] ?? null, 180),
            'destination_text' => $this->short($input['destination_text'] ?? null, 180),
            'start_odometer' => $start,
            'end_odometer' => $end,
            'distance_km' => $distance,
            'purpose' => mb_substr($purpose, 0, 180),
            'job_id' => (int) ($input['job_id'] ?? 0) > 0 ? (int) $input['job_id'] : null,
            'project_id' => (int) ($input['project_id'] ?? 0) > 0 ? (int) $input['project_id'] : null,
            'trip_id' => (int) ($input['trip_id'] ?? 0) > 0 ? (int) $input['trip_id'] : null,
            'rate_per_km' => $rate,
            'cost' => $cost,
            'source' => $source,
            'reimbursable' => $use === 'PERSONAL' ? 1 : 0,
            'anomaly' => $anomaly ? 1 : 0,
            'anomaly_note' => $note,
            'created_by' => $userId,
        ]);
        $this->audit->record('mileage', $id, 'MILEAGE_RECORDED', null, ['distance' => $distance, 'rate' => $rate], $userId);
        BusinessEventDispatcher::emit('MILEAGE_RECORDED', 'MILEAGE', $id, $userId, ['km' => $distance]);

        return ['errors' => [], 'id' => $id, 'distance' => $distance, 'rate' => $rate, 'cost' => $cost];
    }

    /**
     * @param list<int> $jobIds
     * @return array{errors: array<string, string>, id: int|null, number: string|null}
     */
    public function createTrip(array $input, array $jobIds, int $userId): array
    {
        if (!can('trips.manage')) {
            return ['errors' => ['_form' => 'You cannot record a trip.'], 'id' => null, 'number' => null];
        }
        $date = trim((string) ($input['trip_date'] ?? ''));
        $purpose = trim((string) ($input['purpose'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $purpose === '') {
            return ['errors' => ['purpose' => 'Enter the date and purpose.'], 'id' => null, 'number' => null];
        }
        $id = 0;
        $number = '';
        Database::transaction(function () use ($input, $jobIds, $userId, $date, $purpose, &$id, &$number): void {
            $number = $this->numbers->fieldTrip();
            $id = $this->repo->insertTrip([
                'trip_number' => $number,
                'trip_date' => $date,
                'driver_user_id' => $userId,
                'vehicle_label' => $this->short($input['vehicle_label'] ?? null, 120),
                'purpose' => mb_substr($purpose, 0, 180),
                'origin_text' => $this->short($input['origin_text'] ?? null, 180),
                'allocation_method' => 'EQUAL',
                'created_by' => $userId,
            ]);
            $sort = 1;
            foreach ($jobIds as $jobId) {
                if ((int) $jobId < 1) {
                    continue;
                }
                $this->repo->addStop($id, (int) $jobId, 'Job ' . $jobId, $sort);
                $this->repo->addTripAllocation($id, (int) $jobId, null, null);
                $sort++;
            }
        });
        $this->audit->record('field_trip', $id, 'TRIP_CREATED', null, ['number' => $number], $userId);
        BusinessEventDispatcher::emit('TRIP_CREATED', 'FIELD_TRIP', $id, $userId, []);

        return ['errors' => [], 'id' => $id, 'number' => $number];
    }

    /**
     * Allocates trip mileage once. An existing logistics cost is referenced, not copied.
     *
     * @return array{errors: array<string, string>, amounts: array<int, string>}
     */
    public function completeTrip(int $id, int $userId): array
    {
        if (!can('trips.manage')) {
            return ['errors' => ['_form' => 'You cannot complete a trip.'], 'amounts' => []];
        }
        $trip = $this->repo->trip($id);
        if ($trip === null) {
            return ['errors' => ['_form' => 'That trip was not found.'], 'amounts' => []];
        }
        $allocations = $this->repo->tripAllocations($id);
        if ($allocations === []) {
            return ['errors' => ['jobs' => 'Add at least one job.'], 'amounts' => []];
        }
        $total = '0.00';
        foreach ($this->repo->mileageForTrip($id) as $row) {
            $total = Decimal::money(Decimal::add($total, (string) $row['cost']));
        }
        $weights = array_fill(0, count($allocations), '1');
        $shares = $this->shares($total, $weights);
        $amounts = [];
        $jobs = [];
        Database::transaction(function () use ($trip, $allocations, $shares, $userId, &$amounts, &$jobs): void {
            foreach ($allocations as $index => $allocation) {
                $jobId = (int) $allocation['job_id'];
                $amount = $shares[$index];
                $amounts[$jobId] = $amount;
                if ($this->repo->tripPosting((int) $trip['id'], $jobId) !== null) {
                    continue;
                }
                $existing = $this->repo->logisticsForJob($jobId);
                $otherId = null;
                if (Decimal::cmp($amount, '0') > 0) {
                    $reference = 'TRIP-' . $trip['trip_number'] . '-J' . $jobId;
                    $prior = $this->ops->otherCostByReference($jobId, $reference);
                    if ($prior === null) {
                        $otherId = $this->ops->insertOther([
                            'job_id' => $jobId,
                            'cost_type' => 'TRAVEL',
                            'description' => 'Trip ' . $trip['trip_number'],
                            'supplier_id' => null,
                            'quantity' => '1.0000',
                            'unit_cost' => $amount,
                            'total_cost' => $amount,
                            'reference' => $reference,
                            'created_by' => $userId,
                        ]);
                        $jobs[$jobId] = true;
                    } else {
                        $otherId = (int) $prior['id'];
                    }
                }
                $this->repo->insertTripPosting((int) $trip['id'], $jobId, $otherId, $existing !== null ? (int) $existing['id'] : null, $amount);
            }
            $this->repo->completeTrip((int) $trip['id']);
            foreach (array_keys($jobs) as $jobId) {
                $this->costing->refresh((int) $jobId);
            }
        });
        if ((string) $trip['status'] === 'OPEN') {
            $this->audit->record('field_trip', $id, 'TRIP_COMPLETED', null, [], $userId);
            BusinessEventDispatcher::emit('TRIP_COMPLETED', 'FIELD_TRIP', $id, $userId, []);
        }

        return ['errors' => [], 'amounts' => $amounts];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function page(int $limit, int $offset, int $userId): array
    {
        if (!can('expenses.view')) {
            return [];
        }

        return $this->repo->page($limit, $offset, can('expenses.view_all') ? null : $userId);
    }

    /**
     * @return array<string, int>
     */
    public function dashboard(): array
    {
        return can('expenses.view') ? $this->repo->counts() : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function open(int $id, int $userId): ?array
    {
        $row = $this->repo->find($id);
        if ($row === null || !$this->canSee($row, $userId)) {
            return null;
        }
        $row['allocations'] = $this->repo->allocations($id);
        $row['postings'] = $this->repo->postings($id);

        return $row;
    }

    /**
     * @param list<string> $weights
     * @return list<string>
     */
    public function shares(string $total, array $weights): array
    {
        $total = Decimal::money($total);
        $count = count($weights);
        if ($count === 0) {
            return [];
        }
        $cents = Decimal::mul($total, '100', 0);
        $weightSum = '0';
        foreach ($weights as $weight) {
            $weightSum = Decimal::add($weightSum, $weight, 4);
        }
        $divisor = Decimal::floorDiv($weightSum, '1');
        if (Decimal::cmp($divisor, '0') === 0) {
            return array_fill(0, $count, '0.00');
        }
        $out = [];
        $used = '0.00';
        foreach ($weights as $index => $weight) {
            if ($index === $count - 1) {
                $out[] = Decimal::money(Decimal::sub($total, $used));
                break;
            }
            $shareCents = Decimal::floorDiv(Decimal::mul($cents, Decimal::floorDiv($weight, '1'), 0), $divisor);
            $share = Decimal::money(Decimal::div($shareCents, '100', 4));
            $out[] = $share;
            $used = Decimal::money(Decimal::add($used, $share));
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $expense
     * @param array<string, mixed> $line
     * @param array<int, bool> $jobs
     */
    private function postLine(array $expense, array $line, int $userId, array &$jobs): void
    {
        if ((string) $expense['costing_behaviour'] === 'DO_NOT_POST') {
            $this->repo->insertPosting([
                'expense_id' => (int) $expense['id'],
                'allocation_id' => (int) $line['id'],
                'job_id' => null,
                'project_id' => null,
                'other_cost_id' => null,
                'direct_cost_id' => null,
                'amount' => '0.00',
            ]);

            return;
        }
        $type = (string) $expense['other_cost_type'];
        if (OtherCostType::tryFrom($type) === null) {
            $type = 'OTHER';
        }
        $amount = Decimal::money((string) $line['amount']);
        $reference = 'EXP-' . $expense['expense_number'] . '-A' . $line['id'];
        $otherId = null;
        $directId = null;
        $jobId = $line['job_id'] !== null ? (int) $line['job_id'] : null;
        $projectId = $line['project_id'] !== null ? (int) $line['project_id'] : null;
        if ($jobId !== null && (string) $line['target_type'] !== 'PROJECT') {
            $existing = $this->ops->otherCostByReference($jobId, $reference);
            $otherId = $existing !== null ? (int) $existing['id'] : $this->ops->insertOther([
                'job_id' => $jobId,
                'cost_type' => $type,
                'description' => mb_substr((string) $expense['description'], 0, 180),
                'supplier_id' => $expense['supplier_id'] !== null ? (int) $expense['supplier_id'] : null,
                'quantity' => '1.0000',
                'unit_cost' => $amount,
                'total_cost' => $amount,
                'reference' => $reference,
                'created_by' => $userId,
            ]);
            $jobs[$jobId] = true;
            $projectId = null;
        } elseif ($projectId !== null) {
            $directId = $this->repo->insertDirectCost($projectId, (string) $expense['expense_number'] . ' ' . $expense['description'], $amount, (string) $expense['expense_date'], $userId);
        }
        $this->repo->insertPosting([
            'expense_id' => (int) $expense['id'],
            'allocation_id' => (int) $line['id'],
            'job_id' => $jobId,
            'project_id' => $projectId,
            'other_cost_id' => $otherId,
            'direct_cost_id' => $directId,
            'amount' => $jobId !== null || $projectId !== null ? $amount : '0.00',
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeAllocations(int $expenseId, string $total, array $input): void
    {
        $raw = $input['allocations'] ?? null;
        if (!is_array($raw) || $raw === []) {
            $jobId = (int) ($input['job_id'] ?? 0);
            $projectId = (int) ($input['project_id'] ?? 0);
            if ($jobId > 0) {
                $this->repo->insertAllocation([
                    'expense_id' => $expenseId, 'line_no' => 1, 'target_type' => 'JOB', 'target_id' => $jobId,
                    'job_id' => $jobId, 'project_id' => null, 'method' => 'MANUAL', 'percent' => '100.0000',
                    'amount' => $total, 'calculation_note' => 'Full amount on the job.',
                ]);
            } elseif ($projectId > 0) {
                $this->repo->insertAllocation([
                    'expense_id' => $expenseId, 'line_no' => 1, 'target_type' => 'PROJECT', 'target_id' => $projectId,
                    'job_id' => null, 'project_id' => $projectId, 'method' => 'MANUAL', 'percent' => '100.0000',
                    'amount' => $total, 'calculation_note' => 'Project cost. Not also posted on a job.',
                ]);
            } else {
                $this->repo->insertAllocation([
                    'expense_id' => $expenseId, 'line_no' => 1, 'target_type' => 'GENERAL', 'target_id' => null,
                    'job_id' => null, 'project_id' => null, 'method' => 'MANUAL', 'percent' => '100.0000',
                    'amount' => $total, 'calculation_note' => 'General operations. Not a job cost.',
                ]);
            }

            return;
        }
        $weights = [];
        $clean = [];
        foreach ($raw as $line) {
            if (!is_array($line)) {
                continue;
            }
            $weight = trim((string) ($line['percent'] ?? ''));
            if ($weight === '') {
                $weight = '1';
            }
            $weights[] = $weight;
            $clean[] = $line;
        }
        $method = strtoupper(trim((string) ($input['allocation_method'] ?? 'PERCENTAGE')));
        if (!in_array($method, ['MANUAL', 'PERCENTAGE', 'EQUAL', 'BY_DISTANCE', 'BY_VALUE'], true)) {
            $method = 'PERCENTAGE';
        }
        $shares = $method === 'MANUAL' ? [] : $this->shares($total, $method === 'EQUAL' ? array_fill(0, count($clean), '1') : $weights);
        foreach ($clean as $index => $line) {
            $type = strtoupper(trim((string) ($line['target_type'] ?? 'JOB')));
            if (!in_array($type, self::ALLOCATIONS, true)) {
                $type = 'JOB';
            }
            $jobId = (int) ($line['job_id'] ?? 0);
            $projectId = (int) ($line['project_id'] ?? 0);
            $amount = $method === 'MANUAL' ? ($this->moneyField($line['amount'] ?? null) ?? '0.00') : $shares[$index];
            $percent = $line['percent'] ?? null;
            $this->repo->insertAllocation([
                'expense_id' => $expenseId,
                'line_no' => $index + 1,
                'target_type' => $type,
                'target_id' => $jobId > 0 ? $jobId : ($projectId > 0 ? $projectId : null),
                'job_id' => $jobId > 0 ? $jobId : null,
                'project_id' => $type === 'PROJECT' ? ($projectId > 0 ? $projectId : null) : null,
                'method' => $method,
                'percent' => is_numeric((string) $percent) ? Decimal::round((string) $percent, 4) : null,
                'amount' => $amount,
                'calculation_note' => $method . ' share of ' . $total,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $category
     */
    private function reimbursement(array $category, string $method): string
    {
        if ((int) $category['reimbursable'] !== 1) {
            return 'NOT_APPLICABLE';
        }
        if (in_array($method, ['PERSONAL_CARD', 'CASH'], true)) {
            return 'NOT_SUBMITTED';
        }

        return 'NOT_APPLICABLE';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function visible(int $id, int $userId): ?array
    {
        $row = $this->repo->find($id);
        if ($row === null || !$this->canSee($row, $userId)) {
            return null;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function canSee(array $row, int $userId): bool
    {
        if (!can('expenses.view') && !can('expenses.view_all')) {
            return false;
        }
        if (can('expenses.view_all')) {
            return true;
        }

        return (int) $row['submitted_by'] === $userId || (int) ($row['incurred_by'] ?? 0) === $userId;
    }

    private function moneyField(mixed $value): ?string
    {
        $text = str_replace(['R', ' ', ','], '', trim((string) $value));
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return Decimal::money($text);
    }

    private function optionalMoney(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->moneyField($value);
    }

    private function optionalDecimal(mixed $value, int $scale): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !Decimal::isNumeric($text)) {
            return null;
        }

        return Decimal::round($text, $scale);
    }

    private function hash(mixed $value): ?string
    {
        $text = strtolower(trim((string) $value));
        if (preg_match('/^[a-f0-9]{64}$/', $text) !== 1) {
            return null;
        }

        return $text;
    }

    private function short(mixed $value, int $length): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $length);
    }

    private function labelled(string $text, string $label): string
    {
        if (preg_match('/' . preg_quote($label, '/') . '\s*:\s*(.+)/i', $text, $match) === 1) {
            return trim($match[1]);
        }

        return '';
    }

    private function labelledMoney(string $text, string $label): string
    {
        $line = $this->labelled($text, $label);
        $money = $this->moneyField($line);

        return $money ?? '';
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    private function scalar(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }

    private function csv(string $value): string
    {
        if (preg_match('/^[=+\-@]/', $value) === 1) {
            $value = "'" . $value;
        }
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * @param array<string, string> $errors
     * @return array{errors: array<string, string>, id: null, warning: null, number: null}
     */
    private function fail(array $errors): array
    {
        return ['errors' => $errors, 'id' => null, 'warning' => null, 'number' => null];
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Decimal;

/**
 * Financial documents. Balances written here are a cache of the ledger
 * (invoices, credits, and allocations). Services recompute them in the
 * same transaction as the change.
 */
final class FinanceRepository extends Repository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function terms(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM payment_terms';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }

        return $this->rows($sql . ' ORDER BY days_due, name');
    }

    public function term(int $id): ?array
    {
        return $this->one('SELECT * FROM payment_terms WHERE id = ? LIMIT 1', [$id]);
    }

    public function termByName(string $name): ?array
    {
        return $this->one('SELECT * FROM payment_terms WHERE name = ? LIMIT 1', [$name]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertInvoice(array $data): int
    {
        $this->run(
            'INSERT INTO invoices (
                invoice_number, invoice_type, customer_id, contact_id, quote_id, quote_revision_number, job_id,
                invoice_date, due_date, status, subtotal, discount_type, discount_value, discount_amount,
                subtotal_after_discount, vat_mode, vat_rate, vat_amount, total, amount_paid, credit_applied, balance_due,
                customer_po_number, customer_notes, internal_notes, terms, payment_term_name_snapshot, payment_term_days_snapshot,
                created_by, updated_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['invoice_number'], $data['invoice_type'], $data['customer_id'], $data['contact_id'],
                $data['quote_id'], $data['quote_revision_number'], $data['job_id'], $data['invoice_date'], $data['due_date'],
                $data['status'], $data['subtotal'], $data['discount_type'], $data['discount_value'], $data['discount_amount'],
                $data['subtotal_after_discount'], $data['vat_mode'], $data['vat_rate'], $data['vat_amount'], $data['total'],
                $data['amount_paid'], $data['credit_applied'], $data['balance_due'], $data['customer_po_number'],
                $data['customer_notes'], $data['internal_notes'], $data['terms'], $data['payment_term_name_snapshot'],
                $data['payment_term_days_snapshot'], $data['created_by'], $data['updated_by'],
            ]
        );

        return $this->insertId();
    }

    public function lockInvoice(int $id): ?array
    {
        return $this->one('SELECT * FROM invoices WHERE id = ? LIMIT 1 FOR UPDATE', [$id]);
    }

    public function invoice(int $id): ?array
    {
        return $this->one(
            'SELECT i.*, c.company_name, c.first_name, c.last_name, c.customer_type, c.account_on_hold, c.credit_limit,
                    c.account_hold_reason, j.job_number, q.quote_number
             FROM invoices i
             INNER JOIN customers c ON c.id = i.customer_id
             LEFT JOIN jobs j ON j.id = i.job_id
             LEFT JOIN quotes q ON q.id = i.quote_id
             WHERE i.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function invoices(array $filters, int $limit = 200): array
    {
        $sql = 'SELECT i.*, c.company_name, c.first_name, c.last_name, c.customer_type, j.job_number
                FROM invoices i
                INNER JOIN customers c ON c.id = i.customer_id
                LEFT JOIN jobs j ON j.id = i.job_id
                WHERE 1 = 1';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (i.invoice_number LIKE ? OR i.customer_po_number LIKE ? OR c.company_name LIKE ? OR j.job_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'OVERDUE') {
                $sql .= " AND i.balance_due > 0 AND i.due_date < CURDATE() AND i.status IN ('ISSUED', 'PARTIALLY_PAID')";
            } else {
                $sql .= ' AND i.status = ?';
                $params[] = $filters['status'];
            }
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND i.invoice_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND i.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['job_id'])) {
            $sql .= ' AND i.job_id = ?';
            $params[] = (int) $filters['job_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND i.invoice_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND i.invoice_date <= ?';
            $params[] = $filters['to'];
        }
        $sql .= ' ORDER BY i.id DESC LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateDraft(int $id, array $data): void
    {
        $this->run(
            'UPDATE invoices SET
                invoice_type = ?, invoice_date = ?, due_date = ?, contact_id = ?, customer_po_number = ?,
                customer_notes = ?, internal_notes = ?, terms = ?, discount_type = ?, discount_value = ?,
                payment_term_name_snapshot = ?, payment_term_days_snapshot = ?, collection_flag = ?,
                updated_by = ?, version_number = version_number + 1
             WHERE id = ? AND status = \'DRAFT\'',
            [
                $data['invoice_type'], $data['invoice_date'], $data['due_date'], $data['contact_id'],
                $data['customer_po_number'], $data['customer_notes'], $data['internal_notes'], $data['terms'],
                $data['discount_type'], $data['discount_value'], $data['payment_term_name_snapshot'],
                $data['payment_term_days_snapshot'], $data['collection_flag'], $data['updated_by'], $id,
            ]
        );
    }

    /**
     * @param array<string, mixed> $totals
     */
    public function saveTotals(int $id, array $totals): void
    {
        $this->run(
            'UPDATE invoices SET
                subtotal = ?, discount_amount = ?, subtotal_after_discount = ?, vat_mode = ?, vat_rate = ?,
                vat_amount = ?, total = ?, balance_due = ?
             WHERE id = ?',
            [
                $totals['subtotal'], $totals['discount_amount'], $totals['subtotal_after_discount'],
                $totals['vat_mode'], $totals['vat_rate'], $totals['vat_amount'], $totals['total'],
                $totals['balance_due'], $id,
            ]
        );
    }

    /**
     * @param array<string, mixed> $snap
     */
    public function issue(int $id, array $snap): void
    {
        $this->run(
            'UPDATE invoices SET
                invoice_number = ?, status = ?, issued_at = NOW(), issued_by = ?,
                customer_name_snapshot = ?, customer_vat_number_snapshot = ?, customer_address_snapshot = ?,
                customer_email_snapshot = ?, company_name_snapshot = ?, company_vat_number_snapshot = ?,
                company_address_snapshot = ?, bank_name_snapshot = ?, account_name_snapshot = ?,
                account_number_snapshot = ?, branch_code_snapshot = ?, account_type_snapshot = ?,
                over_invoice_reason = ?, over_invoice_by = ?, over_invoice_at = ?,
                version_number = version_number + 1
             WHERE id = ? AND status = \'DRAFT\'',
            [
                $snap['invoice_number'], $snap['status'], $snap['issued_by'],
                $snap['customer_name_snapshot'], $snap['customer_vat_number_snapshot'], $snap['customer_address_snapshot'],
                $snap['customer_email_snapshot'], $snap['company_name_snapshot'], $snap['company_vat_number_snapshot'],
                $snap['company_address_snapshot'], $snap['bank_name_snapshot'], $snap['account_name_snapshot'],
                $snap['account_number_snapshot'], $snap['branch_code_snapshot'], $snap['account_type_snapshot'],
                $snap['over_invoice_reason'], $snap['over_invoice_by'], $snap['over_invoice_at'], $id,
            ]
        );
    }

    public function cancel(int $id, int $userId, string $reason): void
    {
        $this->run(
            'UPDATE invoices SET status = \'CANCELLED\', cancelled_at = NOW(), cancelled_by = ?, cancellation_reason = ?,
                    balance_due = 0, version_number = version_number + 1
             WHERE id = ?',
            [$userId, $reason, $id]
        );
    }

    public function setCollection(int $id, ?string $flag): void
    {
        $this->run('UPDATE invoices SET collection_flag = ? WHERE id = ?', [$flag, $id]);
    }

    /**
     * @param array<string, mixed> $amounts
     */
    public function applyAmounts(int $id, array $amounts): void
    {
        $this->run(
            'UPDATE invoices SET amount_paid = ?, credit_applied = ?, balance_due = ?, status = ?, paid_at = ?
             WHERE id = ?',
            [
                $amounts['amount_paid'], $amounts['credit_applied'], $amounts['balance_due'],
                $amounts['status'], $amounts['paid_at'], $id,
            ]
        );
    }

    public function addHistory(int $invoiceId, ?string $old, string $new, ?int $userId, ?string $notes): void
    {
        $this->run(
            'INSERT INTO invoice_status_history (invoice_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)',
            [$invoiceId, $old, $new, $userId, $notes]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $invoiceId): array
    {
        return $this->rows(
            'SELECT h.*, u.name AS user_name FROM invoice_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.invoice_id = ? ORDER BY h.id',
            [$invoiceId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $invoiceId): array
    {
        return $this->rows('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$invoiceId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertItem(array $data): int
    {
        $this->run(
            'INSERT INTO invoice_items (
                invoice_id, sort_order, source_type, source_id, description, quantity, unit, unit_price,
                line_subtotal, discount_amount, vat_rate_snapshot, vat_amount, line_total
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['invoice_id'], $data['sort_order'], $data['source_type'], $data['source_id'],
                $data['description'], $data['quantity'], $data['unit'], $data['unit_price'],
                $data['line_subtotal'], $data['discount_amount'], $data['vat_rate_snapshot'],
                $data['vat_amount'], $data['line_total'],
            ]
        );

        return $this->insertId();
    }

    public function deleteItems(int $invoiceId): void
    {
        $this->run('DELETE FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);
    }

    public function updateItemFigures(int $id, string $vat, string $total, string $rate): void
    {
        $this->run(
            'UPDATE invoice_items SET vat_rate_snapshot = ?, vat_amount = ?, line_total = ? WHERE id = ?',
            [$rate, $vat, $total, $id]
        );
    }

    public function nextSort(int $invoiceId): int
    {
        $row = $this->one('SELECT COALESCE(MAX(sort_order), 0) AS n FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);

        return (int) ($row['n'] ?? 0) + 1;
    }

    public function depositExists(int $quoteId, ?int $jobId): bool
    {
        $sql = "SELECT id FROM invoices WHERE invoice_type = 'DEPOSIT' AND status <> 'CANCELLED' AND (quote_id = ?";
        $params = [$quoteId];
        if ($jobId !== null && $jobId > 0) {
            $sql .= ' OR job_id = ?';
            $params[] = $jobId;
        }
        $sql .= ') LIMIT 1';

        return $this->one($sql, $params) !== null;
    }

    public function invoicedNet(?int $quoteId, ?int $jobId): string
    {
        if (($quoteId === null || $quoteId < 1) && ($jobId === null || $jobId < 1)) {
            return '0.00';
        }
        $where = [];
        $params = [];
        if ($quoteId !== null && $quoteId > 0) {
            $where[] = 'i.quote_id = ?';
            $params[] = $quoteId;
        }
        if ($jobId !== null && $jobId > 0) {
            $where[] = 'i.job_id = ?';
            $params[] = $jobId;
        }
        $clause = implode(' OR ', $where);
        $invoiced = $this->one(
            "SELECT COALESCE(SUM(i.total), 0) AS n FROM invoices i
             WHERE i.status NOT IN ('DRAFT', 'CANCELLED') AND ({$clause})",
            $params
        );
        $credited = $this->one(
            "SELECT COALESCE(SUM(cn.total), 0) AS n FROM credit_notes cn
             INNER JOIN invoices i ON i.id = cn.invoice_id
             WHERE cn.status IN ('ISSUED', 'APPLIED') AND ({$clause})",
            $params
        );

        return Decimal::money(Decimal::sub((string) ($invoiced['n'] ?? '0'), (string) ($credited['n'] ?? '0')));
    }

    public function paidForJob(int $jobId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(a.amount), 0) AS n
             FROM payment_allocations a
             INNER JOIN invoices i ON i.id = a.invoice_id
             INNER JOIN payments p ON p.id = a.payment_id
             WHERE i.job_id = ? AND a.reversed_at IS NULL AND p.status = 'RECORDED'",
            [$jobId]
        );

        return Decimal::money((string) ($row['n'] ?? '0'));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertPayment(array $data): int
    {
        $this->run(
            'INSERT INTO payments (
                payment_reference, customer_id, payment_date, amount, payment_method,
                external_reference, bank_reference, notes, status, recorded_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['payment_reference'], $data['customer_id'], $data['payment_date'], $data['amount'],
                $data['payment_method'], $data['external_reference'], $data['bank_reference'], $data['notes'],
                $data['status'], $data['recorded_by'],
            ]
        );

        return $this->insertId();
    }

    public function lockPayment(int $id): ?array
    {
        return $this->one('SELECT * FROM payments WHERE id = ? LIMIT 1 FOR UPDATE', [$id]);
    }

    public function payment(int $id): ?array
    {
        return $this->one(
            'SELECT p.*, c.company_name, c.first_name, c.last_name, c.customer_type, u.name AS recorded_by_name
             FROM payments p
             INNER JOIN customers c ON c.id = p.customer_id
             LEFT JOIN users u ON u.id = p.recorded_by
             WHERE p.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payments(array $filters, int $limit = 200): array
    {
        $sql = 'SELECT p.*, c.company_name, c.first_name, c.last_name, c.customer_type
                FROM payments p INNER JOIN customers c ON c.id = p.customer_id WHERE 1 = 1';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (p.payment_reference LIKE ? OR p.external_reference LIKE ? OR c.company_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND p.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        $sql .= ' ORDER BY p.id DESC LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    public function allocated(int $paymentId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(amount), 0) AS n FROM payment_allocations WHERE payment_id = ? AND reversed_at IS NULL',
            [$paymentId]
        );

        return Decimal::money((string) ($row['n'] ?? '0'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allocations(int $paymentId): array
    {
        return $this->rows(
            'SELECT a.*, i.invoice_number, i.total, i.balance_due, i.status
             FROM payment_allocations a
             INNER JOIN invoices i ON i.id = a.invoice_id
             WHERE a.payment_id = ? ORDER BY a.id',
            [$paymentId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertAllocation(array $data): int
    {
        $this->run(
            'INSERT INTO payment_allocations (payment_id, invoice_id, amount, allocated_by) VALUES (?, ?, ?, ?)',
            [$data['payment_id'], $data['invoice_id'], $data['amount'], $data['allocated_by']]
        );

        return $this->insertId();
    }

    public function reverseAllocations(int $paymentId): void
    {
        $this->run(
            'UPDATE payment_allocations SET reversed_at = NOW() WHERE payment_id = ? AND reversed_at IS NULL',
            [$paymentId]
        );
    }

    public function reversePayment(int $id, int $userId, string $reason): void
    {
        $this->run(
            'UPDATE payments SET status = \'REVERSED\', reversal_reason = ?, reversed_by = ?, reversed_at = NOW() WHERE id = ?',
            [$reason, $userId, $id]
        );
    }

    public function paidOnInvoice(int $invoiceId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(a.amount), 0) AS n
             FROM payment_allocations a
             INNER JOIN payments p ON p.id = a.payment_id
             WHERE a.invoice_id = ? AND a.reversed_at IS NULL AND p.status = 'RECORDED'",
            [$invoiceId]
        );

        return Decimal::money((string) ($row['n'] ?? '0'));
    }

    public function creditedOnInvoice(int $invoiceId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS n FROM credit_notes
             WHERE invoice_id = ? AND status IN ('ISSUED', 'APPLIED')",
            [$invoiceId]
        );

        return Decimal::money((string) ($row['n'] ?? '0'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openInvoices(int $customerId): array
    {
        return $this->rows(
            "SELECT * FROM invoices
             WHERE customer_id = ? AND status IN ('ISSUED', 'PARTIALLY_PAID') AND balance_due > 0
             ORDER BY due_date, id",
            [$customerId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCredit(array $data): int
    {
        $this->run(
            'INSERT INTO credit_notes (
                credit_note_number, customer_id, invoice_id, job_id, credit_date, status, reason,
                vat_mode, vat_rate, subtotal, vat_amount, total, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['credit_note_number'], $data['customer_id'], $data['invoice_id'], $data['job_id'],
                $data['credit_date'], $data['status'], $data['reason'], $data['vat_mode'], $data['vat_rate'],
                $data['subtotal'], $data['vat_amount'], $data['total'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function lockCredit(int $id): ?array
    {
        return $this->one('SELECT * FROM credit_notes WHERE id = ? LIMIT 1 FOR UPDATE', [$id]);
    }

    public function credit(int $id): ?array
    {
        return $this->one(
            'SELECT cn.*, c.company_name, c.first_name, c.last_name, c.customer_type, i.invoice_number
             FROM credit_notes cn
             INNER JOIN customers c ON c.id = cn.customer_id
             LEFT JOIN invoices i ON i.id = cn.invoice_id
             WHERE cn.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function credits(array $filters, int $limit = 200): array
    {
        $sql = 'SELECT cn.*, c.company_name, c.first_name, c.last_name, c.customer_type, i.invoice_number
                FROM credit_notes cn
                INNER JOIN customers c ON c.id = cn.customer_id
                LEFT JOIN invoices i ON i.id = cn.invoice_id WHERE 1 = 1';
        $params = [];
        if (!empty($filters['q'])) {
            $sql .= ' AND (cn.credit_note_number LIKE ? OR c.company_name LIKE ? OR i.invoice_number LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND cn.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        $sql .= ' ORDER BY cn.id DESC LIMIT ' . (int) $limit;

        return $this->rows($sql, $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function creditItems(int $creditId): array
    {
        return $this->rows('SELECT * FROM credit_note_items WHERE credit_note_id = ? ORDER BY id', [$creditId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCreditItem(array $data): void
    {
        $this->run(
            'INSERT INTO credit_note_items (credit_note_id, description, quantity, unit_price, subtotal, vat_rate_snapshot, vat_amount, total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['credit_note_id'], $data['description'], $data['quantity'], $data['unit_price'],
                $data['subtotal'], $data['vat_rate_snapshot'], $data['vat_amount'], $data['total'],
            ]
        );
    }

    public function deleteCreditItems(int $creditId): void
    {
        $this->run('DELETE FROM credit_note_items WHERE credit_note_id = ?', [$creditId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveCreditTotals(int $id, array $data): void
    {
        $this->run(
            'UPDATE credit_notes SET subtotal = ?, vat_mode = ?, vat_rate = ?, vat_amount = ?, total = ?, version_number = version_number + 1 WHERE id = ?',
            [$data['subtotal'], $data['vat_mode'], $data['vat_rate'], $data['vat_amount'], $data['total'], $id]
        );
    }

    public function issueCredit(int $id, string $number, string $status, string $name, int $userId): void
    {
        $this->run(
            'UPDATE credit_notes SET credit_note_number = ?, status = ?, customer_name_snapshot = ?, issued_at = NOW(), issued_by = ?,
                    version_number = version_number + 1
             WHERE id = ? AND status = \'DRAFT\'',
            [$number, $status, $name, $userId, $id]
        );
    }

    public function cancelCredit(int $id): void
    {
        $this->run('UPDATE credit_notes SET status = \'CANCELLED\', version_number = version_number + 1 WHERE id = ?', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertVariation(array $data): int
    {
        $this->run(
            'INSERT INTO job_variations (job_id, variation_number, description, status, vat_mode, vat_rate, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['variation_number'], $data['description'], $data['status'],
                $data['vat_mode'], $data['vat_rate'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function lockVariation(int $id): ?array
    {
        return $this->one('SELECT * FROM job_variations WHERE id = ? LIMIT 1 FOR UPDATE', [$id]);
    }

    public function variation(int $id): ?array
    {
        return $this->one('SELECT * FROM job_variations WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function variations(int $jobId): array
    {
        return $this->rows('SELECT * FROM job_variations WHERE job_id = ? ORDER BY id', [$jobId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function variationItems(int $variationId): array
    {
        return $this->rows('SELECT * FROM job_variation_items WHERE job_variation_id = ? ORDER BY id', [$variationId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertVariationItem(array $data): void
    {
        $this->run(
            'INSERT INTO job_variation_items (job_variation_id, product_id, description, quantity, unit, unit_cost_snapshot, unit_price, line_total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_variation_id'], $data['product_id'], $data['description'], $data['quantity'],
                $data['unit'], $data['unit_cost_snapshot'], $data['unit_price'], $data['line_total'],
            ]
        );
    }

    /**
     * @param array<string, mixed> $totals
     */
    public function saveVariationTotals(int $id, array $totals, string $status): void
    {
        $this->run(
            'UPDATE job_variations SET subtotal = ?, vat_amount = ?, total = ?, status = ? WHERE id = ?',
            [$totals['subtotal'], $totals['vat_amount'], $totals['total'], $status, $id]
        );
    }

    public function approveVariation(int $id, string $name, string $method): void
    {
        $this->run(
            'UPDATE job_variations SET status = \'APPROVED\', customer_approved = 1, approved_at = NOW(), approved_by_name = ?, approval_method = ? WHERE id = ?',
            [$name, $method, $id]
        );
    }

    public function markVariationsInvoiced(int $jobId): void
    {
        $this->run(
            "UPDATE job_variations SET status = 'INVOICED' WHERE job_id = ? AND status = 'APPROVED'",
            [$jobId]
        );
    }

    public function nextVariationNumber(int $jobId): string
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM job_variations WHERE job_id = ?', [$jobId]);

        return 'VAR-' . ((int) ($row['n'] ?? 0) + 1);
    }

    public function approvedVariationTotal(int $jobId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS n FROM job_variations WHERE job_id = ? AND status IN ('APPROVED', 'INVOICED')",
            [$jobId]
        );

        return Decimal::money((string) ($row['n'] ?? '0'));
    }

    /**
     * @return array<string, string>
     */
    public function account(int $customerId): array
    {
        $invoiced = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS n FROM invoices WHERE customer_id = ? AND status NOT IN ('DRAFT', 'CANCELLED')",
            [$customerId]
        );
        $credits = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS n FROM credit_notes WHERE customer_id = ? AND status IN ('ISSUED', 'APPLIED')",
            [$customerId]
        );
        $payments = $this->one(
            "SELECT COALESCE(SUM(amount), 0) AS n FROM payments WHERE customer_id = ? AND status = 'RECORDED'",
            [$customerId]
        );
        $outstanding = $this->one(
            "SELECT COALESCE(SUM(balance_due), 0) AS n FROM invoices
             WHERE customer_id = ? AND status NOT IN ('DRAFT', 'CANCELLED')",
            [$customerId]
        );
        $overdue = $this->one(
            "SELECT COALESCE(SUM(balance_due), 0) AS n FROM invoices
             WHERE customer_id = ? AND status IN ('ISSUED', 'PARTIALLY_PAID') AND balance_due > 0 AND due_date < CURDATE()",
            [$customerId]
        );
        $allocated = $this->one(
            "SELECT COALESCE(SUM(a.amount), 0) AS n
             FROM payment_allocations a
             INNER JOIN payments p ON p.id = a.payment_id
             WHERE p.customer_id = ? AND p.status = 'RECORDED' AND a.reversed_at IS NULL",
            [$customerId]
        );
        $looseCredits = $this->one(
            "SELECT COALESCE(SUM(total), 0) AS n FROM credit_notes
             WHERE customer_id = ? AND invoice_id IS NULL AND status = 'ISSUED'",
            [$customerId]
        );
        $unallocated = Decimal::money(Decimal::sub((string) ($payments['n'] ?? '0'), (string) ($allocated['n'] ?? '0')));
        $unallocated = Decimal::money(Decimal::add($unallocated, (string) ($looseCredits['n'] ?? '0')));

        return [
            'invoiced' => Decimal::money((string) ($invoiced['n'] ?? '0')),
            'credits' => Decimal::money((string) ($credits['n'] ?? '0')),
            'payments' => Decimal::money((string) ($payments['n'] ?? '0')),
            'outstanding' => Decimal::money((string) ($outstanding['n'] ?? '0')),
            'overdue' => Decimal::money((string) ($overdue['n'] ?? '0')),
            'unallocated' => $unallocated,
        ];
    }

    public function setHold(int $customerId, int $hold, ?string $reason, ?int $userId): void
    {
        $this->run(
            'UPDATE customers SET account_on_hold = ?, account_hold_reason = ?, account_hold_by = ?, account_hold_at = IF(? = 1, NOW(), NULL) WHERE id = ?',
            [$hold, $reason, $userId, $hold, $customerId]
        );
    }

    public function setCredit(int $customerId, ?string $limit, ?int $termId): void
    {
        $this->run(
            'UPDATE customers SET credit_limit = ?, payment_term_id = ? WHERE id = ?',
            [$limit, $termId, $customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ageingLines(): array
    {
        return $this->rows(
            "SELECT i.*, c.company_name, c.first_name, c.last_name, c.customer_type
             FROM invoices i
             INNER JOIN customers c ON c.id = i.customer_id
             WHERE i.status IN ('ISSUED', 'PARTIALLY_PAID') AND i.balance_due > 0
             ORDER BY i.due_date, i.id"
        );
    }

    /**
     * @return array<string, string>
     */
    public function vatSummary(string $from, string $to): array
    {
        $invoices = $this->one(
            "SELECT COALESCE(SUM(CASE WHEN vat_mode <> 'NO_VAT' THEN subtotal_after_discount ELSE 0 END), 0) AS taxable,
                    COALESCE(SUM(vat_amount), 0) AS vat
             FROM invoices
             WHERE status NOT IN ('DRAFT', 'CANCELLED') AND invoice_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $credits = $this->one(
            "SELECT COALESCE(SUM(CASE WHEN vat_mode <> 'NO_VAT' THEN subtotal ELSE 0 END), 0) AS taxable,
                    COALESCE(SUM(vat_amount), 0) AS vat
             FROM credit_notes
             WHERE status IN ('ISSUED', 'APPLIED') AND credit_date BETWEEN ? AND ?",
            [$from, $to]
        );

        return [
            'taxable' => Decimal::money((string) ($invoices['taxable'] ?? '0')),
            'output_vat' => Decimal::money((string) ($invoices['vat'] ?? '0')),
            'credit_taxable' => Decimal::money((string) ($credits['taxable'] ?? '0')),
            'credit_vat' => Decimal::money((string) ($credits['vat'] ?? '0')),
            'net_vat' => Decimal::money(Decimal::sub((string) ($invoices['vat'] ?? '0'), (string) ($credits['vat'] ?? '0'))),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function desk(): array
    {
        $month = date('Y-m-01');
        $row = $this->one(
            "SELECT
                SUM(status NOT IN ('DRAFT', 'CANCELLED') AND invoice_date >= ?) AS month_count,
                COALESCE(SUM(CASE WHEN status NOT IN ('DRAFT', 'CANCELLED') AND invoice_date >= ? THEN total ELSE 0 END), 0) AS month_value,
                COALESCE(SUM(CASE WHEN status NOT IN ('DRAFT', 'CANCELLED') THEN balance_due ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN status IN ('ISSUED', 'PARTIALLY_PAID') AND balance_due > 0 AND due_date < CURDATE() THEN balance_due ELSE 0 END), 0) AS overdue,
                SUM(status IN ('ISSUED', 'PARTIALLY_PAID') AND balance_due > 0 AND due_date = CURDATE()) AS due_today,
                SUM(status IN ('ISSUED', 'PARTIALLY_PAID') AND balance_due > 0 AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS due_week
             FROM invoices",
            [$month, $month]
        ) ?? [];
        $payments = $this->one(
            "SELECT COALESCE(SUM(amount), 0) AS n FROM payments WHERE status = 'RECORDED' AND payment_date >= ?",
            [$month]
        );
        $credits = $this->one(
            "SELECT COUNT(*) AS n FROM credit_notes WHERE status IN ('ISSUED', 'APPLIED') AND credit_date >= ?",
            [$month]
        );
        $unallocated = '0.00';
        foreach ($this->rows("SELECT id, amount FROM payments WHERE status = 'RECORDED'") as $payment) {
            $left = Decimal::sub((string) $payment['amount'], $this->allocated((int) $payment['id']));
            if (Decimal::cmp($left, '0') > 0) {
                $unallocated = Decimal::money(Decimal::add($unallocated, $left));
            }
        }

        return [
            'month_count' => (int) ($row['month_count'] ?? 0),
            'month_value' => Decimal::money((string) ($row['month_value'] ?? '0')),
            'outstanding' => Decimal::money((string) ($row['outstanding'] ?? '0')),
            'overdue' => Decimal::money((string) ($row['overdue'] ?? '0')),
            'due_today' => (int) ($row['due_today'] ?? 0),
            'due_week' => (int) ($row['due_week'] ?? 0),
            'payments_month' => Decimal::money((string) ($payments['n'] ?? '0')),
            'credits_month' => (int) ($credits['n'] ?? 0),
            'unallocated' => $unallocated,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchDocuments(string $term, int $limit = 20): array
    {
        $like = '%' . $term . '%';

        return $this->rows(
            'SELECT \'invoice\' AS kind, id, invoice_number AS reference, customer_id FROM invoices WHERE invoice_number LIKE ?
             UNION ALL
             SELECT \'credit\' AS kind, id, credit_note_number AS reference, customer_id FROM credit_notes WHERE credit_note_number LIKE ?
             UNION ALL
             SELECT \'payment\' AS kind, id, payment_reference AS reference, customer_id FROM payments WHERE payment_reference LIKE ? OR external_reference LIKE ?
             LIMIT ' . (int) $limit,
            [$like, $like, $like, $like]
        );
    }
}

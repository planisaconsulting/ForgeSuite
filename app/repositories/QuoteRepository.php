<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * SQL for quotations, their lines, revisions, and status history.
 */
final class QuoteRepository extends Repository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one($this->select() . ' WHERE q.id = ? LIMIT 1', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lock(int $id): ?array
    {
        return $this->one('SELECT * FROM quotes WHERE id = ? FOR UPDATE', [$id]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters): array
    {
        $sql = $this->select() . ' WHERE q.archived = 0';
        $params = [];
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = like_term($term);
            $sql .= ' AND (q.quote_number LIKE ? ESCAPE \'\\\\\'
                      OR c.company_name LIKE ? ESCAPE \'\\\\\'
                      OR c.last_name LIKE ? ESCAPE \'\\\\\'
                      OR c.first_name LIKE ? ESCAPE \'\\\\\'
                      OR ct.name LIKE ? ESCAPE \'\\\\\' )';
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && $status !== 'ALL') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND q.assigned_to = ?';
            $params[] = (int) $filters['assigned_to'];
        }
        if (!empty($filters['customer_id'])) {
            $sql .= ' AND q.customer_id = ?';
            $params[] = (int) $filters['customer_id'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND q.quote_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND q.quote_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['expired'])) {
            $sql .= ' AND q.expiry_date IS NOT NULL AND q.expiry_date < CURDATE()
                      AND q.status IN (\'DRAFT\', \'READY\', \'SENT\', \'VIEWED\')';
        }

        $sort = (string) ($filters['sort'] ?? 'newest');
        $order = match ($sort) {
            'oldest' => 'q.quote_date ASC, q.id ASC',
            'highest' => 'q.total DESC, q.id DESC',
            'lowest' => 'q.total ASC, q.id DESC',
            'customer' => 'c.company_name ASC, c.last_name ASC, q.id DESC',
            default => 'q.updated_at DESC, q.id DESC',
        };

        return $this->rows($sql . ' ORDER BY ' . $order . ' LIMIT 200', $params);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forCustomer(int $customerId): array
    {
        return $this->rows(
            $this->select() . ' WHERE q.customer_id = ? AND q.archived = 0 ORDER BY q.quote_date DESC, q.id DESC',
            [$customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forOpportunity(int $opportunityId): array
    {
        return $this->rows(
            $this->select() . ' WHERE q.opportunity_id = ? AND q.archived = 0 ORDER BY q.id DESC',
            [$opportunityId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentDrafts(): array
    {
        return $this->rows(
            'SELECT q.id, q.quote_number, q.customer_id, c.company_name, c.first_name, c.last_name, c.customer_type
             FROM quotes q
             INNER JOIN customers c ON c.id = q.customer_id
             WHERE q.status = \'DRAFT\' AND q.archived = 0
             ORDER BY q.updated_at DESC LIMIT 20'
        );
    }

    public function draftsFor(int $customerId): array
    {
        return $this->rows(
            'SELECT id, quote_number, revision_number FROM quotes
             WHERE customer_id = ? AND status = \'DRAFT\' AND archived = 0
             ORDER BY updated_at DESC LIMIT 30',
            [$customerId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $this->run(
            'INSERT INTO quotes (
                quote_number, revision_number, version_number, opportunity_id, customer_id, contact_id,
                quote_date, expiry_date, status, pricing_level_id, vat_mode, vat_rate,
                customer_notes, internal_notes, terms, assigned_to, created_by, updated_by,
                status_changed_at, status_changed_by
             ) VALUES (?, 1, 1, ?, ?, ?, ?, ?, \'DRAFT\', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)',
            [
                $data['quote_number'], $data['opportunity_id'], $data['customer_id'], $data['contact_id'],
                $data['quote_date'], $data['expiry_date'], $data['pricing_level_id'], $data['vat_mode'],
                $data['vat_rate'], $data['customer_notes'], $data['internal_notes'], $data['terms'],
                $data['assigned_to'], $data['created_by'], $data['created_by'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setFollowUp(int $id, string $date): void
    {
        $this->run(
            'UPDATE quotes SET next_follow_up_date = ? WHERE id = ? AND (next_follow_up_date IS NULL OR next_follow_up_date <> ?)',
            [$date, $id, $date]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateHeader(int $id, array $data): void
    {
        $this->run(
            'UPDATE quotes SET
                contact_id = ?, quote_date = ?, expiry_date = ?, pricing_level_id = ?,
                discount_type = ?, discount_value = ?, vat_mode = ?, vat_rate = ?,
                deposit_type = ?, deposit_value = ?, customer_notes = ?, internal_notes = ?,
                terms = ?, assigned_to = ?, updated_by = ?, version_number = version_number + 1
             WHERE id = ?',
            [
                $data['contact_id'], $data['quote_date'], $data['expiry_date'], $data['pricing_level_id'],
                $data['discount_type'], $data['discount_value'], $data['vat_mode'], $data['vat_rate'],
                $data['deposit_type'], $data['deposit_value'], $data['customer_notes'], $data['internal_notes'],
                $data['terms'], $data['assigned_to'], $data['updated_by'], $id,
            ]
        );
    }

    /**
     * @param array<string, mixed> $totals
     */
    public function updateTotals(int $id, array $totals): void
    {
        $this->run(
            'UPDATE quotes SET
                subtotal = ?, discount_type = ?, discount_value = ?, discount_amount = ?,
                subtotal_after_discount = ?, vat_mode = ?, vat_rate = ?, vat_amount = ?, total = ?,
                deposit_type = ?, deposit_value = ?, deposit_amount = ?
             WHERE id = ?',
            [
                $totals['subtotal'], $totals['discount_type'], $totals['discount_value'], $totals['discount_amount'],
                $totals['subtotal_after_discount'], $totals['vat_mode'], $totals['vat_rate'], $totals['vat_amount'],
                $totals['total'], $totals['deposit_type'], $totals['deposit_value'], $totals['deposit_amount'], $id,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function touch(int $id, int $userId): void
    {
        $this->run(
            'UPDATE quotes SET version_number = version_number + 1, updated_by = ? WHERE id = ?',
            [$userId, $id]
        );
    }

    public function updateStatus(int $id, array $data): void
    {
        $this->run(
            'UPDATE quotes SET status = ?, status_changed_at = NOW(), status_changed_by = ?,
                    updated_by = ?, version_number = version_number + 1
             WHERE id = ?',
            [$data['status'], $data['user_id'], $data['user_id'], $id]
        );
    }

    public function bumpRevision(int $id, int $revision, string $status, int $userId): void
    {
        $this->run(
            'UPDATE quotes SET revision_number = ?, status = ?, accepted_at = NULL, accepted_by_name = NULL,
                    accepted_by_user_id = NULL, acceptance_method = NULL, acceptance_reference = NULL,
                    acceptance_notes = NULL, status_changed_at = NOW(), status_changed_by = ?,
                    updated_by = ?, version_number = version_number + 1
             WHERE id = ?',
            [$revision, $status, $userId, $userId, $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function markAccepted(int $id, array $data): void
    {
        $this->run(
            'UPDATE quotes SET status = \'ACCEPTED\', accepted_at = NOW(), accepted_by_name = ?,
                    accepted_by_user_id = ?, acceptance_method = ?, acceptance_reference = ?,
                    acceptance_notes = ?, status_changed_at = NOW(), status_changed_by = ?,
                    updated_by = ?, version_number = version_number + 1
             WHERE id = ?',
            [
                $data['accepted_by_name'], $data['user_id'], $data['acceptance_method'],
                $data['acceptance_reference'], $data['acceptance_notes'], $data['user_id'],
                $data['user_id'], $id,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $quoteId): array
    {
        return $this->rows(
            'SELECT * FROM quote_items WHERE quote_id = ? ORDER BY sort_order ASC, id ASC',
            [$quoteId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function item(int $id): ?array
    {
        return $this->one('SELECT * FROM quote_items WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $line
     */
    public function insertItem(int $quoteId, array $line, int $sort): int
    {
        $line['quote_id'] = $quoteId;
        $line['sort_order'] = $sort;
        $columns = [
            'quote_id', 'section_id', 'sort_order', 'product_id', 'is_custom_item', 'is_optional',
            'include_optional', 'product_name_snapshot', 'product_description_snapshot', 'sku_snapshot',
            'product_type_snapshot', 'pricing_method_snapshot', 'width_mm', 'height_mm', 'length_mm',
            'quantity', 'actual_quantity', 'billable_quantity', 'actual_area', 'billable_area', 'waste_area',
            'waste_mode', 'standard_waste_percent_snapshot', 'cost_unit_snapshot', 'unit_cost_snapshot',
            'base_cost', 'waste_cost', 'total_cost', 'pricing_level_id', 'markup_percent_snapshot',
            'calculated_price', 'final_sell_price', 'unit_sell_price', 'price_overridden', 'override_reason',
            'overridden_by', 'overridden_at', 'line_discount_type', 'line_discount_value', 'discount_amount',
            'line_subtotal', 'line_total', 'customer_description', 'internal_description', 'measure_snapshot',
        ];
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $this->run(
            'INSERT INTO quote_items (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
            array_map(static fn (string $column) => $line[$column] ?? null, $columns)
        );

        return $this->insertId();
    }

    public function deleteItem(int $id, int $quoteId): void
    {
        $this->run('DELETE FROM quote_items WHERE id = ? AND quote_id = ?', [$id, $quoteId]);
    }

    public function nextSort(int $quoteId): int
    {
        $row = $this->one('SELECT COALESCE(MAX(sort_order), 0) AS sort_order FROM quote_items WHERE quote_id = ?', [$quoteId]);

        return ((int) ($row['sort_order'] ?? 0)) + 10;
    }

    /**
     * @param list<int> $ids
     */
    public function reorder(int $quoteId, array $ids): void
    {
        $sort = 10;
        foreach ($ids as $id) {
            $this->run(
                'UPDATE quote_items SET sort_order = ? WHERE id = ? AND quote_id = ?',
                [$sort, $id, $quoteId]
            );
            $sort += 10;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sections(int $quoteId): array
    {
        return $this->rows(
            'SELECT * FROM quote_sections WHERE quote_id = ? ORDER BY sort_order ASC, id ASC',
            [$quoteId]
        );
    }

    public function insertSection(int $quoteId, string $title, int $sort): int
    {
        $this->run(
            'INSERT INTO quote_sections (quote_id, title, sort_order) VALUES (?, ?, ?)',
            [$quoteId, $title, $sort]
        );

        return $this->insertId();
    }

    public function assignSection(int $itemId, int $quoteId, ?int $sectionId): void
    {
        $this->run(
            'UPDATE quote_items SET section_id = ? WHERE id = ? AND quote_id = ?',
            [$sectionId, $itemId, $quoteId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revisions(int $quoteId): array
    {
        return $this->rows(
            'SELECT r.id, r.quote_id, r.revision_number, r.change_summary, r.created_at, u.name AS user_name
             FROM quote_revisions r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.quote_id = ? ORDER BY r.revision_number DESC',
            [$quoteId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function revision(int $quoteId, int $number): ?array
    {
        return $this->one(
            'SELECT * FROM quote_revisions WHERE quote_id = ? AND revision_number = ? LIMIT 1',
            [$quoteId, $number]
        );
    }

    public function insertRevision(int $quoteId, int $number, string $json, ?string $summary, ?int $userId): void
    {
        $this->run(
            'INSERT INTO quote_revisions (quote_id, revision_number, snapshot_json, change_summary, created_by)
             VALUES (?, ?, ?, ?, ?)',
            [$quoteId, $number, $json, $summary, $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $quoteId): array
    {
        return $this->rows(
            'SELECT h.*, u.name AS user_name
             FROM quote_status_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.quote_id = ? ORDER BY h.id DESC',
            [$quoteId]
        );
    }

    public function insertHistory(int $quoteId, ?string $old, string $new, ?int $userId, ?string $notes): void
    {
        $this->run(
            'INSERT INTO quote_status_history (quote_id, old_status, new_status, changed_by, notes)
             VALUES (?, ?, ?, ?, ?)',
            [$quoteId, $old, $new, $userId, $notes]
        );
    }

    /**
     * @return array<string, string>
     */
    public function desk(string $monthStart): array
    {
        $open = $this->one(
            'SELECT COUNT(*) AS n FROM quotes
             WHERE archived = 0 AND status IN (\'SENT\', \'VIEWED\')'
        );
        $drafts = $this->one('SELECT COUNT(*) AS n FROM quotes WHERE archived = 0 AND status = \'DRAFT\'');
        $accepted = $this->one('SELECT COUNT(*) AS n FROM quotes WHERE archived = 0 AND status = \'ACCEPTED\'');
        $expiring = $this->one(
            'SELECT COUNT(*) AS n FROM quotes
             WHERE archived = 0 AND status IN (\'READY\', \'SENT\', \'VIEWED\')
               AND expiry_date IS NOT NULL AND expiry_date >= CURDATE()
               AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)'
        );
        $quoted = $this->one(
            'SELECT COALESCE(SUM(total), 0) AS n FROM quotes
             WHERE archived = 0 AND quote_date >= ? AND status <> \'DRAFT\'',
            [$monthStart]
        );
        $acceptedValue = $this->one(
            'SELECT COALESCE(SUM(total), 0) AS n FROM quotes
             WHERE archived = 0 AND status IN (\'ACCEPTED\', \'CONVERTED\')
               AND accepted_at >= ?',
            [$monthStart . ' 00:00:00']
        );
        $created = $this->one(
            'SELECT COUNT(*) AS n FROM quotes WHERE archived = 0 AND quote_date >= ?',
            [$monthStart]
        );
        $decidedAccepted = $this->one(
            'SELECT COUNT(*) AS n FROM quotes
             WHERE archived = 0 AND status IN (\'ACCEPTED\', \'CONVERTED\') AND quote_date >= ?',
            [$monthStart]
        );
        $declined = $this->one(
            'SELECT COUNT(*) AS n FROM quotes
             WHERE archived = 0 AND status = \'DECLINED\' AND quote_date >= ?',
            [$monthStart]
        );

        return [
            'awaiting' => (string) ($open['n'] ?? 0),
            'drafts' => (string) ($drafts['n'] ?? 0),
            'accepted' => (string) ($accepted['n'] ?? 0),
            'expiring' => (string) ($expiring['n'] ?? 0),
            'quoted_value' => (string) ($quoted['n'] ?? '0'),
            'accepted_value' => (string) ($acceptedValue['n'] ?? '0'),
            'created' => (string) ($created['n'] ?? 0),
            'decided_accepted' => (string) ($decidedAccepted['n'] ?? 0),
            'declined' => (string) ($declined['n'] ?? 0),
        ];
    }

    private function select(): string
    {
        return 'SELECT q.*, c.company_name, c.first_name, c.last_name, c.customer_type,
                       c.email AS customer_email, c.phone AS customer_phone,
                       c.billing_address, c.vat_number AS customer_vat,
                       ct.name AS contact_name, ct.email AS contact_email, ct.phone AS contact_phone,
                       u.name AS salesperson_name, pl.name AS pricing_level_name, pl.code AS pricing_level_code
                FROM quotes q
                INNER JOIN customers c ON c.id = q.customer_id
                LEFT JOIN customer_contacts ct ON ct.id = q.contact_id
                LEFT JOIN users u ON u.id = q.assigned_to
                LEFT JOIN pricing_levels pl ON pl.id = q.pricing_level_id';
    }
}

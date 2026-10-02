<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Sales intake rows. Pricing is not stored here.
 */
final class SalesIntakeRepository extends Repository
{
    /**
     * @param array<string, mixed> $row
     */
    public function insertIntake(array $row): int
    {
        $this->run(
            'INSERT INTO sales_intakes (
                intake_number, source_type, source_reference_id, communication_id, external_message_id,
                customer_id, contact_id, lead_id, opportunity_id, status, intent_type, language_code,
                assigned_user_id, sender_name, sender_email, sender_phone, subject, original_message,
                received_at, created_by
             ) VALUES (
                :intake_number, :source_type, :source_reference_id, :communication_id, :external_message_id,
                :customer_id, :contact_id, :lead_id, :opportunity_id, :status, :intent_type, :language_code,
                :assigned_user_id, :sender_name, :sender_email, :sender_phone, :subject, :original_message,
                :received_at, :created_by
             )',
            $row
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $sets
     */
    public function updateIntake(int $id, array $sets): void
    {
        if ($sets === []) {
            return;
        }
        $columns = [];
        $params = ['id' => $id];
        foreach ($sets as $column => $value) {
            $columns[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }
        $this->run('UPDATE sales_intakes SET ' . implode(', ', $columns) . ' WHERE id = :id', $params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM sales_intakes WHERE id = ?', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByMessage(string $messageId): ?array
    {
        return $this->one('SELECT * FROM sales_intakes WHERE external_message_id = ? ORDER BY id DESC LIMIT 1', [$messageId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumber(string $number): ?array
    {
        return $this->one('SELECT * FROM sales_intakes WHERE intake_number = ?', [$number]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function page(int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        return $this->rows(
            'SELECT id, intake_number, status, source_type, intent_type, sender_email, customer_id, assigned_user_id, received_at
             FROM sales_intakes ORDER BY received_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $rows = $this->rows('SELECT status, COUNT(*) AS total FROM sales_intakes GROUP BY status');
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function unassigned(): int
    {
        return (int) ($this->one("SELECT COUNT(*) AS total FROM sales_intakes WHERE assigned_user_id IS NULL AND status NOT IN ('CLOSED','SPAM','CONVERTED')")['total'] ?? 0);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertAnalysis(array $row): int
    {
        $this->run(
            'INSERT INTO intake_analyses (intake_id, provider, model_name, prompt_version, input_hash, input_reference, structured_json, status, created_by)
             VALUES (:intake_id, :provider, :model_name, :prompt_version, :input_hash, :input_reference, :structured_json, :status, :created_by)',
            $row
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function analysisByHash(int $intakeId, string $hash): ?array
    {
        return $this->one('SELECT * FROM intake_analyses WHERE intake_id = ? AND input_hash = ?', [$intakeId, $hash]);
    }

    public function analysisCount(int $intakeId): int
    {
        return (int) ($this->one('SELECT COUNT(*) AS total FROM intake_analyses WHERE intake_id = ?', [$intakeId])['total'] ?? 0);
    }

    public function clearItems(int $intakeId): void
    {
        $this->run('DELETE FROM intake_items WHERE intake_id = ?', [$intakeId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertItem(array $row): void
    {
        $this->run(
            'INSERT INTO intake_items (
                intake_id, line_no, requirement_type, description, quantity, width_mm, height_mm, depth_mm, thickness_mm,
                material_text, material_category, finish, print_sides, installation_proposed, is_approximate,
                dimension_source, original_text, notes, review_status
             ) VALUES (
                :intake_id, :line_no, :requirement_type, :description, :quantity, :width_mm, :height_mm, :depth_mm, :thickness_mm,
                :material_text, :material_category, :finish, :print_sides, :installation_proposed, :is_approximate,
                :dimension_source, :original_text, :notes, :review_status
             )',
            $row
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(int $intakeId): array
    {
        return $this->rows('SELECT * FROM intake_items WHERE intake_id = ? ORDER BY line_no', [$intakeId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function field(int $intakeId, string $key): ?array
    {
        return $this->one('SELECT * FROM intake_fields WHERE intake_id = ? AND field_key = ?', [$intakeId, $key]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(int $intakeId): array
    {
        return $this->rows('SELECT * FROM intake_fields WHERE intake_id = ? ORDER BY id', [$intakeId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertField(array $row): void
    {
        $this->run(
            'INSERT INTO intake_fields (
                intake_id, field_key, proposed_value, confirmed_value, conflict_value, original_text,
                extraction_method, evidence, review_status, is_approximate, dimension_source
             ) VALUES (
                :intake_id, :field_key, :proposed_value, :confirmed_value, :conflict_value, :original_text,
                :extraction_method, :evidence, :review_status, :is_approximate, :dimension_source
             )',
            $row
        );
    }

    /**
     * @param array<string, mixed> $sets
     */
    public function updateField(int $id, array $sets): void
    {
        $columns = [];
        $params = ['id' => $id];
        foreach ($sets as $column => $value) {
            $columns[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }
        $this->run('UPDATE intake_fields SET ' . implode(', ', $columns) . ' WHERE id = :id', $params);
    }

    public function clearQuestions(int $intakeId): void
    {
        $this->run("DELETE FROM intake_questions WHERE intake_id = ? AND status = 'DRAFT'", [$intakeId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertQuestion(array $row): void
    {
        $this->run(
            'INSERT INTO intake_questions (intake_id, field_key, question_text, draft_text, language_code, status)
             VALUES (:intake_id, :field_key, :question_text, :draft_text, :language_code, :status)',
            $row
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questions(int $intakeId): array
    {
        return $this->rows('SELECT * FROM intake_questions WHERE intake_id = ? ORDER BY id', [$intakeId]);
    }

    public function setQuestionStatus(int $id, string $status, ?string $answer): void
    {
        $this->run('UPDATE intake_questions SET status = ?, answer_text = ? WHERE id = ?', [$status, $answer, $id]);
    }

    /**
     * @param array<string, mixed> $sets
     */
    public function updateItem(int $id, array $sets): void
    {
        $columns = [];
        $params = ['id' => $id];
        foreach ($sets as $column => $value) {
            $columns[] = $column . ' = :' . $column;
            $params[$column] = $value;
        }
        $this->run('UPDATE intake_items SET ' . implode(', ', $columns) . ' WHERE id = :id', $params);
    }

    public function clearMatches(int $intakeId): void
    {
        $this->run("DELETE FROM intake_product_matches WHERE intake_id = ? AND review_status = 'PROPOSED'", [$intakeId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertMatch(array $row): int
    {
        $this->run(
            'INSERT INTO intake_product_matches (
                intake_id, line_no, product_id, catalogue_item_id, specification_id, recipe_id,
                match_state, match_rank, why_text, missing_json, warnings_json, review_status
             ) VALUES (
                :intake_id, :line_no, :product_id, :catalogue_item_id, :specification_id, :recipe_id,
                :match_state, :match_rank, :why_text, :missing_json, :warnings_json, :review_status
             )',
            $row
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function matches(int $intakeId): array
    {
        return $this->rows('SELECT * FROM intake_product_matches WHERE intake_id = ? ORDER BY match_rank, id', [$intakeId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function match(int $id): ?array
    {
        return $this->one('SELECT * FROM intake_product_matches WHERE id = ?', [$id]);
    }

    public function confirmMatch(int $id): void
    {
        $this->run("UPDATE intake_product_matches SET review_status = 'CONFIRMED' WHERE id = ?", [$id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertFile(array $row): int
    {
        $this->run(
            'INSERT INTO intake_files (intake_id, original_name, sha256, mime_type, rejected, reject_reason, extracted_text)
             VALUES (:intake_id, :original_name, :sha256, :mime_type, :rejected, :reject_reason, :extracted_text)',
            $row
        );

        return $this->insertId();
    }

    public function clearImport(int $intakeId): void
    {
        $this->run('DELETE FROM intake_import_rows WHERE intake_id = ?', [$intakeId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertImport(array $row): void
    {
        $this->run(
            'INSERT INTO intake_import_rows (intake_id, row_no, site_code, branch_name, address_text, sign_type, quantity, width_mm, height_mm, issues)
             VALUES (:intake_id, :row_no, :site_code, :branch_name, :address_text, :sign_type, :quantity, :width_mm, :height_mm, :issues)',
            $row
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function importRows(int $intakeId): array
    {
        return $this->rows('SELECT * FROM intake_import_rows WHERE intake_id = ? ORDER BY row_no', [$intakeId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function insertReviewRequest(array $row): void
    {
        $this->run(
            'INSERT INTO product_review_requests (intake_id, description, status, created_by) VALUES (:intake_id, :description, :status, :created_by)',
            $row
        );
    }

    /**
     * @return array<string, string>
     */
    public function terms(): array
    {
        $map = [];
        foreach ($this->rows('SELECT customer_term, erp_category FROM intake_term_mappings WHERE active = 1') as $row) {
            $map[(string) $row['customer_term']] = (string) $row['erp_category'];
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requirementRules(): array
    {
        return $this->rows('SELECT * FROM intake_requirement_rules WHERE active = 1 ORDER BY id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customersByEmail(string $email): array
    {
        return $this->rows(
            'SELECT DISTINCT c.id, c.company_name, c.email, c.phone, c.mobile
             FROM customers c
             LEFT JOIN customer_contacts cc ON cc.customer_id = c.id
             WHERE c.active = 1 AND (LOWER(c.email) = LOWER(?) OR LOWER(cc.email) = LOWER(?))',
            [$email, $email]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function customersByCompany(string $like): array
    {
        return $this->rows(
            'SELECT id, company_name, email FROM customers WHERE active = 1 AND company_name LIKE ? ORDER BY id LIMIT 20',
            ['%' . $like . '%']
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function product(int $id): ?array
    {
        return $this->one('SELECT id, name, sku, product_type, cost_price, pricing_method, active FROM products WHERE id = ?', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function productsLike(string $term): array
    {
        $like = '%' . $term . '%';

        return $this->rows(
            "SELECT id, name, sku, product_type, cost_price FROM products
             WHERE active = 1 AND (name LIKE ? OR description LIKE ? OR sku LIKE ?)
             ORDER BY id
             LIMIT 12",
            [$like, $like, $like]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogueItems(int $customerId): array
    {
        return $this->rows(
            "SELECT i.id, i.product_id, i.specification_id, i.name, i.customer_code
             FROM customer_catalogue_items i
             JOIN customer_catalogues c ON c.id = i.catalogue_id
             WHERE c.customer_id = ? AND c.status = 'ACTIVE' AND i.status = 'ACTIVE'
             ORDER BY i.id",
            [$customerId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function specificationsLike(string $term): array
    {
        $like = '%' . $term . '%';

        return $this->rows(
            "SELECT id, code, name, estimator_type, category FROM sign_specifications
             WHERE status = 'APPROVED' AND (name LIKE ? OR category LIKE ?) LIMIT 5",
            [$like, $like]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function specRules(int $specificationId): array
    {
        return $this->rows('SELECT * FROM specification_rules WHERE specification_id = ? AND active = 1', [$specificationId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sites(int $customerId, string $place): array
    {
        $like = '%' . $place . '%';

        return $this->rows(
            'SELECT s.id, s.site_name, s.city, s.project_id
             FROM project_sites s
             JOIN projects p ON p.id = s.project_id
             WHERE p.customer_id = ? AND (s.city LIKE ? OR s.site_name LIKE ?)
             LIMIT 5',
            [$customerId, $like, $like]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function assets(int $customerId, string $needle): array
    {
        $like = '%' . $needle . '%';

        return $this->rows(
            'SELECT id, asset_number, name, project_site_id FROM customer_assets
             WHERE customer_id = ? AND (name LIKE ? OR asset_number = ? OR description LIKE ?)
             LIMIT 5',
            [$customerId, $like, $needle, $like]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentQuotes(int $customerId): array
    {
        return $this->rows('SELECT id, quote_number, status FROM quotes WHERE customer_id = ? ORDER BY id DESC LIMIT 5', [$customerId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentJobs(int $customerId): array
    {
        return $this->rows('SELECT id, job_number, status FROM jobs WHERE customer_id = ? ORDER BY id DESC LIMIT 5', [$customerId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function duplicateCandidate(string $email, string $hash, int $exceptId): ?array
    {
        return $this->one(
            "SELECT id, intake_number FROM sales_intakes
             WHERE sender_email = ? AND analysis_hash = ? AND id <> ?
               AND received_at >= (NOW() - INTERVAL 1 DAY)
             ORDER BY id DESC LIMIT 1",
            [$email, $hash, $exceptId]
        );
    }

    /**
     * @return array{proposed: int, confirmed: int, corrected: int, rejected: int}
     */
    public function fieldOutcomes(): array
    {
        $rows = $this->rows('SELECT review_status, COUNT(*) AS total FROM intake_fields GROUP BY review_status');
        $out = ['proposed' => 0, 'confirmed' => 0, 'corrected' => 0, 'rejected' => 0];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['review_status']);
            if (isset($out[$key])) {
                $out[$key] = (int) $row['total'];
            }
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    public function sourceCounts(): array
    {
        $rows = $this->rows('SELECT source_type, COUNT(*) AS total FROM sales_intakes GROUP BY source_type');
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['source_type']] = (int) $row['total'];
        }

        return $out;
    }

    public function projectCount(): int
    {
        return (int) ($this->one('SELECT COUNT(*) AS total FROM projects')['total'] ?? 0);
    }

    public function customerCount(): int
    {
        return (int) ($this->one('SELECT COUNT(*) AS total FROM customers')['total'] ?? 0);
    }

    public function statusIndexExists(): bool
    {
        $row = $this->one("SHOW INDEX FROM sales_intakes WHERE Key_name = 'idx_sales_intakes_status'");

        return $row !== null;
    }
}

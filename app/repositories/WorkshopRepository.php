<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * SQL for workshop tracking, labels, production items, dispatch, and documents.
 */
final class WorkshopRepository extends Repository
{
    public function findCode(string $code): ?array
    {
        return $this->one('SELECT * FROM tracking_codes WHERE tracking_code = ?', [$code]);
    }

    public function findCodeByEntity(string $type, int $id): ?array
    {
        return $this->one('SELECT * FROM tracking_codes WHERE entity_type = ? AND entity_id = ?', [$type, $id]);
    }

    public function insertCode(string $type, int $id, string $code): int
    {
        $this->run(
            'INSERT INTO tracking_codes (entity_type, entity_id, tracking_code) VALUES (?, ?, ?)',
            [$type, $id, $code]
        );

        return $this->insertId();
    }

    public function insertToken(string $type, int $id, string $hash, int $userId): int
    {
        $this->run(
            'INSERT INTO tracking_tokens (entity_type, entity_id, token_hash, active, created_by) VALUES (?, ?, ?, 1, ?)',
            [$type, $id, $hash, $userId]
        );

        return $this->insertId();
    }

    public function tokenByHash(string $hash): ?array
    {
        return $this->one(
            'SELECT * FROM tracking_tokens WHERE token_hash = ? AND active = 1 AND revoked_at IS NULL',
            [$hash]
        );
    }

    public function activeToken(string $type, int $id): ?array
    {
        return $this->one(
            'SELECT * FROM tracking_tokens WHERE entity_type = ? AND entity_id = ? AND active = 1 AND revoked_at IS NULL ORDER BY id DESC LIMIT 1',
            [$type, $id]
        );
    }

    public function revokeTokens(string $type, int $id): void
    {
        $this->run(
            'UPDATE tracking_tokens SET active = 0, revoked_at = NOW() WHERE entity_type = ? AND entity_id = ? AND active = 1',
            [$type, $id]
        );
    }

    public function insertProductionItem(array $data): int
    {
        $this->run(
            'INSERT INTO production_items
                (job_id, job_item_id, tracking_code, description, quantity, quantity_completed, sequence_number, status, current_stage_id, assigned_resource_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['tracking_code'], $data['description'],
                $data['quantity'], $data['quantity_completed'], $data['sequence_number'], $data['status'],
                $data['current_stage_id'], $data['assigned_resource_id'],
            ]
        );

        return $this->insertId();
    }

    public function productionItems(int $jobId): array
    {
        return $this->rows(
            'SELECT p.*, i.description AS item_description, i.width_mm, i.height_mm, i.quantity AS item_quantity, i.sort_order
             FROM production_items p
             INNER JOIN job_items i ON i.id = p.job_item_id
             WHERE p.job_id = ? ORDER BY p.job_item_id, p.sequence_number',
            [$jobId]
        );
    }

    public function productionByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM production_items WHERE tracking_code = ?', [$code]);
    }

    public function productionItem(int $id): ?array
    {
        return $this->one('SELECT * FROM production_items WHERE id = ?', [$id]);
    }

    public function lockProductionItem(int $id): ?array
    {
        return $this->one('SELECT * FROM production_items WHERE id = ? FOR UPDATE', [$id]);
    }

    public function productionItemByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM production_items WHERE tracking_code = ?', [$code]);
    }

    public function itemsForJobItem(int $jobItemId): array
    {
        return $this->rows('SELECT * FROM production_items WHERE job_item_id = ? ORDER BY sequence_number', [$jobItemId]);
    }

    public function updateProductionItem(int $id, array $data): void
    {
        $this->run(
            'UPDATE production_items
             SET status = ?, quantity_completed = ?, current_stage_id = ?, assigned_resource_id = ?
             WHERE id = ?',
            [$data['status'], $data['quantity_completed'], $data['current_stage_id'], $data['assigned_resource_id'], $id]
        );
    }

    public function setTrackingMode(int $jobItemId, string $mode): void
    {
        $this->run('UPDATE job_items SET tracking_mode = ? WHERE id = ?', [$mode, $jobItemId]);
    }

    public function setItemStatus(int $jobItemId, string $status): void
    {
        $this->run('UPDATE job_items SET production_status = ? WHERE id = ?', [$status, $jobItemId]);
    }

    public function insertEvent(array $data): int
    {
        $this->run(
            'INSERT INTO production_events
                (production_item_id, job_production_stage_id, action, reason, quantity, notes, idempotency_key, user_id, client_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['production_item_id'], $data['job_production_stage_id'], $data['action'], $data['reason'],
                $data['quantity'], $data['notes'], $data['idempotency_key'], $data['user_id'], $data['client_at'],
            ]
        );

        return $this->insertId();
    }

    public function eventByKey(string $key): ?array
    {
        return $this->one('SELECT * FROM production_events WHERE idempotency_key = ?', [$key]);
    }

    public function stages(int $jobId, ?int $jobItemId): array
    {
        if ($jobItemId === null) {
            return $this->rows(
                'SELECT s.*, p.name AS stage_name FROM job_production_stages s
                 INNER JOIN production_stages p ON p.id = s.production_stage_id
                 WHERE s.job_id = ? ORDER BY s.sort_order, s.id',
                [$jobId]
            );
        }

        return $this->rows(
            'SELECT s.*, p.name AS stage_name FROM job_production_stages s
             INNER JOIN production_stages p ON p.id = s.production_stage_id
             WHERE s.job_id = ? AND (s.job_item_id = ? OR s.job_item_id IS NULL)
             ORDER BY s.sort_order, s.id',
            [$jobId, $jobItemId]
        );
    }

    public function stage(int $id): ?array
    {
        return $this->one(
            'SELECT s.*, p.name AS stage_name FROM job_production_stages s
             INNER JOIN production_stages p ON p.id = s.production_stage_id WHERE s.id = ?',
            [$id]
        );
    }

    public function updateStage(int $id, array $data): void
    {
        $this->run(
            'UPDATE job_production_stages SET status = ?, started_at = ?, completed_at = ?, notes = ?, actual_minutes = ? WHERE id = ?',
            [$data['status'], $data['started_at'], $data['completed_at'], $data['notes'], $data['actual_minutes'], $id]
        );
    }

    public function insertIssue(array $data): int
    {
        $this->run(
            'INSERT INTO material_issues
                (idempotency_key, job_id, job_item_id, production_item_id, inventory_item_id, product_id,
                 production_quantity, waste_quantity, override_reason, usage_id, waste_usage_id, offcut_inventory_item_id, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['idempotency_key'], $data['job_id'], $data['job_item_id'], $data['production_item_id'],
                $data['inventory_item_id'], $data['product_id'], $data['production_quantity'], $data['waste_quantity'],
                $data['override_reason'], $data['usage_id'], $data['waste_usage_id'], $data['offcut_inventory_item_id'],
                $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function issueByKey(string $key): ?array
    {
        return $this->one('SELECT * FROM material_issues WHERE idempotency_key = ?', [$key]);
    }

    public function usageCount(int $jobId, int $inventoryItemId, string $type): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM job_material_usage u
             INNER JOIN stock_movements m ON m.job_material_usage_id = u.id
             WHERE u.job_id = ? AND m.inventory_item_id = ? AND u.usage_type = ?',
            [$jobId, $inventoryItemId, $type]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function requirement(int $jobId, int $productId): ?array
    {
        return $this->one(
            'SELECT * FROM job_material_requirements WHERE job_id = ? AND product_id = ? ORDER BY id DESC LIMIT 1',
            [$jobId, $productId]
        );
    }

    public function consumed(int $jobId, int $productId): string
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(quantity), 0) AS qty FROM job_material_usage
             WHERE job_id = ? AND product_id = ? AND usage_type IN (\'PRODUCTION\', \'WASTE\', \'REWORK\')',
            [$jobId, $productId]
        );

        return (string) ($row['qty'] ?? '0');
    }

    public function reserved(int $jobId, int $inventoryItemId): string
    {
        $row = $this->one(
            "SELECT COALESCE(SUM(quantity), 0) AS qty FROM stock_reservations
             WHERE job_id = ? AND inventory_item_id = ? AND status = 'RESERVED'",
            [$jobId, $inventoryItemId]
        );

        return (string) ($row['qty'] ?? '0');
    }

    public function inventoryByCode(string $code): ?array
    {
        return $this->one(
            'SELECT i.*, p.name AS product_name, p.sku, l.code AS location_code, l.name AS location_name
             FROM inventory_items i
             INNER JOIN products p ON p.id = i.product_id
             INNER JOIN stock_locations l ON l.id = i.stock_location_id
             WHERE i.inventory_code = ?',
            [$code]
        );
    }

    public function insertQuality(array $data): int
    {
        $this->run(
            'INSERT INTO quality_checks
                (job_id, job_item_id, production_item_id, check_type, status, fail_reason, fail_action, notes, checked_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['production_item_id'], $data['check_type'],
                $data['status'], $data['fail_reason'], $data['fail_action'], $data['notes'], $data['checked_by'],
            ]
        );

        return $this->insertId();
    }

    public function unresolvedFail(int $jobId, ?int $productionItemId = null): ?array
    {
        if ($productionItemId === null) {
            return $this->one(
                "SELECT * FROM quality_checks WHERE job_id = ? AND status = 'FAIL' AND resolved_at IS NULL ORDER BY id DESC LIMIT 1",
                [$jobId]
            );
        }

        return $this->one(
            "SELECT * FROM quality_checks WHERE production_item_id = ? AND status = 'FAIL' AND resolved_at IS NULL ORDER BY id DESC LIMIT 1",
            [$productionItemId]
        );
    }

    public function resolveQuality(int $id, string $reason): void
    {
        $this->run(
            'UPDATE quality_checks SET resolved_at = NOW(), override_reason = ? WHERE id = ? AND resolved_at IS NULL',
            [$reason, $id]
        );
    }

    public function checklist(?int $productId): array
    {
        if ($productId !== null && $productId > 0) {
            $rows = $this->rows(
                'SELECT * FROM qc_checklist_items WHERE active = 1 AND product_id = ? ORDER BY sort_order',
                [$productId]
            );
            if ($rows !== []) {
                return $rows;
            }
        }

        return $this->rows(
            'SELECT * FROM qc_checklist_items WHERE active = 1 AND product_id IS NULL ORDER BY sort_order'
        );
    }

    public function reprintReasons(): array
    {
        return $this->rows('SELECT * FROM reprint_reasons WHERE active = 1 ORDER BY label');
    }

    public function insertReprint(array $data): int
    {
        $this->run(
            'INSERT INTO reprints
                (job_id, job_item_id, production_item_id, stage_id, quantity, reason_code, material_quantity, labour_minutes, chargeable, variation_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['job_item_id'], $data['production_item_id'], $data['stage_id'],
                $data['quantity'], $data['reason_code'], $data['material_quantity'], $data['labour_minutes'],
                $data['chargeable'], $data['variation_id'], $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function reprintTotals(): array
    {
        $row = $this->one(
            'SELECT COALESCE(SUM(quantity), 0) AS reprinted,
                    (SELECT COALESCE(SUM(quantity_completed), 0) FROM production_items) AS produced
             FROM reprints'
        );

        return ['reprinted' => (string) ($row['reprinted'] ?? '0'), 'produced' => (string) ($row['produced'] ?? '0')];
    }

    public function yieldCounts(): array
    {
        $rows = $this->rows('SELECT production_item_id, job_id, status FROM quality_checks ORDER BY id ASC');
        $seen = [];
        $firstPass = 0;
        $inspected = 0;
        foreach ($rows as $row) {
            $key = ($row['production_item_id'] ?? '') . ':' . $row['job_id'] . ':' . ($row['production_item_id'] === null ? $row['id'] ?? $inspected : '');
            $key = (string) ($row['production_item_id'] ?? ('job-' . $row['job_id']));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $inspected++;
            if (in_array((string) $row['status'], ['PASS', 'PASS_WITH_NOTE'], true)) {
                $firstPass++;
            }
        }

        return ['first_pass' => $firstPass, 'inspected' => $inspected];
    }

    public function insertDispatch(array $data): int
    {
        $this->run(
            'INSERT INTO dispatches
                (dispatch_number, job_id, dispatch_type, customer_id, scheduled_at, vehicle_resource_id, driver_user_id, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['dispatch_number'], $data['job_id'], $data['dispatch_type'], $data['customer_id'],
                $data['scheduled_at'], $data['vehicle_resource_id'], $data['driver_user_id'], $data['status'],
                $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function dispatch(int $id): ?array
    {
        return $this->one(
            'SELECT d.*, j.job_number, j.title AS job_title, j.customer_po_number, c.company_name, c.first_name, c.last_name, c.customer_type
             FROM dispatches d
             INNER JOIN jobs j ON j.id = d.job_id
             INNER JOIN customers c ON c.id = d.customer_id
             WHERE d.id = ?',
            [$id]
        );
    }

    public function lockDispatch(int $id): ?array
    {
        return $this->one('SELECT * FROM dispatches WHERE id = ? FOR UPDATE', [$id]);
    }

    public function dispatches(array $filters = []): array
    {
        $sql = 'SELECT d.*, j.job_number FROM dispatches d INNER JOIN jobs j ON j.id = d.job_id WHERE 1 = 1';
        $params = [];
        if (($filters['job_id'] ?? 0) > 0) {
            $sql .= ' AND d.job_id = ?';
            $params[] = (int) $filters['job_id'];
        }
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (d.dispatch_number LIKE ? OR j.job_number LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return $this->rows($sql . ' ORDER BY d.id DESC LIMIT 100', $params);
    }

    public function updateDispatch(int $id, array $data): void
    {
        $this->run(
            'UPDATE dispatches SET status = ?, dispatched_at = ?, notes = ? WHERE id = ?',
            [$data['status'], $data['dispatched_at'], $data['notes'], $id]
        );
    }

    public function insertDispatchItem(array $data): int
    {
        $this->run(
            'INSERT INTO dispatch_items (dispatch_id, job_item_id, production_item_id, description, quantity, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['dispatch_id'], $data['job_item_id'], $data['production_item_id'],
                $data['description'], $data['quantity'], $data['status'],
            ]
        );

        return $this->insertId();
    }

    public function dispatchItems(int $dispatchId): array
    {
        return $this->rows('SELECT * FROM dispatch_items WHERE dispatch_id = ? ORDER BY id', [$dispatchId]);
    }

    public function dispatchItemForProduction(int $dispatchId, int $productionItemId): ?array
    {
        return $this->one(
            'SELECT * FROM dispatch_items WHERE dispatch_id = ? AND production_item_id = ?',
            [$dispatchId, $productionItemId]
        );
    }

    public function markDispatchItem(int $id, string $status): void
    {
        $this->run('UPDATE dispatch_items SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function insertSignature(array $data): int
    {
        $this->run(
            'INSERT INTO digital_signatures
                (entity_type, entity_id, signer_name, signer_contact, statement_text, statement_version, file_path, captured_by, signed_at, client_signed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['entity_type'], $data['entity_id'], $data['signer_name'], $data['signer_contact'],
                $data['statement_text'], $data['statement_version'], $data['file_path'], $data['captured_by'],
                $data['signed_at'], $data['client_signed_at'],
            ]
        );

        return $this->insertId();
    }

    public function signature(int $id): ?array
    {
        return $this->one('SELECT * FROM digital_signatures WHERE id = ?', [$id]);
    }

    public function insertPod(array $data): int
    {
        $this->run(
            'INSERT INTO proof_of_delivery
                (dispatch_id, recipient_name, recipient_contact, delivery_datetime, signature_file_id, photo_file_id, gps_latitude, gps_longitude, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['dispatch_id'], $data['recipient_name'], $data['recipient_contact'], $data['delivery_datetime'],
                $data['signature_file_id'], $data['photo_file_id'], $data['gps_latitude'], $data['gps_longitude'],
                $data['notes'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function podByDispatch(int $dispatchId): ?array
    {
        return $this->one('SELECT * FROM proof_of_delivery WHERE dispatch_id = ?', [$dispatchId]);
    }

    public function insertSnag(array $data): int
    {
        $this->run(
            'INSERT INTO job_snags (job_id, installation_id, description, priority, assigned_to, target_date, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['job_id'], $data['installation_id'], $data['description'], $data['priority'],
                $data['assigned_to'], $data['target_date'], $data['status'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function snag(int $id): ?array
    {
        return $this->one('SELECT * FROM job_snags WHERE id = ?', [$id]);
    }

    public function snags(array $filters = []): array
    {
        $sql = 'SELECT s.*, j.job_number FROM job_snags s INNER JOIN jobs j ON j.id = s.job_id WHERE 1 = 1';
        $params = [];
        if (($filters['job_id'] ?? 0) > 0) {
            $sql .= ' AND s.job_id = ?';
            $params[] = (int) $filters['job_id'];
        }
        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND s.status = ?';
            $params[] = $filters['status'];
        }

        return $this->rows($sql . ' ORDER BY s.id DESC LIMIT 200', $params);
    }

    public function updateSnag(int $id, array $data): void
    {
        $this->run(
            'UPDATE job_snags SET status = ?, assigned_to = ?, resolved_at = ?, priority = ? WHERE id = ?',
            [$data['status'], $data['assigned_to'], $data['resolved_at'], $data['priority'], $id]
        );
    }

    public function criticalOpen(int $jobId): int
    {
        $row = $this->one(
            "SELECT COUNT(*) AS n FROM job_snags WHERE job_id = ? AND priority = 'CRITICAL' AND status IN ('OPEN', 'IN_PROGRESS')",
            [$jobId]
        );

        return (int) ($row['n'] ?? 0);
    }

    public function overdueSnags(): array
    {
        return $this->rows(
            "SELECT * FROM job_snags WHERE status IN ('OPEN', 'IN_PROGRESS') AND target_date IS NOT NULL AND target_date < CURDATE()"
        );
    }

    public function labelTemplates(?string $type = null): array
    {
        if ($type === null || $type === '') {
            return $this->rows('SELECT * FROM label_templates WHERE active = 1 ORDER BY name');
        }

        return $this->rows('SELECT * FROM label_templates WHERE active = 1 AND entity_type = ? ORDER BY name', [$type]);
    }

    public function labelTemplate(int $id): ?array
    {
        return $this->one('SELECT * FROM label_templates WHERE id = ?', [$id]);
    }

    public function saveLabelTemplate(array $data, ?int $id): int
    {
        if ($id === null) {
            $this->run(
                'INSERT INTO label_templates (name, entity_type, width_mm, height_mm, orientation, layout_definition, active)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$data['name'], $data['entity_type'], $data['width_mm'], $data['height_mm'], $data['orientation'], $data['layout_definition'], $data['active']]
            );

            return $this->insertId();
        }
        $this->run(
            'UPDATE label_templates SET name = ?, entity_type = ?, width_mm = ?, height_mm = ?, orientation = ?, layout_definition = ?, active = ? WHERE id = ?',
            [$data['name'], $data['entity_type'], $data['width_mm'], $data['height_mm'], $data['orientation'], $data['layout_definition'], $data['active'], $id]
        );

        return $id;
    }

    public function insertReprintLog(array $data): void
    {
        $this->run(
            'INSERT INTO label_reprints (entity_type, entity_id, template_id, quantity, reason, printed_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['entity_type'], $data['entity_id'], $data['template_id'], $data['quantity'], $data['reason'], $data['printed_by']]
        );
    }

    public function documentTemplates(?string $type = null): array
    {
        if ($type === null || $type === '') {
            return $this->rows('SELECT * FROM document_templates ORDER BY document_type, name');
        }

        return $this->rows('SELECT * FROM document_templates WHERE document_type = ? ORDER BY id DESC', [$type]);
    }

    public function activeDocumentTemplate(string $type): ?array
    {
        return $this->one(
            'SELECT * FROM document_templates WHERE document_type = ? AND active = 1 ORDER BY template_version DESC, id DESC LIMIT 1',
            [$type]
        );
    }

    public function saveDocumentTemplate(array $data, ?int $id): int
    {
        if ($id === null) {
            $this->run(
                'INSERT INTO document_templates (document_type, name, template_version, layout_config, active) VALUES (?, ?, ?, ?, ?)',
                [$data['document_type'], $data['name'], $data['template_version'], $data['layout_config'], $data['active']]
            );

            return $this->insertId();
        }
        $this->run(
            'UPDATE document_templates SET name = ?, layout_config = ?, active = ?, template_version = template_version + 1 WHERE id = ? AND active = 1',
            [$data['name'], $data['layout_config'], $data['active'], $id]
        );

        return $id;
    }

    public function insertDocument(array $data): int
    {
        $this->run(
            'INSERT INTO generated_documents
                (document_type, entity_type, entity_id, document_number, template_id, template_version, file_path, status, immutable, generated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['document_type'], $data['entity_type'], $data['entity_id'], $data['document_number'],
                $data['template_id'], $data['template_version'], $data['file_path'], $data['status'],
                $data['immutable'], $data['generated_by'],
            ]
        );

        return $this->insertId();
    }

    public function document(int $id): ?array
    {
        return $this->one('SELECT * FROM generated_documents WHERE id = ?', [$id]);
    }

    public function currentDocument(string $type, string $entityType, int $entityId): ?array
    {
        return $this->one(
            "SELECT * FROM generated_documents
             WHERE document_type = ? AND entity_type = ? AND entity_id = ? AND status = 'CURRENT'
             ORDER BY id DESC LIMIT 1",
            [$type, $entityType, $entityId]
        );
    }

    public function documentsFor(string $entityType, int $entityId): array
    {
        return $this->rows(
            'SELECT * FROM generated_documents WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC',
            [$entityType, $entityId]
        );
    }

    public function searchDocuments(array $filters): array
    {
        $sql = 'SELECT g.*, j.job_number, c.company_name
                FROM generated_documents g
                LEFT JOIN jobs j ON g.entity_type = \'job\' AND j.id = g.entity_id
                LEFT JOIN customers c ON c.id = j.customer_id
                WHERE 1 = 1';
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (g.document_number LIKE ? OR g.document_type LIKE ? OR j.job_number LIKE ? OR c.company_name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (($filters['type'] ?? '') !== '') {
            $sql .= ' AND g.document_type = ?';
            $params[] = $filters['type'];
        }
        if (($filters['from'] ?? '') !== '') {
            $sql .= ' AND DATE(g.generated_at) >= ?';
            $params[] = $filters['from'];
        }
        if (($filters['to'] ?? '') !== '') {
            $sql .= ' AND DATE(g.generated_at) <= ?';
            $params[] = $filters['to'];
        }

        return $this->rows($sql . ' ORDER BY g.id DESC LIMIT 150', $params);
    }

    public function customerDocuments(int $customerId): array
    {
        return $this->rows(
            "SELECT g.* FROM generated_documents g
             INNER JOIN jobs j ON g.entity_type = 'job' AND j.id = g.entity_id
             WHERE j.customer_id = ?
               AND g.document_type IN ('DELIVERY_NOTE', 'COLLECTION_NOTE', 'COMPLETION_CERTIFICATE', 'PROOF_OF_DELIVERY')
               AND g.status <> 'CANCELLED'
             ORDER BY g.id DESC LIMIT 40",
            [$customerId]
        );
    }

    public function supersedeOpen(string $type, string $entityType, int $entityId, string $reason): int
    {
        $this->run(
            "UPDATE generated_documents
             SET status = 'SUPERSEDED', outdated_reason = ?
             WHERE document_type = ? AND entity_type = ? AND entity_id = ? AND status = 'CURRENT' AND immutable = 0",
            [$reason, $type, $entityType, $entityId]
        );

        return $this->affected();
    }

    public function markImmutable(int $id): void
    {
        $this->run('UPDATE generated_documents SET immutable = 1 WHERE id = ?', [$id]);
    }

    public function insertScan(array $data): void
    {
        $this->run(
            'INSERT INTO scan_events
                (tracking_token_id, tracking_code, entity_type, entity_id, action, user_id, device_identifier, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['tracking_token_id'], $data['tracking_code'], $data['entity_type'], $data['entity_id'],
                $data['action'], $data['user_id'], $data['device_identifier'], $data['metadata_json'],
            ]
        );
    }

    public function scans(string $type, int $id): array
    {
        return $this->rows(
            'SELECT s.*, u.name AS user_name FROM scan_events s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.entity_type = ? AND s.entity_id = ? ORDER BY s.id ASC',
            [$type, $id]
        );
    }

    public function jobTrace(int $jobId): array
    {
        return $this->rows(
            'SELECT s.created_at, s.action, s.tracking_code, s.entity_type, s.entity_id, u.name AS user_name
             FROM scan_events s
             LEFT JOIN users u ON u.id = s.user_id
             WHERE (s.entity_type = \'JOB\' AND s.entity_id = ?)
                OR s.entity_id IN (SELECT id FROM production_items WHERE job_id = ?)
                OR (s.entity_type = \'DISPATCH\' AND s.entity_id IN (SELECT id FROM dispatches WHERE job_id = ?))
             ORDER BY s.id ASC LIMIT 200',
            [$jobId, $jobId, $jobId]
        );
    }

    public function materialTrace(int $inventoryItemId): array
    {
        return $this->rows(
            'SELECT m.created_at, m.movement_type, m.quantity, m.job_id, j.job_number, m.reason
             FROM stock_movements m
             LEFT JOIN jobs j ON j.id = m.job_id
             WHERE m.inventory_item_id = ?
             ORDER BY m.id ASC',
            [$inventoryItemId]
        );
    }

    public function insertPackage(array $data): int
    {
        $this->run(
            'INSERT INTO packages (package_code, job_id, description, status, created_by) VALUES (?, ?, ?, ?, ?)',
            [$data['package_code'], $data['job_id'], $data['description'], $data['status'], $data['created_by']]
        );

        return $this->insertId();
    }

    public function package(int $id): ?array
    {
        return $this->one('SELECT * FROM packages WHERE id = ?', [$id]);
    }

    public function packageByCode(string $code): ?array
    {
        return $this->one('SELECT * FROM packages WHERE package_code = ?', [$code]);
    }

    public function dispatchByNumber(string $number): ?array
    {
        return $this->one('SELECT * FROM dispatches WHERE dispatch_number = ?', [$number]);
    }

    public function jobByNumber(string $number): ?array
    {
        return $this->one('SELECT id, job_number FROM jobs WHERE job_number = ?', [$number]);
    }

    public function addPackageItem(array $data): void
    {
        $this->run(
            'INSERT INTO package_items (package_id, production_item_id, job_item_id, description, quantity) VALUES (?, ?, ?, ?, ?)',
            [$data['package_id'], $data['production_item_id'], $data['job_item_id'], $data['description'], $data['quantity']]
        );
    }

    public function packageItems(int $packageId): array
    {
        return $this->rows('SELECT * FROM package_items WHERE package_id = ?', [$packageId]);
    }

    public function pin(int $userId): ?array
    {
        return $this->one('SELECT * FROM kiosk_pins WHERE user_id = ?', [$userId]);
    }

    public function savePin(int $userId, string $hash): void
    {
        $this->run(
            'INSERT INTO kiosk_pins (user_id, pin_hash, failed_attempts, locked_until) VALUES (?, ?, 0, NULL)
             ON DUPLICATE KEY UPDATE pin_hash = VALUES(pin_hash), failed_attempts = 0, locked_until = NULL',
            [$userId, $hash]
        );
    }

    public function pinFailure(int $userId, int $attempts, ?string $lockedUntil): void
    {
        $this->run(
            'UPDATE kiosk_pins SET failed_attempts = ?, locked_until = ? WHERE user_id = ?',
            [$attempts, $lockedUntil, $userId]
        );
    }

    public function clearPinFailures(int $userId): void
    {
        $this->run('UPDATE kiosk_pins SET failed_attempts = 0, locked_until = NULL WHERE user_id = ?', [$userId]);
    }

    public function userByEmail(string $email): ?array
    {
        return $this->one(
            'SELECT u.*, r.code AS role_code FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.email = ? AND u.active = 1',
            [$email]
        );
    }

    public function floor(array $filters): array
    {
        $sql = 'SELECT j.id, j.job_number, j.title, j.status, j.priority, j.target_date, j.production_due_date,
                       c.company_name, c.first_name, c.last_name, c.customer_type,
                       (SELECT COUNT(*) FROM production_items p WHERE p.job_id = j.id AND p.status = \'BLOCKED\') AS blocked_items,
                       (SELECT COUNT(*) FROM production_items p WHERE p.job_id = j.id AND p.status IN (\'WAITING\', \'PARTIAL\', \'IN_PROGRESS\')) AS open_items
                FROM jobs j
                INNER JOIN customers c ON c.id = j.customer_id
                WHERE j.archived = 0 AND j.status NOT IN (\'COMPLETED\', \'CANCELLED\')';
        $params = [];
        if (($filters['priority'] ?? '') !== '') {
            $sql .= ' AND j.priority = ?';
            $params[] = $filters['priority'];
        }
        if (($filters['due'] ?? '') !== '') {
            $sql .= ' AND j.target_date = ?';
            $params[] = $filters['due'];
        }

        return $this->rows($sql . ' ORDER BY j.target_date IS NULL, j.target_date, j.id DESC LIMIT 80', $params);
    }

    public function counts(): array
    {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $one = static function (self $repo, string $sql, array $params = []): int {
            $row = $repo->one($sql, $params);

            return (int) ($row['n'] ?? 0);
        };

        return [
            'due_today' => $one($this, 'SELECT COUNT(*) AS n FROM jobs WHERE archived = 0 AND target_date = ? AND status NOT IN (\'COMPLETED\', \'CANCELLED\')', [$today]),
            'due_tomorrow' => $one($this, 'SELECT COUNT(*) AS n FROM jobs WHERE archived = 0 AND target_date = ? AND status NOT IN (\'COMPLETED\', \'CANCELLED\')', [$tomorrow]),
            'overdue' => $one($this, 'SELECT COUNT(*) AS n FROM jobs WHERE archived = 0 AND target_date < ? AND status NOT IN (\'COMPLETED\', \'CANCELLED\')', [$today]),
            'in_progress' => $one($this, "SELECT COUNT(*) AS n FROM jobs WHERE status = 'IN_PRODUCTION'"),
            'blocked' => $one($this, "SELECT COUNT(*) AS n FROM production_items WHERE status = 'BLOCKED'"),
            'qc' => $one($this, "SELECT COUNT(*) AS n FROM jobs WHERE status = 'QUALITY_CONTROL'"),
            'ready' => $one($this, "SELECT COUNT(*) AS n FROM production_items WHERE status = 'READY_FOR_DISPATCH'"),
            'reprints' => $one($this, 'SELECT COUNT(*) AS n FROM reprints WHERE DATE(created_at) = ?', [$today]),
        ];
    }

    public function openReservations(int $jobId): array
    {
        return $this->rows(
            "SELECT * FROM stock_reservations WHERE job_id = ? AND status = 'RESERVED'",
            [$jobId]
        );
    }

    public function openTime(int $jobId): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM job_time_entries WHERE job_id = ? AND ended_at IS NULL', [$jobId]);

        return (int) ($row['n'] ?? 0);
    }

    public function noteSchedule(int $jobId, string $note): void
    {
        $this->run(
            "UPDATE schedule_entries SET notes = LEFT(CONCAT(IFNULL(notes, ''), ' ', ?), 255)
             WHERE job_id = ? AND status = 'PLANNED'",
            [$note, $jobId]
        );
    }

    public function roleId(string $code): ?int
    {
        $row = $this->one('SELECT id FROM roles WHERE code = ?', [$code]);

        return $row === null ? null : (int) $row['id'];
    }

    public function movementCount(int $inventoryItemId, string $type): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM stock_movements WHERE inventory_item_id = ? AND movement_type = ?',
            [$inventoryItemId, $type]
        );

        return (int) ($row['n'] ?? 0);
    }
}

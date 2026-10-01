<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Phase 13 configuration, approvals, connectors, and review records.
 */
final class PlatformRepository extends Repository
{
    /**
     * @param array<string, mixed> $data
     */
    public function insertEvent(array $data): int
    {
        $this->run(
            'INSERT INTO business_events
                (event_uuid, event_type, entity_type, entity_id, actor_user_id, payload_summary_json)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['event_uuid'], $data['event_type'], $data['entity_type'], $data['entity_id'],
                $data['actor_user_id'], $data['payload_summary_json'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function eventByUuid(string $uuid): ?array
    {
        return $this->one('SELECT * FROM business_events WHERE event_uuid = ? LIMIT 1', [$uuid]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function event(int $id): ?array
    {
        return $this->one('SELECT * FROM business_events WHERE id = ? LIMIT 1', [$id]);
    }

    public function markEventProcessed(int $id): void
    {
        $this->run('UPDATE business_events SET processed_at = NOW() WHERE id = ? AND processed_at IS NULL', [$id]);
    }

    public function consume(int $eventId, string $key): bool
    {
        $this->run(
            'INSERT IGNORE INTO business_event_consumers (event_id, consumer_key) VALUES (?, ?)',
            [$eventId, $key]
        );

        return $this->affected() === 1;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertPolicy(array $data): int
    {
        $this->run(
            'INSERT INTO approval_policies (name, entity_type, action_key, active, priority, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$data['name'], $data['entity_type'], $data['action_key'], $data['active'], $data['priority'], $data['created_by']]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function policies(): array
    {
        return $this->rows('SELECT * FROM approval_policies ORDER BY priority ASC, name ASC');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function policyFor(string $entityType, string $actionKey): ?array
    {
        return $this->one(
            'SELECT * FROM approval_policies
             WHERE active = 1 AND entity_type = ? AND action_key = ?
             ORDER BY priority ASC, id ASC LIMIT 1',
            [$entityType, $actionKey]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertPolicyRule(array $data): void
    {
        $this->run(
            'INSERT INTO approval_policy_rules
                (policy_id, name, match_mode, conditions_json, steps_json, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['policy_id'], $data['name'], $data['match_mode'], $data['conditions_json'],
                $data['steps_json'], $data['sort_order'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function policyRules(int $policyId): array
    {
        return $this->rows(
            'SELECT * FROM approval_policy_rules WHERE policy_id = ? ORDER BY sort_order ASC, id ASC',
            [$policyId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openApproval(string $entityType, int $entityId, string $actionKey): ?array
    {
        return $this->one(
            'SELECT * FROM approval_requests
             WHERE entity_type = ? AND entity_id = ? AND action_key = ? AND open_key = ? LIMIT 1',
            [$entityType, $entityId, $actionKey, 'OPEN']
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function approvedRequest(string $entityType, int $entityId, string $actionKey): ?array
    {
        return $this->one(
            "SELECT * FROM approval_requests
             WHERE entity_type = ? AND entity_id = ? AND action_key = ? AND status = 'APPROVED'
             ORDER BY id DESC LIMIT 1",
            [$entityType, $entityId, $actionKey]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertApproval(array $data): int
    {
        $this->run(
            'INSERT INTO approval_requests
                (policy_id, policy_version, entity_type, entity_id, action_key, requested_by, status, current_step, reason, context_json, open_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [
                $data['policy_id'], $data['policy_version'], $data['entity_type'], $data['entity_id'],
                $data['action_key'], $data['requested_by'], $data['status'], $data['reason'],
                $data['context_json'], $data['open_key'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertApprovalStep(array $data): void
    {
        $this->run(
            'INSERT INTO approval_steps
                (approval_request_id, sequence, approver_type, approver_id, role_code, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['approval_request_id'], $data['sequence'], $data['approver_type'],
                $data['approver_id'], $data['role_code'], $data['status'],
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function approval(int $id): ?array
    {
        return $this->one('SELECT * FROM approval_requests WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvalQueue(): array
    {
        return $this->rows(
            "SELECT * FROM approval_requests WHERE status = 'PENDING' ORDER BY requested_at ASC"
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvalSteps(int $requestId): array
    {
        return $this->rows(
            'SELECT * FROM approval_steps WHERE approval_request_id = ? ORDER BY sequence ASC',
            [$requestId]
        );
    }

    public function decideStep(int $stepId, string $status, int $userId, ?int $delegatedFrom, string $comment): void
    {
        $this->run(
            'UPDATE approval_steps
             SET status = ?, decision_by = ?, delegated_from_user_id = ?, decision_at = NOW(), comment = ?
             WHERE id = ?',
            [$status, $userId, $delegatedFrom, $comment, $stepId]
        );
    }

    public function advanceApproval(int $id, int $step, string $status, bool $close): void
    {
        if ($close) {
            $this->run(
                'UPDATE approval_requests
                 SET status = ?, current_step = ?, resolved_at = NOW(), open_key = ?
                 WHERE id = ?',
                [$status, $step, (string) $id, $id]
            );

            return;
        }
        $this->run(
            'UPDATE approval_requests SET status = ?, current_step = ? WHERE id = ?',
            [$status, $step, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function staleApprovals(string $before): array
    {
        return $this->rows(
            "SELECT * FROM approval_requests WHERE status = 'PENDING' AND requested_at < ?",
            [$before]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertDelegation(array $data): int
    {
        $this->run(
            'INSERT INTO approval_delegations
                (original_user_id, delegate_user_id, starts_at, ends_at, reason, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['original_user_id'], $data['delegate_user_id'], $data['starts_at'],
                $data['ends_at'], $data['reason'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeDelegation(int $originalUserId): ?array
    {
        return $this->one(
            'SELECT * FROM approval_delegations
             WHERE original_user_id = ? AND starts_at <= NOW() AND ends_at >= NOW()
             ORDER BY id DESC LIMIT 1',
            [$originalUserId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertField(array $data): int
    {
        $this->run(
            'INSERT INTO custom_field_definitions
                (entity_type, field_key, label, field_type, required, options_json, validation_json, default_value, expose_documents, active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['entity_type'], $data['field_key'], $data['label'], $data['field_type'], $data['required'],
                $data['options_json'], $data['validation_json'], $data['default_value'], $data['expose_documents'],
                $data['active'], $data['sort_order'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fields(string $entityType, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM custom_field_definitions WHERE entity_type = ?';
        if ($activeOnly) {
            $sql .= ' AND active = 1';
        }

        return $this->rows($sql . ' ORDER BY sort_order ASC, id ASC', [$entityType]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function field(int $id): ?array
    {
        return $this->one('SELECT * FROM custom_field_definitions WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertFieldValue(array $data): void
    {
        $this->run(
            'INSERT INTO custom_field_values
                (field_definition_id, entity_type, entity_id, value_text, value_number, value_date, value_json, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                value_text = VALUES(value_text), value_number = VALUES(value_number),
                value_date = VALUES(value_date), value_json = VALUES(value_json), updated_by = VALUES(updated_by)',
            [
                $data['field_definition_id'], $data['entity_type'], $data['entity_id'], $data['value_text'],
                $data['value_number'], $data['value_date'], $data['value_json'], $data['updated_by'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fieldValues(string $entityType, int $entityId, bool $documentsOnly): array
    {
        $sql = 'SELECT v.*, d.field_key, d.label, d.field_type, d.expose_documents
                FROM custom_field_values v
                INNER JOIN custom_field_definitions d ON d.id = v.field_definition_id
                WHERE v.entity_type = ? AND v.entity_id = ?';
        if ($documentsOnly) {
            $sql .= ' AND d.expose_documents = 1 AND d.active = 1';
        }

        return $this->rows($sql, [$entityType, $entityId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertForm(array $data): int
    {
        $this->run(
            'INSERT INTO custom_forms (name, purpose, entity_type, active, current_version, created_by)
             VALUES (?, ?, ?, ?, 1, ?)',
            [$data['name'], $data['purpose'], $data['entity_type'], $data['active'], $data['created_by']]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function form(int $id): ?array
    {
        return $this->one('SELECT * FROM custom_forms WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forms(): array
    {
        return $this->rows('SELECT * FROM custom_forms ORDER BY name ASC');
    }

    public function bumpForm(int $id): int
    {
        $this->run('UPDATE custom_forms SET current_version = current_version + 1 WHERE id = ?', [$id]);
        $row = $this->form($id);

        return (int) ($row['current_version'] ?? 1);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertFormField(array $data): void
    {
        $this->run(
            'INSERT INTO custom_form_fields
                (form_id, form_version, field_source, field_key, label, field_type, required, options_json, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['form_id'], $data['form_version'], $data['field_source'], $data['field_key'],
                $data['label'], $data['field_type'], $data['required'], $data['options_json'], $data['sort_order'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function formFields(int $formId, int $version): array
    {
        return $this->rows(
            'SELECT * FROM custom_form_fields WHERE form_id = ? AND form_version = ? ORDER BY sort_order ASC, id ASC',
            [$formId, $version]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertSubmission(array $data): int
    {
        $this->run(
            'INSERT INTO custom_form_submissions
                (form_id, form_version, entity_type, entity_id, submitted_by, values_json, signature_name, signature_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['form_id'], $data['form_version'], $data['entity_type'], $data['entity_id'],
                $data['submitted_by'], $data['values_json'], $data['signature_name'], $data['signature_at'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function submission(int $id): ?array
    {
        return $this->one('SELECT * FROM custom_form_submissions WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertChecklist(array $data): int
    {
        $this->run(
            'INSERT INTO checklist_bindings (name, area, form_id, active) VALUES (?, ?, ?, ?)',
            [$data['name'], $data['area'], $data['form_id'], $data['active']]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rulesFor(string $key): array
    {
        return $this->rows(
            'SELECT * FROM business_rules WHERE rule_key = ? AND active = 1 ORDER BY id ASC',
            [$key]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allRules(): array
    {
        return $this->rows('SELECT * FROM business_rules WHERE active = 1 ORDER BY rule_key ASC, scope_type ASC');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertRule(array $data): void
    {
        $this->run(
            'INSERT INTO business_rules (rule_key, scope_type, scope_id, value_text, active, updated_by)
             VALUES (?, ?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), active = 1, updated_by = VALUES(updated_by)',
            [$data['rule_key'], $data['scope_type'], $data['scope_id'], $data['value_text'], $data['updated_by']]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function flags(): array
    {
        return $this->rows('SELECT * FROM feature_flags ORDER BY feature_key ASC');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function flag(string $key): ?array
    {
        return $this->one('SELECT * FROM feature_flags WHERE feature_key = ? LIMIT 1', [$key]);
    }

    public function saveFlag(string $key, int $enabled, ?string $config, int $userId): void
    {
        $this->run(
            'INSERT INTO feature_flags (feature_key, enabled, configuration_json, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), configuration_json = VALUES(configuration_json), updated_by = VALUES(updated_by)',
            [$key, $enabled, $config, $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function statusLabels(string $entityType): array
    {
        return $this->rows(
            'SELECT * FROM status_labels WHERE entity_type = ? AND active = 1 ORDER BY sort_order ASC',
            [$entityType]
        );
    }

    public function relabel(string $entityType, string $code, string $label): bool
    {
        $this->run(
            'UPDATE status_labels SET label = ? WHERE entity_type = ? AND status_code = ?',
            [$label, $entityType, $code]
        );

        return $this->affected() === 1;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertPaymentRequest(array $data): int
    {
        $this->run(
            'INSERT INTO payment_requests
                (invoice_id, provider, public_token, external_reference, amount, currency, amount_basis, status, payment_url, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['invoice_id'], $data['provider'], $data['public_token'], $data['external_reference'],
                $data['amount'], $data['currency'], $data['amount_basis'], $data['status'], $data['payment_url'],
                $data['expires_at'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function paymentRequest(int $id): ?array
    {
        return $this->one('SELECT * FROM payment_requests WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * @return array<string, mixed>|null
     */
    public function paymentRequestByReference(string $reference): ?array
    {
        return $this->one('SELECT * FROM payment_requests WHERE external_reference = ? LIMIT 1', [$reference]);
    }

    public function paymentRequestByToken(string $token): ?array
    {
        return $this->one('SELECT * FROM payment_requests WHERE public_token = ? LIMIT 1', [$token]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockPaymentRequest(int $id): ?array
    {
        return $this->one('SELECT * FROM payment_requests WHERE id = ? LIMIT 1 FOR UPDATE', [$id]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function paymentRequestByEvent(string $eventId): ?array
    {
        return $this->one('SELECT * FROM payment_requests WHERE provider_event_id = ? LIMIT 1', [$eventId]);
    }

    public function markPaymentRequest(int $id, string $status, ?string $eventId, ?int $paymentId): void
    {
        $this->run(
            'UPDATE payment_requests SET status = ?, provider_event_id = ?, payment_id = ? WHERE id = ?',
            [$status, $eventId, $paymentId, $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertExtraction(array $data): int
    {
        $this->run(
            'INSERT INTO document_extractions
                (document_type, source_label, original_text, entity_type, entity_id, status, proposed_json, confidence, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['document_type'], $data['source_label'], $data['original_text'], $data['entity_type'],
                $data['entity_id'], $data['status'], $data['proposed_json'], $data['confidence'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function extraction(int $id): ?array
    {
        return $this->one('SELECT * FROM document_extractions WHERE id = ? LIMIT 1', [$id]);
    }

    public function reviewExtraction(int $id, string $status, ?string $confirmed, int $userId): void
    {
        $this->run(
            'UPDATE document_extractions
             SET status = ?, confirmed_json = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?',
            [$status, $confirmed, $userId, $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertInteraction(array $data): int
    {
        $this->run(
            'INSERT INTO ai_interactions
                (user_id, feature, entity_type, entity_id, provider, model, prompt_version, input_summary, output_summary, status, tokens_input, tokens_output, estimated_cost)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['user_id'], $data['feature'], $data['entity_type'], $data['entity_id'], $data['provider'],
                $data['model'], $data['prompt_version'], $data['input_summary'], $data['output_summary'],
                $data['status'], $data['tokens_input'], $data['tokens_output'], $data['estimated_cost'],
            ]
        );

        return $this->insertId();
    }

    public function interactionsThisMonth(int $userId): int
    {
        $row = $this->one(
            'SELECT COUNT(*) AS n FROM ai_interactions WHERE user_id = ? AND created_at >= DATE_FORMAT(NOW(), \'%Y-%m-01\')',
            [$userId]
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activePrompt(string $feature): ?array
    {
        return $this->one(
            'SELECT * FROM ai_prompt_templates WHERE feature = ? AND active = 1 ORDER BY version_number DESC LIMIT 1',
            [$feature]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertSuggestion(array $data): int
    {
        $this->run(
            'INSERT INTO ai_suggestions (interaction_id, feature, entity_type, entity_id, suggestion_type, content, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['interaction_id'], $data['feature'], $data['entity_type'], $data['entity_id'],
                $data['suggestion_type'], $data['content'], $data['status'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertReview(array $data): int
    {
        $this->run(
            'INSERT INTO review_items
                (item_type, entity_type, entity_id, proposed_action, source, reason, assigned_to, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['item_type'], $data['entity_type'], $data['entity_id'], $data['proposed_action'],
                $data['source'], $data['reason'], $data['assigned_to'], $data['status'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function reviews(?string $status): array
    {
        if ($status === null || $status === '') {
            return $this->rows('SELECT * FROM review_items ORDER BY id DESC LIMIT 100');
        }

        return $this->rows('SELECT * FROM review_items WHERE status = ? ORDER BY id DESC LIMIT 100', [$status]);
    }

    public function resolveReview(int $id, string $status, int $userId): void
    {
        $this->run(
            'UPDATE review_items SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?',
            [$status, $userId, $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertIssue(array $data): int
    {
        $this->run(
            'INSERT INTO integration_issues
                (provider, entity_type, entity_id, failure, attempts, max_attempts, safe_retry, idempotency_key, status, next_retry_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, failure = VALUES(failure), last_attempt_at = NOW(), status = VALUES(status)',
            [
                $data['provider'], $data['entity_type'], $data['entity_id'], $data['failure'], $data['attempts'],
                $data['max_attempts'], $data['safe_retry'], $data['idempotency_key'], $data['status'], $data['next_retry_at'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function issues(string $status = 'OPEN'): array
    {
        return $this->rows(
            'SELECT * FROM integration_issues WHERE status = ? ORDER BY last_attempt_at DESC LIMIT 100',
            [$status]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function issue(int $id): ?array
    {
        return $this->one('SELECT * FROM integration_issues WHERE id = ? LIMIT 1', [$id]);
    }

    public function updateIssue(int $id, string $status, int $attempts, ?string $next): void
    {
        $this->run(
            'UPDATE integration_issues SET status = ?, attempts = ?, next_retry_at = ?, last_attempt_at = NOW() WHERE id = ?',
            [$status, $attempts, $next, $id]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueIssues(): array
    {
        return $this->rows(
            "SELECT * FROM integration_issues
             WHERE status = 'OPEN' AND safe_retry = 1 AND attempts < max_attempts
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())
             ORDER BY id ASC LIMIT 20"
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertConnector(array $data): void
    {
        $this->run(
            'INSERT INTO integration_connectors
                (connector_key, connector_type, provider, active, configuration_json, tested_at, health_status, last_success_at, last_error)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                connector_type = VALUES(connector_type), provider = VALUES(provider), active = VALUES(active),
                configuration_json = VALUES(configuration_json), tested_at = VALUES(tested_at),
                health_status = VALUES(health_status), last_success_at = VALUES(last_success_at), last_error = VALUES(last_error)',
            [
                $data['connector_key'], $data['connector_type'], $data['provider'], $data['active'],
                $data['configuration_json'], $data['tested_at'], $data['health_status'],
                $data['last_success_at'], $data['last_error'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function connectors(): array
    {
        return $this->rows('SELECT id, connector_key, connector_type, provider, active, configuration_json, tested_at, health_status, last_success_at, last_error, updated_at FROM integration_connectors ORDER BY connector_key ASC');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function connector(string $key): ?array
    {
        return $this->one('SELECT * FROM integration_connectors WHERE connector_key = ? LIMIT 1', [$key]);
    }

    public function saveSecret(int $connectorId, string $key, string $value): void
    {
        $this->run(
            'INSERT INTO connector_secrets (connector_id, secret_key, secret_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE secret_value = VALUES(secret_value)',
            [$connectorId, $key, $value]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertDraft(array $data): int
    {
        $this->run(
            'INSERT INTO message_drafts (channel, entity_type, entity_id, recipient, subject, body, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['channel'], $data['entity_type'], $data['entity_id'], $data['recipient'],
                $data['subject'], $data['body'], $data['status'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    public function countDrafts(string $status): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM message_drafts WHERE status = ?', [$status]);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array<string, mixed> $filter
     */
    public function insertView(int $userId, string $name, string $entityType, array $filter): int
    {
        $this->run(
            'INSERT INTO saved_views (user_id, name, entity_type, filter_json) VALUES (?, ?, ?, ?)',
            [$userId, $name, $entityType, json_encode($filter, JSON_THROW_ON_ERROR)]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function views(int $userId): array
    {
        return $this->rows('SELECT * FROM saved_views WHERE user_id = ? ORDER BY name ASC', [$userId]);
    }

    /**
     * @param list<string> $widgets
     */
    public function saveLayout(?int $roleId, ?int $userId, array $widgets): void
    {
        $json = json_encode(array_values($widgets), JSON_THROW_ON_ERROR);
        if ($userId !== null) {
            $this->run(
                'INSERT INTO dashboard_layouts (user_id, layout_json) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE layout_json = VALUES(layout_json)',
                [$userId, $json]
            );

            return;
        }
        $this->run(
            'INSERT INTO dashboard_layouts (role_id, layout_json) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE layout_json = VALUES(layout_json)',
            [$roleId, $json]
        );
    }

    /**
     * @return list<string>
     */
    public function layoutFor(?int $roleId, ?int $userId): array
    {
        $row = null;
        if ($userId !== null) {
            $row = $this->one('SELECT layout_json FROM dashboard_layouts WHERE user_id = ? LIMIT 1', [$userId]);
        }
        if ($row === null && $roleId !== null) {
            $row = $this->one('SELECT layout_json FROM dashboard_layouts WHERE role_id = ? LIMIT 1', [$roleId]);
        }
        if ($row === null) {
            return [];
        }
        $decoded = json_decode((string) $row['layout_json'], true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    public function tagId(string $name): int
    {
        $existing = $this->one('SELECT id FROM tags WHERE name = ? LIMIT 1', [$name]);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $this->run('INSERT INTO tags (name) VALUES (?)', [$name]);

        return $this->insertId();
    }

    public function attachTag(int $tagId, string $entityType, int $entityId, int $userId): void
    {
        $this->run(
            'INSERT IGNORE INTO entity_tags (tag_id, entity_type, entity_id, created_by) VALUES (?, ?, ?, ?)',
            [$tagId, $entityType, $entityId, $userId]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function codeMaps(): array
    {
        return $this->rows('SELECT * FROM accounting_code_maps ORDER BY map_type ASC, local_code ASC');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertCodeMap(array $data): void
    {
        $this->run(
            'INSERT INTO accounting_code_maps (map_type, local_code, external_code, description)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE external_code = VALUES(external_code), description = VALUES(description)',
            [$data['map_type'], $data['local_code'], $data['external_code'], $data['description']]
        );
    }

    public function countIndex(string $name): int
    {
        $row = $this->one(
            'SELECT COUNT(DISTINCT index_name) AS n
             FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND index_name = ?',
            [$name]
        );

        return (int) ($row['n'] ?? 0);
    }
}

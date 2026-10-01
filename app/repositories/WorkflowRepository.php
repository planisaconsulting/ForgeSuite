<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Workflow definitions, versions, and execution history.
 */
final class WorkflowRepository extends Repository
{
    /**
     * @param array<string, mixed> $data
     */
    public function insertDefinition(array $data): int
    {
        $this->run(
            'INSERT INTO workflow_definitions
                (name, description, entity_type, trigger_event, active, priority, stop_on_match, version_number, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [
                $data['name'], $data['description'], $data['entity_type'], $data['trigger_event'],
                $data['active'], $data['priority'], $data['stop_on_match'], $data['created_by'],
            ]
        );

        return $this->insertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateDefinition(int $id, array $data): void
    {
        $this->run(
            'UPDATE workflow_definitions
             SET name = ?, description = ?, entity_type = ?, trigger_event = ?, active = ?, priority = ?,
                 stop_on_match = ?, version_number = version_number + 1
             WHERE id = ?',
            [
                $data['name'], $data['description'], $data['entity_type'], $data['trigger_event'],
                $data['active'], $data['priority'], $data['stop_on_match'], $id,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->one('SELECT * FROM workflow_definitions WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->rows('SELECT * FROM workflow_definitions ORDER BY priority ASC, name ASC');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeFor(string $event, string $entityType): array
    {
        return $this->rows(
            'SELECT * FROM workflow_definitions
             WHERE active = 1 AND trigger_event = ? AND entity_type = ?
             ORDER BY priority ASC, id ASC',
            [$event, $entityType]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertVersion(array $data): void
    {
        $this->run(
            'INSERT INTO workflow_definition_versions
                (workflow_id, version_number, snapshot_json, changed_by, reason)
             VALUES (?, ?, ?, ?, ?)',
            [$data['workflow_id'], $data['version_number'], $data['snapshot_json'], $data['changed_by'], $data['reason']]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function versions(int $workflowId): array
    {
        return $this->rows(
            'SELECT * FROM workflow_definition_versions WHERE workflow_id = ? ORDER BY version_number DESC',
            [$workflowId]
        );
    }

    public function deleteConditions(int $workflowId): void
    {
        $this->run('DELETE FROM workflow_conditions WHERE workflow_id = ?', [$workflowId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertCondition(array $data): void
    {
        $this->run(
            'INSERT INTO workflow_conditions
                (workflow_id, field_key, operator, comparison_value, condition_group, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['workflow_id'], $data['field_key'], $data['operator'], $data['comparison_value'],
                $data['condition_group'], $data['sort_order'],
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function conditions(int $workflowId): array
    {
        return $this->rows(
            'SELECT * FROM workflow_conditions WHERE workflow_id = ? ORDER BY condition_group ASC, sort_order ASC, id ASC',
            [$workflowId]
        );
    }

    public function deleteActions(int $workflowId): void
    {
        $this->run('DELETE FROM workflow_actions WHERE workflow_id = ?', [$workflowId]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertAction(array $data): int
    {
        $this->run(
            'INSERT INTO workflow_actions (workflow_id, action_type, configuration_json, sort_order) VALUES (?, ?, ?, ?)',
            [$data['workflow_id'], $data['action_type'], $data['configuration_json'], $data['sort_order']]
        );

        return $this->insertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function actions(int $workflowId): array
    {
        return $this->rows(
            'SELECT * FROM workflow_actions WHERE workflow_id = ? ORDER BY sort_order ASC, id ASC',
            [$workflowId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function executionByKey(string $key): ?array
    {
        return $this->one('SELECT * FROM workflow_executions WHERE idempotency_key = ? LIMIT 1', [$key]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertExecution(array $data): int
    {
        $this->run(
            'INSERT INTO workflow_executions
                (workflow_id, trigger_event, entity_type, entity_id, event_uuid, origin_workflow_id, execution_depth,
                 status, error_message, context_snapshot_json, idempotency_key, completed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['workflow_id'], $data['trigger_event'], $data['entity_type'], $data['entity_id'],
                $data['event_uuid'], $data['origin_workflow_id'], $data['execution_depth'], $data['status'],
                $data['error_message'], $data['context_snapshot_json'], $data['idempotency_key'], $data['completed_at'],
            ]
        );

        return $this->insertId();
    }

    public function finishExecution(int $id, string $status, ?string $error): void
    {
        $this->run(
            'UPDATE workflow_executions SET status = ?, error_message = ?, completed_at = NOW() WHERE id = ?',
            [$status, $error, $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertActionExecution(array $data): void
    {
        $this->run(
            'INSERT INTO workflow_action_executions
                (workflow_execution_id, workflow_action_id, status, result_summary)
             VALUES (?, ?, ?, ?)',
            [$data['workflow_execution_id'], $data['workflow_action_id'], $data['status'], $data['result_summary']]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentExecutions(int $limit = 50): array
    {
        return $this->rows(
            'SELECT e.*, d.name
             FROM workflow_executions e
             INNER JOIN workflow_definitions d ON d.id = e.workflow_id
             ORDER BY e.id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
    }

    /**
     * @param list<string> $columns
     * @return array<string, string>
     */
    public function scalarFacts(string $table, int $id, array $columns): array
    {
        $allowed = [
            'jobs' => ['status', 'priority', 'customer_id'],
            'invoices' => ['status', 'total', 'balance_due', 'customer_id'],
            'customers' => ['customer_type', 'email'],
            'leads' => ['status'],
            'sales_opportunities' => ['status', 'estimated_value'],
        ];
        if (!isset($allowed[$table])) {
            return [];
        }
        $columns = array_values(array_intersect($columns, $allowed[$table]));
        if ($columns === []) {
            return [];
        }
        $row = $this->one(
            'SELECT ' . implode(', ', $columns) . ' FROM ' . $table . ' WHERE id = ? LIMIT 1',
            [$id]
        );
        if ($row === null) {
            return [];
        }
        $facts = [];
        foreach ($row as $key => $value) {
            $facts[(string) $key] = $value === null ? '' : (string) $value;
        }

        return $facts;
    }

    public function setJobPriority(int $jobId, string $priority): bool
    {
        if (!in_array($priority, ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true)) {
            return false;
        }
        $this->run('UPDATE jobs SET priority = ? WHERE id = ?', [$priority, $jobId]);

        return $this->affected() === 1;
    }

    public function assignJobUser(int $jobId, int $userId): bool
    {
        $this->run(
            'UPDATE jobs SET assigned_to = ? WHERE id = ? AND EXISTS (SELECT 1 FROM users u WHERE u.id = ? AND u.active = 1)',
            [$userId, $jobId, $userId]
        );

        return $this->affected() === 1;
    }

    public function countStatus(string $status): int
    {
        $row = $this->one('SELECT COUNT(*) AS n FROM workflow_executions WHERE status = ?', [$status]);

        return (int) ($row['n'] ?? 0);
    }
}

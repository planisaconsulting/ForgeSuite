<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\WorkflowRepository;

/**
 * Saves a workflow as structured rows and keeps the previous version.
 */
final class WorkflowService
{
    public function __construct(
        private readonly WorkflowRepository $workflows = new WorkflowRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function save(array $input, int $userId, ?int $id = null): array
    {
        if (!can('workflows.manage')) {
            return ['errors' => ['_form' => 'You cannot change workflows.'], 'id' => null];
        }
        $errors = $this->validate($input);
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $header = [
            'name' => trim((string) $input['name']),
            'description' => blank_to_null($input['description'] ?? null),
            'entity_type' => strtoupper((string) $input['entity_type']),
            'trigger_event' => strtoupper((string) $input['trigger_event']),
            'active' => posted_flag($input, 'active', 0),
            'priority' => max(1, min(1000, (int) ($input['priority'] ?? 100))),
            'stop_on_match' => posted_flag($input, 'stop_on_match', 0),
            'created_by' => $userId,
        ];
        $saved = Database::transaction(function () use ($header, $input, $userId, $id): int {
            if ($id === null) {
                $id = $this->workflows->insertDefinition($header);
                $this->audit->record('workflow', $id, 'WORKFLOW_CREATED', null, ['name' => $header['name']], $userId);
            } else {
                $current = $this->workflows->find($id);
                if ($current === null) {
                    throw new \RuntimeException('Missing workflow');
                }
                $this->workflows->insertVersion([
                    'workflow_id' => $id,
                    'version_number' => (int) $current['version_number'],
                    'snapshot_json' => json_encode([
                        'definition' => $current,
                        'conditions' => $this->workflows->conditions($id),
                        'actions' => $this->workflows->actions($id),
                    ], JSON_THROW_ON_ERROR),
                    'changed_by' => $userId,
                    'reason' => blank_to_null($input['reason'] ?? null),
                ]);
                $this->workflows->updateDefinition($id, $header);
                $this->workflows->deleteConditions($id);
                $this->workflows->deleteActions($id);
                $this->audit->record('workflow', $id, (int) $header['active'] === 1 ? 'WORKFLOW_ACTIVATED' : 'WORKFLOW_CHANGED', null, [
                    'name' => $header['name'],
                ], $userId);
            }
            $sort = 0;
            foreach ($input['conditions'] as $condition) {
                if (!is_array($condition) || trim((string) ($condition['field_key'] ?? '')) === '') {
                    continue;
                }
                $this->workflows->insertCondition([
                    'workflow_id' => $id,
                    'field_key' => strtolower(trim((string) $condition['field_key'])),
                    'operator' => strtoupper((string) $condition['operator']),
                    'comparison_value' => blank_to_null($condition['comparison_value'] ?? null),
                    'condition_group' => max(1, (int) ($condition['condition_group'] ?? 1)),
                    'sort_order' => $sort++,
                ]);
            }
            $sort = 0;
            foreach ($input['actions'] as $action) {
                if (!is_array($action)) {
                    continue;
                }
                $this->workflows->insertAction([
                    'workflow_id' => $id,
                    'action_type' => strtoupper((string) $action['action_type']),
                    'configuration_json' => json_encode(SafeValue::object($action['configuration'] ?? []), JSON_THROW_ON_ERROR),
                    'sort_order' => $sort++,
                ]);
            }

            return $id;
        });

        return ['errors' => [], 'id' => $saved];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $errors = [];
        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors['name'] = 'Name the workflow.';
        }
        if (!in_array(strtoupper((string) ($input['entity_type'] ?? '')), WorkflowFactReader::ENTITIES, true)) {
            $errors['entity_type'] = 'Choose a supported record type.';
        }
        if (!in_array(strtoupper((string) ($input['trigger_event'] ?? '')), WorkflowEngine::TRIGGERS, true)) {
            $errors['trigger_event'] = 'Choose a supported event.';
        }
        $actions = $input['actions'] ?? [];
        if (!is_array($actions) || $actions === []) {
            $errors['actions'] = 'Add at least one action.';
        }
        foreach (is_array($actions) ? $actions : [] as $action) {
            if (!is_array($action) || !in_array(strtoupper((string) ($action['action_type'] ?? '')), WorkflowActionRunner::TYPES, true)) {
                $errors['actions'] = 'Use one of the listed actions.';
            }
        }
        foreach (is_array($input['conditions'] ?? []) ? $input['conditions'] : [] as $condition) {
            if (!is_array($condition) || trim((string) ($condition['field_key'] ?? '')) === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9_.]{1,64}$/', strtolower((string) $condition['field_key']))) {
                $errors['conditions'] = 'Condition fields use a simple name.';
            }
            if (!in_array(strtoupper((string) ($condition['operator'] ?? '')), WorkflowConditionEvaluator::OPERATORS, true)) {
                $errors['conditions'] = 'That comparison is not available.';
            }
        }

        return $errors;
    }
}

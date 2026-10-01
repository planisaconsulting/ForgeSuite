<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;
use App\Repositories\WorkflowRepository;

/**
 * Runs active workflows for one business event.
 * Test mode reports what would happen and writes nothing.
 */
final class WorkflowEngine
{
    /** @var list<string> */
    public const TRIGGERS = [
        'LEAD_CREATED', 'OPPORTUNITY_CREATED', 'QUOTE_CREATED', 'QUOTE_SENT', 'QUOTE_ACCEPTED',
        'QUOTE_EXPIRING', 'QUOTE_DECLINED', 'ESTIMATE_APPROVED', 'JOB_CREATED', 'JOB_STATUS_CHANGED',
        'ARTWORK_APPROVED', 'MATERIAL_SHORTAGE', 'PRODUCTION_STARTED', 'PRODUCTION_BLOCKED',
        'QC_FAILED', 'QC_PASSED', 'INSTALLATION_SCHEDULED', 'INSTALLATION_COMPLETED', 'JOB_COMPLETED',
        'INVOICE_ISSUED', 'INVOICE_OVERDUE', 'PAYMENT_RECEIVED', 'STOCK_LOW', 'PO_CREATED', 'PO_OVERDUE',
        'CUSTOMER_DORMANT', 'FEEDBACK_RECEIVED',
    ];

    public function __construct(
        private readonly WorkflowRepository $workflows = new WorkflowRepository(),
        private readonly PlatformRepository $platform = new PlatformRepository(),
        private readonly WorkflowConditionEvaluator $conditions = new WorkflowConditionEvaluator(),
        private readonly WorkflowFactReader $facts = new WorkflowFactReader(),
        private readonly WorkflowActionRunner $actions = new WorkflowActionRunner()
    ) {
    }

    public function handle(int $eventId): void
    {
        if (!(new FeatureFlagService($this->platform))->enabled('ADVANCED_WORKFLOWS')) {
            return;
        }
        $event = $this->platform->event($eventId);
        if ($event === null) {
            return;
        }
        $entityType = strtoupper((string) $event['entity_type']);
        $entityId = (int) $event['entity_id'];
        $loaded = $this->facts->read($entityType, $entityId);
        $summary = json_decode((string) ($event['payload_summary_json'] ?? '{}'), true);
        if (is_array($summary)) {
            foreach ($summary as $key => $value) {
                if (is_string($key) && (is_scalar($value) || $value === null) && !isset($loaded[$key])) {
                    $loaded[$key] = $value === null ? '' : (string) $value;
                }
            }
        }
        $actor = (int) ($event['actor_user_id'] ?? 0);
        foreach ($this->workflows->activeFor((string) $event['event_type'], $entityType) as $workflow) {
            $workflowId = (int) $workflow['id'];
            $conditions = $this->workflows->conditions($workflowId);
            $result = $this->conditions->evaluate($conditions, $loaded);
            if (!$result['matched']) {
                continue;
            }
            $key = $workflowId . '|' . $event['event_uuid'];
            if ($this->workflows->executionByKey($key) !== null) {
                continue;
            }
            $blocked = WorkflowRunGuard::enter($workflowId);
            if ($blocked !== null) {
                $this->record($workflow, $event, $loaded, 'LOOP_PREVENTED', $blocked, $workflowId . '|' . $event['event_uuid'] . '|loop');
                continue;
            }
            try {
                if (!$this->platform->consume($eventId, 'workflow:' . $workflowId)) {
                    continue;
                }
                $executionId = $this->record($workflow, $event, $loaded, 'RUNNING', null, $key);
                $failed = null;
                foreach ($this->workflows->actions($workflowId) as $action) {
                    try {
                        $outcome = $this->actions->run($action, $entityType, $entityId, $actor, $loaded, false);
                    } catch (\Throwable $e) {
                        $outcome = ['status' => 'FAILED', 'summary' => mb_substr($e->getMessage(), 0, 240)];
                    }
                    $this->workflows->insertActionExecution([
                        'workflow_execution_id' => $executionId,
                        'workflow_action_id' => (int) $action['id'],
                        'status' => $outcome['status'],
                        'result_summary' => mb_substr($outcome['summary'], 0, 255),
                    ]);
                    if ($outcome['status'] === 'FAILED') {
                        $failed = $outcome['summary'];
                    }
                }
                $this->workflows->finishExecution($executionId, $failed === null ? 'COMPLETED' : 'FAILED', $failed);
                (new AuditService())->record('workflow', $workflowId, 'WORKFLOW_EXECUTED', null, [
                    'event' => $event['event_type'],
                    'entity_id' => $entityId,
                    'status' => $failed === null ? 'COMPLETED' : 'FAILED',
                ], $actor > 0 ? $actor : null);
            } finally {
                WorkflowRunGuard::leave();
            }
            if ((int) $workflow['stop_on_match'] === 1) {
                break;
            }
        }
        $this->platform->markEventProcessed($eventId);
    }

    /**
     * @return array{matched: bool, results: list<array<string, mixed>>, actions: list<string>}
     */
    public function test(int $workflowId, int $entityId): array
    {
        $workflow = $this->workflows->find($workflowId);
        if ($workflow === null) {
            return ['matched' => false, 'results' => [], 'actions' => []];
        }
        $facts = $this->facts->read((string) $workflow['entity_type'], $entityId);
        $evaluated = $this->conditions->evaluate($this->workflows->conditions($workflowId), $facts);
        $actions = [];
        if ($evaluated['matched']) {
            foreach ($this->workflows->actions($workflowId) as $action) {
                $actions[] = $this->actions->run($action, (string) $workflow['entity_type'], $entityId, 0, $facts, true)['summary'];
            }
        }

        return [
            'matched' => $evaluated['matched'],
            'results' => $evaluated['results'],
            'actions' => $actions,
        ];
    }

    /**
     * @param array<string, mixed> $workflow
     * @param array<string, mixed> $event
     * @param array<string, string> $facts
     */
    private function record(array $workflow, array $event, array $facts, string $status, ?string $error, string $key): int
    {
        $existing = $this->workflows->executionByKey($key);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        try {
            return $this->workflows->insertExecution([
                'workflow_id' => (int) $workflow['id'],
                'trigger_event' => (string) $event['event_type'],
                'entity_type' => strtoupper((string) $event['entity_type']),
                'entity_id' => (int) $event['entity_id'],
                'event_uuid' => (string) $event['event_uuid'],
                'origin_workflow_id' => WorkflowRunGuard::origin(),
                'execution_depth' => WorkflowRunGuard::depth(),
                'status' => $status,
                'error_message' => $error !== null ? mb_substr($error, 0, 255) : null,
                'context_snapshot_json' => json_encode($facts, JSON_THROW_ON_ERROR),
                'idempotency_key' => $key,
                'completed_at' => $status === 'RUNNING' ? null : date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException) {
            $again = $this->workflows->executionByKey($key);

            return $again === null ? 0 : (int) $again['id'];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationRepository;
use App\Repositories\PlatformRepository;
use App\Repositories\WorkflowRepository;

/**
 * Each action is a named operation. Configuration cannot name a class, a query, or a shell command.
 */
final class WorkflowActionRunner
{
    /** @var list<string> */
    public const TYPES = [
        'CREATE_NOTIFICATION',
        'CREATE_TASK',
        'CREATE_FOLLOWUP',
        'REQUEST_APPROVAL',
        'ASSIGN_USER',
        'ASSIGN_TEAM',
        'SET_PRIORITY',
        'ADD_TAG',
        'CREATE_DRAFT_EMAIL',
        'CREATE_DRAFT_WHATSAPP',
        'CREATE_PURCHASE_REQUEST',
        'CREATE_INTERNAL_NOTE',
        'CHANGE_STATUS',
        'CALL_WEBHOOK',
        'GENERATE_DOCUMENT',
    ];

    /** @var array<string, list<string>> */
    private const KEYS = [
        'CREATE_NOTIFICATION' => ['title', 'message', 'user_id'],
        'CREATE_TASK' => ['title', 'description'],
        'CREATE_FOLLOWUP' => ['days', 'title'],
        'REQUEST_APPROVAL' => ['action_key', 'approver_type', 'reason'],
        'ASSIGN_USER' => ['user_id'],
        'ASSIGN_TEAM' => ['team_id'],
        'SET_PRIORITY' => ['priority'],
        'ADD_TAG' => ['tag'],
        'CREATE_DRAFT_EMAIL' => ['recipient', 'subject', 'body'],
        'CREATE_DRAFT_WHATSAPP' => ['recipient', 'body'],
        'CREATE_PURCHASE_REQUEST' => ['product_id', 'quantity', 'reason'],
        'CREATE_INTERNAL_NOTE' => ['message'],
        'CHANGE_STATUS' => ['status'],
        'CALL_WEBHOOK' => ['url'],
        'GENERATE_DOCUMENT' => ['document_type'],
    ];

    public function __construct(
        private readonly PlatformRepository $platform = new PlatformRepository(),
        private readonly WorkflowRepository $workflows = new WorkflowRepository(),
        private readonly NotificationRepository $notifications = new NotificationRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, string> $facts
     * @return array{status: string, summary: string}
     */
    public function run(array $action, string $entityType, int $entityId, int $actorId, array $facts, bool $testMode): array
    {
        $type = strtoupper((string) ($action['action_type'] ?? ''));
        if (!isset(self::KEYS[$type])) {
            return ['status' => 'SKIPPED', 'summary' => 'That action is not available.'];
        }
        $config = SafeValue::only(self::KEYS[$type], SafeValue::object($action['configuration_json'] ?? $action['configuration'] ?? []));
        if ($testMode) {
            return ['status' => 'WOULD_RUN', 'summary' => $type];
        }

        return match ($type) {
            'CREATE_NOTIFICATION', 'CREATE_INTERNAL_NOTE' => $this->notify($type, $config, $entityType, $entityId, $actorId),
            'CREATE_TASK' => $this->task($config, $entityType, $entityId, $actorId),
            'CREATE_FOLLOWUP' => $this->followUp($config, $entityType, $entityId, $actorId, (int) ($action['id'] ?? 0)),
            'REQUEST_APPROVAL' => $this->approval($config, $entityType, $entityId, $actorId, $facts),
            'ASSIGN_USER' => $this->assignUser($config, $entityType, $entityId),
            'ASSIGN_TEAM' => ['status' => 'DONE', 'summary' => 'Team ' . (int) ($config['team_id'] ?? 0) . ' was noted. Job status was not changed.'],
            'SET_PRIORITY' => $this->priority($config, $entityType, $entityId),
            'ADD_TAG' => $this->tag($config, $entityType, $entityId, $actorId),
            'CREATE_DRAFT_EMAIL' => $this->draft('EMAIL', $config, $entityType, $entityId, $actorId),
            'CREATE_DRAFT_WHATSAPP' => $this->draft('WHATSAPP', $config, $entityType, $entityId, $actorId),
            'CREATE_PURCHASE_REQUEST' => $this->purchase($config, $actorId),
            'CHANGE_STATUS' => $this->status($config, $entityType, $entityId, $actorId),
            'CALL_WEBHOOK' => $this->webhook($config, $entityType, $entityId),
            'GENERATE_DOCUMENT' => $this->document($config, $entityType, $entityId, $actorId),
            default => ['status' => 'SKIPPED', 'summary' => 'That action is not available.'],
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function notify(string $type, array $config, string $entityType, int $entityId, int $actorId): array
    {
        $title = trim((string) ($config['title'] ?? $config['message'] ?? 'Workflow note'));
        $this->notifications->insert([
            'user_id' => (int) ($config['user_id'] ?? $actorId) ?: $actorId,
            'role_id' => null,
            'type' => $type,
            'title' => mb_substr($title === '' ? 'Workflow note' : $title, 0, 180),
            'message' => mb_substr((string) ($config['message'] ?? $title), 0, 500),
            'entity_type' => strtolower($entityType),
            'entity_id' => $entityId,
            'priority' => 'NORMAL',
            'dedupe_key' => 'workflow:' . $type . ':' . $entityType . ':' . $entityId . ':' . md5($title),
            'expires_at' => null,
        ]);

        return ['status' => 'DONE', 'summary' => 'Notification recorded.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function task(array $config, string $entityType, int $entityId, int $actorId): array
    {
        if ($entityType !== 'JOB') {
            return $this->notify('CREATE_TASK', ['title' => (string) ($config['title'] ?? 'Task'), 'message' => (string) ($config['description'] ?? '')], $entityType, $entityId, $actorId);
        }
        $result = (new JobService())->addTask($entityId, [
            'title' => (string) ($config['title'] ?? 'Workflow task'),
            'description' => (string) ($config['description'] ?? ''),
        ], $actorId);
        if ($result !== []) {
            return ['status' => 'FAILED', 'summary' => mb_substr((string) reset($result), 0, 240)];
        }

        return ['status' => 'DONE', 'summary' => 'Task added through the job service.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function followUp(array $config, string $entityType, int $entityId, int $actorId, int $actionId): array
    {
        $days = max(1, min(60, (int) ($config['days'] ?? 2)));
        $when = (new \DateTimeImmutable('today'))->modify('+' . $days . ' days')->format('Y-m-d 08:00:00');
        $this->notifications->insertReminder([
            'user_id' => $actorId,
            'entity_type' => strtolower($entityType),
            'entity_id' => $entityId,
            'title' => mb_substr((string) ($config['title'] ?? 'Follow-up'), 0, 180),
            'description' => 'Created by a workflow. Nothing was sent to the customer.',
            'remind_at' => $when,
            'dedupe_key' => 'workflow-followup:' . $actionId . ':' . $entityType . ':' . $entityId,
        ]);

        return ['status' => 'DONE', 'summary' => 'Follow-up in ' . $days . ' days.'];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, string> $facts
     * @return array{status: string, summary: string}
     */
    private function approval(array $config, string $entityType, int $entityId, int $actorId, array $facts): array
    {
        $actionKey = strtoupper(trim((string) ($config['action_key'] ?? 'WORKFLOW')));
        $approvals = new ApprovalService($this->platform);
        $steps = $approvals->stepsFor($entityType, $actionKey, $facts);
        if ($steps === []) {
            $type = strtoupper((string) ($config['approver_type'] ?? 'MANAGEMENT'));
            if (!in_array($type, ApprovalService::APPROVER_TYPES, true)) {
                $type = 'MANAGEMENT';
            }
            $steps = [['approver_type' => $type, 'approver_id' => null, 'role_code' => null]];
        }
        $id = $approvals->open(
            $entityType,
            $entityId,
            $actionKey,
            $actorId,
            $steps,
            (string) ($config['reason'] ?? 'Workflow approval'),
            $facts,
            null,
            null
        );

        return ['status' => 'DONE', 'summary' => 'Approval ' . $id . ' requested.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function assignUser(array $config, string $entityType, int $entityId): array
    {
        $userId = (int) ($config['user_id'] ?? 0);
        if ($entityType === 'JOB' && $this->workflows->assignJobUser($entityId, $userId)) {
            return ['status' => 'DONE', 'summary' => 'Job assigned.'];
        }
        if ($entityType === 'LEAD' && $userId > 0) {
            (new \App\Repositories\LeadRepository())->assign($entityId, $userId);

            return ['status' => 'DONE', 'summary' => 'Lead assigned.'];
        }

        return ['status' => 'SKIPPED', 'summary' => 'No assignment was made.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function priority(array $config, string $entityType, int $entityId): array
    {
        if ($entityType === 'JOB' && $this->workflows->setJobPriority($entityId, strtoupper((string) ($config['priority'] ?? '')))) {
            return ['status' => 'DONE', 'summary' => 'Priority updated. Status was left alone.'];
        }

        return ['status' => 'SKIPPED', 'summary' => 'Priority was not changed.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function tag(array $config, string $entityType, int $entityId, int $actorId): array
    {
        $name = strtoupper(trim((string) ($config['tag'] ?? '')));
        if ($name === '' || !preg_match('/^[A-Z0-9 ]{2,40}$/', $name)) {
            return ['status' => 'SKIPPED', 'summary' => 'Tag name was not accepted.'];
        }
        $this->platform->attachTag($this->platform->tagId($name), $entityType, $entityId, $actorId);

        return ['status' => 'DONE', 'summary' => 'Tag added.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function draft(string $channel, array $config, string $entityType, int $entityId, int $actorId): array
    {
        $body = trim((string) ($config['body'] ?? ''));
        if ($body === '') {
            return ['status' => 'SKIPPED', 'summary' => 'Draft had no text.'];
        }
        $this->platform->insertDraft([
            'channel' => $channel,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'recipient' => blank_to_null($config['recipient'] ?? null),
            'subject' => blank_to_null($config['subject'] ?? null),
            'body' => mb_substr($body, 0, 2000),
            'status' => 'DRAFT',
            'created_by' => $actorId,
        ]);

        return ['status' => 'DONE', 'summary' => $channel . ' draft stored. It was not sent.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function purchase(array $config, int $actorId): array
    {
        $result = (new PurchasingService())->request([
            'product_id' => (int) ($config['product_id'] ?? 0),
            'quantity' => (string) ($config['quantity'] ?? ''),
            'reason' => (string) ($config['reason'] ?? 'Workflow purchase request'),
        ], $actorId);
        if ($result['id'] === null) {
            return ['status' => 'FAILED', 'summary' => mb_substr((string) reset($result['errors']), 0, 240)];
        }

        return ['status' => 'DONE', 'summary' => 'Purchase request ' . $result['id'] . ' created.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function status(array $config, string $entityType, int $entityId, int $actorId): array
    {
        $status = strtoupper(trim((string) ($config['status'] ?? '')));
        if ($entityType === 'JOB') {
            $job = (new \App\Repositories\JobRepository())->find($entityId);
            if ($job === null) {
                return ['status' => 'FAILED', 'summary' => 'Job was not found.'];
            }
            $errors = (new JobService())->changeStatus($entityId, $status, (int) $job['version_number'], $actorId, 'Workflow status change');
            if ($errors !== []) {
                return ['status' => 'FAILED', 'summary' => mb_substr((string) reset($errors), 0, 240)];
            }

            return ['status' => 'DONE', 'summary' => 'Job status changed through JobWorkflowService.'];
        }
        if ($entityType === 'QUOTE') {
            $quote = (new \App\Repositories\QuoteRepository())->find($entityId);
            if ($quote === null) {
                return ['status' => 'FAILED', 'summary' => 'Quotation was not found.'];
            }
            $errors = (new QuoteService())->changeStatus($entityId, $status, (int) $quote['version_number'], $actorId, 'Workflow status change');
            if ($errors !== []) {
                return ['status' => 'FAILED', 'summary' => mb_substr((string) reset($errors), 0, 240)];
            }

            return ['status' => 'DONE', 'summary' => 'Quotation status changed through QuoteService.'];
        }

        return ['status' => 'SKIPPED', 'summary' => 'Status changes for this record stay in its own service.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function webhook(array $config, string $entityType, int $entityId): array
    {
        $url = trim((string) ($config['url'] ?? ''));
        if (!SsrfGuard::allows($url)) {
            $this->platform->insertIssue([
                'provider' => 'webhook',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'failure' => 'Webhook address was rejected.',
                'attempts' => 1,
                'max_attempts' => 1,
                'safe_retry' => 0,
                'idempotency_key' => null,
                'status' => 'OPEN',
                'next_retry_at' => null,
            ]);

            return ['status' => 'FAILED', 'summary' => 'Webhook address was rejected.'];
        }
        $body = json_encode(['entity_type' => $entityType, 'entity_id' => $entityId], JSON_THROW_ON_ERROR);
        $secret = (string) SettingsService::get('payment_webhook_secret', '');
        $signature = hash_hmac('sha256', $body, $secret);
        $handle = curl_init($url);
        if ($handle === false) {
            return ['status' => 'FAILED', 'summary' => 'Webhook could not be opened.'];
        }
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-SignForge-Signature: ' . $signature,
                'X-SignForge-Timestamp: ' . (string) time(),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        curl_exec($handle);
        $error = curl_error($handle);
        $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($error !== '' || $code >= 400) {
            $this->platform->insertIssue([
                'provider' => 'webhook',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'failure' => mb_substr($error !== '' ? $error : 'HTTP ' . $code, 0, 255),
                'attempts' => 1,
                'max_attempts' => 3,
                'safe_retry' => 1,
                'idempotency_key' => null,
                'status' => 'OPEN',
                'next_retry_at' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
            ]);

            return ['status' => 'FAILED', 'summary' => 'Signed webhook was not accepted.'];
        }

        return ['status' => 'DONE', 'summary' => 'Signed webhook sent.'];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{status: string, summary: string}
     */
    private function document(array $config, string $entityType, int $entityId, int $actorId): array
    {
        if ($entityType !== 'JOB' || strtoupper((string) ($config['document_type'] ?? '')) !== 'JOB_CARD') {
            return ['status' => 'SKIPPED', 'summary' => 'Only a job card can be generated from this action.'];
        }
        $result = (new WorkshopDocumentService())->jobCard($entityId, $actorId, false);
        if (($result['id'] ?? null) === null && isset($result['errors'])) {
            return ['status' => 'FAILED', 'summary' => 'Job card was not generated.'];
        }

        return ['status' => 'DONE', 'summary' => 'Job card requested from the workshop document service.'];
    }
}

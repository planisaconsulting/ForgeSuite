<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * Thresholds decide who must approve. Decisions are kept.
 * Escalation notifies. It never approves by itself.
 */
final class ApprovalService
{
    /** @var list<string> */
    public const STATUSES = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED', 'EXPIRED'];

    /** @var list<string> */
    public const APPROVER_TYPES = ['USER', 'ROLE', 'TEAM', 'MANAGEMENT'];

    public function __construct(
        private readonly PlatformRepository $platform = new PlatformRepository(),
        private readonly WorkflowConditionEvaluator $conditions = new WorkflowConditionEvaluator(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, string> $facts
     * @return list<array{approver_type: string, approver_id: int|null, role_code: string|null}>
     */
    public function stepsFor(string $entityType, string $actionKey, array $facts): array
    {
        $policy = $this->platform->policyFor(strtoupper($entityType), strtoupper($actionKey));
        if ($policy === null) {
            return [];
        }
        $steps = [];
        $seen = [];
        foreach ($this->platform->policyRules((int) $policy['id']) as $rule) {
            $decoded = json_decode((string) $rule['conditions_json'], true);
            $conditions = [];
            foreach (is_array($decoded) ? $decoded : [] as $condition) {
                if (is_array($condition)) {
                    $conditions[] = $condition;
                }
            }
            if (($rule['match_mode'] ?? 'ALL') === 'ANY') {
                $ok = false;
                foreach ($conditions as $condition) {
                    if ($this->conditions->evaluate([$condition], $facts)['matched']) {
                        $ok = true;
                        break;
                    }
                }
            } else {
                $ok = $this->conditions->evaluate($conditions, $facts)['matched'];
            }
            if (!$ok) {
                continue;
            }
            $rawSteps = json_decode((string) $rule['steps_json'], true);
            foreach (is_array($rawSteps) ? $rawSteps : [] as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $type = strtoupper((string) ($step['approver_type'] ?? ''));
                if (!in_array($type, self::APPROVER_TYPES, true)) {
                    continue;
                }
                $normal = [
                    'approver_type' => $type,
                    'approver_id' => isset($step['approver_id']) && (int) $step['approver_id'] > 0 ? (int) $step['approver_id'] : null,
                    'role_code' => blank_to_null($step['role_code'] ?? null) !== null ? strtoupper((string) $step['role_code']) : null,
                ];
                $fingerprint = $normal['approver_type'] . ':' . (string) $normal['approver_id'] . ':' . (string) $normal['role_code'];
                if (isset($seen[$fingerprint])) {
                    continue;
                }
                $seen[$fingerprint] = true;
                $steps[] = $normal;
            }
        }

        return $steps;
    }

    /**
     * @param array<string, string> $facts
     * @return array{blocked: bool, request_id: int|null}
     */
    public function gate(string $entityType, int $entityId, string $actionKey, int $userId, array $facts, string $reason): array
    {
        $entityType = strtoupper($entityType);
        $actionKey = strtoupper($actionKey);
        if ($this->platform->approvedRequest($entityType, $entityId, $actionKey) !== null) {
            return ['blocked' => false, 'request_id' => null];
        }
        $steps = $this->stepsFor($entityType, $actionKey, $facts);
        if ($steps === []) {
            return ['blocked' => false, 'request_id' => null];
        }
        $requestId = $this->open($entityType, $entityId, $actionKey, $userId, $steps, $reason, $facts, null, null);

        return ['blocked' => true, 'request_id' => $requestId];
    }

    /**
     * @param list<array{approver_type: string, approver_id: int|null, role_code: string|null}> $steps
     * @param array<string, mixed> $facts
     */
    public function open(
        string $entityType,
        int $entityId,
        string $actionKey,
        int $userId,
        array $steps,
        string $reason,
        array $facts,
        ?int $policyId,
        ?int $policyVersion
    ): int {
        $existing = $this->platform->openApproval($entityType, $entityId, $actionKey);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        if ($policyId === null) {
            $policy = $this->platform->policyFor($entityType, $actionKey);
            if ($policy !== null) {
                $policyId = (int) $policy['id'];
                $policyVersion = (int) $policy['version_number'];
            }
        }
        try {
            $id = $this->platform->insertApproval([
                'policy_id' => $policyId,
                'policy_version' => $policyVersion,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action_key' => $actionKey,
                'requested_by' => $userId,
                'status' => 'PENDING',
                'reason' => mb_substr($reason, 0, 255),
                'context_json' => json_encode($facts, JSON_THROW_ON_ERROR),
                'open_key' => 'OPEN',
            ]);
        } catch (\PDOException) {
            $again = $this->platform->openApproval($entityType, $entityId, $actionKey);

            return $again === null ? 0 : (int) $again['id'];
        }
        $sequence = 1;
        foreach ($steps as $step) {
            $this->platform->insertApprovalStep([
                'approval_request_id' => $id,
                'sequence' => $sequence,
                'approver_type' => $step['approver_type'],
                'approver_id' => $step['approver_id'],
                'role_code' => $step['role_code'],
                'status' => 'PENDING',
            ]);
            $sequence++;
        }
        $this->audit->record('approval', $id, 'APPROVAL_REQUESTED', null, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action_key' => $actionKey,
        ], $userId);
        (new ReviewQueueService($this->platform))->add(
            'APPROVAL',
            $entityType,
            $entityId,
            'Approval required: ' . $actionKey,
            'approval',
            $reason,
            null
        );

        return $id;
    }

    /**
     * @return array<string, string>
     */
    public function decide(int $requestId, string $decision, int $userId, string $comment): array
    {
        if (!can('approvals.decide')) {
            return ['_form' => 'You cannot decide this approval.'];
        }
        $decision = strtoupper($decision);
        if (!in_array($decision, ['APPROVED', 'REJECTED', 'CHANGES_REQUESTED'], true)) {
            return ['decision' => 'Choose approve, reject, or request changes.'];
        }
        $request = $this->platform->approval($requestId);
        if ($request === null || (string) $request['status'] !== 'PENDING') {
            return ['_form' => 'That approval is no longer pending.'];
        }
        $steps = $this->platform->approvalSteps($requestId);
        $current = null;
        foreach ($steps as $step) {
            if ((int) $step['sequence'] === (int) $request['current_step'] && (string) $step['status'] === 'PENDING') {
                $current = $step;
                break;
            }
        }
        if ($current === null) {
            return ['_form' => 'There is no open step on this approval.'];
        }
        if (!$this->canDecide($current, $userId)) {
            return ['_form' => 'This step is assigned to someone else.'];
        }
        $delegatedFrom = null;
        if ((string) $current['approver_type'] === 'USER' && (int) $current['approver_id'] !== $userId) {
            $delegatedFrom = (int) $current['approver_id'];
        }
        $stepStatus = $decision === 'CHANGES_REQUESTED' ? 'CHANGES_REQUESTED' : $decision;
        $this->platform->decideStep((int) $current['id'], $stepStatus, $userId, $delegatedFrom, mb_substr(trim($comment), 0, 255));
        if ($decision === 'REJECTED') {
            $this->platform->advanceApproval($requestId, (int) $current['sequence'], 'REJECTED', true);
            $this->audit->record('approval', $requestId, 'APPROVAL_REJECTED', null, ['comment' => $comment], $userId);

            return [];
        }
        if ($decision === 'CHANGES_REQUESTED') {
            $this->audit->record('approval', $requestId, 'APPROVAL_CHANGES_REQUESTED', null, ['comment' => $comment], $userId);

            return [];
        }
        $later = null;
        foreach ($steps as $step) {
            if ((int) $step['sequence'] > (int) $current['sequence']) {
                $later = $step;
                break;
            }
        }
        if ($later !== null) {
            $this->platform->advanceApproval($requestId, (int) $later['sequence'], 'PENDING', false);

            return [];
        }
        $this->platform->advanceApproval($requestId, (int) $current['sequence'], 'APPROVED', true);
        $this->audit->record('approval', $requestId, 'APPROVAL_APPROVED', null, ['comment' => $comment], $userId);

        return [];
    }

    public function escalate(): int
    {
        $hours = max(1, (int) SettingsService::get('approval_escalation_hours', '24'));
        $before = (new \DateTimeImmutable('now'))->modify('-' . $hours . ' hours')->format('Y-m-d H:i:s');
        $count = 0;
        $notifications = new \App\Repositories\NotificationRepository();
        foreach ($this->platform->staleApprovals($before) as $request) {
            $created = $notifications->insert([
                'user_id' => null,
                'role_id' => null,
                'type' => 'APPROVAL_ESCALATION',
                'title' => 'Approval still pending',
                'message' => 'Pending approval was not decided. It was not approved automatically.',
                'entity_type' => 'approval',
                'entity_id' => (int) $request['id'],
                'priority' => 'HIGH',
                'dedupe_key' => 'approval-escalate:' . $request['id'] . ':' . date('Y-m-d'),
                'expires_at' => null,
            ]);
            if ($created) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function delegate(array $input, int $userId): array
    {
        $original = (int) ($input['original_user_id'] ?? 0);
        $delegate = (int) ($input['delegate_user_id'] ?? 0);
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($original < 1 || $delegate < 1 || $original === $delegate || $reason === '') {
            return ['errors' => ['_form' => 'Name the original approver, a different delegate, and a reason.'], 'id' => null];
        }
        $id = $this->platform->insertDelegation([
            'original_user_id' => $original,
            'delegate_user_id' => $delegate,
            'starts_at' => (string) ($input['starts_at'] ?? date('Y-m-d H:i:s')),
            'ends_at' => (string) ($input['ends_at'] ?? date('Y-m-d H:i:s', strtotime('+7 days'))),
            'reason' => mb_substr($reason, 0, 255),
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $step
     */
    private function canDecide(array $step, int $userId): bool
    {
        $user = auth_user();
        $role = (string) ($user['role_code'] ?? '');
        if ($role === 'ADMIN' || $role === 'MANAGEMENT') {
            return true;
        }
        $type = (string) $step['approver_type'];
        if ($type === 'MANAGEMENT') {
            return $role === 'MANAGEMENT';
        }
        if ($type === 'ROLE') {
            return $role === (string) ($step['role_code'] ?? '');
        }
        if ($type === 'USER') {
            if ((int) $step['approver_id'] === $userId) {
                return true;
            }
            $delegation = $this->platform->activeDelegation((int) $step['approver_id']);

            return $delegation !== null && (int) $delegation['delegate_user_id'] === $userId;
        }

        return false;
    }
}

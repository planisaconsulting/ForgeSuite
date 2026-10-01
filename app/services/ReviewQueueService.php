<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlatformRepository;

/**
 * One queue for human decisions. Resolving an item does not apply a side effect
 * unless the caller does that separately.
 */
final class ReviewQueueService
{
    /** @var list<string> */
    public const STATUSES = ['PENDING', 'APPROVED', 'EDITED', 'REJECTED', 'RESOLVED'];

    public function __construct(private readonly PlatformRepository $platform = new PlatformRepository())
    {
    }

    public function add(
        string $type,
        ?string $entityType,
        ?int $entityId,
        string $action,
        string $source,
        ?string $reason,
        ?int $assignee
    ): int {
        return $this->platform->insertReview([
            'item_type' => strtoupper($type),
            'entity_type' => $entityType !== null ? strtoupper($entityType) : null,
            'entity_id' => $entityId,
            'proposed_action' => mb_substr($action, 0, 180),
            'source' => mb_substr($source, 0, 60),
            'reason' => $reason !== null ? mb_substr($reason, 0, 255) : null,
            'assigned_to' => $assignee,
            'status' => 'PENDING',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function resolve(int $id, string $status, int $userId): array
    {
        if (!can('review_queue.resolve')) {
            return ['_form' => 'You cannot resolve this review.'];
        }
        $status = strtoupper($status);
        if (!in_array($status, ['APPROVED', 'EDITED', 'REJECTED', 'RESOLVED'], true)) {
            return ['status' => 'Choose a review outcome.'];
        }
        $this->platform->resolveReview($id, $status, $userId);

        return [];
    }
}

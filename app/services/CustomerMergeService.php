<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\ExpenseRepository;

/**
 * Moves one customer's relationships onto another and archives the source.
 * The source row stays. Totals are not copied.
 */
final class CustomerMergeService
{
    /** @var list<string> */
    private const FIELDS = ['company_name', 'email', 'phone', 'mobile', 'vat_number', 'registration_number'];

    public function __construct(
        private readonly ExpenseRepository $repo = new ExpenseRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @return array{errors: array<string, string>, preview: array<string, mixed>|null}
     */
    public function preview(int $sourceId, int $targetId): array
    {
        if (!can('entity_merge.preview') && !can('entity_merge.execute')) {
            return ['errors' => ['_form' => 'You cannot preview a merge.'], 'preview' => null];
        }
        $source = $this->repo->customer($sourceId);
        $target = $this->repo->customer($targetId);
        if ($source === null || $target === null || $sourceId === $targetId) {
            return ['errors' => ['_form' => 'Choose two different customers.'], 'preview' => null];
        }
        if ($source['merged_into_id'] !== null) {
            return ['errors' => ['_form' => 'That customer is already archived.'], 'preview' => null];
        }
        $differences = [];
        foreach (self::FIELDS as $field) {
            $left = trim((string) ($source[$field] ?? ''));
            $right = trim((string) ($target[$field] ?? ''));
            if ($left !== $right) {
                $differences[] = ['field' => $field, 'source' => $left, 'target' => $right];
            }
        }

        return ['errors' => [], 'preview' => [
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'source_name' => (string) ($source['company_name'] ?? ''),
            'target_name' => (string) ($target['company_name'] ?? ''),
            'differences' => $differences,
            'source_links' => $this->repo->customerLinks($sourceId),
            'target_links' => $this->repo->customerLinks($targetId),
        ]];
    }

    /**
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function execute(int $sourceId, int $targetId, int $userId): array
    {
        if (!can('entity_merge.execute')) {
            return ['errors' => ['_form' => 'You cannot merge customers.'], 'id' => null];
        }
        $preview = $this->preview($sourceId, $targetId);
        if ($preview['preview'] === null) {
            return ['errors' => $preview['errors'], 'id' => null];
        }
        $id = 0;
        try {
            Database::transaction(function () use ($sourceId, $targetId, $userId, $preview, &$id): void {
                $this->repo->reassignCustomer($sourceId, $targetId);
                $id = $this->repo->insertMerge('CUSTOMER', $sourceId, $targetId, json_encode($preview['preview'], JSON_THROW_ON_ERROR), $userId);
            });
        } catch (\Throwable) {
            return ['errors' => ['_form' => 'The merge stopped because a relationship could not be moved. Nothing was changed.'], 'id' => null];
        }
        $this->audit->record('customer', $targetId, 'ENTITY_MERGED', ['source' => $sourceId], ['target' => $targetId], $userId);
        BusinessEventDispatcher::emit('ENTITY_MERGED', 'CUSTOMER', $targetId, $userId, ['source' => $sourceId]);

        return ['errors' => [], 'id' => $id];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductionControlRepository;

/**
 * Says whether a change touches released work.
 * A phone number does not. A dimension does.
 */
final class ProductionChangeImpactService
{
    /** @var list<string> */
    private const NONE = [
        'INTERNAL_NOTE', 'SALES_CONTACT', 'PHONE', 'CUSTOMER_PHONE', 'SALESPERSON_NOTE',
    ];

    /** @var list<string> */
    private const RERELEASE = [
        'QUANTITY', 'DIMENSIONS', 'WIDTH_MM', 'HEIGHT_MM', 'ARTWORK_REVISION', 'SPECIFICATION',
        'BOM', 'FULFILMENT', 'FULFILMENT_TYPE', 'MATERIAL',
    ];

    public function __construct(private readonly ProductionControlRepository $repo = new ProductionControlRepository())
    {
    }

    public function classify(string $field): string
    {
        $field = strtoupper(trim($field));
        if (in_array($field, self::NONE, true)) {
            return 'NO_PRODUCTION_IMPACT';
        }
        if (in_array($field, self::RERELEASE, true)) {
            return 'RE_RELEASE_REQUIRED';
        }

        return 'REVIEW_REQUIRED';
    }

    /**
     * @return array{impact: string, id: int|null, errors: array<string, string>}
     */
    public function record(int $jobId, string $field, string $source, string $reason, string $change, int $userId): array
    {
        if (!can('production.change.request') && !can('production.release.approve')) {
            return ['impact' => '', 'id' => null, 'errors' => ['_form' => 'You cannot request a production change.']];
        }
        $impact = $this->classify($field);
        $current = $this->repo->currentRelease($jobId);
        $id = $this->repo->insertChange([
            'job_id' => $jobId,
            'release_id' => $current['id'] ?? null,
            'source' => strtoupper($source),
            'reason' => $reason,
            'requested_change' => $change,
            'artwork_impact' => $field === 'ARTWORK_REVISION' ? $change : null,
            'material_impact' => $field === 'BOM' || $field === 'MATERIAL' ? $change : null,
            'schedule_impact' => null,
            'cost_impact' => null,
            'impact' => $impact,
            'status' => 'OPEN',
            'requested_by' => $userId,
        ]);
        if ($impact === 'RE_RELEASE_REQUIRED' && $current !== null) {
            $this->repo->setReleaseStatus((int) $current['id'], 'REVIEW_REQUIRED');
            $this->repo->preparation($jobId, 'RELEASE_BLOCKED');
            BusinessEventDispatcher::emit('PRODUCTION_RELEASE_BLOCKED', 'PRODUCTION_RELEASE', (int) $current['id'], $userId, ['impact' => 'review']);
        }
        BusinessEventDispatcher::emit('PRODUCTION_CHANGE_REQUESTED', 'JOB', $jobId, $userId, ['impact' => strtolower($impact)]);

        return ['impact' => $impact, 'id' => $id, 'errors' => []];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\WorkshopRepository;

/**
 * Groups production items into one labelled package for delivery or installation.
 */
final class PackageService
{
    public function __construct(
        private readonly WorkshopRepository $workshop = new WorkshopRepository(),
        private readonly NumberingService $numbers = new NumberingService(),
        private readonly TrackingCodeService $tracking = new TrackingCodeService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param list<int> $productionIds
     * @return array{errors: array<string, string>, id: int|null, code: string}
     */
    public function create(int $jobId, string $description, array $productionIds, int $userId): array
    {
        if (!can('dispatch.create') && !can('workshop.scan')) {
            return ['errors' => ['_form' => 'You cannot pack this job.'], 'id' => null, 'code' => ''];
        }
        $ids = [];
        foreach ($productionIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return ['errors' => ['_form' => 'Choose at least one production item.'], 'id' => null, 'code' => ''];
        }
        try {
            $created = Database::transaction(function () use ($jobId, $description, $ids, $userId): array {
                $code = $this->numbers->package();
                $packageId = $this->workshop->insertPackage([
                    'package_code' => $code,
                    'job_id' => $jobId,
                    'description' => mb_substr(trim($description) !== '' ? trim($description) : 'Package', 0, 255),
                    'status' => 'OPEN',
                    'created_by' => $userId,
                ]);
                foreach ($ids as $productionId) {
                    $piece = $this->workshop->productionItem($productionId);
                    if ($piece === null || (int) $piece['job_id'] !== $jobId) {
                        throw new StockRejected(['_form' => 'WRONG JOB. That item is not on this package.']);
                    }
                    $this->workshop->addPackageItem([
                        'package_id' => $packageId,
                        'production_item_id' => $productionId,
                        'job_item_id' => (int) $piece['job_item_id'],
                        'description' => (string) $piece['description'],
                        'quantity' => (string) $piece['quantity'],
                    ]);
                }
                $this->tracking->ensure('PACKAGE', $packageId, $code, $userId);
                $this->audit->record('package', $packageId, 'PACKAGE_CREATED', null, [
                    'job_id' => $jobId,
                    'code' => $code,
                    'items' => count($ids),
                ], $userId);

                return ['id' => $packageId, 'code' => $code];
            });
        } catch (StockRejected $e) {
            return ['errors' => $e->errors, 'id' => null, 'code' => ''];
        }

        return ['errors' => [], 'id' => $created['id'], 'code' => $created['code']];
    }
}

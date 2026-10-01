<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\AssetRepository;

/**
 * Inspections copy the checklist onto the visit so a later template edit
 * does not rewrite the report.
 */
final class InspectionService
{
    public const TYPES = ['ROUTINE', 'WARRANTY', 'SAFETY', 'ELECTRICAL', 'STRUCTURAL', 'PREVENTATIVE', 'CUSTOM'];

    public const RESULTS = ['PASS', 'PASS_WITH_NOTES', 'SERVICE_REQUIRED', 'URGENT_REPAIR', 'OUT_OF_SERVICE'];

    public function __construct(private readonly AssetRepository $assets = new AssetRepository())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function record(int $assetId, array $input, int $userId): array
    {
        if (!can('inspections.perform') && !can('inspections.manage')) {
            return ['errors' => ['_form' => 'You cannot record an inspection.'], 'id' => null];
        }
        if ($this->assets->find($assetId) === null) {
            return ['errors' => ['_form' => 'That asset was not found.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['inspection_type'] ?? 'ROUTINE')));
        if (!in_array($type, self::TYPES, true)) {
            return ['errors' => ['inspection_type' => 'Choose an inspection type.'], 'id' => null];
        }
        $result = strtoupper(trim((string) ($input['result'] ?? 'PASS')));
        if (!in_array($result, self::RESULTS, true)) {
            return ['errors' => ['result' => 'Choose a result.'], 'id' => null];
        }
        $date = trim((string) ($input['inspected_on'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['errors' => ['inspected_on' => 'Inspection date is required.'], 'id' => null];
        }
        $items = $input['items'] ?? [];
        if (!is_array($items) || $items === []) {
            $items = [
                ['label' => 'Structure secure', 'result' => 'PASS'],
                ['label' => 'Face condition', 'result' => 'PASS'],
                ['label' => 'Lighting', 'result' => 'PASS'],
            ];
        }
        $id = 0;
        Database::transaction(function () use ($assetId, $input, $userId, $type, $result, $date, $items, &$id): void {
            $id = $this->assets->insertInspection([
                'asset_id' => $assetId,
                'service_request_id' => (int) ($input['service_request_id'] ?? 0) > 0 ? (int) $input['service_request_id'] : null,
                'job_id' => (int) ($input['job_id'] ?? 0) > 0 ? (int) $input['job_id'] : null,
                'inspection_type' => $type,
                'result' => $result,
                'findings' => blank_to_null($input['findings'] ?? null),
                'recommendations' => blank_to_null($input['recommendations'] ?? null),
                'inspected_on' => $date,
                'technician_user_id' => $userId > 0 ? $userId : null,
                'customer_visible' => empty($input['internal_only']) ? 1 : 0,
            ]);
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $itemResult = strtoupper(trim((string) ($item['result'] ?? 'PASS')));
                if (!in_array($itemResult, self::RESULTS, true)) {
                    $itemResult = 'PASS';
                }
                $this->assets->insertInspectionItem([
                    'inspection_id' => $id,
                    'label' => mb_substr($label, 0, 180),
                    'result' => $itemResult,
                    'note' => blank_to_null($item['note'] ?? null),
                ]);
            }
            $this->assets->insertEvent([
                'asset_id' => $assetId,
                'event_type' => 'INSPECTION',
                'summary' => $type . ' inspection ' . $result,
                'related_type' => 'asset_inspection',
                'related_id' => $id,
                'happened_at' => $date . ' 00:00:00',
                'created_by' => $userId,
            ]);
        });
        if (in_array($result, ['SERVICE_REQUIRED', 'URGENT_REPAIR', 'OUT_OF_SERVICE'], true)) {
            BusinessEventDispatcher::emit('INSPECTION_FAILED', 'ASSET', $assetId, $userId, [
                'inspection_id' => $id,
                'result' => $result,
            ]);
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * Customer-safe inspection. Costs are not on this record.
     *
     * @return array<string, mixed>|null
     */
    public function customerReport(int $inspectionId, int $customerId): ?array
    {
        $rows = $this->assets->rowsForReport(
            'SELECT i.*, a.asset_number, a.name, a.customer_id, ps.site_name
             FROM asset_inspections i
             JOIN customer_assets a ON a.id = i.asset_id
             LEFT JOIN project_sites ps ON ps.id = a.project_site_id
             WHERE i.id = ?',
            [$inspectionId]
        );
        $row = $rows[0] ?? null;
        if ($row === null || (int) $row['customer_id'] !== $customerId || (int) $row['customer_visible'] !== 1) {
            return null;
        }
        $row['items'] = $this->assets->inspectionItems($inspectionId);
        unset($row['customer_id']);

        return $row;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\AssetRepository;

/**
 * Serviceable parts on an asset. A replacement keeps the old row.
 */
final class AssetComponentService
{
    public const STATUSES = ['ACTIVE', 'FAILED', 'REPLACED', 'REMOVED', 'UNKNOWN'];

    public function __construct(private readonly AssetRepository $assets = new AssetRepository())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function add(int $assetId, array $input, int $userId): array
    {
        if (!can('assets.manage_components')) {
            return ['errors' => ['_form' => 'You cannot manage components.'], 'id' => null];
        }
        if ($this->assets->find($assetId) === null) {
            return ['errors' => ['_form' => 'That asset was not found.'], 'id' => null];
        }
        $data = $this->fields($assetId, $input, null);
        if ($data['errors'] !== []) {
            return ['errors' => $data['errors'], 'id' => null];
        }
        $id = $this->assets->insertComponent($data['row']);
        $this->assets->insertEvent([
            'asset_id' => $assetId,
            'event_type' => 'COMPONENT',
            'summary' => 'Component added: ' . $data['row']['description'],
            'related_type' => 'asset_component',
            'related_id' => $id,
            'happened_at' => ($data['row']['installed_date'] ?? date('Y-m-d')) . ' 00:00:00',
            'created_by' => $userId,
        ]);

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function replace(int $componentId, array $input, int $userId, ?int $jobId = null): array
    {
        if (!can('assets.manage_components')) {
            return ['errors' => ['_form' => 'You cannot replace a component.'], 'id' => null];
        }
        $old = $this->assets->component($componentId);
        if ($old === null) {
            return ['errors' => ['_form' => 'That component was not found.'], 'id' => null];
        }
        $incoming = array_merge($old, $input);
        $incoming['status'] = 'ACTIVE';
        $data = $this->fields((int) $old['asset_id'], $incoming, $componentId);
        if ($data['errors'] !== []) {
            return ['errors' => $data['errors'], 'id' => null];
        }
        $newId = 0;
        Database::transaction(function () use ($old, $data, $userId, $jobId, &$newId): void {
            $this->assets->setComponentStatus((int) $old['id'], 'REPLACED');
            $newId = $this->assets->insertComponent($data['row']);
            $this->assets->insertEvent([
                'asset_id' => (int) $old['asset_id'],
                'event_type' => 'COMPONENT_REPLACED',
                'summary' => (string) $old['description'] . ' replaced',
                'related_type' => 'job',
                'related_id' => $jobId,
                'happened_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
            ]);
        });
        BusinessEventDispatcher::emit('ASSET_COMPONENT_REPLACED', 'ASSET', (int) $old['asset_id'], $userId, [
            'old_component_id' => $componentId,
            'new_component_id' => $newId,
        ]);

        return ['errors' => [], 'id' => $newId];
    }

    public function markFailed(int $componentId, int $userId): array
    {
        $old = $this->assets->component($componentId);
        if ($old === null) {
            return ['errors' => ['_form' => 'That component was not found.'], 'id' => null];
        }
        $this->assets->setComponentStatus($componentId, 'FAILED');
        BusinessEventDispatcher::emit('ASSET_COMPONENT_FAILED', 'ASSET', (int) $old['asset_id'], $userId, [
            'component_id' => $componentId,
        ]);

        return ['errors' => [], 'id' => $componentId];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, row: array<string, mixed>}
     */
    private function fields(int $assetId, array $input, ?int $replaces): array
    {
        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            return ['errors' => ['description' => 'Describe the component.'], 'row' => []];
        }
        $status = strtoupper(trim((string) ($input['status'] ?? 'ACTIVE')));
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'ACTIVE';
        }
        $quantity = trim((string) ($input['quantity'] ?? '1'));
        if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
            return ['errors' => ['quantity' => 'Quantity must be greater than zero.'], 'row' => []];
        }
        $type = strtoupper(trim((string) ($input['component_type'] ?? 'OTHER')));
        if ($type === '') {
            $type = 'OTHER';
        }

        return ['errors' => [], 'row' => [
            'asset_id' => $assetId,
            'component_type' => mb_substr($type, 0, 40),
            'product_id' => (int) ($input['product_id'] ?? 0) > 0 ? (int) $input['product_id'] : null,
            'inventory_item_id' => (int) ($input['inventory_item_id'] ?? 0) > 0 ? (int) $input['inventory_item_id'] : null,
            'description' => mb_substr($description, 0, 180),
            'manufacturer' => blank_to_null($input['manufacturer'] ?? null),
            'model' => blank_to_null($input['model'] ?? null),
            'serial_number' => blank_to_null($input['serial_number'] ?? null),
            'quantity' => Decimal::round($quantity, 4),
            'installed_date' => $this->date($input['installed_date'] ?? null),
            'warranty_start_date' => $this->date($input['warranty_start_date'] ?? null),
            'warranty_end_date' => $this->date($input['warranty_end_date'] ?? null),
            'status' => $status,
            'replaces_component_id' => $replaces,
            'notes' => blank_to_null($input['notes'] ?? null),
        ]];
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}

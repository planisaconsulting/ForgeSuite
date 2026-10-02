<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InventoryRepository;
use App\Repositories\ProcurementRepository;

/**
 * Locations are a hierarchy on the existing stock location table.
 * Put-away suggests a bin. A person confirms the move.
 */
final class WarehouseService
{
    public function __construct(
        private readonly ProcurementRepository $repo = new ProcurementRepository(),
        private readonly InventoryRepository $inventory = new InventoryRepository(),
        private readonly StockMovementService $stock = new StockMovementService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function createLocation(array $input, int $userId): array
    {
        if (!can('inventory.locations.manage') && !can('inventory.adjust')) {
            return ['errors' => ['_form' => 'You cannot manage locations.'], 'id' => null];
        }
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if ($code === '' || $this->repo->locationByCode($code) !== null) {
            return ['errors' => ['code' => 'Choose a unique location code.'], 'id' => null];
        }
        $type = strtoupper(trim((string) ($input['location_type'] ?? 'BIN')));
        $allowed = ['WAREHOUSE', 'WORKSHOP', 'VEHICLE', 'INSTALLATION', 'ZONE', 'AISLE', 'RACK', 'SHELF', 'BIN', 'QUARANTINE', 'OFFCUT', 'OTHER'];
        if (!in_array($type, $allowed, true)) {
            return ['errors' => ['location_type' => 'That location type is not valid.'], 'id' => null];
        }
        $id = $this->repo->insertLocation([
            'parent_id' => ((int) ($input['parent_id'] ?? 0)) > 0 ? (int) $input['parent_id'] : null,
            'code' => $code,
            'name' => mb_substr(trim((string) ($input['name'] ?? $code)), 0, 120),
            'location_type' => $type,
            'description' => blank_to_null($input['description'] ?? null),
        ]);
        (new AuditService())->record('stock_location', $id, 'LOCATION_CREATED', null, ['code' => $code], $userId);

        return ['errors' => [], 'id' => $id];
    }

    public function suggest(int $productId): ?array
    {
        return $this->repo->putawayLocation($productId);
    }

    /**
     * @return array<string, string>
     */
    public function confirmPutaway(int $productId, int $fromLocationId, int $toLocationId, string $quantity, int $userId, int $itemId = 0): array
    {
        if (!can('inventory.putaway') && !can('inventory.transfer')) {
            return ['_form' => 'You cannot put stock away.'];
        }
        $moved = $this->stock->transfer([
            'product_id' => $productId,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'quantity' => $quantity,
            'inventory_item_id' => $itemId,
            'reason' => 'Put-away',
        ], $userId);
        if ($moved !== []) {
            return $moved;
        }
        BusinessEventDispatcher::emit('STOCK_PUT_AWAY', 'PRODUCT', $productId, $userId, ['to' => $toLocationId]);

        return [];
    }

    public function rememberPutaway(int $productId, int $locationId): void
    {
        $this->repo->insertPutawayRule($productId, $locationId);
    }

    public function locationOfItem(int $itemId): ?array
    {
        $item = $this->inventory->item($itemId);
        if ($item === null) {
            return null;
        }

        return $this->inventory->location((int) $item['stock_location_id']);
    }
}

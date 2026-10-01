<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Connects a job material usage row to the stock ledger.
 * Untracked products return null and leave stock unchanged.
 */
final class JobStockHook implements StockConsumptionHook
{
    public function __construct(private readonly StockMovementService $stock = new StockMovementService())
    {
    }

    public function prepare(array $product, array $input): ?array
    {
        $quoted = $this->stock->quoteJobCost($product, $input);
        if ($quoted === null) {
            return null;
        }
        if ((int) ($input['stock_location_id'] ?? 0) < 1 && (int) ($input['inventory_item_id'] ?? 0) < 1 && (int) ($input['reservation_id'] ?? 0) < 1) {
            throw new StockRejected(['stock_location_id' => 'Choose a stock location or a tracked roll, sheet, or offcut.']);
        }
        $itemId = (int) ($input['inventory_item_id'] ?? 0);
        if ($itemId > 0) {
            $item = (new \App\Repositories\InventoryRepository())->item($itemId);
            if ($item !== null && (int) ($item['customer_supplied'] ?? 0) === 1) {
                $quoted['unit_cost'] = '0.0000';
            }
        }

        return $quoted;
    }

    public function recordConsumption(array $usage): void
    {
        $this->stock->consumeForJob($usage);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Phase 4 connects job material usage to stock_movements through this hook.
 * Phase 3 records the usage and the cost snapshot only. It does not change inventory.
 */
interface StockConsumptionHook
{
    /**
     * Decide the cost snapshot before the usage row is written.
     * Null means the product catalogue cost is used and no stock moves.
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed> $input
     * @return array{unit_cost: string, unit: string}|null
     */
    public function prepare(array $product, array $input): ?array;

    /**
     * Called inside the usage transaction after the usage row exists.
     *
     * @param array<string, mixed> $usage
     */
    public function recordConsumption(array $usage): void;
}

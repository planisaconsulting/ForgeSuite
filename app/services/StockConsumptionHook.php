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
     * @param array<string, mixed> $usage
     */
    public function recordConsumption(array $usage): void;
}

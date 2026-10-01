<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\InventoryRepository;

/**
 * Suggests stock, offcuts, and rolls for a recipe requirement.
 * Nothing here reserves or consumes stock.
 */
final class RecipeStockMatcher
{
    public function __construct(private readonly InventoryRepository $inventory = new InventoryRepository())
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function suggest(int $productId, string $required, ?string $widthMm = null, ?string $heightMm = null): array
    {
        if ($productId < 1) {
            return [
                'required' => $required,
                'on_hand' => '0.0000',
                'reserved' => '0.0000',
                'available' => '0.0000',
                'shortage' => $required,
                'offcuts' => [],
                'rolls' => [],
            ];
        }
        $onHand = $this->inventory->onHand($productId);
        $reserved = $this->inventory->reserved($productId);
        $available = Decimal::sub($onHand, $reserved, 4);
        if (Decimal::cmp($available, '0') < 0) {
            $available = '0.0000';
        }
        $short = Decimal::sub($required, $available, 4);
        $offcuts = [];
        if ($widthMm !== null && $heightMm !== null && Decimal::cmp($widthMm, '0') > 0 && Decimal::cmp($heightMm, '0') > 0) {
            $offcuts = $this->inventory->offcuts($productId, $widthMm, $heightMm, true);
        } else {
            foreach ($this->inventory->offcuts($productId, '0', '0', true) as $row) {
                $offcuts[] = $row;
            }
        }
        $rolls = $this->inventory->rollsForProduct($productId);

        return [
            'required' => Decimal::round($required, 4),
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => Decimal::round($available, 4),
            'shortage' => Decimal::cmp($short, '0') > 0 ? Decimal::round($short, 4) : '0.0000',
            'offcuts' => $offcuts,
            'rolls' => $rolls,
        ];
    }
}

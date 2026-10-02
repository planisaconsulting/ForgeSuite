<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Decimal;
use App\Repositories\ForecastRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductionControlRepository;

/**
 * Required, reserved, available, and incoming are separate.
 * Incoming purchase orders are not physically available.
 */
final class MaterialReadinessService
{
    public function __construct(
        private readonly ProductionControlRepository $repo = new ProductionControlRepository(),
        private readonly InventoryRepository $inventory = new InventoryRepository(),
        private readonly ForecastRepository $forecast = new ForecastRepository()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forJob(int $jobId): array
    {
        $incoming = [];
        foreach ($this->forecast->incomingPurchaseLines() as $line) {
            $productId = (int) $line['product_id'];
            $incoming[$productId][] = $line;
        }
        $rows = [];
        foreach ($this->repo->requirements($jobId) as $requirement) {
            $productId = (int) ($requirement['product_id'] ?? 0);
            $required = (string) $requirement['final_required_quantity'];
            if ($productId < 1 || Decimal::cmp($required, '0') <= 0) {
                $rows[] = [
                    'requirement_id' => (int) $requirement['id'],
                    'product_id' => $productId,
                    'name' => (string) ($requirement['product_name'] ?? 'Material'),
                    'required' => $required,
                    'reserved' => '0',
                    'available' => '0',
                    'incoming' => '0',
                    'shortage' => '0',
                    'status' => 'NOT_CHECKED',
                    'expected_date' => null,
                ];
                continue;
            }
            $available = $this->inventory->onHand($productId);
            $reserved = $this->inventory->reserved($productId);
            $free = Decimal::sub($available, $reserved, 4);
            if (Decimal::cmp($free, '0') < 0) {
                $free = '0';
            }
            $due = '0';
            $expected = null;
            foreach ($incoming[$productId] ?? [] as $line) {
                $due = Decimal::add($due, (string) $line['quantity'], 4);
                $expected = (string) $line['arrives'];
            }
            $shortage = Decimal::sub($required, $free, 4);
            if (Decimal::cmp($shortage, '0') < 0) {
                $shortage = '0';
            }
            $status = 'AVAILABLE';
            if (Decimal::cmp($shortage, '0') > 0 && Decimal::cmp($due, '0') > 0 && Decimal::cmp($due, $shortage) >= 0) {
                $status = 'INCOMING';
            } elseif (Decimal::cmp($shortage, '0') > 0 && Decimal::cmp($free, '0') > 0) {
                $status = 'PARTIALLY_AVAILABLE';
            } elseif (Decimal::cmp($shortage, '0') > 0) {
                $status = 'SHORTAGE';
            }
            $rows[] = [
                'requirement_id' => (int) $requirement['id'],
                'product_id' => $productId,
                'name' => (string) ($requirement['product_name'] ?? 'Material'),
                'required' => Decimal::round($required, 4),
                'reserved' => Decimal::round($reserved, 4),
                'available' => Decimal::round($free, 4),
                'incoming' => Decimal::round($due, 4),
                'shortage' => Decimal::round($shortage, 4),
                'status' => $status,
                'expected_date' => $expected,
            ];
        }

        return $rows;
    }
}

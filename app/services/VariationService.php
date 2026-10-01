<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\VariationStatus;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\FinanceRepository;
use App\Repositories\JobRepository;
use App\Repositories\PricingLevelRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;

/**
 * Approved variations add to the job's commercial value.
 * They do not change the accepted quotation.
 */
final class VariationService
{
    public function __construct(
        private readonly FinanceRepository $finance = new FinanceRepository(),
        private readonly JobRepository $jobs = new JobRepository(),
        private readonly QuoteRepository $quotes = new QuoteRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly PricingLevelRepository $levels = new PricingLevelRepository(),
        private readonly PricingService $pricing = new PricingService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(int $jobId, array $input, int $userId): array
    {
        if (!can('invoices.create')) {
            return ['errors' => ['_form' => 'You cannot add a variation.'], 'id' => null];
        }
        $job = $this->jobs->find($jobId);
        if ($job === null) {
            return ['errors' => ['_form' => 'That job was not found.'], 'id' => null];
        }
        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '') {
            return ['errors' => ['description' => 'Describe the variation.'], 'id' => null];
        }
        $quote = $this->quotes->find((int) $job['quote_id']);
        try {
            $id = Database::transaction(function () use ($job, $quote, $description, $userId): int {
                $id = $this->finance->insertVariation([
                    'job_id' => (int) $job['id'],
                    'variation_number' => $this->finance->nextVariationNumber((int) $job['id']),
                    'description' => substr($description, 0, 255),
                    'status' => VariationStatus::Draft->value,
                    'vat_mode' => (string) ($quote['vat_mode'] ?? 'EXCLUSIVE'),
                    'vat_rate' => (string) ($quote['vat_rate'] ?? SettingsService::get('default_vat_percent', '15')),
                    'created_by' => $userId,
                ]);
                $this->audit->record('job_variation', $id, 'VARIATION_CREATED', null, [
                    'job_id' => (int) $job['id'],
                ], $userId);

                return $id;
            });
        } catch (FinanceRejected $e) {
            return ['errors' => $e->errors, 'id' => null];
        }

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function addItem(int $variationId, array $input, int $userId): array
    {
        if (!can('invoices.create')) {
            return ['_form' => 'You cannot edit a variation.'];
        }
        try {
            Database::transaction(function () use ($variationId, $input, $userId): void {
                $variation = $this->finance->lockVariation($variationId);
                if ($variation === null || (string) $variation['status'] !== VariationStatus::Draft->value) {
                    throw new FinanceRejected(['_form' => 'Only a draft variation can be changed.']);
                }
                $productId = (int) ($input['product_id'] ?? 0);
                $quantity = str_replace(',', '.', trim((string) ($input['quantity'] ?? '1')));
                if (!Decimal::isNumeric($quantity) || Decimal::cmp($quantity, '0') <= 0) {
                    throw new FinanceRejected(['quantity' => 'Enter a quantity.']);
                }
                $description = trim((string) ($input['description'] ?? ''));
                $unitCost = '0.0000';
                $unitPrice = $this->money($input['unit_price'] ?? '');
                $unit = 'unit';
                if ($productId > 0) {
                    $product = $this->products->find($productId);
                    if ($product === null) {
                        throw new FinanceRejected(['product_id' => 'That product was not found.']);
                    }
                    $priced = $this->pricing->price($product, [
                        'quantity' => $quantity,
                        'width_mm' => $input['width_mm'] ?? $product['sheet_width_mm'] ?? $product['roll_width_mm'] ?? '',
                        'height_mm' => $input['height_mm'] ?? $product['sheet_height_mm'] ?? '',
                        'length_mm' => $input['length_mm'] ?? '',
                    ], $this->levels->all());
                    if (($priced['errors'] ?? []) !== []) {
                        throw new FinanceRejected(['product_id' => (string) $priced['errors'][0]]);
                    }
                    $sell = '0.00';
                    foreach ($priced['levels'] as $level) {
                        if ((string) $level['code'] === 'Q1') {
                            $sell = (string) $level['selling_price'];
                        }
                    }
                    if ($sell === '0.00' && ($priced['levels'][0]['selling_price'] ?? null) !== null) {
                        $sell = (string) $priced['levels'][0]['selling_price'];
                    }
                    $billable = (string) ($priced['billable_quantity'] ?? $quantity);
                    $unitPrice = Decimal::cmp($billable, '0') > 0
                        ? Decimal::round(Decimal::div($sell, $billable, 8), 4)
                        : Decimal::round($sell, 4);
                    $quantity = Decimal::cmp($billable, '0') > 0 ? $billable : $quantity;
                    $unitCost = Decimal::round((string) $product['cost_price'], 4);
                    $unit = (string) ($product['cost_unit'] ?: 'unit');
                    if ($description === '') {
                        $description = (string) $product['name'];
                    }
                }
                if ($description === '' || $unitPrice === null) {
                    throw new FinanceRejected(['description' => 'Describe the variation line and its selling price.']);
                }
                $this->finance->insertVariationItem([
                    'job_variation_id' => $variationId,
                    'product_id' => $productId > 0 ? $productId : null,
                    'description' => substr($description, 0, 255),
                    'quantity' => Decimal::qty($quantity),
                    'unit' => substr($unit, 0, 20),
                    'unit_cost_snapshot' => $unitCost,
                    'unit_price' => Decimal::round($unitPrice, 4),
                    'line_total' => Decimal::money(Decimal::mul($quantity, $unitPrice)),
                ]);
                $this->retotal($variation);
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function submit(int $variationId, int $userId): array
    {
        return $this->move($variationId, VariationStatus::AwaitingApproval->value, $userId, null, null);
    }

    /**
     * @return array<string, string>
     */
    public function approve(int $variationId, int $userId, string $name, string $method): array
    {
        if (!can('invoices.issue')) {
            return ['_form' => 'You cannot approve a variation.'];
        }
        $name = trim($name);
        if ($name === '') {
            return ['approved_by_name' => 'Record who approved the variation.'];
        }
        try {
            Database::transaction(function () use ($variationId, $userId, $name, $method): void {
                $variation = $this->finance->lockVariation($variationId);
                if ($variation === null || !in_array((string) $variation['status'], ['DRAFT', 'AWAITING_APPROVAL'], true)) {
                    throw new FinanceRejected(['_form' => 'This variation cannot be approved.']);
                }
                if ($this->finance->variationItems($variationId) === []) {
                    throw new FinanceRejected(['_form' => 'Add a line before approving the variation.']);
                }
                $this->retotal($variation);
                $this->finance->approveVariation($variationId, substr($name, 0, 120), substr(strtoupper($method), 0, 20));
                $this->audit->record('job_variation', $variationId, 'VARIATION_APPROVED', null, [
                    'approved_by_name' => $name,
                ], $userId);
            });
        } catch (FinanceRejected $e) {
            return $e->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function decline(int $variationId, int $userId): array
    {
        return $this->move($variationId, VariationStatus::Declined->value, $userId, null, null);
    }

    /**
     * @return array<string, string>
     */
    private function move(int $variationId, string $status, int $userId, ?string $name, ?string $method): array
    {
        if (!can('invoices.create')) {
            return ['_form' => 'You cannot change this variation.'];
        }
        $variation = $this->finance->variation($variationId);
        if ($variation === null) {
            return ['_form' => 'That variation was not found.'];
        }
        $this->finance->saveVariationTotals($variationId, [
            'subtotal' => $variation['subtotal'],
            'vat_amount' => $variation['vat_amount'],
            'total' => $variation['total'],
        ], $status);

        return [];
    }

    /**
     * @param array<string, mixed> $variation
     */
    private function retotal(array $variation): void
    {
        $lines = [];
        foreach ($this->finance->variationItems((int) $variation['id']) as $item) {
            $lines[] = ['line_subtotal' => (string) $item['line_total']];
        }
        $document = FinanceMath::document(
            $lines,
            'NONE',
            '0',
            (string) $variation['vat_mode'],
            (string) $variation['vat_rate']
        );
        $this->finance->saveVariationTotals((int) $variation['id'], [
            'subtotal' => $document['subtotal_after_discount'],
            'vat_amount' => $document['vat_amount'],
            'total' => $document['total'],
        ], (string) $variation['status']);
    }

    private function money(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '' || !Decimal::isNumeric($value) || Decimal::cmp($value, '0') < 0) {
            return null;
        }

        return $value;
    }
}

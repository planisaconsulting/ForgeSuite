<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\PricingMethod;
use App\Domain\ProductType;
use App\Domain\Units;
use App\Domain\WasteMode;
use App\Helpers\Database;
use App\Helpers\Decimal;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SupplierRepository;

/**
 * Saves a catalogue row and, when the cost changes, a price-history row
 * in the same transaction. Historical cost is not overwritten.
 */
final class ProductService
{
    public function __construct(
        private readonly ProductRepository $products = new ProductRepository(),
        private readonly CategoryRepository $categories = new CategoryRepository(),
        private readonly SupplierRepository $suppliers = new SupplierRepository(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, string>, id: int|null}
     */
    public function create(array $input, int $userId): array
    {
        $errors = $this->validate($input, null);
        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }
        $data = $this->data($input, $userId);
        $id = 0;
        Database::transaction(function () use ($data, &$id): void {
            $id = $this->products->insert($data);
            $this->audit->record('product', $id, 'created', null, $this->snapshot($data));
        });

        return ['errors' => [], 'id' => $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function update(int $id, array $input, int $userId): array
    {
        $existing = $this->products->find($id);
        if ($existing === null) {
            return ['_form' => 'That product was not found.'];
        }
        $errors = $this->validate($input, $id);
        if ($errors !== []) {
            return $errors;
        }
        $data = $this->data($input, (int) ($existing['created_by'] ?? $userId));
        $oldCost = (string) $existing['cost_price'];
        $newCost = (string) $data['cost_price'];
        $costChanged = Decimal::cmp($oldCost, $newCost) !== 0;

        Database::transaction(function () use ($id, $data, $existing, $costChanged, $oldCost, $newCost, $userId): void {
            $this->products->update($id, $data);
            if ($costChanged) {
                $this->products->addPriceHistory($id, Decimal::round($oldCost, 4), Decimal::round($newCost, 4), $userId);
                $this->audit->record('product', $id, 'cost_changed', ['cost_price' => $oldCost], ['cost_price' => $newCost], $userId);
            }
            $this->audit->record('product', $id, 'updated', $this->snapshot($existing), $this->snapshot($data), $userId);
        });

        return [];
    }

    public function setActive(int $id, bool $active): bool
    {
        $existing = $this->products->find($id);
        if ($existing === null) {
            return false;
        }
        Database::transaction(function () use ($id, $active, $existing): void {
            $this->products->setActive($id, $active ? 1 : 0);
            $this->audit->record(
                'product',
                $id,
                $active ? 'activated' : 'deactivated',
                ['active' => (int) $existing['active']],
                ['active' => $active ? 1 : 0]
            );
        });

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input, ?int $id): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $sku = trim((string) ($input['sku'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if ($sku === '') {
            $errors['sku'] = 'SKU is required.';
        } elseif ($this->products->skuTaken($sku, $id)) {
            $errors['sku'] = 'That SKU is already used.';
        }
        $categoryId = (int) ($input['category_id'] ?? 0);
        if ($this->categories->find($categoryId) === null) {
            $errors['category_id'] = 'Choose a category.';
        }
        $supplierId = (int) ($input['supplier_id'] ?? 0);
        if ($supplierId > 0 && $this->suppliers->find($supplierId) === null) {
            $errors['supplier_id'] = 'That supplier was not found.';
        }
        $type = strtoupper(trim((string) ($input['product_type'] ?? '')));
        if (!in_array($type, ProductType::values(), true)) {
            $errors['product_type'] = 'Choose a product type.';
        }
        $method = strtoupper(trim((string) ($input['pricing_method'] ?? '')));
        if (PricingMethod::tryFrom($method) === null) {
            $errors['pricing_method'] = 'Choose how this product is priced.';
        }
        $cost = $this->number($input['cost_price'] ?? '');
        if ($cost === null || Decimal::cmp($cost, '0') < 0) {
            $errors['cost_price'] = 'Cost must be zero or greater.';
        }
        $waste = $this->number($input['standard_waste_percent'] ?? '0');
        if ($waste === null || Decimal::cmp($waste, '0') < 0 || Decimal::cmp($waste, '500') > 0) {
            $errors['standard_waste_percent'] = 'Manufacturing waste must be between 0 and 500 percent.';
        }
        $thresholdRaw = trim((string) ($input['waste_threshold_percent'] ?? ''));
        if ($thresholdRaw !== '') {
            $threshold = $this->number($thresholdRaw);
            if ($threshold === null || Decimal::cmp($threshold, '0') < 0 || Decimal::cmp($threshold, '100') > 0) {
                $errors['waste_threshold_percent'] = 'Waste threshold must be between 0 and 100 percent.';
            }
        }
        $policy = strtoupper(trim((string) ($input['default_waste_policy'] ?? 'ACTUAL')));
        if (WasteMode::tryFrom($policy) === null) {
            $errors['default_waste_policy'] = 'Choose a default waste treatment.';
        }
        if ($method === 'SHEET') {
            foreach (['sheet_width_mm' => 'Sheet width', 'sheet_height_mm' => 'Sheet height'] as $field => $label) {
                $size = $this->number($input[$field] ?? '');
                if ($size === null || Decimal::cmp($size, '0') <= 0) {
                    $errors[$field] = $label . ' is required for a sheet product.';
                }
            }
        }
        if ($policy === 'CONSUMED_WIDTH') {
            $roll = $this->number($input['roll_width_mm'] ?? '');
            if ($roll === null || Decimal::cmp($roll, '0') <= 0) {
                $errors['roll_width_mm'] = 'Consumed-width charging needs a roll width.';
            }
        }
        $stock = trim((string) ($input['minimum_stock_level'] ?? ''));
        if ($stock !== '' && ($this->number($stock) === null || Decimal::cmp($this->number($stock) ?? '0', '0') < 0)) {
            $errors['minimum_stock_level'] = 'Minimum stock must be zero or greater.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function data(array $input, ?int $createdBy): array
    {
        $method = strtoupper(trim((string) $input['pricing_method']));
        $supplierId = (int) ($input['supplier_id'] ?? 0);

        return [
            'category_id' => (int) $input['category_id'],
            'supplier_id' => $supplierId > 0 ? $supplierId : null,
            'sku' => trim((string) $input['sku']),
            'name' => trim((string) $input['name']),
            'description' => blank_to_null($input['description'] ?? null),
            'product_type' => strtoupper(trim((string) $input['product_type'])),
            'pricing_method' => $method,
            'cost_price' => Decimal::round((string) $this->number($input['cost_price'] ?? '0'), 4),
            'cost_unit' => Units::forMethod($method),
            'roll_width_mm' => $this->optionalNumber($input['roll_width_mm'] ?? null, 2),
            'sheet_width_mm' => $this->optionalNumber($input['sheet_width_mm'] ?? null, 2),
            'sheet_height_mm' => $this->optionalNumber($input['sheet_height_mm'] ?? null, 2),
            'standard_waste_percent' => Decimal::round((string) $this->number($input['standard_waste_percent'] ?? '0'), 2),
            'waste_threshold_percent' => $this->optionalNumber($input['waste_threshold_percent'] ?? null, 2),
            'default_waste_policy' => strtoupper(trim((string) ($input['default_waste_policy'] ?? 'ACTUAL'))),
            'allow_rotation' => posted_flag($input, 'allow_rotation', 0),
            'allow_nesting' => posted_flag($input, 'allow_nesting', 0),
            'track_stock' => posted_flag($input, 'track_stock', 0),
            'minimum_stock_level' => $this->optionalNumber($input['minimum_stock_level'] ?? null, 4),
            'supplier_code' => blank_to_null($input['supplier_code'] ?? null),
            'active' => posted_flag($input, 'active', 1),
            'notes' => blank_to_null($input['notes'] ?? null),
            'created_by' => $createdBy,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function snapshot(array $row): array
    {
        $keys = [
            'category_id', 'supplier_id', 'sku', 'name', 'product_type', 'pricing_method',
            'cost_price', 'cost_unit', 'roll_width_mm', 'sheet_width_mm', 'sheet_height_mm',
            'standard_waste_percent', 'waste_threshold_percent', 'default_waste_policy',
            'allow_rotation', 'allow_nesting', 'track_stock', 'minimum_stock_level',
            'supplier_code', 'active',
        ];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $row[$key] ?? null;
        }

        return $out;
    }

    private function number(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ($value === '' || !Decimal::isNumeric($value)) {
            return null;
        }

        return $value;
    }

    private function optionalNumber(mixed $value, int $scale): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $number = $this->number($value);

        return $number === null ? null : Decimal::round($number, $scale);
    }
}

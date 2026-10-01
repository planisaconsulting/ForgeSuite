<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\InventoryItemStatus;
use App\Domain\MovementType;
use App\Helpers\Decimal;
use App\Helpers\View;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PurchasingRepository;
use App\Services\StockMovementService;
use App\Services\StockValuation;

final class InventoryController
{
    public function index(): void
    {
        $inventory = new InventoryRepository();
        $term = trim((string) ($_GET['q'] ?? ''));
        $rows = $inventory->balances();
        if ($term !== '') {
            $needle = strtolower($term);
            $rows = array_values(array_filter($rows, static function (array $row) use ($needle): bool {
                return str_contains(strtolower((string) $row['product_name'] . ' ' . $row['sku']), $needle);
            }));
        }
        $code = $inventory->itemByCode($term);
        $low = $this->lowStock($inventory);
        $out = array_values(array_filter($low, static fn (array $row): bool => Decimal::cmp((string) $row['available'], '0') <= 0));
        $purchasing = new PurchasingRepository();
        View::render('inventory/index', [
            'title' => 'Inventory',
            'activeNav' => 'inventory',
            'rows' => $rows,
            'term' => $term,
            'direct' => $code,
            'locations' => $inventory->locations(true),
            'products' => (new ProductRepository())->search('', 'active', null, 300),
            'low' => $low,
            'out' => $out,
            'value' => can('inventory.view_cost') ? $inventory->stockValue() : null,
            'showCost' => can('inventory.view_cost'),
            'desk' => $purchasing->desk(),
            'receipts' => $purchasing->recentReceipts(6),
            'high' => can('inventory.view_cost') ? $inventory->highValueItems(6) : [],
            'used' => $inventory->mostUsed(6),
            'waste' => $inventory->wasteByProduct(),
            'adjustments' => array_merge(
                $inventory->movements(['movement_type' => 'ADJUSTMENT_IN'], 4),
                $inventory->movements(['movement_type' => 'ADJUSTMENT_OUT'], 4)
            ),
            'wastage' => $inventory->movements(['movement_type' => 'WASTE'], 6),
        ]);
    }

    public function movements(): void
    {
        $filters = $this->movementFilters();
        $rows = (new InventoryRepository())->movements($filters);
        if (($_GET['export'] ?? '') === 'csv') {
            $this->csv($rows);
        }
        View::render('inventory/movements', [
            'title' => 'Stock movements',
            'activeNav' => 'movements',
            'rows' => $rows,
            'filters' => $filters,
            'locations' => (new InventoryRepository())->locations(),
            'types' => MovementType::cases(),
            'showCost' => can('inventory.view_cost'),
        ]);
    }

    public function item(string $id): void
    {
        $row = (new InventoryRepository())->item(route_id($id));
        if ($row === null) {
            abort_not_found('That inventory item was not found.');
        }
        $this->renderItem($row);
    }

    public function code(string $code): void
    {
        $row = (new InventoryRepository())->itemByCode(rawurldecode($code));
        if ($row === null) {
            abort_not_found('That inventory code was not found.');
        }
        $this->renderItem($row);
    }

    public function label(string $id): void
    {
        $row = (new InventoryRepository())->item(route_id($id));
        if ($row === null) {
            abort_not_found('That inventory item was not found.');
        }
        View::render('inventory/label', [
            'title' => (string) $row['inventory_code'],
            'activeNav' => 'inventory',
            'item' => $row,
            'layout' => 'print',
        ]);
    }

    public function offcuts(): void
    {
        $productId = (int) ($_GET['product_id'] ?? 0);
        $width = trim((string) ($_GET['min_width'] ?? ''));
        $height = trim((string) ($_GET['min_height'] ?? ''));
        $rows = [];
        if ($productId > 0 && Decimal::isNumeric($width) && Decimal::isNumeric($height)) {
            $rows = (new InventoryRepository())->offcuts($productId, $width, $height, isset($_GET['rotate']));
        } elseif ($productId > 0) {
            $rows = (new InventoryRepository())->searchItems(['product_id' => $productId, 'type' => 'OFFCUT', 'status' => 'AVAILABLE']);
        }
        View::render('inventory/offcuts', [
            'title' => 'Offcuts',
            'activeNav' => 'offcuts',
            'rows' => $rows,
            'products' => (new ProductRepository())->search('', 'active', null, 300),
            'productId' => $productId,
            'minWidth' => $width,
            'minHeight' => $height,
            'locations' => (new InventoryRepository())->locations(true),
        ]);
    }

    public function counts(): void
    {
        View::render('inventory/counts', [
            'title' => 'Stock counts',
            'activeNav' => 'counts',
            'rows' => (new InventoryRepository())->counts(),
            'locations' => (new InventoryRepository())->locations(true),
        ]);
    }

    public function count(string $id): void
    {
        $count = (new InventoryRepository())->count(route_id($id));
        if ($count === null) {
            abort_not_found('That stock count was not found.');
        }
        $lines = (new InventoryRepository())->countItems((int) $count['id']);
        foreach ($lines as &$line) {
            $physical = $line['physical_quantity'];
            $line['variance'] = $physical === null ? null : Decimal::sub((string) $physical, (string) $line['system_quantity'], 4);
            $line['value_variance'] = $line['variance'] === null
                ? null
                : Decimal::money(Decimal::mul((string) $line['variance'], (string) $line['unit_cost_snapshot']));
        }
        unset($line);
        View::render('inventory/count', [
            'title' => (string) $count['reference_code'],
            'activeNav' => 'counts',
            'count' => $count,
            'lines' => $lines,
            'showCost' => can('inventory.view_cost'),
        ]);
    }

    public function requirements(): void
    {
        $rows = (new PurchasingRepository())->requirementBoard();
        foreach ($rows as &$row) {
            $required = (string) ($row['final_required_quantity'] ?: $row['required_quantity']);
            $covered = Decimal::add((string) $row['reserved_qty'], (string) $row['issued_qty'], 4);
            $short = Decimal::sub($required, $covered, 4);
            $row['required_qty'] = $required;
            $row['shortage'] = Decimal::cmp($short, '0') > 0 ? $short : '0.0000';
            $row['customer_label'] = customer_label($row);
        }
        unset($row);
        View::render('inventory/requirements', [
            'title' => 'Material requirements',
            'activeNav' => 'requirements',
            'rows' => $rows,
        ]);
    }

    public function opening(): void
    {
        $this->finish((new StockMovementService())->opening($_POST, $this->userId()), '/inventory', 'Stock recorded.');
    }

    public function adjust(): void
    {
        $this->finish((new StockMovementService())->adjust($_POST, $this->userId()), '/inventory', 'Adjustment recorded.');
    }

    public function transfer(): void
    {
        $errors = (new StockMovementService())->transfer($_POST, $this->userId());
        $this->done($errors, '/inventory', 'Stock transferred.');
    }

    public function offcut(): void
    {
        $result = (new StockMovementService())->createOffcut($_POST, $this->userId());
        $target = $result['id'] !== null ? '/inventory/items/' . $result['id'] : '/inventory/offcuts';
        $this->finish($result, $target, 'Offcut created. Dimensions are what the operator measured.');
    }

    public function supplierReturn(): void
    {
        $this->finish((new StockMovementService())->supplierReturn($_POST, $this->userId()), '/inventory/movements', 'Supplier return recorded.');
    }

    public function startCount(): void
    {
        $result = (new StockMovementService())->startCount((int) ($_POST['stock_location_id'] ?? 0), $this->userId());
        $target = $result['id'] !== null ? '/inventory/counts/' . $result['id'] : '/inventory/counts';
        $this->finish($result, $target, 'Stock count started. Enter the physical quantities, then approve.');
    }

    public function saveCount(string $id): void
    {
        $errors = (new StockMovementService())->saveCount(route_id($id), $_POST, $this->userId());
        $this->done($errors, '/inventory/counts/' . route_id($id), 'Physical quantities saved.');
    }

    public function approveCount(string $id): void
    {
        $errors = (new StockMovementService())->approveCount(route_id($id), $this->userId());
        $this->done($errors, '/inventory/counts/' . route_id($id), 'Count approved. Variances are new movements.');
    }

    public function saveLocation(): void
    {
        if (!can('settings.manage') && !can('inventory.adjust')) {
            deny_access('You cannot add a stock location.');
        }
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['code'] ?? '')) ?? '');
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($code === '' || $name === '') {
            flash('error', 'A location needs a code and a name.');
            redirect('/inventory');
        }
        (new InventoryRepository())->insertLocation([
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 120),
            'description' => blank_to_null($_POST['description'] ?? null),
            'active' => 1,
        ]);
        flash('success', 'Location added.');
        redirect('/inventory');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function renderItem(array $row): void
    {
        $history = (new InventoryRepository())->movements(['item' => (int) $row['id']]);
        $value = Decimal::money(Decimal::mul((string) $row['remaining_quantity'], (string) $row['unit_cost']));
        View::render('inventory/item', [
            'title' => (string) $row['inventory_code'],
            'activeNav' => 'inventory',
            'item' => $row,
            'history' => $history,
            'value' => $value,
            'ageDays' => $row['received_date'] !== null ? (int) (new \DateTimeImmutable((string) $row['received_date']))->diff(new \DateTimeImmutable('today'))->days : null,
            'showCost' => can('inventory.view_cost'),
            'statuses' => InventoryItemStatus::cases(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function movementFilters(): array
    {
        return [
            'product_id' => (int) ($_GET['product_id'] ?? 0),
            'location_id' => (int) ($_GET['location_id'] ?? 0),
            'movement_type' => trim((string) ($_GET['movement_type'] ?? '')),
            'job_id' => (int) ($_GET['job_id'] ?? 0),
            'purchase_order_id' => (int) ($_GET['purchase_order_id'] ?? 0),
            'supplier_id' => (int) ($_GET['supplier_id'] ?? 0),
            'user_id' => (int) ($_GET['user_id'] ?? 0),
            'from' => trim((string) ($_GET['from'] ?? '')),
            'to' => trim((string) ($_GET['to'] ?? '')),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function csv(array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="stock-movements.csv"');
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            exit;
        }
        fputcsv($out, ['date', 'product', 'sku', 'location', 'type', 'quantity', 'unit', 'job', 'code', 'reason']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['movement_date'],
                $this->csvCell((string) $row['product_name']),
                $this->csvCell((string) $row['sku']),
                $this->csvCell((string) $row['location_name']),
                $row['movement_type'],
                $row['quantity'],
                $row['unit'],
                $row['job_number'] ?? '',
                $row['inventory_code'] ?? '',
                $this->csvCell((string) ($row['reason'] ?? '')),
            ]);
        }
        fclose($out);
        exit;
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lowStock(InventoryRepository $inventory): array
    {
        $sums = [];
        foreach ($inventory->balances(500) as $row) {
            $id = (int) $row['product_id'];
            if (!isset($sums[$id])) {
                $sums[$id] = $row;
                $sums[$id]['on_hand'] = '0.0000';
            }
            $sums[$id]['on_hand'] = Decimal::add((string) $sums[$id]['on_hand'], (string) $row['on_hand'], 4);
        }
        $low = [];
        foreach ($inventory->trackedProducts() as $product) {
            if ($product['minimum_stock_level'] === null) {
                continue;
            }
            $onHand = (string) ($sums[(int) $product['id']]['on_hand'] ?? '0');
            $reserved = $inventory->reserved((int) $product['id']);
            $available = StockValuation::available($onHand, $reserved);
            if (Decimal::cmp($available, (string) $product['minimum_stock_level']) <= 0) {
                $product['available'] = $available;
                $product['on_hand'] = $onHand;
                $low[] = $product;
            }
        }

        return $low;
    }

    /**
     * @param array{errors: array<string, string>, id: int|null} $result
     */
    private function finish(array $result, string $path, string $message): void
    {
        $this->done($result['errors'], $path, $message);
    }

    /**
     * @param array<string, string> $errors
     */
    private function done(array $errors, string $path, string $message): void
    {
        if ($errors !== []) {
            flash('error', (string) ($errors['_form'] ?? reset($errors)));
        } else {
            flash('success', $message);
        }
        redirect($path);
    }

    private function userId(): int
    {
        return (int) auth_user()['id'];
    }
}

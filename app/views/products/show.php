<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1"><?= e((string) $product['sku']) ?></p>
        <h1><?= e((string) $product['name']) ?></h1>
        <p class="sf-muted mb-0"><?= (int) $product['active'] === 1 ? 'Active' : 'Inactive' ?> · <?= e(enum_label(App\Domain\ProductType::class, (string) $product['product_type'])) ?></p>
    </div>
    <?php if ($canManage): ?>
        <div class="sf-action-row">
            <a class="btn btn-sf" href="<?= e(url('/products/' . $product['id'] . '/edit')) ?>">Edit</a>
            <form method="post" action="<?= e(url('/products/' . $product['id'] . '/active')) ?>" onsubmit="return confirm('<?= (int) $product['active'] === 1 ? 'Deactivate this product? The record is kept.' : 'Activate this product?' ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="active" value="<?= (int) $product['active'] === 1 ? '0' : '1' ?>">
                <button class="btn btn-outline-light" type="submit"><?= (int) $product['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Product</h2></div>
            <dl class="sf-dl">
                <div><dt>Category</dt><dd><?= e((string) $product['category_name']) ?></dd></div>
                <div><dt>Pricing method</dt><dd><?= e(enum_label(App\Domain\PricingMethod::class, (string) $product['pricing_method'])) ?></dd></div>
                <div><dt>Cost unit</dt><dd><?= e(App\Domain\Units::label((string) $product['cost_unit'])) ?></dd></div>
                <div><dt>Current cost</dt><dd><?= e(money((string) $product['cost_price'])) ?></dd></div>
                <div><dt>Supplier</dt><dd><?= e((string) ($product['supplier_name'] ?? '—')) ?></dd></div>
                <div><dt>Supplier code</dt><dd><?= e((string) ($product['supplier_code'] ?? '—')) ?></dd></div>
                <div><dt>Roll width</dt><dd><?= $product['roll_width_mm'] === null ? '—' : e((string) $product['roll_width_mm']) . ' mm' ?></dd></div>
                <div><dt>Sheet size</dt><dd><?= $product['sheet_width_mm'] === null ? '—' : e((string) $product['sheet_width_mm'] . ' × ' . $product['sheet_height_mm'] . ' mm') ?></dd></div>
                <div><dt>Manufacturing waste</dt><dd><?= e((string) $product['standard_waste_percent']) ?>%</dd></div>
                <div><dt>Waste threshold</dt><dd><?= $product['waste_threshold_percent'] === null ? '—' : e((string) $product['waste_threshold_percent']) . '%' ?></dd></div>
                <div><dt>Default treatment</dt><dd><?= e(enum_label(App\Domain\WasteMode::class, (string) $product['default_waste_policy'])) ?></dd></div>
                <div><dt>Rotation / nesting</dt><dd><?= (int) $product['allow_rotation'] === 1 ? 'Rotation allowed' : 'No rotation' ?> · <?= (int) $product['allow_nesting'] === 1 ? 'Nesting allowed later' : 'No nesting flag' ?></dd></div>
                <div><dt>Stock flag</dt><dd><?= (int) $product['track_stock'] === 1 ? 'Flagged' : 'Not flagged' ?><?= $product['minimum_stock_level'] !== null ? ' · minimum ' . e((string) $product['minimum_stock_level']) : '' ?></dd></div>
            </dl>
            <?php if (!empty($product['description'])): ?><div class="px-3 pb-3"><?= nl_text((string) $product['description']) ?></div><?php endif; ?>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Price history</h2></div>
            <?php if ($history === []): ?>
                <div class="sf-empty"><p>No cost change has been recorded. The current cost is the one loaded with the product.</p></div>
            <?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($history as $row): ?>
                        <li>
                            <strong><?= e(money((string) $row['old_cost'])) ?> → <?= e(money((string) $row['new_cost'])) ?></strong>
                            <small><?= e((string) ($row['changed_by_name'] ?? 'Unknown')) ?> · <?= e(format_datetime((string) $row['changed_at'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php if ($stock !== null): ?>
<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Stock</h2></div>
    <div class="row g-3 p-3">
        <div class="col-6 col-md-3"><p class="sf-kicker">On hand</p><p class="mb-0"><?= e($stock['on_hand']) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Reserved</p><p class="mb-0"><?= e($stock['reserved']) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Available</p><p class="mb-0"><?= e($stock['available']) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Minimum / reorder</p><p class="mb-0"><?= e((string) ($product['minimum_stock_level'] ?? '—')) ?> / <?= e((string) ($product['reorder_level'] ?? '—')) ?></p></div>
        <?php if ($showCost): ?><div class="col-6 col-md-3"><p class="sf-kicker">Catalogue cost</p><p class="mb-0"><?= e(money((string) $product['cost_price'])) ?></p></div><div class="col-6 col-md-3"><p class="sf-kicker">Average cost</p><p class="mb-0"><?= $product['average_cost'] === null ? '—' : e(money((string) $product['average_cost'])) ?></p></div><?php endif; ?>
    </div>
    <?php if ($stock['items'] !== []): ?>
        <ul class="sf-feed"><?php foreach ($stock['items'] as $item): ?><li><a href="<?= e(url('/inventory/items/' . $item['id'])) ?>"><?= e((string) $item['inventory_code']) ?></a><small><?= e((string) $item['status']) ?> · <?= e((string) $item['remaining_quantity']) ?></small></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<?php if ($stock['suppliers'] !== [] || $supplierOptions !== []): ?>
<section class="sf-panel mt-3"><div class="sf-panel-head"><h2>Supplier prices</h2></div>
    <ul class="sf-feed"><?php foreach ($stock['suppliers'] as $link): ?><li><?= e((string) $link['supplier_name']) ?><small><?= e((string) ($link['supplier_sku'] ?? '')) ?> · <?= e(money((string) $link['cost_price'])) ?></small></li><?php endforeach; ?></ul>
    <?php if ($supplierOptions !== []): ?>
        <form class="p-3 row g-2" method="post" action="<?= e(url('/purchasing/supplier-prices')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
            <div class="col-md-4"><select class="form-select" name="supplier_id" required><?php foreach ($supplierOptions as $supplier): ?><option value="<?= e((string) $supplier['id']) ?>"><?= e((string) $supplier['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><input class="form-control" name="cost_price" placeholder="Cost" required></div>
            <div class="col-md-3"><input class="form-control" name="supplier_sku" placeholder="Supplier SKU"></div>
            <div class="col-md-2"><button class="btn btn-sf w-100" type="submit">Save</button></div>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php if ($stock['purchases'] !== []): ?>
<section class="sf-panel mt-3"><div class="sf-panel-head"><h2>Purchase history</h2></div>
    <ul class="sf-feed"><?php foreach ($stock['purchases'] as $po): ?><li><?= e((string) $po['po_number']) ?> · <?= e((string) $po['supplier_name']) ?><small><?= e((string) $po['ordered_quantity']) ?> ordered · <?= e((string) $po['received_quantity']) ?> received</small></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<?php endif; ?>
<?php if ($audit !== []): ?>
    <section class="sf-panel mt-3">
        <div class="sf-panel-head"><h2>Record history</h2></div>
        <?php require base_path('app/views/partials/audit_list.php'); ?>
    </section>
<?php endif; ?>

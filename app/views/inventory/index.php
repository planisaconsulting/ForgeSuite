<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <div>
        <h1>Inventory</h1>
        <p class="sf-muted mb-0">On hand is the sum of stock movements. Available is on hand minus open reservations.</p>
    </div>
</div>
<div class="row g-3 mb-3">
    <?php if ($value !== null): ?>
        <div class="col-12 col-md-4"><article class="sf-stat"><p class="sf-kicker">Stock value</p><p class="sf-stat-value"><?= e(money($value)) ?></p><p class="sf-muted mb-0">Sum of movement costs. Offcut value follows the valuation set when the offcut was created.</p></article></div>
    <?php endif; ?>
    <div class="col-6 col-md-2"><article class="sf-stat"><p class="sf-kicker">Low stock</p><p class="sf-stat-value"><?= e((string) count($low)) ?></p></article></div>
    <div class="col-6 col-md-2"><article class="sf-stat"><p class="sf-kicker">Out of stock</p><p class="sf-stat-value"><?= e((string) count($out)) ?></p></article></div>
    <div class="col-6 col-md-2"><article class="sf-stat"><p class="sf-kicker">Open orders</p><p class="sf-stat-value"><?= e((string) ((int) $desk['ordered'] + (int) $desk['partial'] + (int) $desk['pending'])) ?></p></article></div>
    <div class="col-6 col-md-2"><article class="sf-stat"><p class="sf-kicker">Overdue orders</p><p class="sf-stat-value"><?= e((string) $desk['overdue']) ?></p></article></div>
</div>
<form class="sf-filters mb-3" method="get" action="<?= e(url('/inventory')) ?>">
    <input class="form-control form-control-lg" type="search" name="q" value="<?= e($term) ?>" placeholder="Product, SKU, or inventory code">
    <button class="btn btn-sf btn-lg" type="submit">Search</button>
</form>
<?php if ($direct !== null): ?>
    <p><a class="btn btn-sf" href="<?= e(url('/inventory/items/' . $direct['id'])) ?>">Open <?= e((string) $direct['inventory_code']) ?></a></p>
<?php endif; ?>
<?php if ($low !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Low stock</h2></div>
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <thead><tr><th>Product</th><th>Available</th><th>Minimum</th><th>Reorder</th></tr></thead>
            <tbody>
            <?php foreach ($low as $row): ?>
                <tr>
                    <td><a href="<?= e(url('/products/' . $row['id'])) ?>"><?= e((string) $row['name']) ?></a></td>
                    <td><?= e((string) $row['available']) ?></td>
                    <td><?= e((string) $row['minimum_stock_level']) ?></td>
                    <td><?= e((string) ($row['reorder_level'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Balances</h2></div>
    <?php if ($rows === []): ?>
        <div class="sf-empty"><p>No movements yet. Record an opening balance for a tracked product.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table sf-table mb-0">
                <thead><tr><th>Product</th><th>Location</th><th>On hand</th><th>Offcut pieces</th><?php if ($showCost): ?><th>Movement value</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= e(url('/products/' . $row['product_id'])) ?>"><?= e((string) $row['product_name']) ?></a><br><small><?= e((string) $row['sku']) ?></small></td>
                        <td><?= e((string) $row['location_name']) ?></td>
                        <td><?= e((string) $row['on_hand']) ?> <?= e((string) $row['cost_unit']) ?></td>
                        <td><?= e((string) $row['offcut_qty']) ?></td>
                        <?php if ($showCost): ?><td><?= e(money((string) $row['movement_value'])) ?></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php if (can('inventory.receive')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Opening balance</h2></div>
    <form class="p-3" method="post" action="<?= e(url('/inventory/opening')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Product</label>
                <select class="form-select form-select-lg" name="product_id" required>
                    <option value="">Choose</option>
                    <?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Location</label>
                <select class="form-select form-select-lg" name="stock_location_id" required>
                    <?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">Quantity</label><input class="form-control form-control-lg" name="quantity" inputmode="decimal" required></div>
            <div class="col-md-3"><label class="form-label">Unit cost</label><input class="form-control form-control-lg" name="unit_cost" inputmode="decimal" placeholder="Uses product cost if blank"></div>
            <div class="col-md-3"><label class="form-label">Roll length (m)</label><input class="form-control form-control-lg" name="length_m" inputmode="decimal" placeholder="Rolls only"></div>
            <div class="col-md-3"><label class="form-label">Width (mm)</label><input class="form-control form-control-lg" name="width_mm" inputmode="decimal"></div>
            <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Record opening balance</button></div>
        </div>
    </form>
</section>
<?php endif; ?>
<div class="row g-3 mb-3">
    <div class="col-md-4"><section class="sf-panel h-100"><div class="sf-panel-head"><h2>Most used</h2></div>
        <?php if ($used === []): ?><div class="sf-empty"><p>No job consumption yet.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($used as $row): ?><li><?= e((string) $row['name']) ?><small><?= e((string) $row['used_qty']) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
    </section></div>
    <div class="col-md-4"><section class="sf-panel h-100"><div class="sf-panel-head"><h2>Recent wastage</h2></div>
        <?php if ($wastage === []): ?><div class="sf-empty"><p>No waste movements yet.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($wastage as $row): ?><li><?= e((string) $row['product_name']) ?><small><?= e((string) $row['quantity']) ?> <?= e((string) $row['unit']) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
    </section></div>
    <div class="col-md-4"><section class="sf-panel h-100"><div class="sf-panel-head"><h2>Recent adjustments</h2></div>
        <?php if ($adjustments === []): ?><div class="sf-empty"><p>No adjustments yet.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($adjustments as $row): ?><li><?= e((string) $row['product_name']) ?><small><?= e((string) $row['movement_type']) ?> <?= e((string) $row['quantity']) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
    </section></div>
</div>
<?php if ($showCost && $high !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>High-value items</h2></div>
    <ul class="sf-feed"><?php foreach ($high as $row): ?><li><a href="<?= e(url('/inventory/items/' . $row['id'])) ?>"><?= e((string) $row['inventory_code']) ?></a><small><?= e((string) $row['product_name']) ?> · <?= e(money((string) $row['line_value'])) ?></small></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<?php if ($waste !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Waste by product</h2></div>
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <thead><tr><th>Product</th><th>Production</th><th>Waste</th><th>Rate</th><?php if ($showCost): ?><th>Waste value</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($waste as $row):
                $rate = \App\Services\StockValuation::wasteRate((string) $row['production_qty'], (string) $row['waste_qty']);
            ?>
                <tr>
                    <td><?= e((string) $row['name']) ?></td>
                    <td><?= e((string) $row['production_qty']) ?></td>
                    <td><?= e((string) $row['waste_qty']) ?></td>
                    <td><?= $rate === null ? '—' : e($rate . '%') ?></td>
                    <?php if ($showCost): ?><td><?= e(money((string) $row['waste_value'])) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="sf-muted px-3 py-2 mb-0">Waste rate is waste divided by production plus waste. A product with no usage of either kind is left blank.</p>
</section>
<?php endif; ?>
<?php if ($receipts !== []): ?>
<section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Recent receipts</h2></div>
    <ul class="sf-feed"><?php foreach ($receipts as $row): ?><li><a href="<?= e(url('/purchasing/orders/' . $row['purchase_order_id'])) ?>"><?= e((string) $row['grn_number']) ?></a><small><?= e((string) $row['supplier_name']) ?></small></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<?php if (can('inventory.adjust') || can('settings.manage')): ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Add a location</h2></div>
    <form class="p-3 row g-3" method="post" action="<?= e(url('/inventory/locations')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-3"><input class="form-control form-control-lg" name="code" placeholder="Code" required></div>
        <div class="col-md-5"><input class="form-control form-control-lg" name="name" placeholder="Name" required></div>
        <div class="col-md-4"><button class="btn btn-outline-light btn-lg w-100" type="submit">Add location</button></div>
    </form>
</section>
<?php endif; ?>

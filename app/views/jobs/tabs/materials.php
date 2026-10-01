<?php
$coverage = $coverage ?? [];
$locations = $locations ?? [];
$openItems = $openItems ?? [];
$reservations = $reservations ?? [];
?>
<?php if (can('materials.record_usage')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Record material</h2></div>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/material')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="product_id">Product</label>
                <select class="form-select" id="product_id" name="product_id" required>
                    <option value="">Choose</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="usage_type">Usage</label>
                <select class="form-select" id="usage_type" name="usage_type">
                    <?php foreach ($usageTypes as $type): ?>
                        <option value="<?= e($type->value) ?>"><?= e($type->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="quantity">Quantity</label>
                <input class="form-control" id="quantity" name="quantity" inputmode="decimal" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="reason">Reason</label>
                <select class="form-select" id="reason" name="reason">
                    <?php foreach ($reasons as $reason): ?>
                        <option value="<?= e($reason->value) ?>"><?= e($reason->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="job_item_id">Item</label>
                <select class="form-select" id="job_item_id" name="job_item_id">
                    <option value="">Whole job</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= e((string) $item['id']) ?>"><?= e((string) $item['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="notes">Notes</label>
                <input class="form-control" id="notes" name="notes">
            </div>
            <?php if (!empty($locations)): ?>
                <div class="col-md-4">
                    <label class="form-label" for="stock_location_id">Location</label>
                    <select class="form-select form-select-lg" id="stock_location_id" name="stock_location_id">
                        <option value="">Not tracked / choose later</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="inventory_item_id">Roll, sheet, or offcut</label>
                    <select class="form-select form-select-lg" id="inventory_item_id" name="inventory_item_id">
                        <option value="">Bulk quantity</option>
                        <?php foreach ($openItems as $piece): ?>
                            <option value="<?= e((string) $piece['id']) ?>"><?= e((string) $piece['inventory_code']) ?> · <?= e((string) $piece['product_name']) ?> · <?= e((string) $piece['remaining_quantity']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="reservation_id">Reservation</label>
                    <select class="form-select form-select-lg" id="reservation_id" name="reservation_id">
                        <option value="">None</option>
                        <?php foreach ($reservations as $reservation): if ((string) $reservation['status'] !== 'RESERVED') continue; ?>
                            <option value="<?= e((string) $reservation['id']) ?>"><?= e((string) $reservation['product_name']) ?> · <?= e((string) $reservation['quantity']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <p class="sf-muted mt-2 mb-0">Physical quantity is what leaves the roll, sheet, or bin. It is separate from the customer billable area on the quotation. The cost is snapshotted from the inventory item or the product. The form cannot set that cost. Tracked products also write a stock movement.</p>
        <div class="sf-form-actions d-flex gap-2">
            <button class="btn btn-sf btn-lg" type="submit">Record</button>
            <button class="btn btn-outline-light btn-lg" type="submit" onclick="document.getElementById('usage_type').value='WASTE'">Record waste</button>
        </div>
    </form>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Add a requirement</h2></div>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/requirements')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <select class="form-select" name="product_id" required>
                    <option value="">Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" name="quantity" placeholder="Quantity" inputmode="decimal" required></div>
            <div class="col-md-3"><button class="btn btn-outline-light w-100" type="submit">Add requirement</button></div>
        </div>
    </form>
</section>
<?php endif; ?>
<?php if (!empty($locations) && (can('inventory.consume') || can('materials.record_usage'))): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Reserve stock</h2></div>
    <form class="p-3 row g-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/reserve')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-4"><select class="form-select form-select-lg" name="product_id" required><option value="">Product</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><select class="form-select form-select-lg" name="stock_location_id"><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><input class="form-control form-control-lg" name="quantity" placeholder="Qty" required></div>
        <div class="col-md-3"><button class="btn btn-sf btn-lg w-100" type="submit">Reserve</button></div>
    </form>
    <?php foreach ($reservations as $reservation): if ((string) $reservation['status'] !== 'RESERVED') continue; ?>
        <form class="px-3 pb-2" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/reservations/' . $reservation['id'] . '/release')) ?>">
            <?= csrf_field() ?>
            <?= e((string) $reservation['product_name']) ?> reserved <?= e((string) $reservation['quantity']) ?>
            <button class="btn btn-sm btn-outline-light" type="submit">Release</button>
        </form>
    <?php endforeach; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Return unused material</h2></div>
    <form class="p-3 row g-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/stock-return')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-4"><select class="form-select form-select-lg" name="product_id" required><option value="">Product</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><select class="form-select form-select-lg" name="stock_location_id"><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><input class="form-control form-control-lg" name="quantity" placeholder="Qty" required></div>
        <div class="col-md-3"><button class="btn btn-outline-light btn-lg w-100" type="submit">Return to stock</button></div>
    </form>
</section>
<form class="mb-3" method="post" action="<?= e(url('/purchasing/requests')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="job_id" value="<?= e((string) $job['id']) ?>">
    <div class="sf-panel"><div class="sf-panel-head"><h2>Request purchase</h2></div>
        <div class="p-3 row g-3">
            <div class="col-md-5"><select class="form-select form-select-lg" name="product_id" required><option value="">Product</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><input class="form-control form-control-lg" name="quantity" placeholder="Shortage qty" required></div>
            <div class="col-md-4"><button class="btn btn-outline-light btn-lg w-100" type="submit">Create purchase request</button></div>
        </div>
    </div>
</form>
<?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Quoted / estimated material</h2></div>
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <thead><tr><th>Product</th><th>Estimated</th><th>Reserved</th><th>Issued</th><th>Shortage</th><th>Source</th></tr></thead>
            <tbody>
                <?php if ($requirements === []): ?><tr><td colspan="6">No requirements yet.</td></tr><?php endif; ?>
                <?php foreach ($requirements as $row): ?>
                    <?php $cover = $coverage[(int) ($row['product_id'] ?? 0)] ?? null; ?>
                    <tr>
                        <td><?= e((string) ($row['product_name'] ?? 'Material')) ?></td>
                        <td><?= e((string) $row['final_required_quantity']) ?> <?= e((string) $row['unit']) ?></td>
                        <td><?= e((string) ($cover['reserved'] ?? '—')) ?></td>
                        <td><?= e((string) ($cover['issued'] ?? '—')) ?></td>
                        <td><?= e((string) ($cover['shortage'] ?? '—')) ?></td>
                        <td><?= e(enum_label(\App\Domain\MaterialSource::class, (string) $row['source'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if (!empty($stockHints)): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Possible stock</h2></div>
    <div class="p-3">
        <p class="sf-muted">These are candidates. Nothing is reserved until you confirm.</p>
        <?php foreach ($stockHints as $productId => $hint): ?>
            <p><strong>Product <?= e((string) $productId) ?></strong> required <?= e((string) $hint['required']) ?> · available <?= e((string) $hint['available']) ?> · reserved <?= e((string) $hint['reserved']) ?> · shortage <?= e((string) $hint['shortage']) ?></p>
            <?php if ($hint['offcuts'] !== []): ?><p>Suitable offcut available: <?php foreach (array_slice($hint['offcuts'], 0, 3) as $offcut): ?><?= e((string) $offcut['inventory_code']) ?> <?= e((string) ($offcut['width_mm'] ?? '')) ?>×<?= e((string) ($offcut['height_mm'] ?? '')) ?> <?php endforeach; ?></p><?php endif; ?>
            <?php if ($hint['rolls'] !== []): ?><p>Rolls: <?php foreach (array_slice($hint['rolls'], 0, 3) as $roll): ?><?= e((string) $roll['inventory_code']) ?> remaining <?= e((string) $roll['remaining_quantity']) ?> at <?= e((string) ($roll['location_name'] ?? '')) ?> <?php endforeach; ?></p><?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Actual usage</h2></div>
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <thead><tr><th>Product</th><th>Type</th><th>Qty</th><th>Reason</th><?php if (can('costing.view')): ?><th>Cost</th><?php endif; ?></tr></thead>
            <tbody>
                <?php if ($usage === []): ?><tr><td colspan="5">Nothing recorded yet.</td></tr><?php endif; ?>
                <?php foreach ($usage as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['product_name'] ?? 'Material')) ?></td>
                        <td><?= e(enum_label(\App\Domain\MaterialUsageType::class, (string) $row['usage_type'])) ?></td>
                        <td><?= e((string) $row['quantity']) ?> <?= e((string) $row['unit']) ?></td>
                        <td><?= e(enum_label(\App\Domain\WasteReason::class, (string) ($row['reason'] ?? ''))) ?></td>
                        <?php if (can('costing.view')): ?><td><?= e(money((string) $row['total_cost'])) ?></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($variance !== []): ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Material variance</h2></div>
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <thead><tr><th>Material</th><th>Estimated</th><th>Production</th><th>Waste</th><th>Actual</th><th>Variance</th></tr></thead>
            <tbody>
                <?php foreach ($variance as $row): ?>
                    <tr>
                        <td><?= e((string) $row['name']) ?></td>
                        <td><?= e((string) $row['estimated']) ?> <?= e((string) $row['unit']) ?></td>
                        <td><?= e((string) $row['production']) ?></td>
                        <td><?= e((string) $row['waste']) ?></td>
                        <td><?= e((string) $row['actual']) ?></td>
                        <td><?= e((string) $row['variance']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>

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
        </div>
        <p class="sf-muted mt-2 mb-0">The product cost is snapshotted when you record this. The form cannot set the cost. Stock quantities are not changed in this phase.</p>
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
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Quoted / estimated material</h2></div>
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <thead><tr><th>Product</th><th>Estimated</th><th>Unit</th><th>Source</th></tr></thead>
            <tbody>
                <?php if ($requirements === []): ?><tr><td colspan="4">No requirements yet.</td></tr><?php endif; ?>
                <?php foreach ($requirements as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['product_name'] ?? 'Material')) ?></td>
                        <td><?= e((string) $row['final_required_quantity']) ?></td>
                        <td><?= e((string) $row['unit']) ?></td>
                        <td><?= e(enum_label(\App\Domain\MaterialSource::class, (string) $row['source'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
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

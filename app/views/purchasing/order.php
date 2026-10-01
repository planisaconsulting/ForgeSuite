<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1><?= e((string) $order['po_number']) ?></h1>
        <p class="sf-muted mb-0"><?= e((string) $order['supplier_name']) ?> · <?= e(enum_label(App\Domain\PurchaseOrderStatus::class, (string) $order['status'])) ?></p>
    </div>
    <a class="btn btn-outline-light btn-lg" href="<?= e(url('/purchasing/orders/' . $order['id'] . '/pdf')) ?>">Download PDF</a>
</div>
<section class="sf-panel mb-3">
    <dl class="sf-dl">
        <div><dt>Order date</dt><dd><?= e((string) $order['order_date']) ?></dd></div>
        <div><dt>Expected</dt><dd><?= e((string) ($order['expected_date'] ?? '—')) ?></dd></div>
        <?php if ($showCost): ?>
            <div><dt>Subtotal</dt><dd><?= e(money((string) $order['subtotal'])) ?></dd></div>
            <div><dt>VAT</dt><dd><?= e((string) $order['vat_rate']) ?>% · <?= e(money((string) $order['vat_amount'])) ?></dd></div>
            <div><dt>Total</dt><dd><?= e(money((string) $order['total'])) ?></dd></div>
        <?php endif; ?>
    </dl>
    <?php if (!empty($order['notes'])): ?><div class="px-3 pb-3"><?= nl_text((string) $order['notes']) ?></div><?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Lines</h2></div>
    <div class="table-responsive"><table class="table sf-table mb-0"><thead><tr><th>Description</th><th>Ordered</th><th>Received</th><?php if ($showCost): ?><th>Unit cost</th><th>Line</th><?php endif; ?><th>Job</th></tr></thead><tbody>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= e((string) $item['description']) ?></td>
            <td><?= e((string) $item['ordered_quantity']) ?> <?= e((string) $item['unit']) ?></td>
            <td><?= e((string) $item['received_quantity']) ?></td>
            <?php if ($showCost): ?><td><?= e(money((string) $item['unit_cost'])) ?></td><td><?= e(money((string) $item['line_total'])) ?></td><?php endif; ?>
            <td><?= $item['job_id'] ? e((string) $item['job_number']) : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php if ((string) $order['status'] === 'DRAFT' && (can('purchasing.edit') || can('purchasing.create'))): ?>
<section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Add a line</h2></div>
    <form class="p-3 row g-3" method="post" action="<?= e(url('/purchasing/orders/' . $order['id'] . '/lines')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-6"><select class="form-select form-select-lg" name="product_id" required><option value="">Product</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><input class="form-control form-control-lg" name="quantity" placeholder="Quantity" required></div>
        <div class="col-md-3"><button class="btn btn-sf btn-lg w-100" type="submit">Add</button></div>
    </form>
</section>
<?php endif; ?>
<?php if (in_array((string) $order['status'], ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'ORDERED'], true)): ?>
<form class="d-flex flex-wrap gap-2 mb-3" method="post" action="<?= e(url('/purchasing/orders/' . $order['id'] . '/status')) ?>">
    <?= csrf_field() ?>
    <?php
    $next = match ((string) $order['status']) {
        'DRAFT' => 'PENDING_APPROVAL',
        'PENDING_APPROVAL' => 'APPROVED',
        'APPROVED' => 'ORDERED',
        default => '',
    };
    ?>
    <?php if ($next !== ''): ?><button class="btn btn-sf btn-lg" name="status" value="<?= e($next) ?>" type="submit"><?= e(enum_label(App\Domain\PurchaseOrderStatus::class, $next)) ?></button><?php endif; ?>
    <?php if (can('purchasing.cancel')): ?><button class="btn btn-outline-light btn-lg" name="status" value="CANCELLED" type="submit">Cancel</button><?php endif; ?>
</form>
<?php endif; ?>
<?php if (in_array((string) $order['status'], ['ORDERED', 'PARTIALLY_RECEIVED'], true) && (can('purchasing.receive') || can('inventory.receive'))): ?>
<section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Receive goods</h2></div>
    <form class="p-3" method="post" action="<?= e(url('/purchasing/orders/' . $order['id'] . '/receive')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4"><label class="form-label">Location</label><select class="form-select form-select-lg" name="stock_location_id" required><?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Delivery note</label><input class="form-control form-control-lg" name="supplier_delivery_note"></div>
            <div class="col-md-4"><label class="form-label">Supplier invoice number</label><input class="form-control form-control-lg" name="supplier_invoice_number"></div>
            <div class="col-md-3"><label class="form-label">Roll/sheet width mm</label><input class="form-control form-control-lg" name="width_mm"></div>
            <div class="col-md-3"><label class="form-label">Length mm</label><input class="form-control form-control-lg" name="length_mm"></div>
            <div class="col-md-6 d-flex align-items-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="track_each" value="1"> One inventory record per sheet or length</label></div>
        </div>
        <?php foreach ($items as $item): $left = (float) $item['ordered_quantity'] - (float) $item['received_quantity']; if ($left <= 0) continue; ?>
            <div class="row g-2 align-items-center mb-2">
                <div class="col-md-6"><?= e((string) $item['description']) ?> · outstanding <?= e((string) $left) ?></div>
                <div class="col-md-3"><input class="form-control form-control-lg" name="receive_qty[<?= e((string) $item['id']) ?>]" inputmode="decimal" placeholder="Qty received"></div>
            </div>
        <?php endforeach; ?>
        <button class="btn btn-sf btn-lg" type="submit">Receive</button>
    </form>
</section>
<?php endif; ?>
<?php if ($receipts !== []): ?>
<section class="sf-panel"><div class="sf-panel-head"><h2>Receipts</h2></div><ul class="sf-feed"><?php foreach ($receipts as $receipt): ?><li><?= e((string) $receipt['grn_number']) ?><small><?= e((string) $receipt['received_date']) ?> · <?= e((string) ($receipt['supplier_delivery_note'] ?? '')) ?></small></li><?php endforeach; ?></ul></section>
<?php endif; ?>

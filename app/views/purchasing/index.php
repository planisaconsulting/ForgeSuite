<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Purchasing</h1></div>
<div class="row g-3 mb-3">
    <?php foreach ([
        'Drafts' => $desk['drafts'],
        'Awaiting approval' => $desk['pending'],
        'Ordered' => $desk['ordered'],
        'Partially received' => $desk['partial'],
        'Overdue' => $desk['overdue'],
        'Expected this week' => $desk['due_week'],
    ] as $label => $value): ?>
        <div class="col-6 col-lg-4"><article class="sf-stat"><p class="sf-kicker"><?= e($label) ?></p><p class="sf-stat-value"><?= e((string) $value) ?></p></article></div>
    <?php endforeach; ?>
    <?php if ($showCost): ?>
        <div class="col-12"><article class="sf-stat"><p class="sf-kicker">Purchase value this month</p><p class="sf-stat-value"><?= e(money((string) $desk['month_value'])) ?></p><p class="sf-muted mb-0">Ordered and received purchase orders. This is not an invoice total.</p></article></div>
    <?php endif; ?>
</div>
<div class="row g-3">
    <div class="col-md-4"><section class="sf-panel h-100"><div class="sf-panel-head"><h2>Top suppliers</h2></div>
        <?php if ($suppliers === []): ?><div class="sf-empty"><p>No purchase value this month.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($suppliers as $row): ?><li><?= e((string) $row['name']) ?><small><?= e(money((string) $row['value'])) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
    </section></div>
    <div class="col-md-4"><section class="sf-panel h-100"><div class="sf-panel-head"><h2>Recent receipts</h2></div>
        <?php if ($receipts === []): ?><div class="sf-empty"><p>No goods receipts yet.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($receipts as $row): ?><li><a href="<?= e(url('/purchasing/orders/' . $row['purchase_order_id'])) ?>"><?= e((string) $row['grn_number']) ?></a><small><?= e((string) $row['supplier_name']) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
    </section></div>
    <div class="col-md-4"><section class="sf-panel h-100"><div class="sf-panel-head"><h2>Supplier price changes</h2></div>
        <?php if ($prices === []): ?><div class="sf-empty"><p>No supplier price has changed.</p></div><?php else: ?><ul class="sf-feed"><?php foreach ($prices as $row): ?><li><?= e((string) $row['product_name']) ?><small><?= e((string) $row['supplier_name']) ?> · <?= e(money((string) $row['old_price'])) ?> → <?= e(money((string) $row['new_price'])) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
    </section></div>
</div>

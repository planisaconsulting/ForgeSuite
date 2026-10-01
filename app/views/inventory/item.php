<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1"><?= e((string) $item['inventory_type']) ?></p>
        <h1><?= e((string) $item['inventory_code']) ?></h1>
        <p class="sf-muted mb-0"><?= e((string) $item['product_name']) ?> · <?= e((string) $item['location_name']) ?></p>
    </div>
    <a class="btn btn-outline-light btn-lg" href="<?= e(url('/inventory/items/' . $item['id'] . '/label')) ?>">Print label</a>
</div>
<section class="sf-panel mb-3">
    <dl class="sf-dl">
        <div><dt>Status</dt><dd><?= e(enum_label(App\Domain\InventoryItemStatus::class, (string) $item['status'])) ?></dd></div>
        <div><dt>Received</dt><dd><?= e((string) ($item['received_date'] ?? '—')) ?><?= $ageDays !== null ? ' · ' . e((string) $ageDays) . ' days' : '' ?></dd></div>
        <div><dt>Original</dt><dd><?= e((string) $item['original_quantity']) ?> <?= e((string) $item['unit']) ?></dd></div>
        <div><dt>Remaining</dt><dd><?= e((string) $item['remaining_quantity']) ?> <?= e((string) $item['unit']) ?></dd></div>
        <div><dt>Size</dt><dd><?= e(trim((string) ($item['width_mm'] ?? '') . ' × ' . (string) ($item['height_mm'] ?? $item['length_mm'] ?? ''), ' ×')) ?: '—' ?> mm</dd></div>
        <?php if ($showCost): ?>
            <div><dt>Unit cost</dt><dd><?= e(money((string) $item['unit_cost'])) ?></dd></div>
            <div><dt>Acquisition cost</dt><dd><?= e(money((string) $item['acquisition_cost'])) ?></dd></div>
            <div><dt>Current value</dt><dd><?= e(money($value)) ?></dd></div>
            <div><dt>Valuation</dt><dd><?= e((string) ($item['valuation_treatment'] ?? '—')) ?></dd></div>
        <?php endif; ?>
        <div><dt>Supplier</dt><dd><?= e((string) ($item['supplier_name'] ?? '—')) ?></dd></div>
        <div><dt>Expiry</dt><dd><?= e((string) ($item['expiry_date'] ?? '—')) ?></dd></div>
    </dl>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>History</h2></div>
    <?php if ($history === []): ?><div class="sf-empty"><p>No movements for this item yet.</p></div><?php else: ?>
        <ul class="sf-feed">
            <?php foreach ($history as $row): ?>
                <li><strong><?= e(enum_label(App\Domain\MovementType::class, (string) $row['movement_type'])) ?></strong> <?= e((string) $row['quantity']) ?> <?= e((string) $row['unit']) ?>
                    <small><?= e((string) $row['movement_date']) ?> · <?= e((string) ($row['reason'] ?? '')) ?></small></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

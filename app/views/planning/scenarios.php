<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Scenarios</h1></div>
<p class="sf-muted">A scenario changes a copy of the figures. Product costs and issued quotes stay as they are.</p>
<?php if (can('scenarios.manage')): ?>
<form method="post" action="<?= e(url('/planning/scenarios')) ?>" class="sf-panel p-3 mb-3 d-grid gap-2">
    <?= csrf_field() ?>
    <input class="form-control form-control-lg" name="name" placeholder="Name" required>
    <select class="form-select form-select-lg" name="scenario_type">
        <option value="MATERIAL_PRICE">Material price change</option>
        <option value="HIGH_SALES">High sales</option>
        <option value="LOW_SALES">Low sales</option>
        <option value="CAPACITY_CHANGE">Capacity change</option>
        <option value="CUSTOM">Custom</option>
    </select>
    <input class="form-control" name="percent" inputmode="decimal" placeholder="Percent, for example 10">
    <input class="form-control" name="product_id" inputmode="numeric" placeholder="Product id">
    <button class="btn btn-sf btn-lg" type="submit">Run scenario</button>
</form>
<?php endif; ?>
<section class="sf-panel">
    <ul class="sf-feed">
        <?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['name']) ?> <small><?= e((string) $row['scenario_type']) ?> · <?= e((string) $row['created_at']) ?></small></li>
        <?php endforeach; ?>
    </ul>
</section>

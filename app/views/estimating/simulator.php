<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>What-if pricing</h1><p class="sf-muted">Nothing is saved. Markup and gross margin are labelled separately.</p></div>
<form method="post" class="sf-panel p-3 mb-3">
    <?= csrf_field() ?>
    <div class="row g-2">
        <?php foreach (['cost' => 'Cost', 'waste_percent' => 'Waste %', 'rate_percent' => 'Rate %', 'discount' => 'Discount amount'] as $name => $label): ?>
            <div class="col-6 col-md-3"><label class="form-label"><?= e($label) ?></label><input class="form-control form-control-lg" name="<?= e($name) ?>" value="<?= e((string) ($_POST[$name] ?? ($name === 'rate_percent' ? '40' : '0'))) ?>"></div>
        <?php endforeach; ?>
        <div class="col-md-4"><label class="form-label">Price from</label><select class="form-select form-select-lg" name="price_mode"><option value="MARGIN">Gross margin</option><option value="MARKUP">Markup</option></select></div>
    </div>
    <button class="btn btn-sf btn-lg mt-3" type="submit">Show result</button>
</form>
<?php if (is_array($result)): ?>
<section class="sf-panel p-3">
    <?php if ($result['error']): ?><p><?= e((string) $result['error']) ?></p><?php endif; ?>
    <p>Cost <?= e((string) $result['cost']) ?> · Sell <?= e((string) $result['sell']) ?> using <?= e((string) $result['mode']) ?></p>
    <p>Margin before discount <?= e((string) ($result['discount']['margin_before'] ?? '—')) ?>% · After <?= e((string) ($result['discount']['margin_after'] ?? '—')) ?>% · Difference <?= e((string) ($result['discount']['margin_points'] ?? '—')) ?> points</p>
</section>
<?php endif; ?>

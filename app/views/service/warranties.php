<div class="sf-page-head"><h1>Warranties</h1></div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Active</p><h2><?= (int) ($dashboard['active_count'] ?? 0) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Expiring</p><h2><?= (int) ($dashboard['expiring'] ?? 0) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Open claims</p><h2><?= (int) ($dashboard['open_claims'] ?? 0) ?></h2></section></div>
    <?php if ($showCosts): ?>
        <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Recovered</p><h2><?= e(money((string) ($dashboard['recovered'] ?? '0'))) ?></h2></section></div>
    <?php endif; ?>
</div>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Ending within 90 days</h2></div>
    <ul class="sf-feed"><?php foreach ($expiring as $row): ?><li><a href="<?= e(url('/assets/' . $row['asset_id'])) ?>"><?= e((string) $row['asset_number']) ?></a> <?= e((string) $row['warranty_type']) ?><small><?= e((string) $row['end_date']) ?></small></li><?php endforeach; ?></ul>
    <?php if ($expiring === []): ?><p class="p-3">No warranties end in the next 90 days.</p><?php endif; ?>
</section>

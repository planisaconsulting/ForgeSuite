<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1><?= e((string) $asset['asset_number']) ?></h1>
    <p><?= e((string) $asset['name']) ?><br><?= e((string) ($asset['site_name'] ?? '')) ?> <?= e((string) ($asset['location_description'] ?? '')) ?></p>
    <p class="sf-muted">Installed <?= e((string) ($asset['installation_date'] ?? '—')) ?>. Next maintenance <?= e((string) ($asset['next_service_date'] ?? '—')) ?>.</p>
</div>
<section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Warranty</h2></div>
    <ul class="sf-feed"><?php foreach ($warranties as $row): ?><li><?= e(str_replace('_', ' ', (string) $row['warranty_type'])) ?><small><?= e((string) $row['start_date']) ?> to <?= e((string) $row['end_date']) ?><?php if ($row['terms']): ?> · <?= e((string) $row['terms']) ?><?php endif; ?></small></li><?php endforeach; ?></ul>
    <?php if ($warranties === []): ?><p class="p-3">No warranty is recorded on this asset.</p><?php endif; ?>
</section>
<section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Service history</h2></div>
    <ul class="sf-feed"><?php foreach ($events as $event): ?><li><?= e((string) $event['summary']) ?><small><?= e((string) $event['happened_at']) ?></small></li><?php endforeach; ?></ul>
</section>
<form class="sf-panel" method="post" action="<?= e(url('/portal/assets/' . $asset['id'] . '/report')) ?>"><?= csrf_field() ?>
    <div class="p-3">
        <label class="form-label">Report a problem</label>
        <textarea class="form-control mb-2" name="description" required></textarea>
        <button class="btn btn-sf" type="submit">Submit</button>
    </div>
</form>

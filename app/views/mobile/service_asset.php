<div class="sf-page-head"><h1><?= e((string) $pack['asset_number']) ?></h1>
    <p><?= e((string) $pack['name']) ?><br><?= e((string) $pack['customer']) ?> · <?= e((string) $pack['site']) ?></p>
</div>
<section class="sf-panel mb-3"><div class="p-3">
    <p><?= e((string) $pack['location']) ?></p>
    <p><?= e((string) $pack['problem']) ?></p>
    <?php if ($pack['latitude'] !== null): ?><p><a href="https://www.openstreetmap.org/?mlat=<?= e((string) $pack['latitude']) ?>&mlon=<?= e((string) $pack['longitude']) ?>">Directions</a></p><?php endif; ?>
</div></section>
<section class="sf-panel"><ul class="sf-feed"><?php foreach ($pack['history'] as $event): ?><li><?= e((string) $event['summary']) ?><small><?= e((string) $event['at']) ?></small></li><?php endforeach; ?></ul></section>

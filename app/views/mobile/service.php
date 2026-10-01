<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>My service jobs</h1></div>
<section class="sf-panel">
    <?php if ($rows === []): ?><p class="p-3">No service requests are in progress.</p><?php endif; ?>
    <ul class="sf-feed"><?php foreach ($rows as $row): ?><li><a href="<?= e(url('/m/service/' . (int) ($row['asset_id'] ?? 0))) ?>"><?= e((string) $row['request_number']) ?></a> <?= e((string) ($row['asset_number'] ?? '')) ?><br><small><?= e((string) $row['company_name']) ?> · <?= e((string) $row['priority']) ?></small></li><?php endforeach; ?></ul>
</section>

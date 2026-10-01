<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Sync centre</h1></div>
<section class="sf-panel mb-3"><div class="p-3">
    <p>Last successful sync: <?= e((string) ($last ?: 'None yet')) ?></p>
    <p>Server photo storage: <?= e((string) round($bytes / 1048576, 1)) ?> MB. Warning at <?= e((string) $warning) ?> MB on the device.</p>
    <p class="mb-0" id="sf-local-storage">Checking this device…</p>
    <button class="btn btn-sf sf-touch mt-3" type="button" id="sf-sync-now">Sync now</button>
    <button class="btn btn-outline-light sf-touch mt-2" type="button" id="sf-field-ready">Field ready</button>
    <p id="sf-sync-result" class="mt-2 mb-0"></p>
</div></section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Failed or waiting</h2></div>
    <?php if ($failed === []): ?><div class="sf-empty"><p>No failed field updates.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($failed as $row): ?>
            <li><strong><?= e((string) $row['operation_type']) ?></strong> <small><?= e((string) ($row['error_code'] ?? '')) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Field packs</h2></div>
    <?php if ($packs === []): ?><div class="sf-empty"><p>No field pack downloaded.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($packs as $pack): ?>
            <li><strong><?= e((string) $pack['pack_type']) ?></strong> <small><?= e((string) $pack['status']) ?> · <?= e((string) $pack['expires_at']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<?php if ($conflicts !== []): ?>
<p><a class="btn btn-outline-light sf-touch" href="<?= e(url('/m/conflicts')) ?>">Review conflicts</a></p>
<?php endif; ?>
<p class="sf-muted">App <?= e($version) ?> · service worker <?= e($sw) ?> · schema <?= e($schema) ?>. This phone is not a backup. After sync, the office record is the source.</p>

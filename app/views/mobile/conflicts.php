<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Sync conflicts</h1></div>
<?php if ($rows === []): ?><div class="sf-empty"><p>No conflicts waiting.</p></div><?php endif; ?>
<?php foreach ($rows as $row): ?>
<section class="sf-panel mb-3"><div class="p-3">
    <p class="mb-1"><strong><?= e((string) $row['field_label']) ?></strong></p>
    <p class="mb-1">Office version</p>
    <p><?= nl2br(e((string) $row['server_text'])) ?></p>
    <p class="mb-1">Offline version</p>
    <p><?= nl2br(e((string) $row['client_text'])) ?></p>
    <?php if (can('sync_conflicts.resolve')): ?>
    <form method="post" action="<?= e(url('/m/conflicts/' . $row['id'])) ?>">
        <?= csrf_field() ?>
        <label class="form-label" for="merged-<?= e((string) $row['id']) ?>">Manual merge</label>
        <textarea class="form-control mb-2" id="merged-<?= e((string) $row['id']) ?>" name="merged" rows="3"></textarea>
        <button class="btn btn-outline-light sf-touch me-2 mb-2" name="choice" value="KEEP_SERVER" type="submit">Keep server</button>
        <button class="btn btn-outline-light sf-touch me-2 mb-2" name="choice" value="APPLY_OFFLINE" type="submit">Apply offline</button>
        <button class="btn btn-sf sf-touch mb-2" name="choice" value="MANUAL_MERGE" type="submit">Manual merge</button>
    </form>
    <?php endif; ?>
</div></section>
<?php endforeach; ?>

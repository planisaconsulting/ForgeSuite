<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Devices</h1></div>
<p class="sf-muted">A trusted device is a name for sync. It does not skip the password. Revoking a lost phone stops the next sync. IndexedDB on that phone is cleared the next time it reaches the server. Until then the browser, not Sign-Forge, controls encryption of local data.</p>
<?php if ($rows === []): ?><div class="sf-empty"><p>No devices registered.</p></div><?php endif; ?>
<?php foreach ($rows as $row): ?>
<section class="sf-panel mb-3"><div class="p-3">
    <p class="mb-1"><strong><?= e((string) $row['device_name']) ?></strong></p>
    <?php if ($admin && isset($row['user_name'])): ?><p class="mb-1"><?= e((string) $row['user_name']) ?></p><?php endif; ?>
    <p class="mb-1"><?= e((string) ($row['platform'] ?? 'Unknown platform')) ?> · last seen <?= e((string) ($row['last_seen_at'] ?? '—')) ?></p>
    <p class="mb-2"><?= $row['revoked_at'] ? 'Revoked ' . e((string) $row['revoked_at']) : 'Active' ?><?php if ($row['pending_count'] !== null): ?> · <?= e((string) $row['pending_count']) ?> waiting when last seen<?php endif; ?></p>
    <?php if ($row['revoked_at'] === null && (can('device.manage_own') || $admin)): ?>
        <form method="post" action="<?= e(url('/m/devices')) ?>" class="mb-2">
            <?= csrf_field() ?>
            <input type="hidden" name="device_id" value="<?= e((string) $row['id']) ?>">
            <label class="form-label" for="name-<?= e((string) $row['id']) ?>">Name</label>
            <input class="form-control mb-2" id="name-<?= e((string) $row['id']) ?>" name="device_name" value="<?= e((string) $row['device_name']) ?>">
            <button class="btn btn-outline-light sf-touch" type="submit">Rename</button>
        </form>
        <form method="post" action="<?= e(url('/m/devices/' . $row['id'] . '/revoke')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-outline-light sf-touch" type="submit">Revoke device</button>
        </form>
    <?php endif; ?>
</div></section>
<?php endforeach; ?>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Offline settings</h1></div>
<form method="post" action="<?= e(url('/admin/offline-settings')) ?>" class="sf-panel p-3">
    <?= csrf_field() ?>
    <?php foreach ($values as $key => $value): ?>
        <?php if ($key === 'schema_version') { continue; } ?>
        <label class="form-label" for="<?= e($key) ?>"><?= e(str_replace('_', ' ', $key)) ?></label>
        <input class="form-control mb-3" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e((string) $value) ?>">
    <?php endforeach; ?>
    <button class="btn btn-sf sf-touch" type="submit">Save</button>
</form>
<p class="sf-muted mt-3">Offline session hours limit how long a downloaded pack can be opened without signing in again. The phone does not store the password. Unsynced drafts are never deleted by the cleanup job.</p>

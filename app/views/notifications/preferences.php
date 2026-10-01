<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Notification preferences</h1><p class="sf-muted mb-0">System notices cannot be switched off.</p></div>
<form class="sf-panel p-3" method="post" action="<?= e(url('/notifications/preferences')) ?>">
    <?= csrf_field() ?>
    <?php foreach ($preferences as $type => $enabled): ?>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="type[<?= e($type) ?>]" value="1" id="pref-<?= e($type) ?>" <?= $enabled ? 'checked' : '' ?> <?= in_array($type, $locked, true) ? 'disabled' : '' ?>>
            <label class="form-check-label" for="pref-<?= e($type) ?>"><?= e(str_replace('_', ' ', $type)) ?><?= in_array($type, $locked, true) ? ' (always on)' : '' ?></label>
        </div>
    <?php endforeach; ?>
    <button class="btn btn-sf mt-3" type="submit">Save</button>
</form>

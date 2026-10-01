<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Settings</h1>
    <p class="sf-muted mb-0">Company details used across Sign-Forge. These values are not hard-coded in the screens.</p>
</div>
<?php if (!empty($errors['_form'])): ?><div class="alert alert-danger sf-alert"><?= e($errors['_form']) ?></div><?php endif; ?>
<form class="sf-form sf-panel" method="post" action="<?= e(url('/settings')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php foreach ($fields as $key => $label): ?>
            <?php $current = old_value($old, $key); ?>
            <div class="<?= in_array($key, ['address', 'default_quote_terms'], true) ? 'col-12' : 'col-md-6' ?>">
                <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                <?php if (in_array($key, ['address', 'default_quote_terms'], true)): ?>
                    <textarea class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" rows="<?= $key === 'default_quote_terms' ? '6' : '3' ?>"><?= e($current) ?></textarea>
                <?php elseif ($key === 'timezone'): ?>
                    <input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" list="timezones" value="<?= e($current) ?>">
                    <datalist id="timezones">
                        <?php foreach ($timezones as $zone): ?>
                            <option value="<?= e($zone) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                <?php else: ?>
                    <input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($current) ?>">
                <?php endif; ?>
                <?= field_error($errors, $key) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Save settings</button>
    </div>
</form>

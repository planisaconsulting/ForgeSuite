<?php
require base_path('app/views/partials/flashes.php');
$isEdit = $account !== null;
$action = $isEdit ? '/users/' . $account['id'] : '/users';
$value = static function (string $key, string $default = '') use ($old): string {
    return old_value($old, $key, $default);
};
?>
<div class="sf-page-head"><h1><?= e($isEdit ? 'Edit user' : 'New user') ?></h1></div>
<?php if (!empty($errors['_form'])): ?><div class="alert alert-danger sf-alert"><?= e($errors['_form']) ?></div><?php endif; ?>
<form class="sf-form sf-panel" method="post" action="<?= e(url($action)) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Name <span class="sf-req">*</span></label>
            <input class="form-control" id="name" name="name" value="<?= e($value('name')) ?>">
            <?= field_error($errors, 'name') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email <span class="sf-req">*</span></label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($value('email')) ?>">
            <?= field_error($errors, 'email') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="role_id">Role <span class="sf-req">*</span></label>
            <select class="form-select" id="role_id" name="role_id">
                <option value="">Choose</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e((string) $role['id']) ?>" <?= (string) $value('role_id') === (string) $role['id'] ? 'selected' : '' ?>><?= e((string) $role['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'role_id') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password"><?= $isEdit ? 'New password' : 'Password' ?> <?= $isEdit ? '' : '<span class="sf-req">*</span>' ?></label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="new-password">
            <div class="form-text"><?= $isEdit ? 'Leave blank to keep the current password. A new password must be changed at next sign-in.' : 'At least 10 characters. The user must change it at first sign-in.' ?></div>
            <?= field_error($errors, 'password') ?>
        </div>
        <div class="col-12">
            <input type="hidden" name="active" value="0">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" <?= is_checked($value('active', '1')) ?>> Active</label>
        </div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Save user</button>
        <a class="btn btn-outline-light" href="<?= e(url('/users')) ?>">Cancel</a>
    </div>
</form>
<?php if ($isEdit): ?>
    <form class="mt-3" method="post" action="<?= e(url('/users/' . $account['id'] . '/active')) ?>" onsubmit="return confirm('<?= (int) $account['active'] === 1 ? 'Deactivate this user?' : 'Activate this user?' ?>');">
        <?= csrf_field() ?>
        <input type="hidden" name="active" value="<?= (int) $account['active'] === 1 ? '0' : '1' ?>">
        <button class="btn btn-outline-light" type="submit"><?= (int) $account['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
    </form>
<?php endif; ?>

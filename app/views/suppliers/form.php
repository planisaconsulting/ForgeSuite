<?php
require base_path('app/views/partials/flashes.php');
$isEdit = $supplier !== null;
$action = $isEdit ? '/suppliers/' . $supplier['id'] : '/suppliers';
$value = static function (string $key, string $default = '') use ($old): string {
    return old_value($old, $key, $default);
};
?>
<div class="sf-page-head"><h1><?= e($isEdit ? 'Edit supplier' : 'New supplier') ?></h1></div>
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
            <label class="form-label" for="contact_name">Contact name</label>
            <input class="form-control" id="contact_name" name="contact_name" value="<?= e($value('contact_name')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" value="<?= e($value('email')) ?>">
            <?= field_error($errors, 'email') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" value="<?= e($value('phone')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="account_number">Account number</label>
            <input class="form-control" id="account_number" name="account_number" value="<?= e($value('account_number')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="website">Website</label>
            <input class="form-control" id="website" name="website" value="<?= e($value('website')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="address">Address</label>
            <textarea class="form-control" id="address" name="address" rows="2"><?= e($value('address')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="notes">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($value('notes')) ?></textarea>
        </div>
        <div class="col-12">
            <input type="hidden" name="active" value="0">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" <?= is_checked($value('active', '1')) ?>> Active</label>
        </div>
        <div class="col-12">
            <input type="hidden" name="is_subcontractor" value="0">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="is_subcontractor" value="1" <?= is_checked($value('is_subcontractor', '0')) ?>> Subcontractor</label>
        </div>
        <div class="col-12">
            <label class="form-label" for="capabilities">Subcontractor capabilities</label>
            <input class="form-control" id="capabilities" name="capabilities" value="<?= e($value('capabilities')) ?>" placeholder="Installation, electrical, crane hire">
        </div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Save supplier</button>
        <a class="btn btn-outline-light" href="<?= e(url($isEdit ? '/suppliers/' . $supplier['id'] : '/suppliers')) ?>">Cancel</a>
    </div>
</form>

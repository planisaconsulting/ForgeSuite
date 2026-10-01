<?php
require base_path('app/views/partials/flashes.php');
$isEdit = $customer !== null;
$action = $isEdit ? '/customers/' . $customer['id'] : '/customers';
$value = static function (string $key, string $default = '') use ($old): string {
    return old_value($old, $key, $default);
};
?>
<div class="sf-page-head">
    <h1><?= e($isEdit ? 'Edit customer' : 'New customer') ?></h1>
</div>
<?php if (!empty($errors['_form'])): ?>
    <div class="alert alert-danger sf-alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>
<form class="sf-form sf-panel" method="post" action="<?= e(url($action)) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="customer_type">Type <span class="sf-req">*</span></label>
            <select class="form-select" id="customer_type" name="customer_type">
                <?php foreach (App\Domain\CustomerType::cases() as $case): ?>
                    <option value="<?= e($case->value) ?>" <?= $value('customer_type', 'BUSINESS') === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'customer_type') ?>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="company_name">Company name</label>
            <input class="form-control" id="company_name" name="company_name" value="<?= e($value('company_name')) ?>">
            <?= field_error($errors, 'company_name') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="first_name">First name</label>
            <input class="form-control" id="first_name" name="first_name" value="<?= e($value('first_name')) ?>">
            <?= field_error($errors, 'first_name') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="last_name">Last name</label>
            <input class="form-control" id="last_name" name="last_name" value="<?= e($value('last_name')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= e($value('email')) ?>">
            <?= field_error($errors, 'email') ?>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="phone">Phone</label>
            <input class="form-control" id="phone" name="phone" value="<?= e($value('phone')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="mobile">Mobile</label>
            <input class="form-control" id="mobile" name="mobile" value="<?= e($value('mobile')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="vat_number">VAT number</label>
            <input class="form-control" id="vat_number" name="vat_number" value="<?= e($value('vat_number')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="registration_number">Registration number</label>
            <input class="form-control" id="registration_number" name="registration_number" value="<?= e($value('registration_number')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="website">Website</label>
            <input class="form-control" id="website" name="website" value="<?= e($value('website')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="billing_address">Billing address</label>
            <textarea class="form-control" id="billing_address" name="billing_address" rows="3"><?= e($value('billing_address')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="physical_address">Physical address</label>
            <textarea class="form-control" id="physical_address" name="physical_address" rows="3"><?= e($value('physical_address')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="notes">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?= e($value('notes')) ?></textarea>
        </div>
        <div class="col-12">
            <input type="hidden" name="active" value="0">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="active" name="active" value="1" <?= is_checked($value('active', '1')) ?>>
                <label class="form-check-label" for="active">Active</label>
            </div>
        </div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Save customer</button>
        <a class="btn btn-outline-light" href="<?= e(url($isEdit ? '/customers/' . $customer['id'] : '/customers')) ?>">Cancel</a>
    </div>
</form>

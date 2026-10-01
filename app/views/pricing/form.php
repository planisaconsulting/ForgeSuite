<?php
require base_path('app/views/partials/flashes.php');
$value = static function (string $key, string $default = '') use ($old): string {
    return old_value($old, $key, $default);
};
?>
<div class="sf-page-head">
    <h1>Edit <?= e((string) $level['code']) ?></h1>
    <p class="sf-muted mb-0">Selling price = cost × (1 + markup ÷ 100). Do not type a margin here.</p>
</div>
<?php if (!empty($errors['_form'])): ?><div class="alert alert-danger sf-alert"><?= e($errors['_form']) ?></div><?php endif; ?>
<form class="sf-form sf-panel" method="post" action="<?= e(url('/pricing-levels/' . $level['id'])) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Code</label>
            <input class="form-control" value="<?= e((string) $level['code']) ?>" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="name">Name <span class="sf-req">*</span></label>
            <input class="form-control" id="name" name="name" value="<?= e($value('name')) ?>">
            <?= field_error($errors, 'name') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="markup_percent">Markup % <span class="sf-req">*</span></label>
            <input class="form-control" id="markup_percent" name="markup_percent" inputmode="decimal" value="<?= e($value('markup_percent')) ?>">
            <?= field_error($errors, 'markup_percent') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sort_order">Sort order</label>
            <input class="form-control" id="sort_order" name="sort_order" value="<?= e($value('sort_order', '0')) ?>">
            <?= field_error($errors, 'sort_order') ?>
        </div>
        <div class="col-12">
            <input type="hidden" name="active" value="0">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" <?= is_checked($value('active', '1')) ?>> Active</label>
        </div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Save pricing level</button>
        <a class="btn btn-outline-light" href="<?= e(url('/pricing-levels')) ?>">Cancel</a>
    </div>
</form>

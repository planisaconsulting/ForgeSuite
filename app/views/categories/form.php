<?php
require base_path('app/views/partials/flashes.php');
$isEdit = $category !== null;
$action = $isEdit ? '/categories/' . $category['id'] : '/categories';
$value = static function (string $key, string $default = '') use ($old): string {
    return old_value($old, $key, $default);
};
?>
<div class="sf-page-head"><h1><?= e($isEdit ? 'Edit category' : 'New category') ?></h1></div>
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
            <label class="form-label" for="parent_id">Parent</label>
            <select class="form-select" id="parent_id" name="parent_id">
                <option value="">None</option>
                <?php foreach ($parents as $parent): ?>
                    <?php if ($isEdit && (int) $parent['id'] === (int) $category['id']) { continue; } ?>
                    <option value="<?= e((string) $parent['id']) ?>" <?= (string) $value('parent_id') === (string) $parent['id'] ? 'selected' : '' ?>><?= e((string) $parent['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'parent_id') ?>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="sort_order">Sort order</label>
            <input class="form-control" id="sort_order" name="sort_order" value="<?= e($value('sort_order', '0')) ?>">
            <?= field_error($errors, 'sort_order') ?>
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="2"><?= e($value('description')) ?></textarea>
        </div>
        <div class="col-12">
            <input type="hidden" name="active" value="0">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" <?= is_checked($value('active', '1')) ?>> Active</label>
        </div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Save category</button>
        <a class="btn btn-outline-light" href="<?= e(url('/categories')) ?>">Cancel</a>
    </div>
</form>
<?php if ($isEdit): ?>
    <form class="mt-3" method="post" action="<?= e(url('/categories/' . $category['id'] . '/active')) ?>" onsubmit="return confirm('<?= (int) $category['active'] === 1 ? 'Deactivate this category?' : 'Activate this category?' ?>');">
        <?= csrf_field() ?>
        <input type="hidden" name="active" value="<?= (int) $category['active'] === 1 ? '0' : '1' ?>">
        <button class="btn btn-outline-light" type="submit"><?= (int) $category['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
    </form>
<?php endif; ?>

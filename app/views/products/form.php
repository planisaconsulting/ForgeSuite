<?php
require base_path('app/views/partials/flashes.php');
$isEdit = $product !== null;
$action = $isEdit ? '/products/' . $product['id'] : '/products';
$value = static function (string $key, string $default = '') use ($old): string {
    return old_value($old, $key, $default);
};
?>
<div class="sf-page-head"><h1><?= e($isEdit ? 'Edit product' : 'New product') ?></h1></div>
<?php if (!empty($errors['_form'])): ?><div class="alert alert-danger sf-alert"><?= e($errors['_form']) ?></div><?php endif; ?>
<form class="sf-form sf-panel" method="post" action="<?= e(url($action)) ?>" id="product-form">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="name">Name <span class="sf-req">*</span></label>
            <input class="form-control" id="name" name="name" value="<?= e($value('name')) ?>">
            <?= field_error($errors, 'name') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sku">SKU <span class="sf-req">*</span></label>
            <input class="form-control" id="sku" name="sku" value="<?= e($value('sku')) ?>">
            <?= field_error($errors, 'sku') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="category_id">Category <span class="sf-req">*</span></label>
            <select class="form-select" id="category_id" name="category_id">
                <option value="">Choose</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= (string) $value('category_id') === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= $category['parent_id'] ? '— ' : '' ?><?= e((string) $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'category_id') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="product_type">Type <span class="sf-req">*</span></label>
            <select class="form-select" id="product_type" name="product_type">
                <?php foreach (App\Domain\ProductType::cases() as $case): ?>
                    <option value="<?= e($case->value) ?>" <?= $value('product_type', 'MATERIAL') === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="pricing_method">Pricing method <span class="sf-req">*</span></label>
            <select class="form-select" id="pricing_method" name="pricing_method">
                <?php foreach (App\Domain\PricingMethod::cases() as $case): ?>
                    <option value="<?= e($case->value) ?>" <?= $value('pricing_method', 'AREA') === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">The cost unit is set from this method. Area is per m². Sheet is per sheet.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="cost_price">Cost price <span class="sf-req">*</span></label>
            <input class="form-control" id="cost_price" name="cost_price" inputmode="decimal" value="<?= e($value('cost_price')) ?>">
            <?= field_error($errors, 'cost_price') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="supplier_id">Preferred supplier</label>
            <select class="form-select" id="supplier_id" name="supplier_id">
                <option value="">None</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= e((string) $supplier['id']) ?>" <?= (string) $value('supplier_id') === (string) $supplier['id'] ? 'selected' : '' ?>><?= e((string) $supplier['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">One preferred supplier for now. More suppliers per product can be added later.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="supplier_code">Supplier code</label>
            <input class="form-control" id="supplier_code" name="supplier_code" value="<?= e($value('supplier_code')) ?>">
        </div>
        <div class="col-md-4 sf-dim" data-methods="AREA">
            <label class="form-label" for="roll_width_mm">Roll width (mm)</label>
            <input class="form-control" id="roll_width_mm" name="roll_width_mm" inputmode="decimal" value="<?= e($value('roll_width_mm')) ?>">
            <?= field_error($errors, 'roll_width_mm') ?>
        </div>
        <div class="col-md-4 sf-dim" data-methods="SHEET">
            <label class="form-label" for="sheet_width_mm">Sheet width (mm)</label>
            <input class="form-control" id="sheet_width_mm" name="sheet_width_mm" inputmode="decimal" value="<?= e($value('sheet_width_mm')) ?>">
            <?= field_error($errors, 'sheet_width_mm') ?>
        </div>
        <div class="col-md-4 sf-dim" data-methods="SHEET">
            <label class="form-label" for="sheet_height_mm">Sheet height (mm)</label>
            <input class="form-control" id="sheet_height_mm" name="sheet_height_mm" inputmode="decimal" value="<?= e($value('sheet_height_mm')) ?>">
            <?= field_error($errors, 'sheet_height_mm') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="standard_waste_percent">Manufacturing waste %</label>
            <input class="form-control" id="standard_waste_percent" name="standard_waste_percent" inputmode="decimal" value="<?= e($value('standard_waste_percent', '0')) ?>">
            <div class="form-text">Applied after the billable quantity. This is not unused roll width.</div>
            <?= field_error($errors, 'standard_waste_percent') ?>
        </div>
        <div class="col-md-4 sf-dim" data-methods="AREA">
            <label class="form-label" for="waste_threshold_percent">Roll waste threshold %</label>
            <input class="form-control" id="waste_threshold_percent" name="waste_threshold_percent" inputmode="decimal" value="<?= e($value('waste_threshold_percent')) ?>">
            <div class="form-text">Warns only. It does not charge the offcut.</div>
            <?= field_error($errors, 'waste_threshold_percent') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="default_waste_policy">Default waste treatment</label>
            <select class="form-select" id="default_waste_policy" name="default_waste_policy">
                <?php foreach (App\Domain\WasteMode::cases() as $case): ?>
                    <option value="<?= e($case->value) ?>" <?= $value('default_waste_policy', 'ACTUAL') === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="minimum_stock_level">Minimum stock level</label>
            <input class="form-control" id="minimum_stock_level" name="minimum_stock_level" inputmode="decimal" value="<?= e($value('minimum_stock_level')) ?>">
            <div class="form-text">A later stock ledger will use this. It is not a quantity on hand.</div>
            <?= field_error($errors, 'minimum_stock_level') ?>
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="2"><?= e($value('description')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="notes">Notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($value('notes')) ?></textarea>
        </div>
        <div class="col-12 d-flex flex-wrap gap-3">
            <?php foreach ([
                'allow_rotation' => 'Allow rotation later',
                'allow_nesting' => 'Allow nesting later',
                'track_stock' => 'Flag for stock tracking',
                'active' => 'Active',
            ] as $flag => $label): ?>
                <input type="hidden" name="<?= e($flag) ?>" value="0">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="<?= e($flag) ?>" value="1" <?= is_checked($value($flag, $flag === 'active' ? '1' : '0')) ?>>
                    <?= e($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Save product</button>
        <a class="btn btn-outline-light" href="<?= e(url($isEdit ? '/products/' . $product['id'] : '/products')) ?>">Cancel</a>
    </div>
</form>

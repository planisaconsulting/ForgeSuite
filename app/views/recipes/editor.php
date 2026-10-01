<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1><?= e($title) ?></h1></div>
<?php if (!empty($errors['_form'])): ?><div class="alert alert-danger"><?= e($errors['_form']) ?></div><?php endif; ?>
<form method="post" action="<?= e(url($recipe === null ? '/recipes' : '/recipes/' . $recipe['id'])) ?>" class="sf-form">
    <?= csrf_field() ?>
    <div class="sf-panel mb-3"><div class="p-3 row g-3">
        <div class="col-md-4"><label class="form-label">Code</label><input class="form-control" name="code" value="<?= e((string) ($old['code'] ?? $recipe['code'] ?? '')) ?>" required><?= field_error($errors, 'code') ?></div>
        <div class="col-md-8"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e((string) ($old['name'] ?? $recipe['name'] ?? '')) ?>" required><?= field_error($errors, 'name') ?></div>
        <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" name="recipe_type"><?php foreach (App\Services\RecipeService::TYPES as $type): ?><option <?= (($old['recipe_type'] ?? $recipe['recipe_type'] ?? '') === $type) ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Category</label><select class="form-select" name="category_id"><option value="">None</option><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['id']) ?>" <?= (int) ($old['category_id'] ?? $recipe['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Finished product</label><select class="form-select" name="finished_product_id"><option value="">None</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>" <?= (int) ($recipe['finished_product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Production route</label><select class="form-select" name="production_route_template_id"><option value="">None</option><?php foreach ($routes as $route): ?><option value="<?= e((string) $route['id']) ?>" <?= (int) ($recipe['production_route_template_id'] ?? 0) === (int) $route['id'] ? 'selected' : '' ?>><?= e((string) $route['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6 form-check mt-4"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= !empty($recipe['active']) ? 'checked' : '' ?>><label for="active">Active</label></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= e((string) ($recipe['description'] ?? '')) ?></textarea></div>
    </div></div>
    <section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Inputs</h2></div><div class="p-3">
        <p class="sf-muted">Width, height, and quantity are always available as W, H, and Q. Add extras such as LAMINATED or ILLUMINATED.</p>
        <?php $inputRows = $inputs === [] ? [['code'=>'','label'=>'','input_type'=>'NUMBER']] : $inputs; ?>
        <?php foreach (array_merge($inputRows, [[], [], []]) as $index => $input): ?>
            <div class="row g-2 mb-2">
                <div class="col-md-2"><input class="form-control" name="inputs[<?= $index ?>][code]" placeholder="CODE" value="<?= e((string) ($input['code'] ?? '')) ?>"></div>
                <div class="col-md-3"><input class="form-control" name="inputs[<?= $index ?>][label]" placeholder="Label" value="<?= e((string) ($input['label'] ?? '')) ?>"></div>
                <div class="col-md-2"><select class="form-select" name="inputs[<?= $index ?>][input_type]"><?php foreach (App\Services\RecipeService::INPUTS as $type): ?><option <?= (($input['input_type'] ?? '') === $type) ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><input class="form-control" name="inputs[<?= $index ?>][unit]" placeholder="Unit" value="<?= e((string) ($input['unit'] ?? '')) ?>"></div>
                <div class="col-md-2"><input class="form-control" name="inputs[<?= $index ?>][default_value]" placeholder="Default" value="<?= e((string) ($input['default_value'] ?? '')) ?>"></div>
                <div class="col-md-1"><input class="form-check-input" type="checkbox" name="inputs[<?= $index ?>][required]" value="1" <?= !isset($input['required']) || (int) ($input['required'] ?? 0) === 1 ? 'checked' : '' ?>></div>
                <div class="col-12"><input class="form-control" name="inputs[<?= $index ?>][options]" placeholder="Select options, comma separated" value="<?= e(is_string($input['options_json'] ?? null) ? implode(', ', json_decode((string) $input['options_json'], true) ?: []) : '') ?>"></div>
            </div>
        <?php endforeach; ?>
        <?= field_error($errors, 'inputs') ?>
    </div></section>
    <section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Components</h2></div><div class="p-3">
        <p class="sf-muted">Formulas may use W, H, L, D, Q, AREA_M2, PERIMETER_M, and CEIL, FLOOR, ROUND, MAX, MIN, ABS. Condition example: LAMINATED, EQ, YES</p>
        <?php $itemRows = $items === [] ? [] : $items; ?>
        <?php foreach (array_merge($itemRows, [[], [], [], []]) as $index => $item): ?>
            <div class="row g-2 mb-3">
                <div class="col-md-4"><input class="form-control" name="items[<?= $index ?>][description]" placeholder="Component" value="<?= e((string) ($item['description'] ?? '')) ?>"></div>
                <div class="col-md-2"><select class="form-select" name="items[<?= $index ?>][component_type]"><?php foreach (App\Services\RecipeService::COMPONENTS as $type): ?><option <?= (($item['component_type'] ?? 'MATERIAL') === $type) ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><select class="form-select" name="items[<?= $index ?>][product_id]"><option value="">No product</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>" <?= (int) ($item['product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><input class="form-control" name="items[<?= $index ?>][quantity_formula]" placeholder="AREA_M2 * Q" value="<?= e((string) ($item['quantity_formula'] ?? '')) ?>"></div>
                <div class="col-md-2"><input class="form-control" name="items[<?= $index ?>][unit]" placeholder="Unit" value="<?= e((string) ($item['unit'] ?? '')) ?>"></div>
                <div class="col-md-2"><select class="form-select" name="items[<?= $index ?>][cost_calculation_method]"><?php foreach (['PRODUCT','LABOUR','TRAVEL','FIXED'] as $method): ?><option <?= (($item['cost_calculation_method'] ?? 'PRODUCT') === $method) ? 'selected' : '' ?>><?= e($method) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><select class="form-select" name="items[<?= $index ?>][rounding_rule]"><?php foreach (['NONE','CEIL','FLOOR','ROUND'] as $rule): ?><option <?= (($item['rounding_rule'] ?? 'NONE') === $rule) ? 'selected' : '' ?>><?= e($rule) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><input class="form-control" name="items[<?= $index ?>][waste_percent_override]" placeholder="Waste %" value="<?= e((string) ($item['waste_percent_override'] ?? '')) ?>"></div>
                <div class="col-md-2"><input class="form-control" name="items[<?= $index ?>][pack_size]" placeholder="Pack" value="<?= e((string) ($item['pack_size'] ?? '')) ?>"></div>
                <div class="col-md-2"><select class="form-select" name="items[<?= $index ?>][yield_mode]"><?php foreach (['NONE','ROLL','SHEET'] as $mode): ?><option <?= (($item['yield_mode'] ?? 'NONE') === $mode) ? 'selected' : '' ?>><?= e($mode) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><input class="form-control" name="items[<?= $index ?>][condition]" placeholder="Condition: CODE, EQ, YES" value="<?php if (!empty($item['condition_json'])) { $c = json_decode((string) $item['condition_json'], true); if (is_array($c) && isset($c['input'])) { echo e($c['input'] . ', ' . ($c['op'] ?? 'EQ') . ', ' . ($c['value'] ?? '')); } } ?>"></div>
            </div>
        <?php endforeach; ?>
        <?= field_error($errors, 'items') ?>
    </div></section>
    <button class="btn btn-sf btn-lg" type="submit">Save recipe</button>
</form>
<?php if ($recipe !== null): ?>
    <div class="sf-action-row mt-3">
        <a class="btn btn-outline-light" href="<?= e(url('/recipes/test?recipe_id=' . $recipe['id'])) ?>">Test recipe</a>
        <form method="post" action="<?= e(url('/recipes/' . $recipe['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light" type="submit">Duplicate recipe</button></form>
        <?php if ((int) $recipe['active'] === 1 && can('recipes.deactivate')): ?>
            <form method="post" action="<?= e(url('/recipes/' . $recipe['id'] . '/deactivate')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light" type="submit">Deactivate</button></form>
        <?php endif; ?>
    </div>
    <section class="sf-panel mt-3"><div class="sf-panel-head"><h2>Versions</h2></div>
        <ul class="sf-feed"><?php foreach ($versions as $version): ?><li>Version <?= e((string) $version['version_number']) ?><small><?= e((string) $version['created_at']) ?></small></li><?php endforeach; ?></ul>
    </section>
<?php endif; ?>

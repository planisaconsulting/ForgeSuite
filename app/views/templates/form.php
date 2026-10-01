<?php require base_path('app/views/partials/flashes.php'); ?>
<h1><?= e($title) ?></h1>
<form class="sf-form sf-panel p-3" method="post" action="<?= e(url(empty($old['id']) ? '/templates' : '/templates/' . $old['id'])) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Code</label><input class="form-control" name="code" value="<?= e((string) ($old['code'] ?? '')) ?>" required><?= field_error($errors, 'code') ?></div>
        <div class="col-md-8"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required><?= field_error($errors, 'name') ?></div>
        <div class="col-md-4"><label class="form-label">Category</label><input class="form-control" name="category" value="<?= e((string) ($old['category'] ?? '')) ?>" placeholder="Boards"></div>
        <div class="col-md-4"><label class="form-label">Recipe</label><select class="form-select" name="recipe_id"><option value="">None</option><?php foreach ($recipes as $recipe): ?><option value="<?= e((string) $recipe['id']) ?>" <?= (int) ($old['recipe_id'] ?? 0) === (int) $recipe['id'] ? 'selected' : '' ?>><?= e((string) $recipe['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Finished product</label><select class="form-select" name="finished_product_id"><option value="">None</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>" <?= (int) ($old['finished_product_id'] ?? 0) === (int) $product['id'] ? 'selected' : '' ?>><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Default width</label><input class="form-control" name="default_w" value="<?= e((string) ($old['default_w'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Default height</label><input class="form-control" name="default_h" value="<?= e((string) ($old['default_h'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Default quantity</label><input class="form-control" name="default_q" value="<?= e((string) ($old['default_q'] ?? '1')) ?>"></div>
        <div class="col-12"><label class="form-label">Customer description</label><textarea class="form-control" name="customer_description" rows="2"><?= e((string) ($old['customer_description'] ?? '')) ?></textarea></div>
        <div class="col-12"><label class="form-label">Internal description</label><textarea class="form-control" name="internal_description" rows="2"><?= e((string) ($old['internal_description'] ?? '')) ?></textarea></div>
        <div class="col-12 form-check"><input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= !isset($old['id']) || !empty($old['active']) ? 'checked' : '' ?>><label for="active">Active</label></div>
    </div>
    <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Save template</button></div>
</form>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Add a configured sign</h1><p class="sf-muted"><?= e((string) $quote['quote_number']) ?></p></div>
<?php if (!empty($errors['_form'])): ?><div class="alert alert-danger"><?= e($errors['_form']) ?></div><?php endif; ?>
<form class="mb-3" method="get" action="<?= e(url('/quotes/' . $quote['id'] . '/configure')) ?>">
    <label class="form-label">Recipe</label>
    <div class="d-flex gap-2">
        <select class="form-select" name="recipe_id"><?php foreach ($recipes as $row): ?><option value="<?= e((string) $row['id']) ?>" <?= (int) ($recipe['id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= e((string) $row['name']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-outline-light" type="submit">Load</button>
    </div>
</form>
<?php if ($templates !== []): ?>
<form class="mb-3" method="get">
    <label class="form-label">Or a template</label>
    <div class="d-flex gap-2">
        <select class="form-select" name="template_id"><?php foreach ($templates as $row): ?><option value="<?= e((string) $row['id']) ?>"><?= e((string) $row['name']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-outline-light" type="submit">Use template</button>
    </div>
</form>
<?php endif; ?>
<?php if ($recipe !== null): ?>
<?php $defaults = []; if ($template !== null && !empty($template['default_inputs_json'])) { $decoded = json_decode((string) $template['default_inputs_json'], true); if (is_array($decoded)) { $defaults = $decoded; } } ?>
<form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/configure')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="recipe_id" value="<?= e((string) $recipe['id']) ?>">
    <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Width mm</label><input class="form-control form-control-lg" name="W" value="<?= e((string) ($old['W'] ?? $defaults['W'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Height mm</label><input class="form-control form-control-lg" name="H" value="<?= e((string) ($old['H'] ?? $defaults['H'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Quantity</label><input class="form-control form-control-lg" name="Q" value="<?= e((string) ($old['Q'] ?? $defaults['Q'] ?? '1')) ?>"></div>
        <?php foreach ($inputs as $input): ?>
            <div class="col-md-4"><label class="form-label"><?= e((string) $input['label']) ?></label>
                <?php if ((string) $input['input_type'] === 'BOOLEAN'): ?>
                    <select class="form-select" name="<?= e((string) $input['code']) ?>"><option value="NO">No</option><option value="YES">Yes</option></select>
                <?php elseif ((string) $input['input_type'] === 'SELECT'): ?>
                    <select class="form-select" name="<?= e((string) $input['code']) ?>"><?php foreach ((array) json_decode((string) ($input['options_json'] ?? '[]'), true) as $option): ?><option><?= e((string) $option) ?></option><?php endforeach; ?></select>
                <?php else: ?>
                    <input class="form-control" name="<?= e((string) $input['code']) ?>" value="<?= e((string) ($input['default_value'] ?? '')) ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="col-12"><label class="form-label">Customer description</label><textarea class="form-control" name="customer_description" rows="2"><?= e((string) ($template['customer_description'] ?? '')) ?></textarea></div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-outline-light" type="submit" formaction="<?= e(url('/quotes/' . $quote['id'] . '/configure/preview')) ?>">Calculate</button>
        <button class="btn btn-sf" type="submit">Add to quotation</button>
    </div>
</form>
<?php endif; ?>
<?php if (is_array($preview) && !empty($preview['ok'])): ?>
<section class="sf-panel mt-3"><div class="p-3">
    <h2>Customer view</h2>
    <p><?= e((string) ($recipe['name'] ?? 'Sign')) ?> · qty <?= e((string) ($preview['inputs']['Q'] ?? '')) ?> · <?= e(money((string) $preview['selling_price'])) ?></p>
    <?php if ($canCost): ?>
        <h2>Internal</h2>
        <p>Cost <?= e(money((string) $preview['total_cost'])) ?> · markup <?= e((string) $preview['markup_percent']) ?>% · GP <?= e(money((string) $preview['gross_profit'])) ?> · margin <?= e((string) ($preview['gross_margin'] ?? '—')) ?>%</p>
        <ul><?php foreach ($preview['components'] as $row): ?><li><?= e((string) $row['description']) ?> · <?= e((string) $row['costed_quantity']) ?> <?= e((string) $row['unit']) ?> · waste <?= e((string) $row['waste_percent']) ?>% · <?= e(money((string) $row['total_cost'])) ?></li><?php endforeach; ?></ul>
        <p>Stages: <?php foreach ($preview['production_route'] as $stage): ?><?= e((string) $stage['name']) ?> <?php endforeach; ?></p>
    <?php endif; ?>
</div></section>
<?php endif; ?>

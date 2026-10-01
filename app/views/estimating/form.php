<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>New estimate</h1><p class="sf-muted">Totals are calculated on the server. A typed line total is ignored.</p></div>
<form method="post" action="<?= e(url('/estimates')) ?>" class="sf-panel p-3">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Type</label>
            <select class="form-select form-select-lg" name="estimate_type">
                <?php foreach (\App\Services\EstimateService::TYPES as $type): ?><option><?= e($type) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8"><label class="form-label">Notes</label><input class="form-control form-control-lg" name="notes"></div>
        <div class="col-12"><label class="form-label">Browser total, ignored</label><input class="form-control" name="subtotal_cost" value="0"></div>
    </div>
    <h2 class="h5 mt-4">Components</h2>
    <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="row g-2 mb-2">
            <div class="col-md-3"><input class="form-control" name="description[]" placeholder="Description"></div>
            <div class="col-md-2"><select class="form-select" name="component_type[]"><?php foreach (['MATERIAL','LABOUR','MACHINE','TRAVEL','INSTALLATION','SUBCONTRACT','OTHER'] as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><input class="form-control" name="estimated_quantity[]" placeholder="Quantity"></div>
            <div class="col-md-2"><input class="form-control" name="unit_cost_snapshot[]" placeholder="Unit cost"></div>
            <div class="col-md-3"><input class="form-control" name="quantity_formula[]" placeholder="Optional formula"></div>
            <input type="hidden" name="estimated_cost[]" value="999999">
        </div>
    <?php endfor; ?>
    <button class="btn btn-sf btn-lg mt-3" type="submit">Calculate and save</button>
</form>

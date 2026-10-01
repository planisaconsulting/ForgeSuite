<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Record acceptance</h1>
    <p class="sf-muted mb-0"><?= e((string) $quote['quote_number']) ?> revision <?= e((string) $quote['revision_number']) ?>. This locks the prices.</p>
</div>
<form class="sf-panel sf-form" method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/accept')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="accepted_by_name">Accepted by <span class="sf-req">*</span></label>
            <input class="form-control" id="accepted_by_name" name="accepted_by_name" value="<?= e((string) ($_POST['accepted_by_name'] ?? '')) ?>">
            <?= field_error($errors, 'accepted_by_name') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="acceptance_method">How it was received</label>
            <select class="form-select" id="acceptance_method" name="acceptance_method">
                <?php foreach ($methods as $method): ?>
                    <option value="<?= e($method->value) ?>"><?= e($method->label()) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'acceptance_method') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="acceptance_reference">Reference</label>
            <input class="form-control" id="acceptance_reference" name="acceptance_reference" placeholder="Email subject, PO, message">
        </div>
        <div class="col-12">
            <label class="form-label" for="acceptance_notes">Notes</label>
            <textarea class="form-control" id="acceptance_notes" name="acceptance_notes" rows="3"></textarea>
        </div>
    </div>
    <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Accept quotation</button></div>
</form>

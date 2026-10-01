<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Convert to a job</h1>
    <p class="sf-muted mb-0"><?= e((string) $quote['quote_number']) ?> revision <?= e((string) $quote['revision_number']) ?> stays the commercial basis. Production tracking is Phase 3.</p>
</div>
<form class="sf-panel sf-form" method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/convert')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="title">Job title <span class="sf-req">*</span></label>
            <input class="form-control" id="title" name="title" value="<?= e((string) ($old['title'] ?? '')) ?>">
            <?= field_error($errors, 'title') ?>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="target_date">Target date</label>
            <input class="form-control" type="date" id="target_date" name="target_date" value="<?= e((string) ($old['target_date'] ?? '')) ?>">
            <?= field_error($errors, 'target_date') ?>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="priority">Priority</label>
            <select class="form-select" id="priority" name="priority">
                <?php foreach (['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($old['priority'] ?? 'NORMAL') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php if (!empty($errors['_form'])): ?><div class="alert alert-danger sf-alert mt-3"><?= e($errors['_form']) ?></div><?php endif; ?>
    <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Create job</button></div>
</form>

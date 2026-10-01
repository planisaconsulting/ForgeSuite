<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1><?= e($title) ?></h1></div>
<form class="sf-panel sf-form" method="post" action="<?= e(url(isset($old['id']) ? '/opportunities/' . $old['id'] : '/opportunities')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="customer_id">Customer <span class="sf-req">*</span></label>
            <select class="form-select" id="customer_id" name="customer_id" <?= isset($old['id']) ? 'disabled' : '' ?>>
                <option value="">Choose</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e((string) $customer['id']) ?>" <?= (string) ($old['customer_id'] ?? '') === (string) $customer['id'] ? 'selected' : '' ?>><?= e(customer_label($customer)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($old['id'])): ?><input type="hidden" name="customer_id" value="<?= e((string) $old['customer_id']) ?>"><?php endif; ?>
            <?= field_error($errors, 'customer_id') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="title">Title <span class="sf-req">*</span></label>
            <input class="form-control" id="title" name="title" value="<?= e((string) ($old['title'] ?? '')) ?>">
            <?= field_error($errors, 'title') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label">Source</label>
            <select class="form-select" name="source">
                <?php foreach ($sources as $source): ?>
                    <option value="<?= e($source->value) ?>" <?= ($old['source'] ?? '') === $source->value ? 'selected' : '' ?>><?= e($source->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status->value) ?>" <?= ($old['status'] ?? 'NEW') === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Salesperson</label>
            <select class="form-select" name="assigned_to">
                <option value="">Unassigned</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= (string) ($old['assigned_to'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e((string) $user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label">Estimated value</label><input class="form-control" name="estimated_value" value="<?= e((string) ($old['estimated_value'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Probability %</label><input class="form-control" name="probability_percent" value="<?= e((string) ($old['probability_percent'] ?? '')) ?>"><?= field_error($errors, 'probability_percent') ?></div>
        <div class="col-md-4"><label class="form-label">Expected close</label><input class="form-control" type="date" name="expected_close_date" value="<?= e((string) ($old['expected_close_date'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Next follow-up</label><input class="form-control" type="date" name="next_follow_up_date" value="<?= e((string) ($old['next_follow_up_date'] ?? '')) ?>"></div>
        <div class="col-md-6">
            <label class="form-label">Contact</label>
            <select class="form-select" name="contact_id">
                <option value="">None</option>
                <?php foreach ($contacts as $contact): ?>
                    <option value="<?= e((string) $contact['id']) ?>" <?= (string) ($old['contact_id'] ?? '') === (string) $contact['id'] ? 'selected' : '' ?>><?= e((string) $contact['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e((string) ($old['description'] ?? '')) ?></textarea></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"><?= e((string) ($old['notes'] ?? '')) ?></textarea></div>
    </div>
    <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Save</button></div>
</form>

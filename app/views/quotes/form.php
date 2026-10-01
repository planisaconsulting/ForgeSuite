<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>New quotation</h1>
    <p class="sf-muted mb-0">An opportunity is optional. You can quote a customer directly.</p>
</div>
<form class="sf-panel sf-form" method="post" action="<?= e(url('/quotes')) ?>">
    <?= csrf_field() ?>
    <?php if (!empty($old['opportunity_id'])): ?>
        <input type="hidden" name="opportunity_id" value="<?= e((string) $old['opportunity_id']) ?>">
    <?php endif; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="customer_id">Customer <span class="sf-req">*</span></label>
            <select class="form-select" id="customer_id" name="customer_id" required>
                <option value="">Choose</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e((string) $customer['id']) ?>" <?= (string) ($old['customer_id'] ?? '') === (string) $customer['id'] ? 'selected' : '' ?>><?= e(customer_label($customer)) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'customer_id') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="contact_id">Contact</label>
            <select class="form-select" id="contact_id" name="contact_id">
                <option value="">None</option>
                <?php foreach ($contacts as $contact): ?>
                    <option value="<?= e((string) $contact['id']) ?>" <?= (string) ($old['contact_id'] ?? '') === (string) $contact['id'] ? 'selected' : '' ?>><?= e((string) $contact['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'contact_id') ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="quote_date">Quote date</label>
            <input class="form-control" type="date" id="quote_date" name="quote_date" value="<?= e((string) ($old['quote_date'] ?? date('Y-m-d'))) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="pricing_level_id">Pricing level</label>
            <select class="form-select" id="pricing_level_id" name="pricing_level_id">
                <?php foreach ($levels as $level): ?>
                    <option value="<?= e((string) $level['id']) ?>"><?= e((string) $level['code']) ?> · <?= e((string) $level['markup_percent']) ?>% markup</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="assigned_to">Salesperson</label>
            <select class="form-select" id="assigned_to" name="assigned_to">
                <?php foreach ($users as $user): ?>
                    <?php if ((int) $user['active'] !== 1) { continue; } ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= (string) ($old['assigned_to'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e((string) $user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="vat_mode">VAT</label>
            <select class="form-select" id="vat_mode" name="vat_mode">
                <?php foreach ($modes as $mode): ?>
                    <option value="<?= e($mode->value) ?>" <?= ($old['vat_mode'] ?? 'EXCLUSIVE') === $mode->value ? 'selected' : '' ?>><?= e($mode->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="sf-form-actions">
        <button class="btn btn-sf" type="submit">Create draft</button>
    </div>
</form>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1><?= e($title) ?></h1></div>
<?php if (!empty($errors['_form'])): ?><div class="alert alert-danger"><?= e($errors['_form']) ?></div><?php endif; ?>
<form class="sf-form" method="post" action="<?= e(url(empty($old['id']) ? '/surveys' : '/surveys/' . $old['id'])) ?>">
    <?= csrf_field() ?>
    <div class="sf-panel mb-3"><div class="p-3 row g-3">
        <?php if (empty($old['id'])): ?>
            <div class="col-12"><label class="form-label" for="customer_id">Customer</label>
                <select class="form-select form-select-lg" id="customer_id" name="customer_id" required>
                    <option value="">Choose</option>
                    <?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>" <?= (int) ($old['customer_id'] ?? 0) === (int) $customer['id'] ? 'selected' : '' ?>><?= e(customer_label($customer)) ?></option><?php endforeach; ?>
                </select><?= field_error($errors, 'customer_id') ?></div>
        <?php else: ?>
            <input type="hidden" name="customer_id" value="<?= e((string) $old['customer_id']) ?>">
        <?php endif; ?>
        <div class="col-12"><label class="form-label" for="site_name">Site</label><input class="form-control form-control-lg" id="site_name" name="site_name" required value="<?= e((string) ($old['site_name'] ?? '')) ?>"><?= field_error($errors, 'site_name') ?></div>
        <div class="col-12"><label class="form-label">Address</label><input class="form-control mb-2" name="address_line_1" value="<?= e((string) ($old['address_line_1'] ?? '')) ?>" placeholder="Street"><input class="form-control" name="address_line_2" value="<?= e((string) ($old['address_line_2'] ?? '')) ?>" placeholder="Building"></div>
        <div class="col-md-4"><input class="form-control" name="city" placeholder="City" value="<?= e((string) ($old['city'] ?? '')) ?>"></div>
        <div class="col-md-4"><input class="form-control" name="province" placeholder="Province" value="<?= e((string) ($old['province'] ?? '')) ?>"></div>
        <div class="col-md-4"><input class="form-control" name="postal_code" placeholder="Postal code" value="<?= e((string) ($old['postal_code'] ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Site contact</label><input class="form-control" name="site_contact_name" value="<?= e((string) ($old['site_contact_name'] ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="site_contact_phone" value="<?= e((string) ($old['site_contact_phone'] ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Survey date</label><input class="form-control" type="date" name="survey_date" value="<?= e((string) ($old['survey_date'] ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= ($old['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <input type="hidden" name="opportunity_id" value="<?= e((string) ($old['opportunity_id'] ?? '')) ?>">
        <input type="hidden" name="quote_id" value="<?= e((string) ($old['quote_id'] ?? '')) ?>">
        <input type="hidden" name="job_id" value="<?= e((string) ($old['job_id'] ?? '')) ?>">
    </div></div>
    <div class="sf-panel mb-3"><div class="sf-panel-head"><h2>Installation conditions</h2></div><div class="p-3 row g-3">
        <div class="col-md-6"><label class="form-label">Indoor or outdoor</label><select class="form-select" name="environment"><option value="">—</option><?php foreach (['INDOOR','OUTDOOR','BOTH'] as $env): ?><option <?= ($old['environment'] ?? '') === $env ? 'selected' : '' ?>><?= e($env) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Surface</label><input class="form-control" name="surface_type" value="<?= e((string) ($old['surface_type'] ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Mounting height mm</label><input class="form-control" name="mounting_height_mm" inputmode="decimal" value="<?= e((string) ($old['mounting_height_mm'] ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Access</label><input class="form-control" name="access_difficulty" value="<?= e((string) ($old['access_difficulty'] ?? '')) ?>"></div>
        <div class="col-12 d-flex flex-wrap gap-3">
            <?php foreach (['ladder_required' => 'Ladder', 'scaffolding_required' => 'Scaffolding', 'cherry_picker_required' => 'Cherry picker', 'electrical_supply' => 'Power on site'] as $key => $label): ?>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="1" <?= !empty($old[$key]) ? 'checked' : '' ?>> <?= e($label) ?></label>
            <?php endforeach; ?>
        </div>
        <div class="col-12"><label class="form-label">Power location</label><input class="form-control" name="power_location" value="<?= e((string) ($old['power_location'] ?? '')) ?>"></div>
        <div class="col-12"><label class="form-label">Working at height</label><textarea class="form-control" name="height_notes" rows="2"><?= e((string) ($old['height_notes'] ?? '')) ?></textarea></div>
        <div class="col-12"><label class="form-label">Traffic or public access</label><textarea class="form-control" name="traffic_notes" rows="2"><?= e((string) ($old['traffic_notes'] ?? '')) ?></textarea></div>
        <div class="col-12"><label class="form-label">Special access</label><textarea class="form-control" name="special_access" rows="2"><?= e((string) ($old['special_access'] ?? '')) ?></textarea></div>
        <div class="col-12"><label class="form-label">Access notes</label><textarea class="form-control" name="access_notes" rows="2"><?= e((string) ($old['access_notes'] ?? '')) ?></textarea></div>
        <div class="col-12"><label class="form-label">Installation notes</label><textarea class="form-control" name="installation_notes" rows="2"><?= e((string) ($old['installation_notes'] ?? '')) ?></textarea></div>
        <div class="col-12"><label class="form-label">Electrical notes</label><textarea class="form-control" name="electrical_notes" rows="2"><?= e((string) ($old['electrical_notes'] ?? '')) ?></textarea></div>
        <div class="col-12"><label class="form-label">General notes</label><textarea class="form-control" name="general_notes" rows="3"><?= e((string) ($old['general_notes'] ?? '')) ?></textarea></div>
    </div></div>
    <button class="btn btn-sf btn-lg" type="submit">Save survey</button>
</form>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>New lead</h1></div>
<form class="sf-panel p-3" method="post" action="<?= e(url('/leads')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Company</label><input class="form-control" name="company_name" value="<?= e((string) ($old['company_name'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e((string) ($old['phone'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e((string) ($old['email'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Source</label>
            <select class="form-select" name="source" required>
                <?php foreach ($sources as $source): ?>
                    <option value="<?= e((string) $source['code']) ?>"><?= e((string) $source['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Service interest</label><input class="form-control" name="service_interest" value="<?= e((string) ($old['service_interest'] ?? '')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Estimated value</label><input class="form-control" name="estimated_value" value="<?= e((string) ($old['estimated_value'] ?? '')) ?>"></div>
        <div class="col-md-3"><label class="form-label">Assign</label>
            <select class="form-select" name="assigned_to"><option value="">Unassigned</option>
                <?php foreach ($users as $user): if ((int) $user['active'] !== 1) continue; ?>
                    <option value="<?= e((string) $user['id']) ?>"><?= e((string) $user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="4" required><?= e((string) ($old['message'] ?? '')) ?></textarea></div>
        <?php if (!empty($errors)): ?><div class="col-12 text-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
        <div class="col-12"><button class="btn btn-sf" type="submit">Save lead</button></div>
    </div>
</form>

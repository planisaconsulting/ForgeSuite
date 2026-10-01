<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Integrations</h1><p class="sf-muted mb-0">Passwords and webhook secrets are write-only after they are stored.</p></div>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">Website leads</h2>
    <p class="mb-0">POST /api/public/leads is part of this application. See the website lead notes in the project documentation.</p>
</section>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">Email</h2>
    <p>Mode <?= e($emailMode) ?>. Host <?= e($smtpHost !== '' ? $smtpHost : 'not set') ?>. Password <?= $passwordStored ? 'stored' : 'not stored' ?>.</p>
</section>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">WhatsApp</h2>
    <p class="mb-0">Manual mode. Click-to-WhatsApp prepares a message and does not mark it delivered.</p>
</section>
<?php if ($lastEvent): ?><p>Last webhook <?= e((string) $lastEvent['received_at']) ?> <?= e((string) $lastEvent['provider']) ?> <?= e((string) $lastEvent['status']) ?></p><?php endif; ?>
<?php if ($lastError): ?><p>Last email failure <?= e((string) $lastError['created_at']) ?> <?= e((string) ($lastError['failure_reason'] ?? '')) ?></p><?php endif; ?>
<?php if (can('integrations.manage')): ?>
<form class="sf-panel p-3" method="post" action="<?= e(url('/admin/integrations')) ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label">Email mode</label>
            <select class="form-select" name="email_delivery_mode">
                <?php foreach (['off', 'log', 'smtp'] as $mode): ?><option <?= $emailMode === $mode ? 'selected' : '' ?>><?= e($mode) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label">SMTP host</label><input class="form-control" name="smtp_host" value="<?= e($smtpHost) ?>"></div>
        <div class="col-md-4"><label class="form-label">SMTP password</label><input class="form-control" type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= $passwordStored ? 'Stored. Leave blank to keep.' : 'Not stored' ?>"></div>
        <div class="col-md-6"><label class="form-label">Webhook secret</label><input class="form-control" type="password" name="webhook_secret" autocomplete="new-password" placeholder="<?= $webhookStored ? 'Stored. Leave blank to keep.' : 'Not stored' ?>"></div>
        <div class="col-12"><button class="btn btn-sf" type="submit">Save</button></div>
    </div>
</form>
<?php endif; ?>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1><?= e($title) ?></h1></div>
<?php if (($mode ?? '') === 'api' && can('api.manage')): ?>
<form method="post" action="<?= e(url('/admin/api-clients')) ?>" class="sf-panel p-3 mb-3 d-grid gap-2">
    <?= csrf_field() ?>
    <input class="form-control form-control-lg" name="name" placeholder="Client name" required>
    <input class="form-control" name="scopes" placeholder="customers.read, jobs.read">
    <button class="btn btn-sf btn-lg" type="submit">Create client</button>
</form>
<?php endif; ?>
<?php if (($mode ?? '') === 'webhooks' && can('webhooks.manage')): ?>
<form method="post" action="<?= e(url('/admin/webhooks')) ?>" class="sf-panel p-3 mb-3 d-grid gap-2">
    <?= csrf_field() ?>
    <input class="form-control form-control-lg" name="name" placeholder="Subscription name" required>
    <input class="form-control" name="endpoint_url" placeholder="https://example.com/hooks">
    <input class="form-control" name="events" placeholder="invoice.issued, job.completed">
    <button class="btn btn-sf btn-lg" type="submit">Save subscription</button>
</form>
<?php endif; ?>
<section class="sf-panel">
    <ul class="sf-feed">
        <?php foreach ($rows as $row): ?>
            <li><?= e((string) ($row['name'] ?? $row['provider'] ?? $row['status'] ?? 'Row')) ?> <small><?= e((string) ($row['client_identifier'] ?? $row['endpoint_url'] ?? $row['message'] ?? $row['status'] ?? '')) ?></small></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php if (!empty($secret)): ?>
    <section class="sf-panel p-3 mt-3"><p class="mb-0">Copy this secret now. It is not shown again. <?= e((string) $secret) ?></p></section>
<?php endif; ?>

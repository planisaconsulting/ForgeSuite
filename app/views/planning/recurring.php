<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div><h1>Recurring jobs</h1><p class="sf-muted mb-0">A template creates a follow-up when it is due. It does not create an invoice.</p></div>
    <?php if ($canManage): ?><form method="post" action="<?= e(url('/recurring/run')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light" type="submit">Run due templates</button></form><?php endif; ?>
</div>
<section class="sf-panel mb-3">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No recurring templates.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['name']) ?><small><?= e(customer_label($row)) ?> · <?= e((string) $row['frequency_type']) ?> · next <?= e((string) $row['next_run_date']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<?php if ($canManage): ?>
<form class="sf-panel p-3 row g-2" method="post" action="<?= e(url('/recurring')) ?>">
    <?= csrf_field() ?>
    <div class="col-md-3"><input class="form-control form-control-lg" name="customer_id" placeholder="Customer id" required></div>
    <div class="col-md-5"><input class="form-control form-control-lg" name="name" placeholder="Name" required></div>
    <div class="col-md-4"><select class="form-select form-select-lg" name="frequency_type"><?php foreach (\App\Services\RecurringJobService::FREQUENCIES as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><input class="form-control form-control-lg" type="date" name="next_run_date" required></div>
    <div class="col-md-3"><input class="form-control form-control-lg" name="interval_value" value="1"></div>
    <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Save template</button></div>
</form>
<?php endif; ?>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Maintenance</h1><p class="sf-muted mb-0">A service window makes the resource unavailable for that time.</p></div>
<section class="sf-panel mb-3">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No open maintenance.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['resource_name']) ?> · <?= e((string) $row['maintenance_type']) ?><small><?= e((string) ($row['scheduled_date'] ?? '')) ?> · <?= e((string) $row['description']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<?php if ($canManage): ?>
<form class="sf-panel p-3 row g-2" method="post" action="<?= e(url('/maintenance')) ?>">
    <?= csrf_field() ?>
    <div class="col-md-4"><select class="form-select form-select-lg" name="resource_id"><?php foreach ($resources as $resource): ?><option value="<?= e((string) $resource['id']) ?>"><?= e((string) $resource['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><select class="form-select form-select-lg" name="maintenance_type"><?php foreach (\App\Services\MaintenanceService::TYPES as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-6"><input class="form-control form-control-lg" type="datetime-local" name="downtime_start" required></div>
    <div class="col-md-6"><input class="form-control form-control-lg" type="datetime-local" name="downtime_end" required></div>
    <div class="col-md-8"><input class="form-control form-control-lg" name="description" placeholder="What is being done" required></div>
    <div class="col-md-4"><input class="form-control form-control-lg" name="cost" placeholder="Cost" value="0"></div>
    <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Schedule maintenance</button></div>
</form>
<?php endif; ?>

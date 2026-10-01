<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div><h1>Resources</h1><p class="sf-muted mb-0">People, teams, machines, vehicles, equipment, and work areas.</p></div>
    <?php if ($canManage): ?><a class="btn btn-sf" href="<?= e(url('/resources/new')) ?>">New resource</a><?php endif; ?>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-light" href="<?= e(url('/resources')) ?>">All</a>
    <?php foreach (['USER','TEAM','MACHINE','VEHICLE','EQUIPMENT','WORK_AREA','SUBCONTRACTOR'] as $option): ?>
        <a class="btn <?= $type === $option ? 'btn-sf' : 'btn-outline-light' ?>" href="<?= e(url('/resources?type=' . $option)) ?>"><?= e($option) ?></a>
    <?php endforeach; ?>
</div>
<section class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No resources in this list.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['name']) ?><small><?= e((string) $row['code']) ?> · <?= e((string) $row['resource_type']) ?> · <?= e((string) $row['status']) ?> · <?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<?php if (can('staff_availability.manage') || can('resources.manage')): ?>
<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Unavailability</h2></div>
    <form class="row g-2 p-3" method="post" action="<?= e(url('/resources/unavailability')) ?>">
        <?= csrf_field() ?>
        <div class="col-md-4"><select class="form-select form-select-lg" name="resource_id"><?php foreach ($rows as $row): ?><option value="<?= e((string) $row['id']) ?>"><?= e((string) $row['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><select class="form-select form-select-lg" name="reason_type"><?php foreach (\App\Services\ResourceService::UNAVAILABLE as $reason): ?><option><?= e($reason) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><input class="form-control form-control-lg" type="datetime-local" name="start_datetime" required></div>
        <div class="col-md-6"><input class="form-control form-control-lg" type="datetime-local" name="end_datetime" required></div>
        <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Save unavailability</button></div>
    </form>
</section>
<?php endif; ?>

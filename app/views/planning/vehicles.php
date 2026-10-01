<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Vehicles</h1></div>
<section class="sf-panel mb-3">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No vehicles yet. Add one as a vehicle resource, then its registration can be stored from administration.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['registration_number']) ?> · <?= e((string) $row['name']) ?><small><?= e((string) ($row['make'] ?? '')) ?> <?= e((string) ($row['model'] ?? '')) ?> · <?= e((string) $row['odometer']) ?> km · <?= e((string) $row['status']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<?php if ($canManage && $rows !== []): ?>
<form class="sf-panel p-3 row g-2" method="post" action="<?= e(url('/vehicles/mileage')) ?>">
    <?= csrf_field() ?>
    <div class="col-md-4"><select class="form-select form-select-lg" name="vehicle_resource_id"><?php foreach ($rows as $row): ?><option value="<?= e((string) $row['id']) ?>"><?= e((string) $row['registration_number']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input class="form-control form-control-lg" name="job_id" placeholder="Job id"></div>
    <div class="col-md-3"><input class="form-control form-control-lg" name="start_odometer" placeholder="Start km" required></div>
    <div class="col-md-3"><input class="form-control form-control-lg" name="end_odometer" placeholder="End km" required></div>
    <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Record mileage</button></div>
</form>
<?php endif; ?>

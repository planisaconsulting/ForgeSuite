<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Maintenance</h1>
    <p class="sf-muted">Due work can raise an internal notice or a draft request. It does not invoice the customer.</p>
</div>
<p>
    <?php foreach ([30, 60, 90, 180] as $window): ?>
        <a href="<?= e(url('/service/maintenance?days=' . $window)) ?>"><?= (int) $window ?> days</a>
    <?php endforeach; ?>
</p>
<section class="sf-panel mb-3">
    <ul class="sf-feed"><?php foreach ($due as $row): ?><li><?= e((string) $row['asset_number']) ?> · <?= e((string) $row['plan_name']) ?><small>Due <?= e((string) $row['next_due_on']) ?></small></li><?php endforeach; ?></ul>
    <?php if ($due === []): ?><p class="p-3">Nothing due in the next <?= (int) $days ?> days.</p><?php endif; ?>
</section>
<?php if (can('maintenance.manage')): ?>
<form class="sf-panel" method="post" action="<?= e(url('/service/maintenance')) ?>"><?= csrf_field() ?>
    <div class="p-3 row g-2">
        <div class="col-md-4"><input class="form-control" name="name" placeholder="Plan name" required></div>
        <div class="col-md-2"><input class="form-control" name="interval_months" placeholder="Months"></div>
        <div class="col-md-2"><input class="form-control" name="interval_days" placeholder="Days"></div>
        <div class="col-md-2"><input class="form-control" name="checklist_name" placeholder="Checklist"></div>
        <div class="col-md-2"><button class="btn btn-sf" type="submit">Save plan</button></div>
    </div>
</form>
<?php endif; ?>
<ul class="mt-3"><?php foreach ($plans as $plan): ?><li><?= e((string) $plan['name']) ?> · <?= (int) $plan['interval_months'] ?> months / <?= (int) $plan['interval_days'] ?> days</li><?php endforeach; ?></ul>

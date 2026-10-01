<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Working calendar</h1><p class="sf-muted mb-0">Public holidays live here. The scheduler reads these dates. It does not keep its own holiday list.</p></div>
<section class="sf-panel mb-3">
    <ul class="sf-feed"><?php foreach ($schedules as $row): ?>
        <li><?= e((string) $row['name']) ?><small><?= (int) $row['is_default'] === 1 ? 'Default' : 'Extra' ?> · break <?= e((string) $row['break_minutes']) ?> min</small></li>
    <?php endforeach; ?></ul>
</section>
<section class="sf-panel mb-3">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No calendar exceptions stored.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['exception_date']) ?> · <?= e((string) $row['name']) ?><small><?= (int) $row['working_day_override'] === 1 ? 'Working' : 'Closed' ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<?php if ($canManage): ?>
<form class="sf-panel p-3 row g-2" method="post" action="<?= e(url('/resources/calendar')) ?>">
    <?= csrf_field() ?>
    <div class="col-md-3"><input class="form-control form-control-lg" type="date" name="exception_date" required></div>
    <div class="col-md-5"><input class="form-control form-control-lg" name="name" placeholder="Holiday name" required></div>
    <div class="col-md-4"><button class="btn btn-sf btn-lg" type="submit">Save date</button></div>
</form>
<?php endif; ?>

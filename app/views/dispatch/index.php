<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Dispatch</h1></div>
<section class="sf-panel p-3 mb-3">
    <form method="post" action="<?= e(url('/dispatch')) ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12 col-md-4"><input class="form-control" name="job_id" inputmode="numeric" placeholder="Job id" required></div>
        <div class="col-12 col-md-4">
            <select class="form-select" name="dispatch_type">
                <?php foreach (\App\Domain\WorkshopCodes::dispatchTypes() as $type): ?><option><?= e($type) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-4"><button class="btn btn-sf w-100" type="submit">Open dispatch</button></div>
    </form>
</section>
<section class="sf-panel">
    <?php if ($rows === []): ?><p class="p-3 mb-0">No dispatches yet.</p><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><a href="<?= e(url('/dispatch/' . $row['id'])) ?>"><?= e((string) $row['dispatch_number']) ?></a>
                <small><?= e((string) $row['job_number']) ?> · <?= e((string) $row['dispatch_type']) ?> · <?= e((string) $row['status']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

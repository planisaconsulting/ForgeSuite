<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Contractors</h1></div>
<section class="sf-panel p-3 mb-3">
    <form method="post" action="<?= e(url('/logistics/contractors')) ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12 col-md-4"><input class="form-control" name="company_name" placeholder="Company" required></div>
        <div class="col-6 col-md-3"><select class="form-select" name="contractor_type"><?php foreach (\App\Services\ContractorWorkService::TYPES as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-6 col-md-3"><select class="form-select" name="status"><option>PENDING</option><option selected>ACTIVE</option><option>SUSPENDED</option><option>INACTIVE</option></select></div>
        <div class="col-12 col-md-2"><button class="btn btn-sf w-100" type="submit">Save</button></div>
    </form>
</section>
<section class="sf-panel">
    <?php if ($rows === []): ?><p class="p-3 mb-0">No contractors yet.</p><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['company_name']) ?> <small><?= e((string) $row['contractor_type']) ?> · <?= e((string) $row['status']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

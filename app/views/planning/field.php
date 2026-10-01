<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $installation['job_number']) ?></p>
    <h1><?= e((string) $installation['title']) ?></h1>
    <p><?= e(customer_label($installation)) ?> · <?= e((string) $installation['status']) ?></p>
</div>
<section class="sf-panel p-3 mb-3">
    <p><strong>Address.</strong> <?= e((string) ($installation['site_address'] ?: $installation['job_site'] ?? 'No address recorded')) ?></p>
    <p><strong>Contact.</strong> <?= e((string) ($installation['site_contact_name'] ?? '')) ?> <?= e((string) ($installation['site_contact_phone'] ?? $installation['customer_phone'] ?? '')) ?></p>
    <p class="mb-0"><strong>Notes.</strong> <?= e((string) ($installation['installation_notes'] ?: $installation['job_install_notes'] ?? '')) ?></p>
</section>
<div class="d-grid gap-2 mb-3">
    <?php foreach (['START_TRAVEL' => 'Start travel', 'ARRIVED' => 'Arrived', 'START_INSTALLATION' => 'Start installation', 'COMPLETE' => 'Finish installation'] as $action => $label): ?>
        <form method="post" action="<?= e(url('/field/' . $installation['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action_name" value="<?= e($action) ?>"><button class="btn btn-sf btn-lg w-100" type="submit"><?= e($label) ?></button></form>
    <?php endforeach; ?>
</div>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Checklist</h2></div>
    <?php if ($checklist === []): ?><div class="sf-empty"><p>No checklist on this installation.</p></div><?php else: ?>
        <?php foreach ($checklist as $item): ?>
            <form class="p-3 border-bottom" method="post" action="<?= e(url('/jobs/' . $installation['job_id'] . '/installations/' . $installation['id'] . '/checklist/' . $item['id'])) ?>">
                <?= csrf_field() ?>
                <?php if ((int) ($item['checked'] ?? 0) !== 1): ?><input type="hidden" name="checked" value="1"><?php endif; ?>
                <button class="btn btn-outline-light btn-lg w-100" type="submit"><?= (int) ($item['checked'] ?? 0) === 1 ? 'Done · ' : '' ?><?= e((string) $item['label']) ?></button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<form class="sf-panel p-3 mt-3" method="post" action="<?= e(url('/jobs/' . $installation['job_id'] . '/installations/' . $installation['id'] . '/photo')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label class="form-label">Photo</label>
    <input class="form-control form-control-lg mb-2" type="file" name="file" accept="image/*" capture="environment">
    <button class="btn btn-outline-light btn-lg" type="submit">Add photo</button>
</form>
<p class="mt-3"><a href="<?= e(url('/jobs/' . $installation['job_id'] . '?tab=materials')) ?>">Record material on the job</a></p>

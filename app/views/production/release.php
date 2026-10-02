<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1">Accepted is not released</p>
    <h1>Release to production</h1>
    <p class="mb-0"><?= e((string) $job['job_number']) ?> · <?= e((string) ($job['company_name'] ?: trim((string) ($job['first_name'] ?? '') . ' ' . (string) ($job['last_name'] ?? '')))) ?> · preparation <?= e((string) $job['preparation_status']) ?></p>
</div>
<?php if ($current): ?>
    <section class="sf-panel mb-3">
        <p>Current release <strong><?= e((string) $current['release_number']) ?></strong> R<?= (int) $current['release_version'] ?> · <?= e((string) $current['status']) ?></p>
        <a href="<?= e(url('/production/releases/' . (int) $current['id'] . '/pack')) ?>">Production pack</a>
    </section>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($readiness['checks'] as $check): ?>
        <div class="col-md-4">
            <section class="sf-panel h-100">
                <p class="mb-1"><strong><?= e(str_replace('_', ' ', (string) $check['check_code'])) ?></strong></p>
                <p class="mb-1"><?= $check['result'] === 'PASS' ? '✓' : ($check['result'] === 'WARNING' ? '⚠' : ($check['result'] === 'BLOCK' ? '✗' : '–')) ?> <?= e((string) $check['result']) ?></p>
                <p class="sf-muted mb-0"><?= e((string) $check['message']) ?></p>
            </section>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($plan): ?>
    <section class="sf-panel mt-3">
        <h2 class="h5">Planning date</h2>
        <p><?= e((string) $plan['label']) ?>: <?= e((string) $plan['planning_date']) ?>. <?= e((string) $plan['capacity_note']) ?></p>
        <?php if ($plan['at_risk']): ?><p><?= e((string) $plan['reason']) ?></p><?php endif; ?>
    </section>
<?php endif; ?>
<?php if ($showCosts): ?>
    <section class="sf-panel mt-3">
        <p>Quoted cost snapshot <?= e((string) $job['quoted_cost_snapshot']) ?>. A bill of materials difference does not reprice the accepted quote.</p>
    </section>
<?php endif; ?>
<?php if (can('production.release.approve')): ?>
    <form method="post" class="sf-panel mt-3">
        <?= csrf_field() ?>
        <h2 class="h5">Release</h2>
        <p class="sf-muted">Leave the item blank to release the remaining quantity of every line. Stock is reserved only when you enter a product. It is not consumed.</p>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Item id</label><input class="form-control" name="item_id"></div>
            <div class="col-md-3"><label class="form-label">Quantity</label><input class="form-control" name="item_quantity"></div>
            <div class="col-md-6"><label class="form-label">Notes</label><input class="form-control" name="notes"></div>
            <div class="col-md-4"><label class="form-label">Reserve product</label><input class="form-control" name="reserve_product_id"></div>
            <div class="col-md-2"><label class="form-label">Reserve qty</label><input class="form-control" name="reserve_quantity"></div>
            <div class="col-md-2"><label class="form-label">Location</label><input class="form-control" name="reserve_location_id"></div>
            <div class="col-md-4"><label class="form-label">Idempotency key</label><input class="form-control" name="idempotency_key"></div>
        </div>
        <button class="btn btn-light mt-3" type="submit">Release to production</button>
    </form>
<?php endif; ?>
<?php if (can('production.change.request') || can('production.release.approve')): ?>
    <form method="post" action="<?= e(url('/production/jobs/' . (int) $job['id'] . '/change')) ?>" class="sf-panel mt-3">
        <?= csrf_field() ?>
        <h2 class="h5">Production change</h2>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Field</label><input class="form-control" name="field" placeholder="DIMENSIONS"></div>
            <div class="col-md-3"><label class="form-label">Source</label><select class="form-select" name="source"><option>CUSTOMER</option><option>DESIGN</option><option>PRODUCTION</option><option>SITE</option><option>SUPPLIER</option><option>MANAGEMENT</option><option>OTHER</option></select></div>
            <div class="col-md-6"><label class="form-label">Reason</label><input class="form-control" name="reason" required></div>
            <div class="col-12"><label class="form-label">Change</label><input class="form-control" name="change" required></div>
        </div>
        <button class="btn btn-outline-light mt-3" type="submit">Record impact</button>
    </form>
<?php endif; ?>

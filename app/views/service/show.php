<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker"><?= e((string) $request['priority']) ?> · <?= e((string) $request['source']) ?> · <?= (int) (new \DateTimeImmutable((string) $request['reported_at']))->diff(new \DateTimeImmutable('today'))->days ?> days</p>
    <h1><?= e((string) $request['request_number']) ?></h1>
    <p><?= e((string) $request['company_name']) ?><?php if ($asset): ?> · <a href="<?= e(url('/assets/' . $asset['id'])) ?>"><?= e((string) $asset['asset_number']) ?></a> <?= e((string) $asset['name']) ?><?php endif; ?></p>
</div>
<section class="sf-panel mb-3"><div class="p-3">
    <p><?= e((string) $request['description']) ?></p>
    <p class="mb-0">Status <?= e((string) $request['status']) ?>. Warranty candidate: <?= (int) $request['warranty_candidate'] === 1 ? 'yes, not approved' : 'no' ?>.</p>
    <?php if ($times['target_hours'] !== null): ?><p class="mb-0">Agreement response target <?= (int) $times['target_hours'] ?> hours. First response <?= e((string) ($times['first_response_hours'] ?? 'not yet')) ?>.</p><?php endif; ?>
</div></section>
<?php if ($warranties !== []): ?>
<section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Warranties on this asset</h2></div>
    <ul class="sf-feed"><?php foreach ($warranties as $row): ?><li><?= e((string) $row['warranty_type']) ?> <?= e((string) $row['start_date']) ?>–<?= e((string) $row['end_date']) ?> <?= $row['likely_active'] ? 'likely active' : e((string) $row['derived_status']) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<?php if (can('service_requests.manage')): ?>
<form class="sf-panel mb-3" method="post" action="<?= e(url('/service/' . $request['id'])) ?>"><?= csrf_field() ?>
    <input type="hidden" name="version" value="<?= (int) $request['version'] ?>">
    <div class="p-3 row g-2">
        <div class="col-md-4"><select class="form-select" name="status"><?php foreach (['TRIAGE','AWAITING_CUSTOMER','AWAITING_ASSESSMENT','AWAITING_QUOTE','AWAITING_APPROVAL','READY_TO_SCHEDULE','IN_PROGRESS','RESOLVED','CLOSED','CANCELLED'] as $status): ?><option <?= $request['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><select class="form-select" name="classification"><option value="">Classification</option><?php foreach (['PAID','WARRANTY','GOODWILL','INTERNAL','MAINTENANCE_CONTRACT'] as $class): ?><option <?= ($request['classification'] ?? '') === $class ? 'selected' : '' ?>><?= e($class) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><button class="btn btn-outline-light" type="submit">Update</button></div>
    </div>
</form>
<form class="sf-panel mb-3" method="post" action="<?= e(url('/service/' . $request['id'] . '/job')) ?>"><?= csrf_field() ?>
    <div class="p-3 row g-2">
        <div class="col-md-3"><input class="form-control" name="charge" placeholder="Customer charge, blank for R0"></div>
        <div class="col-md-3"><select class="form-select" name="classification"><option>PAID</option><option>WARRANTY</option><option>GOODWILL</option><option>INTERNAL</option><option>MAINTENANCE_CONTRACT</option></select></div>
        <div class="col-md-3"><input class="form-control" name="service_reason" placeholder="Reason if goodwill"></div>
        <div class="col-md-3"><button class="btn btn-sf" type="submit">Create quote and job</button></div>
    </div>
</form>
<?php endif; ?>
<p><a href="<?= e(url('/service/' . $request['id'] . '/report')) ?>">Service report</a></p>

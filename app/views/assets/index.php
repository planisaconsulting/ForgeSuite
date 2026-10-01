<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1">After sales</p>
        <h1>Customer assets</h1>
        <p class="sf-muted mb-0">The physical item at a site. A product is what we sell. A job item is what a job produced.</p>
    </div>
    <?php if ($canCreate): ?><a class="btn btn-sf" href="<?= e(url('/assets/new')) ?>">New asset</a><?php endif; ?>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Active</p><h2><?= (int) ($dashboard['active_count'] ?? 0) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Service due</p><h2><?= (int) ($dashboard['service_due'] ?? 0) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Under repair</p><h2><?= (int) ($dashboard['under_repair'] ?? 0) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Out of service</p><h2><?= (int) ($dashboard['out_of_service'] ?? 0) ?></h2></section></div>
</div>
<form class="sf-panel mb-3" method="get" action="<?= e(url('/assets')) ?>">
    <div class="row g-2">
        <div class="col-12 col-md-5"><input class="form-control" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Asset, customer, site, serial, job, or project"></div>
        <div class="col-6 col-md-3">
            <select class="form-select" name="status"><option value="">Status</option>
                <?php foreach (['PLANNED','ACTIVE','SERVICE_DUE','UNDER_REPAIR','OUT_OF_SERVICE','REPLACED','REMOVED'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2"><button class="btn btn-sf" type="submit">Search</button></div>
    </div>
</form>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Asset</th><th>Customer</th><th>Site</th><th>Status</th><th>Installed</th><th>Next service</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="<?= e(url('/assets/' . $row['id'])) ?>"><?= e((string) $row['asset_number']) ?></a><br><small><?= e((string) $row['name']) ?></small></td>
                    <td><?= e((string) $row['company_name']) ?></td>
                    <td><?= e((string) ($row['site_name'] ?? '')) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                    <td><?= e((string) ($row['installation_date'] ?? '')) ?></td>
                    <td><?= e((string) ($row['next_service_date'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($rows === []): ?><p class="p-3 mb-0">No assets match that search.</p><?php endif; ?>
</section>
<p class="mt-3"><a href="<?= e(url('/assets?page=' . ($page + 1) . '&q=' . urlencode((string) $filters['q']))) ?>">Next page</a>
    <?php if (can('assets.import')): ?> · <a href="<?= e(url('/assets/import')) ?>">Import</a><?php endif; ?>
    · <a href="<?= e(url('/service/reports/register')) ?>">Asset register</a></p>

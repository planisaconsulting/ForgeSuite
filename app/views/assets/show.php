<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1"><?= e((string) $asset['type_name']) ?> · <?= e((string) $asset['source']) ?></p>
        <h1><?= e((string) $asset['asset_number']) ?></h1>
        <p class="mb-0"><?= e((string) $asset['name']) ?></p>
        <p class="sf-muted mb-0"><?= e((string) $asset['company_name']) ?><?php if ($asset['site_name']): ?> · <?= e((string) $asset['site_name']) ?><?php endif; ?><?php if ($asset['location_description']): ?> · <?= e((string) $asset['location_description']) ?><?php endif; ?></p>
    </div>
    <div>
        <?php if (can('assets.generate_labels')): ?><a class="btn btn-outline-light" href="<?= e(url('/assets/' . $asset['id'] . '/label')) ?>">Label</a><?php endif; ?>
        <a class="btn btn-sf" href="<?= e(url('/service')) ?>">Service</a>
    </div>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Status</p><h2><?= e(str_replace('_', ' ', (string) $asset['status'])) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Health</p><h2><?= e(str_replace('_', ' ', (string) $health)) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Quantity</p><h2><?= e((string) $asset['quantity']) ?> <?= e((string) $asset['track_mode']) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Next service</p><h2><?= e((string) ($asset['next_service_date'] ?? '—')) ?></h2></section></div>
</div>
<?php if ($asset['source'] !== 'SIGN_FORGE'): ?><p class="sf-muted">Source <?= e((string) $asset['source']) ?>. Installation date and condition may have been supplied by the customer.</p><?php endif; ?>
<?php if ($asset['job_number']): ?><p>Original job <a href="<?= e(url('/jobs/' . $asset['original_job_id'])) ?>"><?= e((string) $asset['job_number']) ?></a><?php if ($asset['project_number']): ?> · Project <a href="<?= e(url('/projects/' . $asset['project_id'])) ?>"><?= e((string) $asset['project_number']) ?></a><?php endif; ?></p><?php endif; ?>

<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Components</h2></div>
    <?php if ($components === []): ?><p class="p-3">No serviceable components yet. Consumables stay on the job.</p><?php endif; ?>
    <ul class="sf-feed"><?php foreach ($components as $row): ?><li><?= e((string) $row['description']) ?> · <?= e((string) $row['quantity']) ?> · <?= e((string) $row['status']) ?><?php if ($row['serial_number']): ?> · <?= e((string) $row['serial_number']) ?><?php endif; ?></li><?php endforeach; ?></ul>
    <?php if (can('assets.manage_components')): ?>
        <form class="p-3" method="post" action="<?= e(url('/assets/' . $asset['id'] . '/components')) ?>"><?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-md-4"><input class="form-control" name="description" placeholder="Mean Well PSU" required></div>
                <div class="col-md-2"><input class="form-control" name="quantity" value="1"></div>
                <div class="col-md-2"><input class="form-control" name="serial_number" placeholder="Serial"></div>
                <div class="col-md-2"><input class="form-control" name="component_type" value="OTHER"></div>
                <div class="col-md-2"><button class="btn btn-sf" type="submit">Add</button></div>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Warranties</h2></div>
    <p class="px-3 sf-muted">A date can show a warranty as likely active. A person confirms the claim.</p>
    <ul class="sf-feed"><?php foreach ($warranties as $row): ?><li><?= e(str_replace('_', ' ', (string) $row['warranty_type'])) ?> · <?= e((string) $row['start_date']) ?> to <?= e((string) $row['end_date']) ?> · <?= e((string) $row['derived_status']) ?><?= $row['likely_active'] ? ' · likely active' : '' ?></li><?php endforeach; ?></ul>
    <?php if (can('assets.manage_warranties')): ?>
        <form class="p-3" method="post" action="<?= e(url('/assets/' . $asset['id'] . '/warranties')) ?>"><?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-md-4"><select class="form-select" name="warranty_type"><option>SIGN_FORGE_WORKMANSHIP</option><option>PRODUCT_MATERIAL</option><option>MANUFACTURER</option><option>INSTALLATION</option><option>CUSTOM</option></select></div>
                <div class="col-md-2"><input class="form-control" type="date" name="start_date" required></div>
                <div class="col-md-2"><input class="form-control" name="months" placeholder="Months"></div>
                <div class="col-md-2"><input class="form-control" name="provider_name" placeholder="Provider"></div>
                <div class="col-md-2"><button class="btn btn-sf" type="submit">Add</button></div>
                <div class="col-12"><input class="form-control" name="terms" placeholder="Terms as they were at installation"></div>
            </div>
        </form>
    <?php endif; ?>
</section>

<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Service history</h2></div>
    <?php if ($events === []): ?><p class="p-3">No service history yet.</p><?php endif; ?>
    <ul class="sf-feed"><?php foreach ($events as $event): ?><li><?= e((string) $event['happened_at']) ?> · <?= e((string) $event['event_type']) ?><small><?= e((string) $event['summary']) ?></small></li><?php endforeach; ?></ul>
</section>

<?php if ($showCosts && $lifetime !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Lifetime figures</h2></div>
    <ul class="sf-feed">
        <li>Original commercial value<small><?= e(money((string) $lifetime['original_commercial_value'])) ?></small></li>
        <li>Paid service commercial value<small><?= e(money((string) $lifetime['paid_service_commercial_value'])) ?></small></li>
        <li>Lifetime commercial value<small><?= e(money((string) $lifetime['lifetime_commercial_value'])) ?></small></li>
        <li>Internal lifetime cost<small><?= e(money((string) $lifetime['internal_lifetime_cost'])) ?></small></li>
        <li>Warranty internal cost<small><?= e(money((string) $lifetime['warranty_internal_cost'])) ?></small></li>
    </ul>
</section>
<?php endif; ?>

<?php if (can('service_requests.create')): ?>
<form class="sf-panel mb-3" method="post" action="<?= e(url('/service')) ?>"><?= csrf_field() ?>
    <input type="hidden" name="asset_id" value="<?= (int) $asset['id'] ?>">
    <input type="hidden" name="customer_id" value="<?= (int) $asset['customer_id'] ?>">
    <div class="sf-panel-head"><h2>Report a problem</h2></div>
    <div class="p-3 row g-2">
        <div class="col-md-4"><select class="form-select" name="problem_category"><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['code']) ?>"><?= e((string) $category['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><select class="form-select" name="priority"><option>LOW</option><option selected>NORMAL</option><option>HIGH</option><option>URGENT</option></select></div>
        <div class="col-md-4"><select class="form-select" name="source"><option>PHONE</option><option>EMAIL</option><option>WHATSAPP</option><option>INTERNAL</option></select></div>
        <div class="col-12"><textarea class="form-control" name="description" required placeholder="What failed"></textarea></div>
        <div class="col-12"><button class="btn btn-sf" type="submit">Log service request</button></div>
    </div>
</form>
<?php endif; ?>

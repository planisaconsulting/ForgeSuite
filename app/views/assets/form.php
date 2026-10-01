<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>New customer asset</h1>
    <p class="sf-muted">Use this for a sign Sign-Forge did not manufacture, or when you are recording one by hand. Stickers and print runs stay job items unless the product is marked to create an asset.</p>
</div>
<form class="sf-panel" method="post" action="<?= e(url('/assets')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Customer id</label><input class="form-control" name="customer_id" value="<?= e((string) ($old['customer_id'] ?? '')) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Project id</label><input class="form-control" name="project_id" value="<?= e((string) ($old['project_id'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Site id</label><input class="form-control" name="project_site_id" value="<?= e((string) ($old['project_site_id'] ?? '')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Type</label>
            <select class="form-select" name="asset_type_id"><?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>"><?= e((string) $type['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="col-md-3"><label class="form-label">Source</label>
            <select class="form-select" name="source">
                <?php foreach (['SIGN_FORGE','THIRD_PARTY','UNKNOWN'] as $source): ?><option <?= (($old['source'] ?? '') === $source) ? 'selected' : '' ?>><?= e($source) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-8"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e((string) ($old['description'] ?? '')) ?>" placeholder="Say when a date or condition was supplied by the customer"></div>
        <div class="col-md-2"><label class="form-label">Quantity</label><input class="form-control" name="quantity" value="<?= e((string) ($old['quantity'] ?? '1')) ?>"></div>
        <div class="col-md-2"><label class="form-label">Tracking</label>
            <select class="form-select" name="track_mode"><option>INDIVIDUAL</option><option>GROUP</option></select>
        </div>
        <div class="col-md-4"><label class="form-label">Customer reference</label><input class="form-control" name="customer_asset_reference"></div>
        <div class="col-md-4"><label class="form-label">Serial</label><input class="form-control" name="serial_number"></div>
        <div class="col-md-4"><label class="form-label">Installed</label><input class="form-control" type="date" name="installation_date"></div>
        <div class="col-12"><label class="form-label">Where it stands</label><input class="form-control" name="location_description" placeholder="Main entrance, northern boundary"></div>
        <?php if (($errors ?? []) !== []): ?><div class="col-12"><p class="text-warning mb-0"><?= e(implode(' ', $errors)) ?></p></div><?php endif; ?>
        <div class="col-12"><button class="btn btn-sf" type="submit">Create asset</button></div>
    </div>
</form>

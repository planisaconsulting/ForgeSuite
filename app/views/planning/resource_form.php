<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>New resource</h1></div>
<?php if ($errors !== []): ?><div class="alert alert-danger"><?= e((string) reset($errors)) ?></div><?php endif; ?>
<form class="sf-panel p-3" method="post" action="<?= e(url('/resources')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Type</label><select class="form-select form-select-lg" name="resource_type"><?php foreach ($types as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Code</label><input class="form-control form-control-lg" name="code" value="<?= e((string) ($resource['code'] ?? '')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Name</label><input class="form-control form-control-lg" name="name" value="<?= e((string) ($resource['name'] ?? '')) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Daily capacity</label><input class="form-control form-control-lg" name="default_daily_capacity" value="480"></div>
        <div class="col-md-4"><label class="form-label">Capacity type</label><select class="form-select form-select-lg" name="capacity_type"><?php foreach (\App\Services\ResourceService::CAPACITY_TYPES as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Can run at once</label><input class="form-control form-control-lg" name="concurrent_capacity" value="1"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description"></textarea></div>
    </div>
    <button class="btn btn-sf btn-lg mt-3" type="submit">Save resource</button>
</form>

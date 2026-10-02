<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head d-flex justify-content-between gap-3">
    <div>
        <h1>Specifications</h1>
        <p class="mb-0">Only an approved current version is offered as a production default. History is kept.</p>
    </div>
</div>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-4"><input class="form-control" name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>" placeholder="Code, name, or category"></div>
    <div class="col-md-2"><select class="form-select" name="status"><option value="">Any status</option><?php foreach (['DRAFT','REVIEW','APPROVED','SUPERSEDED','ARCHIVED'] as $status): ?><option <?= (($_GET['status'] ?? '') === $status) ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-light" type="submit">Filter</button></div>
</form>
<?php if (can('specifications.create')): ?>
<form class="sf-panel mb-3" method="post" action="<?= e(url('/specifications')) ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-3"><input class="form-control" name="code" placeholder="LBX-ACM-002" required></div>
        <div class="col-md-4"><input class="form-control" name="name" placeholder="Name" required></div>
        <div class="col-md-3"><select class="form-select" name="estimator_type"><?php foreach (\App\Services\SpecificationService::ESTIMATORS as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><button class="btn btn-light" type="submit">Create draft</button></div>
    </div>
</form>
<?php endif; ?>
<section class="sf-panel">
    <table class="table table-sm mb-0">
        <thead><tr><th>Code</th><th>Name</th><th>Version</th><th>Status</th><th>Effective</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/specifications/' . $row['id'])) ?>"><?= e((string) $row['code']) ?></a></td>
                <td><?= e((string) $row['name']) ?></td>
                <td><?= (int) $row['version'] ?></td>
                <td><?= e((string) $row['status']) ?></td>
                <td><?= e((string) $row['effective_from']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

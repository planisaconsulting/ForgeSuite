<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>KPI targets</h1><p class="sf-muted mb-0">Reports compare actuals with these values. They do not label a result as a failure.</p></div>
<form method="post" action="<?= e(url('/admin/targets')) ?>" class="sf-panel p-3">
    <?= csrf_field() ?>
    <?php foreach ($rows as $row): ?>
        <div class="row g-2 mb-2 align-items-center">
            <div class="col-md-5"><strong><?= e((string) $row['name']) ?></strong><br><small><?= e((string) $row['kpi_code']) ?> · <?= e((string) $row['comparison_type']) ?></small></div>
            <div class="col-md-3"><input class="form-control" name="target[<?= e((string) $row['id']) ?>]" value="<?= e((string) $row['target_value']) ?>"></div>
            <div class="col-md-2 form-check"><input class="form-check-input" type="checkbox" name="active[<?= e((string) $row['id']) ?>]" value="1" <?= (int) $row['active'] === 1 ? 'checked' : '' ?> id="kpi-<?= e((string) $row['id']) ?>"><label for="kpi-<?= e((string) $row['id']) ?>">Active</label></div>
        </div>
    <?php endforeach; ?>
    <button class="btn btn-sf" type="submit">Save targets</button>
</form>

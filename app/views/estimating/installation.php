<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Installation estimator</h1><p class="sf-muted">Labour, travel, equipment, and accommodation stay separate. Access multipliers are shown.</p></div>
<form method="post" class="sf-panel p-3 mb-3">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label">Access</label><select class="form-select form-select-lg" name="access_category"><?php foreach ($access as $row): ?><option value="<?= e((string) $row['code']) ?>"><?= e((string) $row['name']) ?> × <?= e((string) $row['multiplier']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Height</label><select class="form-select form-select-lg" name="height_category"><?php foreach ($heights as $row): ?><option value="<?= e((string) $row['code']) ?>"><?= e((string) $row['name']) ?></option><?php endforeach; ?></select></div>
        <?php foreach (['installers' => 'Installers', 'site_hours' => 'Site hours', 'hourly_rate' => 'Hourly rate', 'distance_km' => 'Distance km', 'trips' => 'Trips', 'rate_per_km' => 'Rate per km', 'travel_hours' => 'Travel hours', 'nights' => 'Nights', 'cost_per_night' => 'Cost per night', 'subcontract_cost' => 'Subcontract'] as $name => $label): ?>
            <div class="col-6 col-md-3"><label class="form-label"><?= e($label) ?></label><input class="form-control form-control-lg" name="<?= e($name) ?>" value="<?= e((string) ($name === 'trips' || $name === 'installers' ? '1' : ($_POST[$name] ?? ''))) ?>"></div>
        <?php endforeach; ?>
    </div>
    <button class="btn btn-sf btn-lg mt-3" type="submit">Estimate</button>
</form>
<?php if (is_array($result)): ?>
<section class="sf-panel p-3">
    <p>Access <?= e((string) $result['access_category']) ?> × <?= e((string) $result['access_multiplier']) ?> · <?= e((string) $result['trips']) ?> trips · <?= e((string) $result['height_category']) ?></p>
    <div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Component</th><th>Detail</th><th>Cost</th></tr></thead><tbody>
    <?php foreach ($result['components'] as $row): ?><tr><td><?= e($row['description']) ?></td><td><?= e($row['detail']) ?></td><td><?= e($row['estimated_cost']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p class="mt-3 mb-0">Total <?= e((string) $result['total_cost']) ?></p>
</section>
<?php endif; ?>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Vehicle branding</h1><p class="sf-muted">Measured areas only. Design setup is once for the fleet unless you tick every vehicle.</p></div>
<form method="post" class="sf-panel p-3 mb-3">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label">Category</label><select class="form-select form-select-lg" name="category"><?php foreach ($categories as $category): ?><option><?= e($category) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Vehicles</label><input class="form-control form-control-lg" name="vehicle_quantity" value="1"></div>
        <div class="col-md-3"><label class="form-label">Make / model</label><input class="form-control form-control-lg" name="vehicle_name" placeholder="Ford Ranger"></div>
        <div class="col-md-3"><label class="form-label">Removal</label><select class="form-select form-select-lg" name="removal_level"><?php foreach (array_keys($removal) as $level): ?><option><?= e($level) ?></option><?php endforeach; ?><option>MANUAL</option></select></div>
        <?php foreach ($areas as $area): ?><div class="col-6 col-md-3"><label class="form-label"><?= e(str_replace('_', ' ', $area)) ?> m²</label><input class="form-control" name="<?= e($area) ?>" value="0"></div><?php endforeach; ?>
        <?php foreach (['waste_percent' => 'Waste %', 'vinyl_cost_m2' => 'Vinyl / m²', 'laminate_cost_m2' => 'Laminate / m²', 'design_hours' => 'Design hours', 'application_hours' => 'Application hours each', 'hourly_rate' => 'Hourly rate'] as $name => $label): ?>
            <div class="col-6 col-md-3"><label class="form-label"><?= e($label) ?></label><input class="form-control form-control-lg" name="<?= e($name) ?>" value="0"></div>
        <?php endforeach; ?>
        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="setup_each" value="1"> Charge design setup on every vehicle</label></div>
    </div>
    <button class="btn btn-sf btn-lg mt-3" type="submit">Estimate fleet</button>
</form>
<?php if (is_array($result)): ?>
<section class="sf-panel p-3">
    <p>Billable <?= e((string) $result['billable_area_m2']) ?> m² · Estimated physical <?= e((string) $result['estimated_physical_m2']) ?> m² · Design hours <?= e((string) $result['design_hours']) ?></p>
    <div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Component</th><th>Detail</th><th>Cost</th></tr></thead><tbody>
    <?php foreach ($result['components'] as $row): if ((float) $row['estimated_cost'] <= 0) { continue; } ?><tr><td><?= e($row['description']) ?></td><td><?= e($row['detail']) ?></td><td><?= e($row['estimated_cost']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p class="mt-3 mb-0">Total <?= e((string) $result['total_cost']) ?></p>
</section>
<?php endif; ?>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Roll optimiser</h1><p class="sf-muted">Across-roll packing. The lower linear length is shown. You choose the roll.</p></div>
<form method="post" class="sf-panel p-3 mb-3">
    <?= csrf_field() ?>
    <div class="row g-2">
        <?php foreach (['roll_width_mm' => 'Roll width', 'part_width_mm' => 'Graphic width', 'part_height_mm' => 'Graphic length', 'quantity' => 'Quantity', 'horizontal_spacing_mm' => 'Horizontal spacing', 'vertical_spacing_mm' => 'Vertical spacing', 'edge_margin_mm' => 'Edge margin', 'roll_widths' => 'Widths to compare', 'roll_cost' => 'Cost per metre'] as $name => $label): ?>
            <div class="col-6 col-md-3"><label class="form-label"><?= e($label) ?></label><input class="form-control form-control-lg" name="<?= e($name) ?>" value="<?= e((string) ($name === 'roll_widths' ? ($_POST['roll_widths'] ?? '1050,1370,1520') : ($name === 'roll_cost' ? ($_POST['roll_cost'] ?? '0') : ($input[$name] ?? '')))) ?>"></div>
        <?php endforeach; ?>
        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="allow_rotation" value="1" checked> Allow rotation</label> <label class="form-check ms-3"><input class="form-check-input" type="checkbox" name="direction_sensitive" value="1"> Direction sensitive</label></div>
    </div>
    <button class="btn btn-sf btn-lg mt-3" type="submit">Compare</button>
</form>
<?php if (is_array($result)): ?>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">Best layout found on the entered roll</h2>
    <p><?= e((string) $result['across']) ?> across · <?= e((string) $result['rows']) ?> rows · <?= e((string) $result['linear_metres']) ?> m · waste <?= e((string) ($result['waste_percent'] ?? '—')) ?>% · rotated <?= !empty($result['rotated']) ? 'yes' : 'no' ?></p>
    <p>Artwork <?= e((string) $result['artwork_area_m2']) ?> m² · Consumed <?= e((string) $result['consumed_area_m2']) ?> m² · Unused width <?= e((string) $result['unused_width_mm']) ?> mm</p>
</section>
<?php endif; ?>
<?php if ($options !== []): ?>
<section class="sf-panel p-3"><h2 class="h5">Roll options</h2><p>Cheapest material and smallest waste can differ. Nothing is selected for you.</p>
<div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Option</th><th>Metres</th><th>Waste</th><th>Cost</th><th>Fits</th></tr></thead><tbody>
<?php foreach ($options as $option): ?><tr><td><?= e($option['label']) ?></td><td><?= e((string) $option['linear_metres']) ?></td><td><?= e((string) ($option['waste_percent'] ?? '—')) ?>%</td><td><?= e((string) $option['material_cost']) ?></td><td><?= $option['fits'] ? 'Yes' : 'No' ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>

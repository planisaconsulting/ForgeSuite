<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Sheet optimiser</h1><p class="sf-muted">Best grid found for identical rectangles. Kerf and edge margin are included. This is not a perfect layout.</p></div>
<form method="post" class="sf-panel p-3 mb-3">
    <?= csrf_field() ?>
    <div class="row g-2">
        <?php foreach (['sheet_width_mm' => 'Sheet width', 'sheet_height_mm' => 'Sheet height', 'part_width_mm' => 'Part width', 'part_height_mm' => 'Part height', 'quantity' => 'Quantity', 'kerf_mm' => 'Kerf mm', 'edge_margin_mm' => 'Edge margin mm', 'sheet_cost' => 'Cost per sheet', 'setup_cost' => 'Setup cost once', 'setup_minutes' => 'Machine setup min', 'run_minutes' => 'Run min each', 'batch_size' => 'Batch size', 'machine_rate' => 'Machine rate / hour', 'markup_percent' => 'Markup % for breaks', 'product_id' => 'Product id for offcuts'] as $name => $label): ?>
            <div class="col-6 col-md-3"><label class="form-label"><?= e($label) ?></label><input class="form-control form-control-lg" name="<?= e($name) ?>" value="<?= e((string) ($input[$name] ?? $_POST[$name] ?? '')) ?>"></div>
        <?php endforeach; ?>
        <div class="col-12 d-flex gap-3">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="allow_rotation" value="1" checked> Allow rotation</label>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="direction_sensitive" value="1"> Direction sensitive</label>
        </div>
        <div class="col-md-3"><label class="form-label">Manual sheets</label><input class="form-control form-control-lg" name="manual_sheets"></div>
        <div class="col-md-6"><label class="form-label">Override note</label><input class="form-control form-control-lg" name="override_note"></div>
    </div>
    <button class="btn btn-sf btn-lg mt-3" type="submit">Calculate</button>
    <?php if (can('estimates.create')): ?><button class="btn btn-outline-light btn-lg mt-3" name="save_estimate" value="1" type="submit">Save estimate</button><?php endif; ?>
</form>
<?php if (is_array($result)): ?>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">Best layout found</h2>
    <p>Parts per sheet <?= e((string) $result['per_sheet']) ?> · Sheets <?= e((string) $result['sheets']) ?> · Rotated <?= !empty($result['rotated']) ? 'yes' : 'no' ?></p>
    <p>Used area <?= e((string) $result['used_area_m2']) ?> m² · Consumed <?= e((string) $result['consumed_area_m2']) ?> m² · Unused <?= e((string) $result['unused_area_m2']) ?> m² · Waste <?= e((string) ($result['waste_percent'] ?? '—')) ?>%</p>
    <?php foreach ($result['remainders'] ?? [] as $rem): ?><p class="mb-1"><?= e((string) $rem['note']) ?> <?= e((string) $rem['width_mm']) ?> × <?= e((string) $rem['height_mm']) ?> mm</p><?php endforeach; ?>
    <?php if (!empty($result['override'])): ?><p><?= e((string) ($result['override']['error'] ?? 'Manual sheets kept beside the calculated count.')) ?></p><?php endif; ?>
    <?php
        $sheetW = (float) ($result['sheet_width_mm'] ?? 1);
        $sheetH = (float) ($result['sheet_height_mm'] ?? 1);
    ?>
    <svg viewBox="0 0 <?= e((string) $sheetW) ?> <?= e((string) $sheetH) ?>" width="100%" style="max-width:640px;background:#111;border:1px solid #666">
        <rect x="0" y="0" width="<?= e((string) $sheetW) ?>" height="<?= e((string) $sheetH) ?>" fill="none" stroke="#ddd" stroke-width="8"></rect>
        <?php foreach ($result['placements'] as $part): if ((int) $part['sheet'] !== 1) { continue; } ?>
            <rect x="<?= e((string) $part['x']) ?>" y="<?= e((string) $part['y']) ?>" width="<?= e((string) $part['width']) ?>" height="<?= e((string) $part['height']) ?>" fill="#1f6f4a" stroke="#9fefc4" stroke-width="4"></rect>
        <?php endforeach; ?>
    </svg>
    <p class="sf-muted mt-2">Sheet 1. Further sheets use the same grid.</p>
</section>
<?php endif; ?>
<?php if ($offcuts !== []): ?>
<section class="sf-panel p-3 mb-3"><h2 class="h5">Offcut matches</h2><p>Choose use offcut or use full sheet. Stock is not consumed.</p>
<ul><?php foreach ($offcuts as $offcut): ?><li><?= e((string) $offcut['label']) ?> <?= e((string) $offcut['code']) ?> <?= e((string) $offcut['width_mm']) ?> × <?= e((string) $offcut['height_mm']) ?> score <?= e((string) $offcut['score']) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>
<?php if ($breaks !== []): ?>
<section class="sf-panel p-3"><h2 class="h5">Quantity breaks</h2><p class="sf-muted">Setup is once. Sheet count is recalculated. Markup is labelled markup, not margin.</p>
<div class="table-responsive"><table class="table table-dark table-sm mb-0"><thead><tr><th>Qty</th><th>Sheets</th><th>Unit cost</th><th>Total</th><th>Unit sell</th><th>Margin</th></tr></thead><tbody>
<?php foreach ($breaks as $row): ?><tr><td><?= e($row['quantity']) ?></td><td><?= e($row['sheets']) ?></td><td><?= e($row['unit_cost']) ?></td><td><?= e($row['total_cost']) ?></td><td><?= e($row['unit_sell']) ?></td><td><?= e((string) ($row['margin_percent'] ?? '')) ?>%</td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>

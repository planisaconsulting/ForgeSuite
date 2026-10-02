<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1><?= e(str_replace('_', ' ', $type)) ?></h1>
    <p>The result is a draft bill of materials. It is not added to a quote until you confirm it.</p>
</div>
<form method="post" action="<?= e(url('/estimating/signage/' . strtolower($type))) ?>" enctype="multipart/form-data" class="sf-panel">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Approved specification</label>
            <select class="form-select" name="specification_id">
                <option value="">None</option>
                <?php foreach ($specs as $spec): ?><option value="<?= (int) $spec['id'] ?>"><?= e((string) $spec['code']) ?> v<?= (int) $spec['version'] ?> · <?= e((string) $spec['name']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Quantity</label><input class="form-control" name="quantity" value="1"></div>
        <div class="col-md-3"><label class="form-label">Unit</label><select class="form-select" name="unit"><option>mm</option><option>cm</option><option>m</option></select></div>
        <?php if ($type === 'VEHICLE_WRAP'): ?>
            <div class="col-md-6"><label class="form-label">Vehicle template</label><select class="form-select" name="template_id"><option value="">Manual panels</option><?php foreach ($templates as $template): ?><option value="<?= (int) $template['id'] ?>"><?= e((string) $template['year_from'] . ' ' . $template['make_name'] . ' ' . $template['model_name']) ?><?= (int) $template['verified'] === 1 ? ' · verified' : ' · unverified' ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Selected roll mm</label><input class="form-control" name="roll_width_mm" value="1370"></div>
            <div class="col-md-3"><label class="form-label">Coverage</label><select class="form-select" name="coverage"><?php foreach ($coverage as $item): ?><option><?= e($item) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label">Panels</label><div class="d-flex flex-wrap gap-2"><?php foreach ($panels as $panel): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="panels[]" value="<?= e($panel) ?>"> <?= e(str_replace('_', ' ', $panel)) ?></label><?php endforeach; ?></div></div>
            <div class="col-md-4"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="laminate" value="1"> Laminate</label></div>
            <div class="col-md-4"><label class="form-label">Complexity</label><select class="form-select" name="complexity"><option>STANDARD</option><option>MODERATE</option><option>COMPLEX</option><option>SPECIALIST</option></select></div>
        <?php endif; ?>
        <?php if (in_array($type, ['LIGHTBOX', 'PYLON', 'PANEL_FRAME'], true)): ?>
            <div class="col-md-3"><label class="form-label">Width</label><input class="form-control" name="width"></div>
            <div class="col-md-3"><label class="form-label">Height</label><input class="form-control" name="height"></div>
            <div class="col-md-3"><label class="form-label">Depth</label><input class="form-control" name="depth" value="200"></div>
        <?php endif; ?>
        <?php if ($type === 'LIGHTBOX'): ?>
            <div class="col-md-3"><label class="form-label">Faces</label><select class="form-select" name="sides"><option value="1">Single-sided</option><option value="2">Double-sided</option></select></div>
            <div class="col-md-3"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="illuminated" value="1"> Illuminated</label></div>
        <?php endif; ?>
        <?php if ($type === 'PYLON'): ?>
            <div class="col-md-3"><label class="form-label">Overall height</label><input class="form-control" name="overall_height"></div>
            <div class="col-md-3"><label class="form-label">Cabinet width</label><input class="form-control" name="cabinet_width"></div>
            <div class="col-md-3"><label class="form-label">Cabinet height</label><input class="form-control" name="cabinet_height"></div>
            <div class="col-md-3"><label class="form-label">Foundation allowance</label><input class="form-control" name="foundation_allowance" placeholder="Manual amount"></div>
        <?php endif; ?>
        <?php if ($type === 'PANEL_FRAME'): ?>
            <div class="col-md-3"><label class="form-label">Sheet width mm</label><input class="form-control" name="sheet_width_mm" value="2450"></div>
            <div class="col-md-3"><label class="form-label">Sheet height mm</label><input class="form-control" name="sheet_height_mm" value="1225"></div>
            <div class="col-md-3"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="allow_rotation" value="1" checked> Allow rotation</label></div>
            <div class="col-md-3"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="frame" value="1"> Frame</label></div>
        <?php endif; ?>
        <?php if ($type === 'CHANNEL_LETTER'): ?>
            <div class="col-md-4"><label class="form-label">SVG geometry</label><input class="form-control" type="file" name="geometry" accept=".svg,image/svg+xml"></div>
            <div class="col-md-2"><label class="form-label">Nominal height mm</label><input class="form-control" name="nominal_height_mm"></div>
            <div class="col-md-2"><label class="form-label">Return depth mm</label><input class="form-control" name="return_depth" value="100"></div>
            <div class="col-md-2"><label class="form-label">Allowance %</label><input class="form-control" name="allowance_percent" value="5"></div>
            <div class="col-md-2"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="trim_cap" value="1"> Trim cap</label></div>
            <div class="col-md-2"><label class="form-check mt-4"><input class="form-check-input" type="checkbox" name="illuminated" value="1"> Illuminated</label></div>
        <?php endif; ?>
        <?php if (in_array($type, ['CHANNEL_LETTER', 'LIGHTBOX', 'PYLON'], true)): ?>
            <div class="col-md-4"><label class="form-label">LED profile</label><select class="form-select" name="led_profile_id"><option value="">None</option><?php foreach ($profiles as $profile): if ((string) $profile['kind'] !== 'LED_MODULE') continue; ?><option value="<?= (int) $profile['id'] ?>"><?= e((string) $profile['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">PSU profile</label><select class="form-select" name="psu_profile_id"><option value="">None</option><?php foreach ($profiles as $profile): if ((string) $profile['kind'] !== 'PSU') continue; ?><option value="<?= (int) $profile['id'] ?>"><?= e((string) $profile['name']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-light" type="submit">Calculate and save</button>
        <button class="btn btn-outline-light" name="what_if" value="1" type="submit">What-if, do not save</button>
    </div>
</form>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1><?= $row === null ? 'What-if result' : 'Saved calculation' ?></h1>
    <p class="mb-0">Nothing here is a fabrication drawing or a certificate.</p>
</div>
<?php if (($result['warnings'] ?? []) !== []): ?>
    <div class="alert alert-warning"><?php foreach ($result['warnings'] as $warning): ?><div><?= e((string) $warning) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<section class="sf-panel">
    <h2>Quantities</h2>
    <ul>
        <?php foreach (array_merge($result['materials'] ?? [], $result['components'] ?? []) as $line): ?>
            <li><?= e((string) $line['description']) ?>: <?= e((string) $line['quantity']) ?> <?= e((string) ($line['unit'] ?? '')) ?><?php if (!empty($line['original_quantity'])): ?> (calculated <?= e((string) $line['original_quantity']) ?>)<?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($showCosts && isset($result['costs'])): ?>
        <p>Material <?= e((string) $result['costs']['material']) ?>. Labour <?= e((string) $result['costs']['labour']) ?>. Machine <?= e((string) $result['costs']['machine']) ?>. Installation <?= e((string) $result['costs']['installation']) ?>.</p>
        <p>Cost <?= e((string) $result['costs']['total']) ?>. Sell <?= e((string) $result['costs']['sell']) ?>. Gross profit <?= e((string) $result['costs']['profit']) ?>. Margin <?= e((string) ($result['costs']['margin'] ?? '')) ?>%.</p>
        <p class="sf-muted"><?= e((string) ($result['costs']['margin_rule'] ?? '')) ?></p>
    <?php endif; ?>
    <?php if (($result['roll_options'] ?? []) !== []): ?>
        <h2 class="h5">Roll widths</h2>
        <p>The comparison does not change the selected material.</p>
        <ul><?php foreach ($result['roll_options'] as $option): ?><li><?= e((string) $option['roll_width_mm']) ?> mm · <?= e((string) $option['linear_metres']) ?> m · consumed <?= e((string) $option['consumed_area_m2']) ?> m2<?= empty($option['fits']) ? ' · does not fit' : '' ?><?= !empty($option['selected']) ? ' · selected' : '' ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if (!empty($result['weight_kg'])): ?><p>Estimated weight <?= e((string) $result['weight_kg']) ?> kg. This is not a structural rating.</p><?php endif; ?>
    <?php if (!empty($result['schematic'])): ?><div class="mt-3"><?= $result['schematic'] ?></div><p class="sf-muted"><?= e(\App\Services\EngineeringLimits::SCHEMATIC) ?></p><?php endif; ?>
</section>
<section class="sf-panel mt-3">
    <h2>How this was calculated</h2>
    <ul><?php foreach ($result['trace'] ?? [] as $step): ?>
        <li><strong><?= e((string) $step['label']) ?></strong>. <?= e((string) $step['method']) ?>. <?= e((string) $step['detail']) ?> = <?= e((string) $step['result']) ?></li>
    <?php endforeach; ?></ul>
</section>

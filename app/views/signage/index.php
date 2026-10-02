<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1">Manufacturing estimates</p>
    <h1>Advanced estimating</h1>
    <p class="mb-0">These tools calculate material, labour, and load. They do not certify structure or electrical installation.</p>
</div>
<div class="row g-3">
    <?php foreach (['VEHICLE_WRAP' => 'Vehicle wrap', 'CHANNEL_LETTER' => 'Channel letters', 'LIGHTBOX' => 'Lightbox', 'PYLON' => 'Pylon', 'PANEL_FRAME' => 'Panel and frame'] as $code => $label): ?>
        <div class="col-md-4">
            <section class="sf-panel h-100">
                <h2 class="h5"><?= e($label) ?></h2>
                <p class="sf-muted"><?= (int) ($dashboard['by_type'][$code] ?? 0) ?> saved</p>
                <a class="btn btn-outline-light" href="<?= e(url('/estimating/signage/' . strtolower($code))) ?>">Open</a>
            </section>
        </div>
    <?php endforeach; ?>
</div>
<section class="sf-panel mt-3">
    <p>Awaiting technical review: <?= (int) $dashboard['review'] ?>. Outside specification: <?= (int) $dashboard['outside'] ?>.</p>
    <h2 class="h5">Most used specifications</h2>
    <ul><?php foreach ($dashboard['specs'] as $spec): ?><li><?= e((string) $spec['specification_code']) ?> · <?= (int) $spec['total'] ?></li><?php endforeach; ?></ul>
    <p><a href="<?= e(url('/estimating/signage/reports/variance')) ?>">Estimate versus actual</a></p>
</section>

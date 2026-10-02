<div class="sf-page-head"><h1>Estimate versus actual</h1></div>
<section class="sf-panel">
    <p>Completed jobs in the sample: <?= (int) $report['sample_size'] ?>.</p>
    <p>Estimated material <?= e((string) $report['material_estimated']) ?>. Actual material <?= e((string) $report['material_actual']) ?>.</p>
    <p>Estimated labour <?= e((string) $report['labour_estimated']) ?>. Actual labour <?= e((string) $report['labour_actual']) ?>.</p>
    <p><?= e((string) $report['note']) ?></p>
</section>

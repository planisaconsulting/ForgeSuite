<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Pricing health</h1><p class="sf-muted">Variances are facts from completed jobs. Recommendations are not applied on their own.</p></div>
<?php if (empty($health['ok'])): ?>
    <p><?= e((string) ($health['error'] ?? 'Unavailable')) ?></p>
<?php else: ?>
<section class="sf-panel p-3 mb-3">
    <p><?= e((string) $health['variance']['label']) ?> <?= e((string) $health['variance']['amount']) ?> across <?= e((string) $health['variance']['jobs']) ?> jobs in this sample. This is not an accounting loss.</p>
    <p class="sf-muted mb-0"><?= e((string) $health['sample_note']) ?></p>
</section>
<div class="row g-3">
    <div class="col-md-6"><section class="sf-panel p-3"><h2 class="h5">Under estimate</h2><?php if ($health['underestimated'] === []): ?><p>None in this sample above 10%.</p><?php endif; ?><ul><?php foreach ($health['underestimated'] as $row): ?><li><?= e($row['job_number']) ?> <?= e((string) $row['cost_variance_percent']) ?>% unfavourable</li><?php endforeach; ?></ul></section></div>
    <div class="col-md-6"><section class="sf-panel p-3"><h2 class="h5">Over estimate</h2><?php if ($health['overestimated'] === []): ?><p>None in this sample below -10%.</p><?php endif; ?><ul><?php foreach ($health['overestimated'] as $row): ?><li><?= e($row['job_number']) ?> <?= e((string) $row['cost_variance_percent']) ?>% favourable on cost</li><?php endforeach; ?></ul></section></div>
</div>
<section class="sf-panel p-3 mt-3"><h2 class="h5">Recipe cost variance</h2>
<?php if ($health['recipes'] === []): ?><p>No recipe sample.</p><?php endif; ?>
<ul><?php foreach ($health['recipes'] as $recipe): ?><li><?= e($recipe['recipe']) ?> · sample <?= e((string) $recipe['sample']) ?> · median variance <?= e((string) ($recipe['median_variance_percent'] ?? '—')) ?>%</li><?php endforeach; ?></ul>
</section>
<section class="sf-panel p-3 mt-3"><h2 class="h5">Pending recommendations</h2>
<?php if ($health['pending'] === []): ?><p class="mb-0">None.</p><?php endif; ?>
<ul class="mb-0"><?php foreach ($health['pending'] as $row): ?><li><?= e((string) $row['recommendation_type']) ?> <?= e((string) $row['current_value']) ?> to <?= e((string) $row['suggested_value']) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

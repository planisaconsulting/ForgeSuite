<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Estimate vs actual</h1><p class="sf-muted">Variance percent is (actual − estimated) / estimated × 100. Favourable means the actual came in under the estimate.</p></div>
<?php if (empty($report['ok'])): ?><p><?= e((string) ($report['error'] ?? '')) ?></p><?php else: ?>
<section class="sf-panel"><div class="table-responsive"><table class="table table-dark table-sm mb-0">
<thead><tr><th>Job</th><th>Recipe</th><th>Material</th><th>Labour</th><th>Cost</th><th>Margins</th></tr></thead>
<tbody>
<?php if ($report['rows'] === []): ?><tr><td colspan="6">No completed jobs in the sample.</td></tr><?php endif; ?>
<?php foreach ($report['rows'] as $row): ?>
<tr>
    <td><?= e($row['job_number']) ?></td>
    <td><?= e($row['recipe']) ?></td>
    <td><?= e((string) ($row['material_variance_percent'] ?? '—')) ?>% <?= e($row['material_meaning']) ?></td>
    <td><?= e((string) ($row['labour_variance_percent'] ?? '—')) ?>% <?= e($row['labour_meaning']) ?></td>
    <td><?= e((string) ($row['cost_variance_percent'] ?? '—')) ?>% <?= e($row['cost_meaning']) ?></td>
    <td><?= e((string) ($row['estimated_margin'] ?? '—')) ?> / <?= e((string) ($row['actual_margin'] ?? '—')) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>

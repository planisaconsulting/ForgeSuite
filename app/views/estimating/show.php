<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1><?= e((string) $estimate['estimate_number']) ?></h1>
        <p class="sf-muted mb-0"><?= e((string) $estimate['status']) ?> · <?= e((string) $estimate['estimate_type']) ?> · <?= e((string) $estimate['confidence_basis']) ?></p>
    </div>
    <?php if (can('estimates.approve') && (string) $estimate['status'] !== 'APPROVED'): ?>
        <form method="post" action="<?= e(url('/estimates/' . $estimate['id'] . '/approve')) ?>"><?= csrf_field() ?><button class="btn btn-sf btn-lg" type="submit">Approve</button></form>
    <?php endif; ?>
</div>
<section class="sf-panel mb-3 p-3">
    <p class="mb-1">Recommended sell from the target gross margin, not a markup. This is not a customer price.</p>
    <?php if (can('estimates.view_cost')): ?>
        <p class="mb-0">Cost <?= e((string) $estimate['subtotal_cost']) ?> · Suggested sell <?= e((string) $estimate['recommended_sell_price']) ?> · Expected margin <?= e((string) ($estimate['expected_margin'] ?? '—')) ?>%</p>
    <?php endif; ?>
</section>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-dark table-sm mb-0">
            <thead><tr><th>Component</th><th>Billable</th><th>Estimated physical</th><th>Cost</th></tr></thead>
            <tbody>
            <?php foreach ($components as $row): ?>
                <tr>
                    <td><?= e((string) $row['description']) ?><br><small class="sf-muted"><?= e((string) $row['component_type']) ?></small></td>
                    <td><?= e((string) ($row['billable_quantity'] ?? '')) ?></td>
                    <td><?= e((string) $row['estimated_quantity']) ?> <?= e((string) $row['unit']) ?></td>
                    <td><?= can('estimates.view_cost') ? e((string) $row['estimated_cost']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($similar !== []): ?>
<section class="sf-panel mt-3 p-3">
    <h2 class="h5">Comparable completed jobs</h2>
    <p class="sf-muted">Same recipe. Prices are not copied.</p>
    <ul class="mb-0">
        <?php foreach ($similar as $job): ?>
            <li><?= e($job['job_number']) ?> quoted <?= e($job['quoted_value']) ?>, actual cost <?= e($job['actual_cost']) ?>, margin <?= e((string) ($job['margin'] ?? '—')) ?>%</li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

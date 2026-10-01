<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Estimates</h1>
        <p class="sf-muted mb-0">Internal cost workings. A quote is the customer document and is not changed from here.</p>
    </div>
    <?php if (can('estimates.create')): ?><a class="btn btn-sf" href="<?= e(url('/estimates/new')) ?>">New estimate</a><?php endif; ?>
</div>
<form class="row g-2 mb-3" method="get" action="<?= e(url('/estimates')) ?>">
    <div class="col-md-6"><input class="form-control form-control-lg" name="q" value="<?= e($term) ?>" placeholder="Number, customer, quote, job"></div>
    <div class="col-md-2"><button class="btn btn-outline-light btn-lg" type="submit">Search</button></div>
</form>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0">
            <thead><tr><th>Number</th><th>Type</th><th>Customer</th><th>Status</th><th>Cost</th><th>Margin</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="6">No estimates yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="<?= e(url('/estimates/' . $row['id'])) ?>"><?= e((string) $row['estimate_number']) ?></a></td>
                    <td><?= e((string) $row['estimate_type']) ?></td>
                    <td><?= e((string) ($row['company_name'] ?? '')) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                    <td><?= can('estimates.view_cost') ? e((string) $row['subtotal_cost']) : '—' ?></td>
                    <td><?= e((string) ($row['expected_margin'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

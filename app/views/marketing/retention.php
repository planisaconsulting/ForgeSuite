<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Customer retention</h1></div>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">Repeat customer rate</h2>
    <p>Customers with more than one completed job, divided by customers with at least one completed job.
        <?= e((string) $repeat['repeaters']) ?> / <?= e((string) $repeat['with_job']) ?>
        <?= $rate === null ? '(not enough completed jobs)' : '= ' . e($rate) ?>.
        This is not a loyalty score.
    </p>
    <p class="mb-0">Dormant means no completed job in <?= e((string) $months) ?> months. The figure beside a name is historical accepted commercial value on completed jobs, not a forecast.</p>
</section>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>Customer</th><th>Last completed job</th><th>Last quote</th><th>Completed-job commercial value</th></tr></thead>
            <tbody>
            <?php if ($dormant === []): ?><tr><td colspan="4">No dormant customers for this threshold.</td></tr><?php endif; ?>
            <?php foreach ($dormant as $row): ?>
                <tr>
                    <td><a href="<?= e(url('/customers/' . $row['id'])) ?>"><?= e((string) ($row['company_name'] ?: trim($row['first_name'] . ' ' . $row['last_name']))) ?></a></td>
                    <td><?= e((string) $row['last_job']) ?></td>
                    <td><?= e((string) ($row['last_quote'] ?? '')) ?></td>
                    <td><?= e((string) $row['commercial_value']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

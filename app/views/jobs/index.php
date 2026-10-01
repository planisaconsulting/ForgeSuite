<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Jobs</h1>
    <p class="sf-muted mb-0">Each job is the hand-off from one accepted quotation. Production management arrives in Phase 3.</p>
</div>
<div class="sf-table-wrap">
    <table class="table sf-table">
        <thead><tr><th>Job</th><th>Customer</th><th>Quote</th><th>Status</th><th>Priority</th><th>Target</th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="6">No jobs yet. Accept a quotation, then convert it.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/jobs/' . $row['id'])) ?>"><?= e((string) $row['job_number']) ?></a></td>
                <td><?= e(customer_label($row)) ?></td>
                <td><?= e((string) $row['quote_number']) ?> rev <?= e((string) $row['quote_revision_number']) ?></td>
                <td><?= e((string) $row['status']) ?></td>
                <td><?= e((string) $row['priority']) ?></td>
                <td><?= e((string) ($row['target_date'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Installation planner</h1><p class="sf-muted mb-0">The next 30 days.</p></div>
<section class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No installations are booked.</p></div><?php else: ?>
        <div class="table-responsive"><table class="table table-dark mb-0">
            <thead><tr><th>Date</th><th>Job</th><th>Customer</th><th>Location</th><th>Team</th><th>Status</th></tr></thead>
            <tbody><?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['scheduled_date']) ?> <?= e((string) ($row['scheduled_start_time'] ?? '')) ?></td>
                    <td><a href="<?= e(url('/field/' . $row['id'])) ?>"><?= e((string) $row['job_number']) ?></a></td>
                    <td><?= e(customer_label($row)) ?></td>
                    <td><?= e((string) ($row['site_address'] ?: $row['job_site'] ?? '')) ?></td>
                    <td><?= e((string) ($row['team_name'] ?? '—')) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

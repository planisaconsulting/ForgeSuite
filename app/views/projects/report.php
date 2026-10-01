<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1">Project reports</p>
    <h1><?= e(ucwords(str_replace('-', ' ', (string) $slug))) ?></h1>
</div>
<p class="mb-3">
    <?php foreach (['summary','progress','sites','rollout','profitability','cost-variance','timeline','installations','snags','materials','purchasing','financials','delays'] as $name): ?>
        <a href="<?= e(url('/projects/reports/' . $name)) ?>"><?= e($name) ?></a>
    <?php endforeach; ?>
</p>
<section class="sf-panel">
    <?php if ($rows === []): ?><p>No projects to report.</p><?php endif; ?>
    <div class="table-responsive">
        <table class="table table-dark table-sm">
            <thead><tr><th>Project</th><th>Customer</th><th>Status</th><th>Health</th><th>Target</th><?php if ($showMoney): ?><th>Commercial value</th><th>Actual cost</th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= e(url('/projects/' . $row['id'])) ?>"><?= e((string) $row['project_number']) ?></a></td>
                        <td><?= e(customer_label($row)) ?></td>
                        <td><?= e((string) $row['status']) ?></td>
                        <td><?= e((string) $row['project_health']) ?></td>
                        <td><?= e((string) ($row['current_target_date'] ?? '')) ?></td>
                        <?php if ($showMoney): ?>
                            <td><?= e(money((string) ($row['commercial_value_cached'] ?? '0'))) ?></td>
                            <td><?= e(money((string) ($row['actual_cost_cached'] ?? '0'))) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div><h1>Capacity</h1><p class="sf-muted mb-0"><?= e($report['from']) ?> to <?= e($report['to']) ?>. Utilisation is scheduled minutes divided by available working minutes.</p></div>
    <div class="d-flex gap-2">
        <?php foreach ([7, 14, 30] as $days): ?><a class="btn <?= (int) $report['days'] === $days ? 'btn-sf' : 'btn-outline-light' ?>" href="<?= e(url('/capacity?days=' . $days)) ?>"><?= e((string) $days) ?> days</a><?php endforeach; ?>
    </div>
</div>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-dark mb-0">
            <thead><tr><th>Resource</th><th>Available</th><th>Scheduled</th><th>Utilisation</th><th>Over capacity</th></tr></thead>
            <tbody>
            <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <td><?= e((string) $row['resource']['name']) ?><small class="d-block sf-muted"><?= e((string) $row['resource']['resource_type']) ?></small></td>
                    <td><?= e($row['summary']['available_minutes']) ?> min</td>
                    <td><?= e($row['summary']['scheduled_minutes']) ?> min</td>
                    <td><?= e((string) ($row['summary']['utilisation_percent'] ?? '—')) ?>%</td>
                    <td><?= e($row['summary']['over_capacity_hours']) ?> h<?= $row['overloaded'] ? ' · over' : '' ?><?= $row['underused'] ? ' · under' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

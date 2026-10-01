<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Resource utilisation</h1><p class="sf-muted mb-0">Available hours, scheduled hours, and actual time recorded on jobs. This is not a performance score.</p></div>
<section class="sf-panel">
    <table class="table table-dark mb-0">
        <thead><tr><th>Resource</th><th>Available h</th><th>Scheduled h</th><th>Utilisation</th></tr></thead>
        <tbody><?php foreach ($report['rows'] as $row): ?>
            <tr>
                <td><?= e((string) $row['resource']['name']) ?></td>
                <td><?= e(\App\Helpers\Decimal::round(\App\Helpers\Decimal::div($row['summary']['available_minutes'], '60'), 2)) ?></td>
                <td><?= e(\App\Helpers\Decimal::round(\App\Helpers\Decimal::div($row['summary']['scheduled_minutes'], '60'), 2)) ?></td>
                <td><?= e((string) ($row['summary']['utilisation_percent'] ?? '—')) ?>%</td>
            </tr>
        <?php endforeach; ?></tbody>
    </table>
</section>

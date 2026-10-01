<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Lead sources</h1><p class="sf-muted mb-0">Lead to qualified and lead to converted are separate rates. This is first touch.</p></div>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>Source</th><th>Leads</th><th>Valid</th><th>Qualified</th><th>Converted</th><th>Lost</th><th>Lead to converted</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $valid = (int) $row['valid_leads']; $converted = (int) $row['converted']; ?>
                <tr>
                    <td><?= e((string) $row['source']) ?></td>
                    <td><?= e((string) $row['leads']) ?></td>
                    <td><?= e((string) $valid) ?></td>
                    <td><?= e((string) $row['qualified']) ?></td>
                    <td><?= e((string) $converted) ?></td>
                    <td><?= e((string) $row['lost']) ?></td>
                    <td><?= $valid > 0 ? e((string) (int) floor(($converted / $valid) * 100)) . '%' : 'n/a' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

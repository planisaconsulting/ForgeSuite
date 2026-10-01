<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Backups</h1>
        <p class="sf-muted mb-0">Files stay outside the public download path. Restore is a manual database import, not a button in this screen.</p>
    </div>
    <form method="post" action="<?= e(url('/admin/backups')) ?>"><?= csrf_field() ?><button class="btn btn-sf" type="submit">Create backup now</button></form>
</div>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>Started</th><th>Status</th><th>File</th><th>Size</th><th>By</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['started_at']) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                    <td><?= e((string) $row['filename']) ?></td>
                    <td><?= e((string) $row['file_size']) ?></td>
                    <td><?= e((string) ($row['user_name'] ?? '')) ?></td>
                    <td><?php if ((string) $row['status'] === 'SUCCESS'): ?><a href="<?= e(url('/admin/backups/' . $row['id'] . '/download')) ?>">Download</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<p class="sf-muted">To restore, download a successful file and import it with the mysql client into a database you have chosen. Do not import over the live database until you have a newer successful backup.</p>

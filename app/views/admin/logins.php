<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Sign-in history</h1><p class="sf-muted mb-0">Email and IP only. Passwords are not stored.</p></div>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>When</th><th>User</th><th>Email</th><th>IP</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['created_at']) ?></td>
                    <td><?= e((string) ($row['user_name'] ?? '')) ?></td>
                    <td><?= e((string) $row['email']) ?></td>
                    <td><?= e((string) ($row['ip_address'] ?? '')) ?></td>
                    <td><?= (int) $row['success'] === 1 ? 'Success' : 'Failed' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

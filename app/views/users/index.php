<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Users</h1>
        <p class="sf-muted mb-0">Staff accounts. There is no public registration page.</p>
    </div>
    <a class="btn btn-sf" href="<?= e(url('/users/new')) ?>">Add user</a>
</div>
<div class="table-responsive sf-panel">
    <table class="table sf-table sf-stack align-middle mb-0">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last sign-in</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td data-label="Name"><?= e((string) $row['name']) ?></td>
                    <td data-label="Email"><?= e((string) $row['email']) ?></td>
                    <td data-label="Role"><?= e((string) $row['role_name']) ?></td>
                    <td data-label="Last sign-in"><?= e(format_datetime($row['last_login_at'] === null ? null : (string) $row['last_login_at'])) ?></td>
                    <td data-label="Status"><?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td data-label=""><a href="<?= e(url('/users/' . $row['id'] . '/edit')) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

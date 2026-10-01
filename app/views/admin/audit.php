<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div><h1>Audit log</h1><p class="sf-muted mb-0">Changes already stored by the application. Passwords are not kept here.</p></div>
    <a class="btn btn-outline-light" href="<?= e(url('/admin/logins')) ?>">Sign-in history</a>
</div>
<form class="sf-filters" method="get">
    <input class="form-control" name="user" value="<?= e($filters['user_id']) ?>" placeholder="User id" aria-label="User id">
    <input class="form-control" name="action" value="<?= e($filters['action']) ?>" placeholder="Action" aria-label="Action">
    <input class="form-control" name="entity" value="<?= e($filters['entity']) ?>" placeholder="Entity" aria-label="Entity">
    <input class="form-control" name="ip" value="<?= e($filters['ip']) ?>" placeholder="IP" aria-label="IP">
    <input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="From">
    <input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="To">
    <button class="btn btn-sf" type="submit">Filter</button>
</form>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th>Old</th><th>New</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['created_at']) ?></td>
                    <td><?= e((string) ($row['user_name'] ?? '')) ?></td>
                    <td><?= e((string) $row['action']) ?></td>
                    <td><?= e((string) $row['entity_type']) ?> <?= e((string) ($row['entity_id'] ?? '')) ?></td>
                    <td><?= e((string) ($row['ip_address'] ?? '')) ?></td>
                    <td><code><?= e(mb_substr((string) ($row['old_values'] ?? ''), 0, 180)) ?></code></td>
                    <td><code><?= e(mb_substr((string) ($row['new_values'] ?? ''), 0, 180)) ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

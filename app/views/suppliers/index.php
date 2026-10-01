<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Suppliers</h1>
        <p class="sf-muted mb-0">Preferred suppliers for catalogue products.</p>
    </div>
    <?php if ($canManage): ?><a class="btn btn-sf" href="<?= e(url('/suppliers/new')) ?>">Add supplier</a><?php endif; ?>
</div>
<?php $basePath = '/suppliers'; require base_path('app/views/partials/list_tools.php'); ?>
<?php if ($rows === []): ?>
    <section class="sf-panel"><div class="sf-empty"><p>No suppliers match that search.</p></div></section>
<?php else: ?>
    <div class="table-responsive sf-panel">
        <table class="table sf-table sf-stack align-middle mb-0">
            <thead><tr><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Account</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Name"><a href="<?= e(url('/suppliers/' . $row['id'])) ?>"><?= e((string) $row['name']) ?></a></td>
                        <td data-label="Contact"><?= e((string) ($row['contact_name'] ?? '')) ?></td>
                        <td data-label="Email"><?= e((string) ($row['email'] ?? '')) ?></td>
                        <td data-label="Phone"><?= e((string) ($row['phone'] ?? '')) ?></td>
                        <td data-label="Account"><?= e((string) ($row['account_number'] ?? '')) ?></td>
                        <td data-label="Status"><?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

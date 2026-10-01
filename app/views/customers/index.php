<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Customers</h1>
        <p class="sf-muted mb-0">Businesses and individuals. Deactivated records stay in the database.</p>
    </div>
    <?php if ($canManage): ?>
        <a class="btn btn-sf" href="<?= e(url('/customers/new')) ?>">Add customer</a>
    <?php endif; ?>
</div>
<?php
$basePath = '/customers';
require base_path('app/views/partials/list_tools.php');
?>
<?php if ($rows === []): ?>
    <section class="sf-panel"><div class="sf-empty"><p>No customers match that search.</p></div></section>
<?php else: ?>
    <div class="table-responsive sf-panel">
        <table class="table sf-table sf-stack align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Name"><a href="<?= e(url('/customers/' . $row['id'])) ?>"><?= e(customer_label($row)) ?></a></td>
                        <td data-label="Type"><?= e(enum_label(App\Domain\CustomerType::class, (string) $row['customer_type'])) ?></td>
                        <td data-label="Email"><?= e((string) ($row['email'] ?? '')) ?></td>
                        <td data-label="Phone"><?= e((string) (($row['phone'] ?: $row['mobile']) ?? '')) ?></td>
                        <td data-label="Status"><?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

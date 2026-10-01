<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Categories</h1>
        <p class="sf-muted mb-0">Optional parent and child groups for the catalogue.</p>
    </div>
    <a class="btn btn-sf" href="<?= e(url('/categories/new')) ?>">Add category</a>
</div>
<div class="table-responsive sf-panel">
    <table class="table sf-table sf-stack align-middle mb-0">
        <thead><tr><th>Name</th><th>Parent</th><th>Sort</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td data-label="Name"><?= $row['parent_id'] ? '— ' : '' ?><?= e((string) $row['name']) ?></td>
                    <td data-label="Parent"><?= e((string) ($row['parent_name'] ?? '')) ?></td>
                    <td data-label="Sort"><?= e((string) $row['sort_order']) ?></td>
                    <td data-label="Status"><?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td data-label=""><a href="<?= e(url('/categories/' . $row['id'] . '/edit')) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

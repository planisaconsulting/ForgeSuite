<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Stock movements</h1><p class="sf-muted mb-0">These rows are not edited. A correction is another movement.</p></div>
<form class="sf-filters mb-3" method="get">
    <select class="form-select" name="movement_type"><option value="">All types</option>
        <?php foreach ($types as $type): ?><option value="<?= e($type->value) ?>" <?= $filters['movement_type'] === $type->value ? 'selected' : '' ?>><?= e($type->label()) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select" name="location_id"><option value="">All locations</option>
        <?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>" <?= (int) $filters['location_id'] === (int) $location['id'] ? 'selected' : '' ?>><?= e((string) $location['name']) ?></option><?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="from" value="<?= e((string) $filters['from']) ?>">
    <input class="form-control" type="date" name="to" value="<?= e((string) $filters['to']) ?>">
    <button class="btn btn-sf" type="submit">Filter</button>
    <a class="btn btn-outline-light" href="<?= e(url('/inventory/movements?' . http_build_query(array_merge($filters, ['export' => 'csv'])))) ?>">CSV</a>
</form>
<div class="table-responsive sf-panel">
    <table class="table sf-table mb-0">
        <thead><tr><th>Date</th><th>Product</th><th>Location</th><th>Type</th><th>Qty</th><th>Item</th><th>Job</th><?php if ($showCost): ?><th>Value</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e((string) $row['movement_date']) ?></td>
                <td><?= e((string) $row['product_name']) ?></td>
                <td><?= e((string) $row['location_name']) ?></td>
                <td><?= e(enum_label(App\Domain\MovementType::class, (string) $row['movement_type'])) ?></td>
                <td><?= e((string) $row['quantity']) ?> <?= e((string) $row['unit']) ?></td>
                <td><?php if ($row['inventory_code']): ?><a href="<?= e(url('/inventory/items/' . $row['inventory_item_id'])) ?>"><?= e((string) $row['inventory_code']) ?></a><?php endif; ?></td>
                <td><?php if ($row['job_id']): ?><a href="<?= e(url('/jobs/' . $row['job_id'])) ?>"><?= e((string) $row['job_number']) ?></a><?php endif; ?></td>
                <?php if ($showCost): ?><td><?= e(money((string) $row['total_cost'])) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

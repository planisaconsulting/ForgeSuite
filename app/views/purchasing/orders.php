<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row"><div><h1>Purchase orders</h1></div></div>
<form class="sf-filters mb-3" method="get">
    <input class="form-control form-control-lg" name="q" value="<?= e($term) ?>" placeholder="PO number or supplier">
    <select class="form-select" name="status"><option value="">All statuses</option><?php foreach ($statuses as $case): ?><option value="<?= e($case->value) ?>" <?= $status === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sf" type="submit">Filter</button>
</form>
<?php if (can('purchasing.create')): ?>
<form class="sf-filters mb-3" method="post" action="<?= e(url('/purchasing/orders')) ?>">
    <?= csrf_field() ?>
    <select class="form-select form-select-lg" name="supplier_id" required><option value="">Supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= e((string) $supplier['id']) ?>"><?= e((string) $supplier['name']) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sf btn-lg" type="submit">New purchase order</button>
</form>
<?php endif; ?>
<div class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No purchase orders yet.</p></div><?php else: ?>
        <div class="table-responsive"><table class="table sf-table mb-0"><thead><tr><th>Number</th><th>Supplier</th><th>Status</th><th>Expected</th><th>Total</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/purchasing/orders/' . $row['id'])) ?>"><?= e((string) $row['po_number']) ?></a></td>
                <td><?= e((string) $row['supplier_name']) ?></td>
                <td><?= e(enum_label(App\Domain\PurchaseOrderStatus::class, (string) $row['status'])) ?></td>
                <td><?= e((string) ($row['expected_date'] ?? '—')) ?></td>
                <td><?= e(money((string) $row['total'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>

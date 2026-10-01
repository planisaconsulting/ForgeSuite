<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Purchase requests</h1></div>
<?php if (can('purchasing.create') || can('materials.record_usage')): ?>
<form class="row g-2 mb-3" method="post" action="<?= e(url('/purchasing/requests')) ?>">
    <?= csrf_field() ?>
    <div class="col-md-5"><select class="form-select form-select-lg" name="product_id" required><option value="">Product</option><?php foreach ($products as $product): ?><option value="<?= e((string) $product['id']) ?>"><?= e((string) $product['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><input class="form-control form-control-lg" name="quantity" placeholder="Quantity" required></div>
    <div class="col-md-4"><button class="btn btn-sf btn-lg w-100" type="submit">Request</button></div>
</form>
<?php endif; ?>
<form method="post" action="<?= e(url('/purchasing/requests/consolidate')) ?>">
    <?= csrf_field() ?>
    <div class="sf-panel mb-3">
        <?php if ($rows === []): ?><div class="sf-empty"><p>No purchase requests.</p></div><?php else: ?>
            <div class="table-responsive"><table class="table sf-table mb-0"><thead><tr><th></th><th>Product</th><th>Qty</th><th>Job</th><th>Status</th><th></th></tr></thead><tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php if ((string) $row['status'] === 'APPROVED'): ?><input type="checkbox" name="request_ids[]" value="<?= e((string) $row['id']) ?>"><?php endif; ?></td>
                    <td><?= e((string) $row['product_name']) ?></td>
                    <td><?= e((string) $row['quantity']) ?> <?= e((string) $row['unit']) ?></td>
                    <td><?= $row['job_id'] ? e((string) $row['job_number']) : '—' ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                    <td>
                        <?php if ((string) $row['status'] === 'REQUESTED' && can('purchasing.approve')): ?>
                            <button class="btn btn-sm btn-sf" formaction="<?= e(url('/purchasing/requests/' . $row['id'])) ?>" formmethod="post" name="status" value="APPROVED">Approve</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
    <?php if (can('purchasing.create')): ?>
        <div class="d-flex gap-2">
            <select class="form-select form-select-lg" name="supplier_id"><option value="">Supplier for selected requests</option><?php foreach ($suppliers as $supplier): ?><option value="<?= e((string) $supplier['id']) ?>"><?= e((string) $supplier['name']) ?></option><?php endforeach; ?></select>
            <button class="btn btn-outline-light btn-lg" type="submit">Combine onto one PO</button>
        </div>
    <?php endif; ?>
</form>

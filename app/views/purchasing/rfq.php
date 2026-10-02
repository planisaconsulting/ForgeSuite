<div class="sf-page-head">
    <h1><?= e((string) $rfq['rfq_number']) ?></h1>
    <p><?= e((string) $rfq['title']) ?> · <?= e((string) $rfq['status']) ?></p>
</div>
<section class="sf-panel mb-3">
    <p>Comparison shows the facts. It does not select a winner.</p>
    <table class="table">
        <thead><tr><th>Supplier</th><th>Product</th><th>Available</th><th>Unit price</th><th>Lead</th><th>Alternative</th></tr></thead>
        <tbody>
        <?php foreach ($comparison['rows'] as $row): ?>
            <tr>
                <td><?= e((string) $row['supplier']) ?></td>
                <td><?= e((string) $row['product']) ?></td>
                <td><?= e((string) $row['available']) ?></td>
                <td><?= e((string) $row['unit_price']) ?></td>
                <td><?= e((string) ($row['lead_time_days'] ?? '')) ?></td>
                <td><?= $row['alternative'] ? 'Alternative product' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php if (can('procurement.rfq.award') && $comparison['rows'] !== []): ?>
    <form method="post" class="sf-panel">
        <?= csrf_field() ?>
        <h2 class="h5">Award</h2>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Quotation item</label><input class="form-control" name="quotation_item_id"></div>
            <div class="col-md-3"><label class="form-label">Quantity</label><input class="form-control" name="quantity"></div>
            <div class="col-md-3"><label class="form-label">Reason</label>
                <select class="form-select" name="reason">
                    <?php foreach (['BEST_PRICE','BEST_AVAILABILITY','FASTEST_DELIVERY','CONTRACT_SUPPLIER','QUALITY','CUSTOMER_REQUIREMENT','SPLIT_SUPPLY','OTHER'] as $reason): ?>
                        <option><?= e($reason) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button class="btn btn-light mt-3" type="submit">Create draft purchase order</button>
    </form>
<?php endif; ?>

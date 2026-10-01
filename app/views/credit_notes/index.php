<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Credit notes</h1></div>
<?php if (can('credit_notes.create')): ?>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/credit-notes')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><select class="form-select form-select-lg" name="customer_id" required><option value="">Customer</option><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>"><?= e(customer_label($customer)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><input class="form-control form-control-lg" name="description" placeholder="Description" required></div>
        <div class="col-md-2"><input class="form-control form-control-lg" name="amount" placeholder="Amount" inputmode="decimal" required></div>
        <div class="col-md-6"><input class="form-control form-control-lg" name="reason" placeholder="Reason" required></div>
        <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Save draft</button></div>
    </div>
</form>
<?php endif; ?>
<p><a href="<?= e(url('/credit-notes?export=csv')) ?>">CSV</a></p>
<section class="sf-panel"><div class="table-responsive"><table class="table sf-table mb-0">
<thead><tr><th>Credit</th><th>Date</th><th>Customer</th><th>Invoice</th><th>Status</th><th>Total</th></tr></thead>
<tbody><?php foreach ($rows as $row): ?><tr>
<td><a href="<?= e(url('/credit-notes/' . $row['id'])) ?>"><?= e((string) ($row['credit_note_number'] ?: 'Draft ' . $row['id'])) ?></a></td>
<td><?= e((string) $row['credit_date']) ?></td>
<td><?= e(customer_label($row)) ?></td>
<td><?= e((string) ($row['invoice_number'] ?? '')) ?></td>
<td><?= e((string) $row['status']) ?></td>
<td><?= e(money((string) $row['total'])) ?></td>
</tr><?php endforeach; ?></tbody></table></div></section>

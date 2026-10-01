<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Statements</h1></div>
<form class="sf-filters mb-3" method="get" action="<?= e(url('/finance/statements')) ?>">
    <select class="form-select form-select-lg" name="customer_id" required>
        <option value="">Customer</option>
        <?php foreach ($customers as $customer): ?>
            <option value="<?= e((string) $customer['id']) ?>" <?= (int) $customer['id'] === $customerId ? 'selected' : '' ?>><?= e(customer_label($customer)) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
    <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
    <button class="btn btn-sf btn-lg" type="submit">Show</button>
</form>
<?php if ($statement !== null && $statement['customer'] !== null): ?>
<p><a class="btn btn-outline-light" href="<?= e(url('/finance/statements?customer_id=' . $customerId . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&export=pdf')) ?>">PDF</a></p>
<section class="sf-panel">
    <div class="sf-panel-head"><h2><?= e(customer_label($statement['customer'])) ?></h2></div>
    <p class="px-3">Opening <?= e(money($statement['opening'])) ?> · Closing <?= e(money($statement['closing'])) ?> · Overdue now <?= e(money($statement['overdue'])) ?></p>
    <div class="table-responsive"><table class="table sf-table mb-0">
        <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
        <tbody><?php foreach ($statement['rows'] as $row): ?><tr>
            <td><?= e($row['date']) ?></td><td><?= e($row['reference']) ?></td><td><?= e($row['description']) ?></td>
            <td><?= e(money($row['debit'])) ?></td><td><?= e(money($row['credit'])) ?></td><td><?= e(money($row['balance'])) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
</section>
<?php endif; ?>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Payments</h1></div>
<?php if (can('payments.record')): ?>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/payments')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><select class="form-select form-select-lg" name="customer_id" required><option value="">Customer</option><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>"><?= e(customer_label($customer)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><input class="form-control form-control-lg" type="date" name="payment_date" value="<?= e(date('Y-m-d')) ?>"></div>
        <div class="col-md-2"><input class="form-control form-control-lg" name="amount" inputmode="decimal" placeholder="Amount" required></div>
        <div class="col-md-2"><select class="form-select form-select-lg" name="payment_method"><?php foreach ($methods as $method): ?><option value="<?= e($method->value) ?>"><?= e($method->label()) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><input class="form-control form-control-lg" name="external_reference" placeholder="Reference"></div>
        <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Record payment</button></div>
    </div>
</form>
<?php endif; ?>
<p><a href="<?= e(url('/payments?export=csv')) ?>">CSV</a></p>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <thead><tr><th>Payment</th><th>Date</th><th>Customer</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="<?= e(url('/payments/' . $row['id'])) ?>"><?= e((string) $row['payment_reference']) ?></a></td>
                    <td><?= e((string) $row['payment_date']) ?></td>
                    <td><?= e(customer_label($row)) ?></td>
                    <td><?= e(money((string) $row['amount'])) ?></td>
                    <td><?= e((string) $row['payment_method']) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Invoices</h1>
        <p class="sf-muted mb-0">Issued invoices keep the customer, VAT, and prices from the day they were issued.</p>
    </div>
    <?php if (can('invoices.create')): ?>
        <a class="btn btn-sf btn-lg" href="<?= e(url('/invoices/new')) ?>">New invoice</a>
    <?php endif; ?>
</div>
<form class="sf-filters mb-3" method="get">
    <input class="form-control form-control-lg" name="q" value="<?= e($filters['q']) ?>" placeholder="Invoice, customer, job, or PO">
    <select class="form-select form-select-lg" name="status">
        <option value="">Any status</option>
        <?php foreach ($statuses as $status): ?>
            <option value="<?= e($status->value) ?>" <?= $filters['status'] === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select form-select-lg" name="type">
        <option value="">Any type</option>
        <?php foreach ($types as $type): ?>
            <option value="<?= e($type->value) ?>" <?= $filters['type'] === $type->value ? 'selected' : '' ?>><?= e($type->label()) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>">
    <input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>">
    <button class="btn btn-sf btn-lg" type="submit">Filter</button>
    <a class="btn btn-outline-light" href="<?= e(url('/invoices?export=csv&q=' . urlencode($filters['q']))) ?>">CSV</a>
</form>
<section class="sf-panel">
    <?php if ($rows === []): ?>
        <div class="sf-empty"><p>No invoices match this filter.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table sf-table mb-0">
                <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th>Job</th><th>Type</th><th>Status</th><th>Due</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $shown = \App\Domain\InvoiceStatus::present((string) $row['status'], (string) $row['balance_due'], $row['due_date'] !== null ? (string) $row['due_date'] : null, date('Y-m-d'));
                ?>
                    <tr>
                        <td><a href="<?= e(url('/invoices/' . $row['id'])) ?>"><?= e((string) ($row['invoice_number'] ?: 'Draft ' . $row['id'])) ?></a></td>
                        <td><?= e((string) $row['invoice_date']) ?></td>
                        <td><?= e(customer_label($row)) ?></td>
                        <td><?= e((string) ($row['job_number'] ?? '')) ?></td>
                        <td><?= e((string) $row['invoice_type']) ?></td>
                        <td><?= e($shown) ?></td>
                        <td><?= e((string) ($row['due_date'] ?? '')) ?></td>
                        <td><?= e(money((string) $row['total'])) ?></td>
                        <td><?= e(money((string) $row['amount_paid'])) ?></td>
                        <td><?= e(money((string) $row['balance_due'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

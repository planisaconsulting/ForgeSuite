<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>New invoice</h1></div>
<?php if ($quote !== null || $job !== null): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Commercial position</h2></div>
    <div class="row g-3 p-3">
        <div class="col-6 col-md-3"><p class="sf-kicker">Commercial value</p><p class="sf-stat-value"><?= e(money($position['commercial'])) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Invoiced to date</p><p class="sf-stat-value"><?= e(money($position['invoiced'])) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Remaining</p><p class="sf-stat-value"><?= e(money($position['remaining'])) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Deposit on quote</p><p class="sf-stat-value"><?= e(money($position['deposit'])) ?></p></div>
    </div>
</section>
<?php endif; ?>
<form class="sf-panel p-3" method="post" action="<?= e(url('/invoices')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="quote_id" value="<?= e((string) ($quote['id'] ?? '')) ?>">
    <input type="hidden" name="job_id" value="<?= e((string) ($job['id'] ?? '')) ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Customer</label>
            <select class="form-select form-select-lg" name="customer_id" required>
                <option value="">Choose</option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e((string) $customer['id']) ?>" <?= (int) $customer['id'] === $customerId ? 'selected' : '' ?>><?= e(customer_label($customer)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Type</label>
            <select class="form-select form-select-lg" name="invoice_type">
                <?php foreach ($types as $type): ?>
                    <option value="<?= e($type->value) ?>"><?= e($type->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Invoice date</label>
            <input class="form-control form-control-lg" type="date" name="invoice_date" value="<?= e(date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Progress amount</label>
            <input class="form-control form-control-lg" name="progress_amount" inputmode="decimal" placeholder="For a progress invoice">
        </div>
        <div class="col-md-4">
            <label class="form-label">Or percent of commercial value</label>
            <input class="form-control form-control-lg" name="progress_percent" inputmode="decimal">
        </div>
        <div class="col-md-4">
            <label class="form-label">Payment terms</label>
            <select class="form-select form-select-lg" name="payment_term_id">
                <?php foreach ($terms as $term): ?>
                    <option value="<?= e((string) $term['id']) ?>"><?= e((string) $term['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Manual description</label>
            <input class="form-control form-control-lg" name="description" placeholder="Only for an invoice with no quotation">
        </div>
        <div class="col-md-3">
            <label class="form-label">Manual amount</label>
            <input class="form-control form-control-lg" name="amount" inputmode="decimal">
        </div>
        <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Save draft</button></div>
    </div>
</form>

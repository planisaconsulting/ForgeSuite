<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Expenses</h1>
    <p class="sf-muted mb-0">Capture a cost, attach the receipt, and send it for approval. Approving it does not pay anyone.</p>
</div>
<div class="row g-3 mb-3">
    <?php foreach (['drafts' => 'Drafts', 'submitted' => 'Awaiting approval', 'approved' => 'Approved', 'reimbursable' => 'Ready to batch'] as $key => $label): ?>
        <div class="col-6 col-lg-3"><section class="sf-panel p-3"><p class="sf-muted mb-1"><?= e($label) ?></p><strong><?= e((string) ($cards[$key] ?? 0)) ?></strong></section></div>
    <?php endforeach; ?>
</div>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>New expense</h2></div>
    <form class="p-3" method="post" action="<?= e(url('/expenses')) ?>" data-unsaved="1">
        <?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label" for="expense-date">Expense date</label><input class="form-control" id="expense-date" type="date" name="expense_date" required value="<?= e(date('Y-m-d')) ?>"></div>
            <div class="col-md-3"><label class="form-label" for="expense-category">Category</label><select class="form-select" id="expense-category" name="category"><?php foreach ($categories as $category): ?><option value="<?= e((string) $category['code']) ?>"><?= e((string) $category['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label" for="expense-amount">Amount including VAT</label><input class="form-control" id="expense-amount" name="amount_inc_vat" inputmode="decimal" required></div>
            <div class="col-md-3"><label class="form-label" for="expense-method">How it was paid</label><select class="form-select" id="expense-method" name="payment_method"><option value="CASH">Cash</option><option value="PERSONAL_CARD">Personal card</option><option value="COMPANY_CARD">Company card</option><option value="EFT">EFT</option><option value="ACCOUNT">Account</option></select></div>
            <div class="col-md-6"><label class="form-label" for="expense-description">Description</label><input class="form-control" id="expense-description" name="description" required maxlength="180"></div>
            <div class="col-md-3"><label class="form-label" for="expense-merchant">Merchant</label><input class="form-control" id="expense-merchant" name="merchant_name" maxlength="180"></div>
            <div class="col-md-3"><label class="form-label" for="expense-job">Job id</label><input class="form-control" id="expense-job" name="job_id" inputmode="numeric"></div>
        </div>
        <button class="btn btn-sf btn-lg mt-3" type="submit">Capture expense</button>
    </form>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Recent expenses</h2></div>
    <?php if ($rows === []): ?>
        <div class="sf-empty"><p>No expenses yet. Capture parking, tolls, or a small material purchase when it belongs to a job.</p></div>
    <?php else: ?>
        <ul class="sf-feed">
            <?php foreach ($rows as $row): ?>
                <li><a href="<?= e(url('/expenses/' . $row['id'])) ?>"><?= e((string) $row['expense_number']) ?></a><small><?= e((string) $row['expense_date']) ?> · <?= e((string) $row['category_name']) ?> · <?= e((string) $row['status']) ?> · <?= e((string) $row['amount_inc_vat']) ?></small></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<script>
document.querySelector('[data-unsaved]')?.addEventListener('change', function () { window.sfExpenseDirty = true; });
window.addEventListener('beforeunload', function (event) {
    if (!window.sfExpenseDirty) return;
    event.preventDefault();
    event.returnValue = '';
});
</script>

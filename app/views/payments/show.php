<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1><?= e((string) $payment['payment_reference']) ?></h1>
        <p class="sf-muted mb-0"><?= e(customer_label($payment)) ?> · <?= e(money((string) $payment['amount'])) ?> · <?= e((string) $payment['status']) ?></p>
    </div>
    <a class="btn btn-outline-light btn-lg" href="<?= e(url('/payments/' . $payment['id'] . '/receipt')) ?>">Receipt</a>
</div>
<p>This receipt is not a tax invoice. Unallocated <?= e(money($available)) ?>.</p>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Allocations</h2></div>
    <ul class="sf-feed">
        <?php foreach ($allocations as $row): ?>
            <li><a href="<?= e(url('/invoices/' . $row['invoice_id'])) ?>"><?= e((string) ($row['invoice_number'] ?: 'Invoice')) ?></a> <?= e(money((string) $row['amount'])) ?><?= $row['reversed_at'] ? ' · reversed' : '' ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php if ((string) $payment['status'] === 'RECORDED' && can('payments.allocate') && $open !== []): ?>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/payments/' . $payment['id'] . '/allocate')) ?>">
    <?= csrf_field() ?>
    <?php foreach ($open as $invoice): ?>
        <div class="row g-2 mb-2 align-items-center">
            <div class="col-md-6"><?= e((string) $invoice['invoice_number']) ?> balance <?= e(money((string) $invoice['balance_due'])) ?></div>
            <div class="col-md-4"><input class="form-control form-control-lg" name="allocate[<?= e((string) $invoice['id']) ?>]" inputmode="decimal" placeholder="Allocate"></div>
        </div>
    <?php endforeach; ?>
    <button class="btn btn-sf btn-lg" type="submit">Allocate</button>
</form>
<?php endif; ?>
<?php if ((string) $payment['status'] === 'RECORDED' && can('payments.reverse')): ?>
<form class="sf-panel p-3" method="post" action="<?= e(url('/payments/' . $payment['id'] . '/reverse')) ?>">
    <?= csrf_field() ?>
    <input class="form-control form-control-lg mb-2" name="reason" placeholder="Reversal reason" required>
    <button class="btn btn-outline-light btn-lg" type="submit">Reverse payment</button>
</form>
<?php endif; ?>

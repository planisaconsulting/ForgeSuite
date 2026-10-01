<div class="sf-page-head">
    <h1><?= e((string) $invoice['invoice_number']) ?></h1>
    <p class="sf-muted"><?= e((string) $invoice['status']) ?> · due <?= e((string) ($invoice['due_date'] ?? '—')) ?></p>
</div>
<dl class="sf-dl">
    <div><dt>Total</dt><dd><?= e(money((string) $invoice['total'])) ?></dd></div>
    <div><dt>Paid</dt><dd><?= e(money((string) $invoice['amount_paid'])) ?></dd></div>
    <div><dt>Balance</dt><dd><?= e(money((string) $invoice['balance_due'])) ?></dd></div>
</dl>
<p><a class="btn btn-sf" href="<?= e(url('/portal/invoices/' . $invoice['id'] . '/pdf')) ?>">Download PDF</a></p>
<p><a href="<?= e(url('/portal')) ?>">Back</a></p>

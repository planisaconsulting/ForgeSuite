<?php
require base_path('app/views/partials/flashes.php');
$draft = (string) $invoice['status'] === 'DRAFT';
$number = (string) ($invoice['invoice_number'] ?: 'Draft ' . $invoice['id']);
?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1"><?= e((string) $invoice['invoice_type']) ?> · <?= e($presented) ?></p>
        <h1><?= e($number) ?></h1>
        <p class="sf-muted mb-0"><?= e(customer_label($invoice)) ?><?php if ((int) ($invoice['account_on_hold'] ?? 0) === 1): ?> · Account on hold<?php endif; ?></p>
    </div>
    <div class="sf-action-row">
        <?php if (!$draft): ?>
            <a class="btn btn-sf btn-lg" href="<?= e(url('/invoices/' . $invoice['id'] . '/pdf')) ?>">Download PDF</a>
        <?php endif; ?>
    </div>
</div>
<?php if (!empty($invoice['quote_id'])): ?>
<section class="sf-panel mb-3"><div class="p-3 sf-muted mb-0">Commercial value <?= e(money($position['commercial'])) ?>. Invoiced to date <?= e(money($position['invoiced'])) ?>. Remaining to invoice <?= e(money($position['remaining'])) ?>.</div></section>
<?php endif; ?>
<div class="row g-3">
    <div class="col-lg-8">
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Lines</h2></div>
            <div class="table-responsive">
                <table class="table sf-table mb-0">
                    <thead><tr><th>Description</th><th>Qty</th><th>Price</th><th>VAT</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e((string) $item['description']) ?></td>
                            <td><?= e((string) $item['quantity']) ?> <?= e((string) $item['unit']) ?></td>
                            <td><?= e(money((string) $item['unit_price'])) ?></td>
                            <td><?= e(money((string) $item['vat_amount'])) ?></td>
                            <td><?= e(money((string) $item['line_total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3">
                <p class="mb-1">Subtotal <?= e(money((string) $invoice['subtotal'])) ?></p>
                <p class="mb-1">Discount <?= e(money((string) $invoice['discount_amount'])) ?></p>
                <p class="mb-1">VAT <?= e((string) $invoice['vat_rate']) ?>% <?= e(money((string) $invoice['vat_amount'])) ?></p>
                <p class="mb-1"><strong>Total <?= e(money((string) $invoice['total'])) ?></strong></p>
                <p class="mb-1">Paid <?= e(money((string) $invoice['amount_paid'])) ?> · Credits <?= e(money((string) $invoice['credit_applied'])) ?></p>
                <p class="mb-0"><strong>Balance <?= e(money((string) $invoice['balance_due'])) ?></strong></p>
            </div>
        </section>
        <?php if ($draft && (can('invoices.edit_draft') || can('invoices.create'))): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Add a line</h2></div>
            <form class="p-3 row g-3" method="post" action="<?= e(url('/invoices/' . $invoice['id'] . '/lines')) ?>">
                <?= csrf_field() ?>
                <div class="col-md-6"><input class="form-control form-control-lg" name="description" placeholder="Description" required></div>
                <div class="col-md-2"><input class="form-control form-control-lg" name="quantity" value="1" inputmode="decimal"></div>
                <div class="col-md-2"><input class="form-control form-control-lg" name="unit_price" placeholder="Price" inputmode="decimal" required></div>
                <div class="col-md-2"><button class="btn btn-outline-light btn-lg w-100" type="submit">Add</button></div>
            </form>
        </section>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Dates and notes</h2></div>
            <?php if ($draft): ?>
            <form class="p-3" method="post" action="<?= e(url('/invoices/' . $invoice['id'])) ?>">
                <?= csrf_field() ?>
                <label class="form-label">Invoice date</label>
                <input class="form-control form-control-lg mb-2" type="date" name="invoice_date" value="<?= e((string) $invoice['invoice_date']) ?>">
                <label class="form-label">Due date</label>
                <input class="form-control form-control-lg mb-2" type="date" name="due_date" value="<?= e((string) ($invoice['due_date'] ?? '')) ?>">
                <label class="form-label">Customer PO</label>
                <input class="form-control form-control-lg mb-2" name="customer_po_number" value="<?= e((string) ($invoice['customer_po_number'] ?? '')) ?>">
                <label class="form-label">Customer notes</label>
                <textarea class="form-control mb-2" name="customer_notes" rows="3"><?= e((string) ($invoice['customer_notes'] ?? '')) ?></textarea>
                <label class="form-label">Internal notes</label>
                <textarea class="form-control mb-2" name="internal_notes" rows="2"><?= e((string) ($invoice['internal_notes'] ?? '')) ?></textarea>
                <button class="btn btn-outline-light btn-lg" type="submit">Save draft</button>
            </form>
            <?php else: ?>
            <div class="p-3">
                <p>Date <?= e((string) $invoice['invoice_date']) ?></p>
                <p>Due <?= e((string) ($invoice['due_date'] ?? '')) ?></p>
                <p>Terms <?= e((string) ($invoice['payment_term_name_snapshot'] ?? '')) ?></p>
                <p class="mb-0">PO <?= e((string) ($invoice['customer_po_number'] ?? '—')) ?></p>
            </div>
            <?php endif; ?>
        </section>
        <?php if ($draft && can('invoices.issue')): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Issue</h2></div>
            <form class="p-3" method="post" action="<?= e(url('/invoices/' . $invoice['id'] . '/issue')) ?>">
                <?= csrf_field() ?>
                <label class="form-label">Override reason</label>
                <input class="form-control form-control-lg mb-2" name="override_reason" placeholder="Only if over the commercial value, credit limit, or account hold">
                <button class="btn btn-sf btn-lg w-100" type="submit">Issue invoice</button>
            </form>
        </section>
        <?php endif; ?>
        <?php if (!$draft && (string) $invoice['status'] !== 'CANCELLED' && can('credit_notes.create')): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Credit note</h2></div>
            <form class="p-3" method="post" action="<?= e(url('/credit-notes')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="customer_id" value="<?= e((string) $invoice['customer_id']) ?>">
                <input type="hidden" name="invoice_id" value="<?= e((string) $invoice['id']) ?>">
                <input type="hidden" name="job_id" value="<?= e((string) ($invoice['job_id'] ?? '')) ?>">
                <input class="form-control form-control-lg mb-2" name="description" placeholder="What is credited" required>
                <input class="form-control form-control-lg mb-2" name="amount" inputmode="decimal" placeholder="Customer amount" required>
                <input class="form-control form-control-lg mb-2" name="reason" placeholder="Reason" required>
                <button class="btn btn-outline-light btn-lg w-100" type="submit">Draft credit note</button>
            </form>
        </section>
        <?php endif; ?>
        <?php if (!$draft && can('invoices.cancel') && (string) $invoice['status'] !== 'CANCELLED'): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Cancel</h2></div>
            <form class="p-3" method="post" action="<?= e(url('/invoices/' . $invoice['id'] . '/cancel')) ?>">
                <?= csrf_field() ?>
                <input class="form-control form-control-lg mb-2" name="cancellation_reason" placeholder="Reason" required>
                <button class="btn btn-outline-light btn-lg w-100" type="submit">Cancel invoice</button>
            </form>
        </section>
        <?php endif; ?>
        <?php if (!$draft && (can('invoices.issue') || can('debtors.view'))): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Collection note</h2></div>
            <form class="p-3" method="post" action="<?= e(url('/invoices/' . $invoice['id'] . '/flag')) ?>">
                <?= csrf_field() ?>
                <select class="form-select form-select-lg mb-2" name="collection_flag">
                    <option value="">None</option>
                    <option value="DISPUTED" <?= ($invoice['collection_flag'] ?? '') === 'DISPUTED' ? 'selected' : '' ?>>Disputed</option>
                    <option value="COLLECTIONS" <?= ($invoice['collection_flag'] ?? '') === 'COLLECTIONS' ? 'selected' : '' ?>>Collections</option>
                </select>
                <button class="btn btn-outline-light btn-lg w-100" type="submit">Save note</button>
            </form>
        </section>
        <?php endif; ?>
        <?php if (!$draft): ?>
        <section class="sf-panel mb-3">
            <div class="sf-panel-head"><h2>Reminder text</h2></div>
            <textarea class="form-control m-3" rows="5" readonly>Invoice <?= e($number) ?> for <?= e(money((string) $invoice['balance_due'])) ?> is due on <?= e((string) ($invoice['due_date'] ?? '')) ?>. Please use <?= e($number) ?> as the payment reference.</textarea>
        </section>
        <?php endif; ?>
    </div>
</div>

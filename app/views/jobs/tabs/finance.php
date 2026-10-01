<?php $moneyRow = static function (string $label, string $amount, string $note): void { ?>
    <tr><th><?= e($label) ?></th><td><?= e(money($amount)) ?></td><td class="sf-muted"><?= e($note) ?></td></tr>
<?php }; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Commercial and cash</h2></div>
    <div class="table-responsive"><table class="table sf-table mb-0"><tbody>
        <?php $moneyRow('Original quote', (string) $finance['quote_total'], 'Accepted quotation total. Not changed by variations.'); ?>
        <?php $moneyRow('Approved variations', (string) $finance['variations'], 'Extra commercial value.'); ?>
        <?php $moneyRow('Commercial value', (string) $finance['commercial'], 'Quote plus approved variations.'); ?>
        <?php $moneyRow('Invoiced', (string) $finance['invoiced'], 'Issued invoices minus credit notes.'); ?>
        <?php $moneyRow('Paid', (string) $finance['paid'], 'Payments allocated to this job.'); ?>
        <?php $moneyRow('Outstanding', (string) $finance['outstanding'], 'Invoice balances still due.'); ?>
        <?php $moneyRow('Remaining to invoice', (string) $finance['remaining'], 'Commercial value not yet invoiced.'); ?>
    </tbody></table></div>
</section>
<?php if (can('costing.view') || can('finance.costing.view')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Job profitability</h2></div>
    <div class="table-responsive"><table class="table sf-table mb-0"><tbody>
        <?php $moneyRow('Actual material', (string) $finance['actual_material_cost'], 'From recorded usage.'); ?>
        <?php $moneyRow('Actual labour', (string) $finance['actual_labour_cost'], ''); ?>
        <?php $moneyRow('Other costs', (string) $finance['actual_other_cost'], ''); ?>
        <?php $moneyRow('Total actual cost', (string) $finance['actual_total_cost'], ''); ?>
        <tr><th>Operational gross profit</th><td><?= e(money((string) $finance['commercial_profit'])) ?></td><td>Commercial value − actual cost<?= $finance['commercial_margin'] === null ? '' : ' · ' . e((string) $finance['commercial_margin']) . '%' ?></td></tr>
        <tr><th>Invoiced gross profit</th><td><?= e(money((string) $finance['invoiced_profit'])) ?></td><td>Invoiced net − actual cost<?= $finance['invoiced_margin'] === null ? '' : ' · ' . e((string) $finance['invoiced_margin']) . '%' ?></td></tr>
    </tbody></table></div>
</section>
<?php endif; ?>
<?php if (can('invoices.create')): ?>
<p><a class="btn btn-sf btn-lg" href="<?= e(url('/invoices/new?job_id=' . $job['id'])) ?>">Create invoice</a></p>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/variations')) ?>">
    <?= csrf_field() ?>
    <label class="form-label">New variation</label>
    <div class="row g-2"><div class="col-md-8"><input class="form-control form-control-lg" name="description" placeholder="Describe the extra work" required></div>
    <div class="col-md-4"><button class="btn btn-outline-light btn-lg w-100" type="submit">Add variation</button></div></div>
</form>
<?php endif; ?>
<?php foreach ($finance['variations_rows'] as $variation): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2><?= e((string) $variation['variation_number']) ?> · <?= e((string) $variation['status']) ?></h2></div>
    <div class="p-3">
        <p><?= e((string) $variation['description']) ?></p>
        <p>Total <?= e(money((string) $variation['total'])) ?></p>
        <?php if ((string) $variation['status'] === 'DRAFT' && can('invoices.create')): ?>
        <form class="row g-2 mb-2" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/variations/' . $variation['id'] . '/items')) ?>">
            <?= csrf_field() ?>
            <div class="col-md-5"><input class="form-control" name="description" placeholder="Line" required></div>
            <div class="col-md-2"><input class="form-control" name="quantity" value="1"></div>
            <div class="col-md-3"><input class="form-control" name="unit_price" placeholder="Sell price" required></div>
            <div class="col-md-2"><button class="btn btn-outline-light w-100" type="submit">Add</button></div>
        </form>
        <?php endif; ?>
        <?php if (in_array((string) $variation['status'], ['DRAFT', 'AWAITING_APPROVAL'], true) && can('invoices.issue')): ?>
        <form method="post" action="<?= e(url('/jobs/' . $job['id'] . '/variations/' . $variation['id'] . '/approve')) ?>">
            <?= csrf_field() ?>
            <input class="form-control mb-2" name="approved_by_name" placeholder="Customer name" required>
            <select class="form-select mb-2" name="approval_method"><option>EMAIL</option><option>PHONE</option><option>SIGNED</option></select>
            <button class="btn btn-sf" type="submit">Approve variation</button>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php endforeach; ?>
<section class="sf-panel"><div class="sf-panel-head"><h2>Invoices</h2></div>
<ul class="sf-feed"><?php foreach ($finance['invoices'] as $invoice): ?><li><a href="<?= e(url('/invoices/' . $invoice['id'])) ?>"><?= e((string) ($invoice['invoice_number'] ?: 'Draft')) ?></a><small><?= e(money((string) $invoice['total'])) ?> · <?= e((string) $invoice['status']) ?></small></li><?php endforeach; ?></ul>
</section>

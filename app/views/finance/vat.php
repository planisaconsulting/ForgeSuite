<div class="sf-page-head"><h1>Operational VAT summary</h1>
<p class="sf-muted">Invoice-date basis. This does not replace an accountant's VAT201 return.</p></div>
<form class="sf-filters mb-3" method="get">
    <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
    <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
    <button class="btn btn-sf" type="submit">Show</button>
    <a href="<?= e(url('/finance/vat?export=csv&from=' . urlencode($from) . '&to=' . urlencode($to))) ?>">CSV</a>
</form>
<section class="sf-panel p-3">
    <p>Taxable invoice value <?= e(money($summary['taxable'])) ?></p>
    <p>Output VAT <?= e(money($summary['output_vat'])) ?></p>
    <p>Credit note VAT <?= e(money($summary['credit_vat'])) ?></p>
    <p><strong>Net VAT from sales documents <?= e(money($summary['net_vat'])) ?></strong></p>
</section>

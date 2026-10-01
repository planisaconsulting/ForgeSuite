<div class="sf-page-head"><h1>Debtors</h1><p class="sf-muted">Ageing uses the due date. Current means not yet due.</p></div>
<div class="row g-3 mb-3">
<?php foreach (['CURRENT' => 'Current', 'DAYS_1_30' => '1–30', 'DAYS_31_60' => '31–60', 'DAYS_61_90' => '61–90', 'DAYS_90_PLUS' => '90+'] as $key => $label): ?>
    <div class="col-6 col-md"><article class="sf-stat"><p class="sf-kicker"><?= e($label) ?></p><p class="sf-stat-value"><?= e(money($report['buckets'][$key])) ?></p></article></div>
<?php endforeach; ?>
</div>
<p><a href="<?= e(url('/finance/debtors?export=csv')) ?>">CSV</a></p>
<section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Top outstanding</h2></div>
<ul class="sf-feed"><?php foreach (array_slice($report['customers'], 0, 8) as $row): ?><li><a href="<?= e(url('/customers/' . $row['customer_id'])) ?>"><?= e(customer_label($row)) ?></a><small><?= e(money((string) $row['total'])) ?></small></li><?php endforeach; ?></ul>
</section>
<section class="sf-panel"><div class="table-responsive"><table class="table sf-table mb-0"><thead><tr><th>Invoice</th><th>Customer</th><th>Due</th><th>Balance</th><th>Bucket</th></tr></thead>
<tbody><?php foreach ($report['lines'] as $line): ?><tr>
<td><a href="<?= e(url('/invoices/' . $line['id'])) ?>"><?= e((string) $line['invoice_number']) ?></a></td>
<td><?= e(customer_label($line)) ?></td><td><?= e((string) $line['due_date']) ?></td><td><?= e(money((string) $line['balance_due'])) ?></td><td><?= e((string) $line['bucket']) ?></td>
</tr><?php endforeach; ?></tbody></table></div></section>

<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:11px;} table{width:100%;border-collapse:collapse;} td,th{border-bottom:1px solid #ddd;padding:4px;text-align:left;}</style></head><body>
<p><strong><?= e($company) ?></strong></p>
<h1>Statement</h1>
<p><?= e(customer_label($statement['customer'])) ?><br><?= e($statement['from']) ?> to <?= e($statement['to']) ?></p>
<p>Opening balance <?= e(money($statement['opening'])) ?></p>
<table><thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
<tbody><?php foreach ($statement['rows'] as $row): ?><tr>
<td><?= e($row['date']) ?></td><td><?= e($row['reference']) ?></td><td><?= e($row['description']) ?></td>
<td><?= e(money($row['debit'])) ?></td><td><?= e(money($row['credit'])) ?></td><td><?= e(money($row['balance'])) ?></td>
</tr><?php endforeach; ?></tbody></table>
<p>Closing balance <?= e(money($statement['closing'])) ?><br>Amount overdue <?= e(money($statement['overdue'])) ?></p>
<?php if (($bank['name'] ?? '') !== ''): ?><p>Bank <?= e((string) $bank['name']) ?> <?= e((string) $bank['account']) ?> <?= e((string) $bank['number']) ?> branch <?= e((string) $bank['branch']) ?></p><?php endif; ?>
</body></html>

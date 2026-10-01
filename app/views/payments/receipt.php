<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;} </style></head><body>
<p><strong><?= e($company) ?></strong></p>
<h1>Payment receipt</h1>
<p>This is not a tax invoice.</p>
<p><?= e((string) $payment['payment_reference']) ?><br><?= e((string) $payment['payment_date']) ?><br><?= e(customer_label($payment)) ?><br><?= e(money((string) $payment['amount'])) ?> <?= e((string) $payment['payment_method']) ?></p>
<ul><?php foreach ($allocations as $row): if ($row['reversed_at']) continue; ?><li><?= e((string) $row['invoice_number']) ?> <?= e(money((string) $row['amount'])) ?></li><?php endforeach; ?></ul>
</body></html>

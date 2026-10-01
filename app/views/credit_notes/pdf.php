<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;} table{width:100%;border-collapse:collapse;} td,th{border-bottom:1px solid #ddd;padding:4px;}</style></head><body>
<p><strong><?= e($company) ?></strong></p>
<h1>Credit note <?= e((string) $note['credit_note_number']) ?></h1>
<p><?= e((string) ($note['customer_name_snapshot'] ?: customer_label($note))) ?><br>
<?php if (!empty($note['invoice_number'])): ?>Original invoice <?= e((string) $note['invoice_number']) ?><br><?php endif; ?>
Reason: <?= e((string) $note['reason']) ?></p>
<table><tbody><?php foreach ($items as $item): ?><tr><td><?= e((string) $item['description']) ?></td><td><?= e(money((string) $item['total'])) ?></td></tr><?php endforeach; ?></tbody></table>
<p>Subtotal <?= e(money((string) $note['subtotal'])) ?><br>VAT <?= e(money((string) $note['vat_amount'])) ?><br><strong>Total credit <?= e(money((string) $note['total'])) ?></strong></p>
</body></html>

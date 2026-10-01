<?php
/** Customer delivery or collection note. No internal costs. */
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title><?= e((string) $dispatch['dispatch_number']) ?></title>
<style>body{font-family:DejaVu Sans,sans-serif;color:#111;font-size:12px;margin:24px} table{width:100%;border-collapse:collapse} td,th{border-bottom:1px solid #ccc;text-align:left;padding:4px}</style>
</head><body>
<p><?= e((string) ($company['name'] ?? '')) ?></p>
<h1><?= !empty($collection) ? 'Collection note' : 'Delivery note' ?> <?= e((string) $dispatch['dispatch_number']) ?></h1>
<p>Date <?= e((string) $generated_at) ?></p>
<p><?= e(customer_label($dispatch)) ?></p>
<p>Job <?= e((string) ($dispatch['job_number'] ?? '')) ?> · PO <?= e((string) ($dispatch['customer_po_number'] ?? '—')) ?></p>
<table><thead><tr><th>Item</th><th>Qty</th><th>Status</th></tr></thead><tbody>
<?php foreach ($items as $item): ?>
    <tr><td><?= e((string) $item['description']) ?></td><td><?= e((string) $item['quantity']) ?></td><td><?= e((string) $item['status']) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p><?= e((string) $statement) ?></p>
<p>Received by ______________________</p>
</body></html>

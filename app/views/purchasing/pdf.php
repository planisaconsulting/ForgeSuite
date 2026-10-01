<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c1915; }
h1 { font-size: 20px; margin: 0 0 4px; }
table { width: 100%; border-collapse: collapse; margin-top: 16px; }
th, td { border-bottom: 1px solid #ddd; text-align: left; padding: 6px 4px; }
.right { text-align: right; }
</style></head>
<body>
<p><?= e((string) $company) ?></p>
<?php if ($address): ?><p><?= nl_text((string) $address) ?></p><?php endif; ?>
<h1>Purchase order <?= e((string) $order['po_number']) ?></h1>
<p><?= e((string) $order['supplier_name']) ?></p>
<p>Date <?= e((string) $order['order_date']) ?><?= $order['expected_date'] ? ' · Expected ' . e((string) $order['expected_date']) : '' ?></p>
<table>
    <thead><tr><th>Item</th><th class="right">Qty</th><th>Unit</th><th class="right">Price</th><th class="right">Total</th></tr></thead>
    <tbody>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= e((string) $item['description']) ?></td>
            <td class="right"><?= e((string) $item['ordered_quantity']) ?></td>
            <td><?= e((string) $item['unit']) ?></td>
            <td class="right"><?= e(money((string) $item['unit_cost'])) ?></td>
            <td class="right"><?= e(money((string) $item['line_total'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="right">Subtotal <?= e(money((string) $order['subtotal'])) ?><br>VAT <?= e((string) $order['vat_rate']) ?>% <?= e(money((string) $order['vat_amount'])) ?><br><strong>Total <?= e(money((string) $order['total'])) ?></strong></p>
<?php if (!empty($order['notes'])): ?><p><?= nl_text((string) $order['notes']) ?></p><?php endif; ?>
</body>
</html>

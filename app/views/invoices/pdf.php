<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c1915; }
h1 { font-size: 22px; margin: 0; }
table { width: 100%; border-collapse: collapse; margin-top: 12px; }
th, td { border-bottom: 1px solid #ddd; padding: 6px 4px; text-align: left; }
.right { text-align: right; }
.mark { font-size: 18px; letter-spacing: 0.08em; font-weight: bold; }
</style></head><body>
<p class="mark">SIGN-FORGE</p>
<p><?= e((string) ($invoice['company_name_snapshot'] ?: $company)) ?><br>
<?= nl2br(e((string) ($invoice['company_address_snapshot'] ?? ''))) ?><br>
VAT <?= e((string) ($invoice['company_vat_number_snapshot'] ?? '')) ?></p>
<h1>Tax invoice <?= e((string) $invoice['invoice_number']) ?></h1>
<p>Date <?= e((string) $invoice['invoice_date']) ?> · Due <?= e((string) ($invoice['due_date'] ?? '')) ?><br>
<strong>Reference: <?= e((string) $invoice['invoice_number']) ?></strong></p>
<p><?= e((string) ($invoice['customer_name_snapshot'] ?: customer_label($invoice))) ?><br>
<?= nl2br(e((string) ($invoice['customer_address_snapshot'] ?? ''))) ?><br>
VAT <?= e((string) ($invoice['customer_vat_number_snapshot'] ?? '')) ?>
<?php if (!empty($invoice['customer_po_number'])): ?><br>PO <?= e((string) $invoice['customer_po_number']) ?><?php endif; ?></p>
<table>
<thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Total</th></tr></thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr>
<td><?= e((string) $item['description']) ?></td>
<td class="right"><?= e((string) $item['quantity']) ?></td>
<td class="right"><?= e(money((string) $item['unit_price'])) ?></td>
<td class="right"><?= e(money((string) $item['line_total'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p class="right">Subtotal <?= e(money((string) $invoice['subtotal'])) ?><br>
Discount <?= e(money((string) $invoice['discount_amount'])) ?><br>
VAT <?= e((string) $invoice['vat_rate']) ?>% <?= e(money((string) $invoice['vat_amount'])) ?><br>
<strong>Total <?= e(money((string) $invoice['total'])) ?></strong><br>
Paid <?= e(money((string) $invoice['amount_paid'])) ?><br>
<strong>Balance due <?= e(money((string) $invoice['balance_due'])) ?></strong></p>
<?php if (!empty($invoice['bank_name_snapshot'])): ?>
<p>Bank <?= e((string) $invoice['bank_name_snapshot']) ?><br>
Account <?= e((string) ($invoice['account_name_snapshot'] ?? '')) ?> <?= e((string) ($invoice['account_number_snapshot'] ?? '')) ?><br>
Branch <?= e((string) ($invoice['branch_code_snapshot'] ?? '')) ?> <?= e((string) ($invoice['account_type_snapshot'] ?? '')) ?></p>
<?php endif; ?>
<?php if (!empty($invoice['terms'])): ?><p><?= nl2br(e((string) $invoice['terms'])) ?></p><?php endif; ?>
<?php if (!empty($invoice['customer_notes'])): ?><p><?= nl2br(e((string) $invoice['customer_notes'])) ?></p><?php endif; ?>
</body></html>

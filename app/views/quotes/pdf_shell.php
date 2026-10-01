<?php
/** Customer-facing quotation. No costs, markup, internal notes, or waste. */
$symbol = (string) ($company['symbol'] ?? 'R');
$included = [];
$optional = [];
foreach ($items as $item) {
    if ((int) ($item['is_optional'] ?? 0) === 1 && (int) ($item['include_optional'] ?? 0) !== 1) {
        $optional[] = $item;
    } else {
        $included[] = $item;
    }
}
$sectionTitle = [];
foreach ($sections as $section) {
    $sectionTitle[(int) $section['id']] = (string) $section['title'];
}
$groups = [];
foreach ($included as $item) {
    $key = (int) ($item['section_id'] ?? 0);
    $groups[$key][] = $item;
}
$customerName = customer_label($quote);
$vatMode = (string) ($quote['vat_mode'] ?? 'EXCLUSIVE');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e((string) $quote['quote_number']) ?> R<?= e((string) $quote['revision_number']) ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1c1f24; font-size: 12px; margin: 28px; }
        h1 { font-size: 22px; margin: 0; letter-spacing: 0.04em; }
        h2 { font-size: 13px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.06em; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid #1c1f24; padding: 6px 4px; }
        td { border-bottom: 1px solid #d5d8de; padding: 6px 4px; vertical-align: top; }
        .right { text-align: right; }
        .totals { width: 260px; margin-left: auto; margin-top: 12px; }
        .totals td { border: 0; padding: 3px 0; }
        .grand td { font-weight: bold; border-top: 2px solid #1c1f24; font-size: 14px; }
        .muted { color: #5c6570; }
        .head { width: 100%; }
        .head td { border: 0; vertical-align: top; }
        .brand { font-size: 18px; font-weight: bold; }
        .accent { color: #c45512; }
        .box { border: 1px solid #d5d8de; padding: 10px; margin-top: 8px; }
        .sign { margin-top: 28px; }
        .sign td { border: 0; height: 48px; }
        .optional { background: #f6f7f9; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<?php if (!empty($print)): ?>
    <p class="no-print"><button type="button" onclick="window.print()">Print</button></p>
<?php endif; ?>
<table class="head">
    <tr>
        <td>
            <div class="brand">SIGN-FORGE</div>
            <strong><?= e((string) $company['name']) ?></strong><br>
            <?php if (($company['address'] ?? '') !== ''): ?><?= nl2br(e((string) $company['address'])) ?><br><?php endif; ?>
            <?= e((string) ($company['telephone'] ?? '')) ?> <?= e((string) ($company['email'] ?? '')) ?><br>
            <?php if (($company['vat'] ?? '') !== ''): ?>VAT <?= e((string) $company['vat']) ?><br><?php endif; ?>
            <?php if (($company['registration'] ?? '') !== ''): ?>Reg <?= e((string) $company['registration']) ?><?php endif; ?>
        </td>
        <td class="right">
            <h1 class="accent">QUOTATION</h1>
            <strong><?= e((string) $quote['quote_number']) ?></strong><br>
            Revision <?= e((string) $quote['revision_number']) ?><br>
            Date <?= e((string) $quote['quote_date']) ?><br>
            Expiry <?= e((string) ($quote['expiry_date'] ?? '—')) ?>
        </td>
    </tr>
</table>
<h2>Customer</h2>
<div class="box">
    <strong><?= e($customerName) ?></strong><br>
    <?php if (!empty($quote['contact_name'])): ?>Contact: <?= e((string) $quote['contact_name']) ?><br><?php endif; ?>
    <?= e((string) ($quote['customer_email'] ?? $quote['email'] ?? '')) ?>
    <?= e((string) ($quote['customer_phone'] ?? '')) ?><br>
    <?= nl2br(e((string) ($quote['billing_address'] ?? ''))) ?>
    <?php if (!empty($quote['customer_vat'])): ?><br>VAT <?= e((string) $quote['customer_vat']) ?><?php endif; ?>
</div>
<h2>Items</h2>
<table>
    <thead>
        <tr>
            <th>Description</th>
            <th>Dimensions</th>
            <th class="right">Qty</th>
            <th class="right">Unit price</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($groups as $sectionId => $rows): ?>
        <?php if ($sectionId !== 0 && isset($sectionTitle[$sectionId])): ?>
            <tr><td colspan="5"><strong><?= e($sectionTitle[$sectionId]) ?></strong></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $item): ?>
            <tr>
                <td><?= nl2br(e((string) ($item['customer_description'] ?: $item['product_name_snapshot']))) ?></td>
                <td><?= e(quote_line_size($item)) ?></td>
                <td class="right"><?= e((string) $item['quantity']) ?></td>
                <td class="right"><?= e(money((string) $item['unit_sell_price'])) ?></td>
                <td class="right"><?= e(money((string) $item['line_total'])) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if ($included === []): ?>
        <tr><td colspan="5" class="muted">No priced items.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php if ($optional !== []): ?>
    <h2>Optional, not included in the total</h2>
    <table>
        <tbody>
        <?php foreach ($optional as $item): ?>
            <tr class="optional">
                <td><?= nl2br(e((string) ($item['customer_description'] ?: $item['product_name_snapshot']))) ?></td>
                <td class="right"><?= e(money((string) $item['line_total'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<table class="totals">
    <tr><td>Subtotal</td><td class="right"><?= e(money((string) $quote['subtotal'])) ?></td></tr>
    <?php if (\App\Helpers\Decimal::cmp((string) $quote['discount_amount'], '0') > 0): ?>
        <tr><td>Discount</td><td class="right">-<?= e(money((string) $quote['discount_amount'])) ?></td></tr>
        <tr><td>After discount</td><td class="right"><?= e(money((string) $quote['subtotal_after_discount'])) ?></td></tr>
    <?php endif; ?>
    <?php if ($vatMode !== 'NO_VAT'): ?>
        <tr><td>VAT <?= e((string) $quote['vat_rate']) ?>% <?= $vatMode === 'INCLUSIVE' ? '(included)' : '' ?></td><td class="right"><?= e(money((string) $quote['vat_amount'])) ?></td></tr>
    <?php endif; ?>
    <tr class="grand"><td>Total</td><td class="right"><?= e(money((string) $quote['total'])) ?></td></tr>
    <?php if ((string) ($quote['deposit_type'] ?? 'NONE') !== 'NONE'): ?>
        <tr><td>Deposit required</td><td class="right"><?= e(money((string) $quote['deposit_amount'])) ?></td></tr>
    <?php endif; ?>
</table>
<?php if (trim((string) ($quote['customer_notes'] ?? '')) !== ''): ?>
    <h2>Notes</h2>
    <div class="box"><?= nl2br(e((string) $quote['customer_notes'])) ?></div>
<?php endif; ?>
<?php if (trim((string) ($quote['terms'] ?? '')) !== ''): ?>
    <h2>Terms</h2>
    <div class="box"><?= nl2br(e((string) $quote['terms'])) ?></div>
<?php endif; ?>
<div class="sign">
    <h2>Acceptance</h2>
    <p>Accepting this quotation confirms the sizes, price, and terms above.</p>
    <table>
        <tr><td>Name ______________________________</td><td>Signature ______________________________</td></tr>
        <tr><td>Date ______________________________</td><td>Purchase order _________________________</td></tr>
    </table>
</div>
</body>
</html>

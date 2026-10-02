<div class="sf-page-head">
    <h1>Purchasing workbench</h1>
    <p class="mb-0">Shortages, requests, quotations, and receipts stay separate. The cheapest quote is not chosen for you.</p>
</div>
<div class="row g-3">
    <?php foreach ([
        'open_requests' => 'Purchase requests',
        'open_rfqs' => 'Open RFQs',
        'awaiting_response' => 'Awaiting supplier response',
        'quotes_to_review' => 'Quotes to review',
        'late_pos' => 'Late purchase orders',
        'exceptions' => 'Receiving exceptions',
        'returns' => 'Supplier returns',
    ] as $key => $label): ?>
        <div class="col-md-3"><section class="sf-panel"><p class="mb-1"><?= e($label) ?></p><p class="fs-3 mb-0"><?= (int) ($cards[$key] ?? 0) ?></p></section></div>
    <?php endforeach; ?>
</div>
<?php if ($showCost): ?>
    <section class="sf-panel mt-3">
        <p>On-time delivery is <?= (int) $onTime['on_time'] ?> of <?= (int) $onTime['eligible'] ?> receipts. <?= e((string) $onTime['formula']) ?>.</p>
    </section>
<?php endif; ?>

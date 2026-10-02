<div class="sf-page-head">
    <h1>Portal requests</h1>
    <p class="mb-0">Customer orders wait here. Nothing is released to production from this list.</p>
</div>
<div class="row g-3">
    <?php foreach ([
        'quote_requests' => 'Quote requests',
        'new_orders' => 'New orders',
        'info' => 'Information required',
        'cancellations' => 'Cancellation requests',
    ] as $key => $label): ?>
        <div class="col-md-3"><section class="sf-panel p-3"><p class="mb-1"><?= e($label) ?></p><p class="fs-3 mb-0"><?= (int) ($cards[$key] ?? 0) ?></p></section></div>
    <?php endforeach; ?>
</div>

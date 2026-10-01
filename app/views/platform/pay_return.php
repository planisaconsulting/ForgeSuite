<div class="sf-auth-card">
    <h1>Payment</h1>
    <?php if ($request === null): ?>
        <p>That payment link was not found.</p>
    <?php else: ?>
        <p>This page does not record a payment. <?= e((string) $request['status']) === 'PAID' ? 'The provider has already confirmed it.' : 'Waiting for the payment provider to confirm.' ?></p>
        <p class="sf-muted mb-0">Amount on the request: <?= e((string) $request['amount']) ?> <?= e((string) $request['currency']) ?></p>
    <?php endif; ?>
</div>

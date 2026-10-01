<div class="sf-page-head"><h1><?= e(customer_label($customer)) ?></h1></div>
<div class="sf-action-row mb-3">
    <?php $phone = (string) ($customer['mobile'] ?: $customer['phone']); ?>
    <?php if ($phone !== ''): ?>
        <a class="btn btn-sf sf-touch" href="<?= e('tel:' . preg_replace('/\s+/', '', $phone)) ?>">Call</a>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://wa.me/' . preg_replace('/\D+/', '', $phone)) ?>">WhatsApp</a>
    <?php endif; ?>
    <?php if ((string) ($customer['email'] ?? '') !== ''): ?>
        <a class="btn btn-outline-light sf-touch" href="<?= e('mailto:' . $customer['email']) ?>">Email</a>
    <?php endif; ?>
    <?php if ((string) ($customer['physical_address'] ?? '') !== ''): ?>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string) $customer['physical_address'])) ?>">Maps</a>
    <?php endif; ?>
</div>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Open quotes</h2></div>
    <?php if ($quotes === []): ?><div class="sf-empty"><p>No open quotes on this card.</p></div><?php else: ?>
        <?php foreach ($quotes as $quote): ?>
            <a class="sf-field-card" href="<?= e(url('/m/quotes/' . $quote['id'])) ?>">
                <strong><?= e((string) $quote['quote_number']) ?></strong>
                <small><?= e((string) $quote['status']) ?></small>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Active jobs</h2></div>
    <?php if ($jobs === []): ?><div class="sf-empty"><p>No active jobs.</p></div><?php else: ?>
        <?php foreach ($jobs as $job): ?>
            <a class="sf-field-card" href="<?= e(url('/m/jobs/' . $job['id'])) ?>">
                <strong><?= e((string) $job['job_number']) ?></strong>
                <span><?= e((string) $job['title']) ?></span>
                <small><?= e((string) $job['status']) ?></small>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

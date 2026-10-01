<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $job['status']) ?></p>
    <h1><?= e((string) $job['job_number']) ?></h1>
    <p class="sf-muted mb-0"><?= e(customer_label($job)) ?></p>
</div>
<div class="sf-action-row mb-3">
    <?php $phone = (string) ($job['site_contact_phone'] ?: ($job['mobile'] ?: $job['phone'])); ?>
    <?php if ($phone !== ''): ?>
        <a class="btn btn-sf sf-touch" href="<?= e('tel:' . preg_replace('/\s+/', '', $phone)) ?>">Call</a>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://wa.me/' . preg_replace('/\D+/', '', $phone)) ?>">WhatsApp</a>
    <?php endif; ?>
    <?php if ((string) ($job['site_address'] ?? '') !== ''): ?>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string) $job['site_address'])) ?>">Navigate</a>
    <?php endif; ?>
    <a class="btn btn-outline-light sf-touch" href="<?= e(url('/jobs/' . $job['id'])) ?>">Open job</a>
</div>
<section class="sf-panel"><div class="p-3">
    <p><?= e((string) $job['title']) ?></p>
    <p><?= e((string) ($job['site_address'] ?? '')) ?></p>
    <p><?= nl2br(e((string) ($job['description'] ?? ''))) ?></p>
    <?php if ($artwork !== null): ?>
        <p class="mb-0">Approved revision <?= e((string) $artwork['revision_number']) ?> · <?= e((string) $artwork['title']) ?></p>
    <?php endif; ?>
</div></section>
<p class="sf-muted mt-3">Selling price and cost are on the full job only when your role can open costing.</p>

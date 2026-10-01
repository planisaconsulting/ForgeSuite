<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $lead['status']) ?></p>
    <h1><?= e((string) $lead['name']) ?></h1>
    <p class="sf-muted mb-0"><?= e((string) ($lead['company_name'] ?? '')) ?></p>
</div>
<div class="d-grid gap-2">
    <?php if ((string) ($lead['phone'] ?? '') !== ''): ?>
        <a class="btn btn-sf sf-touch" href="<?= e('tel:' . preg_replace('/\s+/', '', (string) $lead['phone'])) ?>">Call</a>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://wa.me/' . preg_replace('/\D+/', '', (string) $lead['phone'])) ?>">WhatsApp</a>
    <?php endif; ?>
    <?php if ((string) ($lead['email'] ?? '') !== ''): ?>
        <a class="btn btn-outline-light sf-touch" href="<?= e('mailto:' . $lead['email']) ?>">Email</a>
    <?php endif; ?>
    <a class="btn btn-outline-light sf-touch" href="<?= e(url('/leads/' . $lead['id'])) ?>">Open lead</a>
</div>

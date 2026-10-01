<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $quote['status']) ?></p>
    <h1><?= e((string) $quote['quote_number']) ?></h1>
    <p class="sf-muted mb-0"><?= e(customer_label($quote)) ?></p>
</div>
<section class="sf-panel mb-3"><div class="p-3">
    <p>Total <?= e((string) $quote['total']) ?></p>
    <p class="mb-0">Valid until <?= e((string) ($quote['expiry_date'] ?? '—')) ?></p>
</div></section>
<div class="d-grid gap-2">
    <a class="btn btn-sf sf-touch" href="<?= e(url('/quotes/' . $quote['id'] . '/pdf')) ?>">Preview PDF</a>
    <a class="btn btn-outline-light sf-touch" href="<?= e(url('/quotes/' . $quote['id'])) ?>">Open quote</a>
</div>
<p class="sf-muted mt-3">Editing the lines stays on the full quote screen.</p>

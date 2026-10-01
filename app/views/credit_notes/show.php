<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div><h1><?= e((string) ($note['credit_note_number'] ?: 'Draft credit note')) ?></h1>
    <p class="sf-muted mb-0"><?= e(customer_label($note)) ?> · <?= e((string) $note['status']) ?></p></div>
    <?php if ((string) $note['status'] !== 'DRAFT'): ?><a class="btn btn-sf btn-lg" href="<?= e(url('/credit-notes/' . $note['id'] . '/pdf')) ?>">PDF</a><?php endif; ?>
</div>
<p>Reason: <?= e((string) $note['reason']) ?></p>
<p>Total <?= e(money((string) $note['total'])) ?> including VAT <?= e(money((string) $note['vat_amount'])) ?>. The original invoice total is not rewritten.</p>
<?php if ((string) $note['status'] === 'DRAFT' && can('credit_notes.issue')): ?>
<form method="post" action="<?= e(url('/credit-notes/' . $note['id'] . '/issue')) ?>"><?= csrf_field() ?><button class="btn btn-sf btn-lg" type="submit">Issue credit note</button></form>
<?php endif; ?>
<ul><?php foreach ($items as $item): ?><li><?= e((string) $item['description']) ?> <?= e(money((string) $item['total'])) ?></li><?php endforeach; ?></ul>

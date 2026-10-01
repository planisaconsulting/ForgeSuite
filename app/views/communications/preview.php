<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Email preview</h1><p class="sf-muted mb-0">Nothing is sent until you confirm. A quote PDF is attached on send.</p></div>
<section class="sf-panel p-3 mb-3">
    <p><strong>To</strong> <?= e((string) ($preview['to'] ?? '')) ?></p>
    <p><strong>Subject</strong> <?= e((string) ($preview['subject'] ?? '')) ?></p>
    <pre class="mb-0"><?= e((string) ($preview['body'] ?? '')) ?></pre>
</section>
<form class="sf-panel p-3" method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/email')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="to" value="<?= e((string) ($preview['to'] ?? '')) ?>">
    <input type="hidden" name="template_id" value="<?= e((string) $templateId) ?>">
    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirm" value="1" required> I have checked the recipient, subject, message, and quote PDF.</label>
    <button class="btn btn-sf" type="submit">Send</button>
</form>

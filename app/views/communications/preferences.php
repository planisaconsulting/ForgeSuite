<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1><?= e((string) $contact['name']) ?></h1><p class="sf-muted mb-0">Marketing consent is separate from quotes, invoices, and other operational messages.</p></div>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/customers/' . $customerId . '/contacts/' . $contact['id'] . '/preferences')) ?>">
    <?= csrf_field() ?>
    <?php foreach (['email_allowed' => 'Operational email', 'whatsapp_allowed' => 'WhatsApp', 'sms_allowed' => 'SMS', 'transactional_allowed' => 'Transactional messages', 'marketing_allowed' => 'Marketing'] as $field => $label): ?>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="<?= e($field) ?>" <?= (int) ($prefs[$field] ?? 0) === 1 ? 'checked' : '' ?>> <?= e($label) ?></label>
    <?php endforeach; ?>
    <button class="btn btn-sf mt-3" type="submit">Save preferences</button>
</form>
<form class="sf-panel p-3" method="post" action="<?= e(url('/customers/' . $customerId . '/contacts/' . $contact['id'] . '/unsubscribe')) ?>">
    <?= csrf_field() ?>
    <h2 class="h6">Unsubscribe from marketing email</h2>
    <input class="form-control mb-2" name="reason" placeholder="Reason, optional">
    <button class="btn btn-outline-light" type="submit">Suppress marketing email</button>
</form>

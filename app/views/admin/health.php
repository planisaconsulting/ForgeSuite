<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>System health</h1><p class="sf-muted mb-0">Sign-Forge <?= e($version) ?></p></div>
<section class="sf-panel">
    <dl class="sf-dl">
        <div><dt>PHP</dt><dd><?= e((string) $health['php']) ?></dd></div>
        <div><dt>Database</dt><dd><?= $health['database_connected'] ? 'Connected' : 'Not connected' ?> <?= e((string) $health['database_version']) ?></dd></div>
        <div><dt>Storage writable</dt><dd><?= $health['storage_writable'] ? 'Yes' : 'No' ?></dd></div>
        <div><dt>Storage used</dt><dd><?= e(number_format(((int) $health['storage_bytes']) / 1048576, 1)) ?> MB</dd></div>
        <div><dt>Application version</dt><dd><?= e((string) $health['app_version']) ?></dd></div>
        <div><dt>Latest migration file</dt><dd><?= e((string) $health['last_migration']) ?></dd></div>
        <div><dt>Last cron</dt><dd><?= e((string) ($health['last_cron'] !== '' ? $health['last_cron'] : 'Not recorded')) ?></dd></div>
        <div><dt>Last successful backup</dt><dd><?= e((string) ($health['last_backup'] !== '' ? $health['last_backup'] : 'None')) ?></dd></div>
        <div><dt>Failed automations, 7 days</dt><dd><?= e((string) $health['failed_automations']) ?></dd></div>
        <div><dt>Website lead endpoint</dt><dd><?= e((string) ($health['website_leads'] ?? '')) ?></dd></div>
        <div><dt>Email</dt><dd><?= e((string) ($health['email_status'] ?? '')) ?></dd></div>
        <div><dt>WhatsApp</dt><dd><?= e((string) ($health['whatsapp_status'] ?? '')) ?></dd></div>
        <div><dt>Last communication error</dt><dd><?= e((string) (($health['last_communication_error'] ?? '') !== '' ? $health['last_communication_error'] : 'None')) ?></dd></div>
        <div><dt>Last webhook event</dt><dd><?= e((string) (($health['last_webhook'] ?? '') !== '' ? $health['last_webhook'] : 'None')) ?></dd></div>
    </dl>
</section>

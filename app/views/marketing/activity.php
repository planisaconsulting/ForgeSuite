<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Communication activity</h1><p class="sf-muted mb-0">Counts this month. This list is not an employee ranking.</p></div>
<section class="sf-panel p-3 mb-3">
    <p class="mb-0">First genuine contact this month: median <?= e((string) ($response['median'] ?? 'n/a')) ?> minutes across <?= e((string) $response['samples']) ?> leads. Range <?= e((string) ($response['low'] ?? 'n/a')) ?> to <?= e((string) ($response['high'] ?? 'n/a')) ?>.</p>
</section>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>Person</th><th>Calls</th><th>Emails sent</th><th>WhatsApp actions</th><th>Lead touches</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['name']) ?></td>
                    <td><?= e((string) $row['calls']) ?></td>
                    <td><?= e((string) $row['emails']) ?></td>
                    <td><?= e((string) $row['whatsapp']) ?></td>
                    <td><?= e((string) $row['lead_touches']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

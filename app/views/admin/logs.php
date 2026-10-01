<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Application log</h1><p class="sf-muted mb-0">Recent lines from the application log. Password-like values are redacted.</p></div>
<section class="sf-panel">
    <?php if ($rows === []): ?>
        <div class="sf-empty"><p>No log lines yet.</p></div>
    <?php else: ?>
        <ul class="sf-feed">
            <?php foreach ($rows as $row): ?>
                <li><small><?= e((string) $row['severity']) ?></small><p class="mb-0"><?= e((string) $row['message']) ?></p></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Traceability</h1><p class="sf-muted mb-0"><?= e((string) $job['job_number']) ?></p></div>
<section class="sf-panel">
    <?php if ($events === []): ?><p class="p-3 mb-0">No scans yet.</p><?php else: ?>
        <ul class="sf-feed"><?php foreach ($events as $event): ?>
            <li><strong><?= e((string) $event['created_at']) ?></strong> <?= e((string) $event['action']) ?>
                <small><?= e((string) ($event['user_name'] ?? '')) ?> <?= e((string) ($event['tracking_code'] ?? '')) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

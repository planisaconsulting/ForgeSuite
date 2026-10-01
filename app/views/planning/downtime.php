<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Downtime</h1><p class="sf-muted mb-0"><?= e($from) ?> to <?= e($to) ?></p></div>
<section class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No downtime in this range.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['resource_name']) ?><small><?= e((string) $row['started_at']) ?> · <?= e((string) $row['reason']) ?><?php if ($row['cost'] !== null): ?> · cost <?= e((string) $row['cost']) ?><?php endif; ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

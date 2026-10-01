<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Late jobs</h1><p class="sf-muted mb-0">Completed after the internal target. Causes are only the block reasons someone recorded.</p></div>
<section class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No late completions in this range.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['job_number']) ?> · <?= e(customer_label($row)) ?><small>Target <?= e((string) $row['target_date']) ?> · completed <?= e((string) $row['completed_at']) ?><?= $row['causes'] === [] ? ' · no recorded cause' : ' · ' . e(implode('; ', $row['causes'])) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Production queue</h1></div>
<section class="sf-panel">
    <?php if ($rows === []): ?><p class="p-3 mb-0">The queue is clear.</p><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><a href="<?= e(url('/jobs/' . $row['id'] . '/card')) ?>"><?= e((string) $row['job_number']) ?></a>
                <small><?= e(customer_label($row)) ?> · <?= e((string) $row['status']) ?> · due <?= e((string) ($row['target_date'] ?? '—')) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

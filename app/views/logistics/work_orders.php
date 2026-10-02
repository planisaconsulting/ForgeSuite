<div class="sf-page-head"><h1>Contractor work</h1></div>
<p>Work orders are sent from a job. A contractor does not see the order until it is sent, and a submitted completion still needs an internal review.</p>
<section class="sf-panel p-3">
    <?php if ($rows === []): ?><p class="mb-0">No contractor companies yet.</p><?php else: ?>
        <ul class="mb-0"><?php foreach ($rows as $row): ?><li><?= e((string) $row['company_name']) ?> · <?= e((string) $row['status']) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>

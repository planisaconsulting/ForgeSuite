<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>My projects</h1></div>
<section class="sf-panel">
    <?php if ($rows === []): ?><p>No projects are assigned to you.</p><?php endif; ?>
    <ul class="sf-feed">
        <?php foreach ($rows as $row): ?>
            <li><a href="<?= e(url('/m/projects/' . $row['id'])) ?>"><?= e((string) $row['project_number']) ?></a> <?= e((string) $row['name']) ?><br><small><?= e((string) $row['project_health']) ?> · <?= (int) $row['site_count'] ?> sites</small></li>
        <?php endforeach; ?>
    </ul>
</section>

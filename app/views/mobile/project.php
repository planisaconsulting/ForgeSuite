<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $project['project_number']) ?></p>
    <h1><?= e((string) $project['name']) ?></h1>
</div>
<section class="sf-panel">
    <?php foreach ($sites as $site): ?>
        <p><a href="<?= e(url('/project-sites/' . $site['id'])) ?>"><?= e((string) $site['site_code']) ?></a> <?= e((string) $site['site_name']) ?><br><small><?= e(str_replace('_', ' ', (string) $site['status'])) ?></small></p>
    <?php endforeach; ?>
    <?php if ($sites === []): ?><p>No sites on this project.</p><?php endif; ?>
</section>
<p class="sf-muted">Gantt editing stays on a desktop screen.</p>

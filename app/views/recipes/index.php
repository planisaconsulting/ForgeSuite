<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div><h1>Recipes</h1><p class="sf-muted mb-0">What a finished sign is made of, and how it is costed.</p></div>
    <?php if ($canEdit): ?><a class="btn btn-sf" href="<?= e(url('/recipes/new')) ?>">New recipe</a><?php endif; ?>
</div>
<section class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No recipes yet.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><a href="<?= e(url('/recipes/' . $row['id'] . '/edit')) ?>"><?= e((string) $row['name']) ?></a>
                <small><?= e((string) $row['code']) ?> · v<?= e((string) $row['version_number']) ?> · <?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Reprints</h1><p class="sf-muted mb-0">Reprint material is recorded as rework, not as ordinary consumption. The reason is not a blame field.</p></div>
<section class="sf-panel p-3">
    <ul class="mb-0"><?php foreach ($reasons as $reason): ?><li><?= e((string) $reason['label']) ?></li><?php endforeach; ?></ul>
</section>

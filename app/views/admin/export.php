<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Data export</h1><p class="sf-muted mb-0">CSV of operational records you are allowed to view. Core records are not deleted by this screen.</p></div>
<section class="sf-panel">
    <ul class="sf-feed">
        <?php foreach ($datasets as $key => $dataset): ?>
            <?php if (!can($dataset['permission'])) { continue; } ?>
            <li><a href="<?= e(url('/admin/export.csv?dataset=' . $key)) ?>"><?= e($dataset['label']) ?></a><small>CSV</small></li>
        <?php endforeach; ?>
    </ul>
</section>

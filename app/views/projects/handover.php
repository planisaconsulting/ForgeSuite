<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1">Customer handover</p>
    <h1><?= e((string) ($pack['project_number'] ?? '')) ?></h1>
    <p><?= e((string) ($pack['name'] ?? '')) ?> · <?= e((string) ($pack['customer'] ?? '')) ?></p>
</div>
<section class="sf-panel">
    <p><?= e((string) ($pack['description'] ?? '')) ?></p>
    <p>Completed <?= e((string) ($pack['completed'] ?: 'not yet')) ?>.</p>
    <h2>Sites</h2>
    <ul>
        <?php foreach (($pack['sites'] ?? []) as $site): ?>
            <li><?= e($site['code']) ?> <?= e($site['name']) ?> · <?= e(str_replace('_', ' ', $site['status'])) ?> <?= e($site['completed']) ?></li>
        <?php endforeach; ?>
    </ul>
    <h2>Documents</h2>
    <?php if (($pack['documents'] ?? []) === []): ?><p>No customer-visible documents.</p><?php endif; ?>
    <ul><?php foreach (($pack['documents'] ?? []) as $document): ?><li><?= e((string) $document['original_filename']) ?></li><?php endforeach; ?></ul>
</section>
<p class="sf-muted">Internal costs, margin, supplier prices, risks, and internal notes are not in this pack.</p>

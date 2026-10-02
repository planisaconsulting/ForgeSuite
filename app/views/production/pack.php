<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <?php if ((string) $release['status'] === 'SUPERSEDED'): ?>
        <p class="display-6">SUPERSEDED — DO NOT PRODUCE.</p>
    <?php endif; ?>
    <h1>Production pack <?= e((string) $release['release_number']) ?></h1>
    <p>Release R<?= (int) $release['release_version'] ?> · Artwork R<?= (int) ($snapshot['artwork']['revision'] ?? 0) ?> · <?= e((string) ($snapshot['generated_at'] ?? '')) ?></p>
</div>
<section class="sf-panel">
    <p>This pack is internal. It does not include customer debt, margin, or markup.</p>
    <p>Job <?= e((string) ($snapshot['job_number'] ?? '')) ?> · target <?= e((string) ($snapshot['target_date'] ?? '')) ?> · fulfilment <?= e((string) ($snapshot['delivery_method'] ?? '')) ?></p>
    <?php if (!empty($snapshot['technical']['specification_code'])): ?>
        <p>Specification <?= e((string) $snapshot['technical']['specification_code']) ?> v<?= (int) $snapshot['technical']['specification_version'] ?></p>
    <?php endif; ?>
    <h2 class="h5">Items</h2>
    <ul>
        <?php foreach ($snapshot['items'] ?? [] as $item): ?>
            <li><?= e((string) ($item['description'] ?? 'Item')) ?> · <?= e((string) ($item['quantity'] ?? '')) ?> · <?= e((string) ($item['width_mm'] ?? '')) ?> × <?= e((string) ($item['height_mm'] ?? '')) ?> mm</li>
        <?php endforeach; ?>
    </ul>
    <h2 class="h5">Materials</h2>
    <ul>
        <?php foreach ($snapshot['requirements'] ?? [] as $row): ?>
            <li><?= e((string) ($row['product_name'] ?? 'Material')) ?> · <?= e((string) ($row['final_required_quantity'] ?? '')) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php if ($showCosts): ?><p>Cost detail is limited to roles that can view production cost. Supplier cost is not printed here.</p><?php endif; ?>
</section>

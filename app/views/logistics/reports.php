<div class="sf-page-head"><h1>Logistics reports</h1></div>
<p>These figures are counts and averages. They are not a score.</p>
<ul>
    <li>Ready to dispatch: <?= (int) $cards['ready_to_dispatch'] ?></li>
    <li>In transit: <?= (int) $cards['in_transit'] ?></li>
    <li>Open exceptions: <?= (int) $cards['delivery_exceptions'] ?></li>
    <li>Installations today: <?= (int) $cards['installations_today'] ?></li>
    <li>Contractor work overdue: <?= (int) $cards['contractor_overdue'] ?></li>
</ul>
<section class="sf-panel p-3">
    <h2 class="h5">Courier performance</h2>
    <?php if ($couriers === []): ?><p class="mb-0">No couriers yet.</p><?php else: ?>
        <ul class="mb-0"><?php foreach ($couriers as $row): ?>
            <li><?= e((string) $row['name']) ?> · shipments <?= (int) $row['shipments'] ?> · failed <?= (int) $row['failed'] ?></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

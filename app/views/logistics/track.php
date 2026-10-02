<?php if ($row === null): ?>
    <h1>Tracking link unavailable</h1>
    <p>This link has expired or been withdrawn.</p>
<?php else: ?>
    <h1><?= e((string) $row['shipment_number']) ?></h1>
    <p><?= e((string) $row['status']) ?></p>
    <?php if (!empty($row['courier_name'])): ?><p><?= e((string) $row['courier_name']) ?></p><?php endif; ?>
    <?php if (!empty($row['tracking_number'])): ?><p>Tracking <?= e((string) $row['tracking_number']) ?></p><?php endif; ?>
    <?php if (!empty($row['tracking_url'])): ?><p><a href="<?= e((string) $row['tracking_url']) ?>">Courier tracking</a></p><?php endif; ?>
<?php endif; ?>

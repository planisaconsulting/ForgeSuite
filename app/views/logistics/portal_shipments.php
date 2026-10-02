<h1>Deliveries</h1>
<?php if ($rows === []): ?><p>Nothing has been dispatched for your account.</p><?php else: ?>
    <?php foreach ($rows as $row): ?>
        <section class="sf-panel p-3 mb-3">
            <h2 class="h5"><?= e((string) $row['shipment_number']) ?></h2>
            <p><?= e((string) $row['status']) ?><?php if (!empty($row['courier_name'])): ?> · <?= e((string) $row['courier_name']) ?><?php endif; ?></p>
            <?php if (!empty($row['tracking_number'])): ?><p>Tracking <?= e((string) $row['tracking_number']) ?></p><?php endif; ?>
            <?php if (!empty($row['tracking_url'])): ?><p><a href="<?= e((string) $row['tracking_url']) ?>">Track</a></p><?php endif; ?>
            <?php if (!empty($row['pod'])): ?><p>Received by <?= e((string) $row['pod']['recipient_name']) ?></p><?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

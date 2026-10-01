<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Stock counts</h1></div>
<?php if (can('inventory.count')): ?>
<form class="sf-filters mb-3" method="post" action="<?= e(url('/inventory/counts')) ?>">
    <?= csrf_field() ?>
    <select class="form-select form-select-lg" name="stock_location_id" required>
        <?php foreach ($locations as $location): ?><option value="<?= e((string) $location['id']) ?>"><?= e((string) $location['name']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-sf btn-lg" type="submit">Start count</button>
</form>
<?php endif; ?>
<div class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No stock counts yet.</p></div><?php else: ?>
        <ul class="sf-feed">
            <?php foreach ($rows as $row): ?>
                <li><a href="<?= e(url('/inventory/counts/' . $row['id'])) ?>"><?= e((string) $row['reference_code']) ?></a>
                    <small><?= e((string) ($row['location_name'] ?? 'All')) ?> · <?= e((string) $row['status']) ?></small></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

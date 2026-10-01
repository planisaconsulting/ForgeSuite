<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1><?= e((string) $count['reference_code']) ?></h1><p class="sf-muted mb-0"><?= e((string) $count['status']) ?> · <?= e((string) ($count['location_name'] ?? '')) ?></p></div>
<form method="post" action="<?= e(url('/inventory/counts/' . $count['id'])) ?>">
    <?= csrf_field() ?>
    <div class="table-responsive sf-panel mb-3">
        <table class="table sf-table mb-0">
            <thead><tr><th>Product</th><th>System</th><th>Physical</th><th>Variance</th><?php if ($showCost): ?><th>Value variance</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td><?= e((string) $line['product_name']) ?></td>
                    <td><?= e((string) $line['system_quantity']) ?> <?= e((string) $line['unit']) ?></td>
                    <td><input class="form-control form-control-lg" name="physical[<?= e((string) $line['id']) ?>]" value="<?= e((string) ($line['physical_quantity'] ?? '')) ?>" inputmode="decimal"></td>
                    <td><?= $line['variance'] === null ? '—' : e((string) $line['variance']) ?></td>
                    <?php if ($showCost): ?><td><?= $line['value_variance'] === null ? '—' : e(money((string) $line['value_variance'])) ?></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ((string) $count['status'] !== 'COMPLETED' && can('inventory.count')): ?>
        <button class="btn btn-sf btn-lg" type="submit">Save physical quantities</button>
    <?php endif; ?>
</form>
<?php if ((string) $count['status'] !== 'COMPLETED' && can('inventory.adjust')): ?>
<form class="mt-3" method="post" action="<?= e(url('/inventory/counts/' . $count['id'] . '/approve')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-outline-light btn-lg" type="submit">Approve and post corrections</button>
</form>
<?php endif; ?>

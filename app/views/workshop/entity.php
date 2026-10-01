<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <div>
        <h1><?= e((string) ($job['job_number'] ?? $piece['tracking_code'] ?? $inventory['inventory_code'] ?? $dispatch['dispatch_number'] ?? ($package['package_code'] ?? 'Scan'))) ?></h1>
        <p class="sf-muted mb-0"><?= e($type) ?><?= $job ? ' · ' . e((string) $job['status']) : '' ?></p>
    </div>
</div>
<?php if ($job): ?>
    <section class="sf-panel p-3 mb-3">
        <p class="mb-1"><strong><?= e(customer_label($job)) ?></strong></p>
        <p class="mb-1"><?= e((string) $job['title']) ?></p>
        <p class="mb-0">Due <?= e((string) ($job['target_date'] ?? '—')) ?></p>
    </section>
<?php endif; ?>
<?php if ($piece): ?>
    <section class="sf-panel p-3 mb-3">
        <p class="mb-1"><?= e((string) $piece['description']) ?></p>
        <p class="mb-1">Qty <?= e((string) $piece['quantity']) ?> · done <?= e((string) $piece['quantity_completed']) ?></p>
        <p class="mb-3">Status <?= e((string) $piece['status']) ?></p>
        <?php if (can('production.start') || can('production.update')): ?>
            <form method="post" action="<?= e(url('/workshop/actions')) ?>" class="d-grid gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="production_item_id" value="<?= e((string) $piece['id']) ?>">
                <input type="hidden" name="idempotency_key" value="<?= e(bin2hex(random_bytes(8))) ?>">
                <button class="btn btn-sf btn-lg" name="action" value="START" type="submit">Start</button>
                <button class="btn btn-outline-light btn-lg" name="action" value="PAUSE" type="submit">Pause</button>
                <label class="form-label" for="reason">Reason</label>
                <select class="form-select form-select-lg" id="reason" name="reason">
                    <?php foreach (array_merge(\App\Domain\WorkshopCodes::pauseReasons(), \App\Domain\WorkshopCodes::blockReasons()) as $reason): ?>
                        <option><?= e($reason) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-light btn-lg" name="action" value="BLOCK" type="submit">Block</button>
                <label class="form-label" for="quantity">Quantity completed</label>
                <input class="form-control form-control-lg" id="quantity" name="quantity" inputmode="decimal" value="<?= e((string) $piece['quantity']) ?>">
                <button class="btn btn-sf btn-lg" name="action" value="COMPLETE" type="submit">Complete</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php if (!empty($package)): ?>
    <section class="sf-panel p-3 mb-3">
        <p class="mb-1"><?= e((string) $package['description']) ?></p>
        <p class="mb-0"><a href="<?= e(url('/labels/preview?type=PACKAGE&id=' . $package['id'])) ?>">Package label</a></p>
    </section>
<?php endif; ?>
<?php if ($inventory): ?>
    <section class="sf-panel p-3 mb-3">
        <p class="mb-1"><?= e((string) ($inventory['product_name'] ?? '')) ?></p>
        <p class="mb-1">Remaining <?= e((string) $inventory['remaining_quantity']) ?> <?= e((string) $inventory['unit']) ?></p>
        <p class="mb-0">Location <?= e((string) ($inventory['location_name'] ?? '')) ?></p>
        <?php if ((int) ($inventory['customer_supplied'] ?? 0) === 1): ?><p class="mb-0">Customer supplied</p><?php endif; ?>
    </section>
<?php endif; ?>
<?php if ($artworks !== []): ?>
    <section class="sf-panel p-3 mb-3">
        <h2 class="h5">Artwork</h2>
        <?php foreach ($artworks as $artwork): ?>
            <p class="mb-1"><?= e((string) $artwork['title']) ?> rev <?= e((string) $artwork['revision_number']) ?> · <?= e((string) $artwork['status']) ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php if ($stages !== []): ?>
    <section class="sf-panel p-3">
        <h2 class="h5">Stages</h2>
        <?php foreach ($stages as $stage): ?>
            <p class="mb-1"><?= e((string) ($stage['stage_name'] ?? '')) ?> · <?= e((string) $stage['status']) ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

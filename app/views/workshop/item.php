<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <div>
        <p class="sf-kicker mb-1"><?= e((string) ($job['job_number'] ?? '')) ?></p>
        <h1><?= e((string) $piece['tracking_code']) ?></h1>
        <p class="sf-muted mb-0"><?= e((string) $piece['description']) ?> · <?= e((string) $piece['status']) ?></p>
    </div>
</div>
<section class="sf-panel p-3 mb-3">
    <p>Required <?= e((string) $piece['quantity']) ?> · completed <?= e((string) $piece['quantity_completed']) ?></p>
    <?php if ($job): ?><p class="mb-0"><?= e(customer_label($job)) ?> · due <?= e((string) ($job['target_date'] ?? '—')) ?></p><?php endif; ?>
</section>
<?php if ($stages !== []): ?>
    <section class="sf-panel p-3 mb-3">
        <h2 class="h5">Work order</h2>
        <?php foreach ($stages as $stage): ?>
            <p class="mb-1"><?= e((string) $stage['stage_name']) ?> · <?= e((string) $stage['status']) ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<section class="sf-panel p-3 mb-3">
    <form method="post" action="<?= e(url('/workshop/actions')) ?>" class="d-grid gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="production_item_id" value="<?= e((string) $piece['id']) ?>">
        <input type="hidden" name="idempotency_key" value="<?= e(bin2hex(random_bytes(8))) ?>">
        <button class="btn btn-sf btn-lg" name="action" value="START">Start</button>
        <select class="form-select form-select-lg" name="reason">
            <?php foreach (\App\Domain\WorkshopCodes::pauseReasons() as $reason): ?><option><?= e($reason) ?></option><?php endforeach; ?>
            <?php foreach (\App\Domain\WorkshopCodes::blockReasons() as $reason): ?><option><?= e($reason) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-outline-light btn-lg" name="action" value="PAUSE">Pause</button>
        <button class="btn btn-outline-light btn-lg" name="action" value="BLOCK">Block</button>
        <input class="form-control form-control-lg" name="quantity" inputmode="decimal" value="<?= e((string) $piece['quantity']) ?>" aria-label="Quantity completed">
        <button class="btn btn-sf btn-lg" name="action" value="COMPLETE">Complete</button>
    </form>
</section>
<?php if (can('production.reprint')): ?>
    <section class="sf-panel p-3">
        <h2 class="h5">Reprint</h2>
        <form method="post" action="<?= e(url('/workshop/actions')) ?>" class="d-grid gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="REPRINT">
            <input type="hidden" name="production_item_id" value="<?= e((string) $piece['id']) ?>">
            <input class="form-control" name="quantity" inputmode="decimal" placeholder="Quantity" required>
            <select class="form-select" name="reason_code">
                <?php foreach ($reasons as $reason): ?><option value="<?= e((string) $reason['code']) ?>"><?= e((string) $reason['label']) ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" name="notes" placeholder="Notes">
            <button class="btn btn-outline-light btn-lg" type="submit">Record reprint</button>
        </form>
    </section>
<?php endif; ?>

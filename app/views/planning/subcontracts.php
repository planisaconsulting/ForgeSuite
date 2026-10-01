<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Subcontractors</h1><p class="sf-muted mb-0">The supplier record is reused. Completing an order adds the actual cost to the job once.</p></div>
<section class="sf-panel mb-3">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No subcontract orders.</p></div><?php else: ?>
        <?php foreach ($rows as $row): ?>
            <div class="p-3 border-bottom">
                <strong><?= e((string) $row['order_number']) ?></strong> · <?= e((string) $row['job_number']) ?> · <?= e((string) $row['supplier_name']) ?>
                <p class="mb-2"><?= e((string) $row['description']) ?> · <?= e((string) $row['status']) ?><?php if ($showCost): ?> · estimated <?= e((string) $row['estimated_cost']) ?><?php if ($row['actual_cost'] !== null): ?> · actual <?= e((string) $row['actual_cost']) ?><?php endif; ?><?php endif; ?></p>
                <?php if ($canManage && $row['other_cost_id'] === null): ?>
                    <form class="d-flex gap-2" method="post" action="<?= e(url('/subcontracts/complete')) ?>"><?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= e((string) $row['id']) ?>">
                        <input class="form-control" name="actual_cost" placeholder="Actual cost" required>
                        <button class="btn btn-sf" type="submit">Complete</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php if ($canManage): ?>
<form class="sf-panel p-3 row g-2" method="post" action="<?= e(url('/subcontracts')) ?>">
    <?= csrf_field() ?>
    <div class="col-md-3"><input class="form-control form-control-lg" name="job_id" placeholder="Job id" required></div>
    <div class="col-md-5"><select class="form-select form-select-lg" name="supplier_id"><?php foreach ($suppliers as $supplier): ?><option value="<?= e((string) $supplier['id']) ?>"><?= e((string) $supplier['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><input class="form-control form-control-lg" name="estimated_cost" placeholder="Estimated cost" value="0"></div>
    <div class="col-12"><input class="form-control form-control-lg" name="description" placeholder="Work description" required></div>
    <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Create order</button></div>
</form>
<?php endif; ?>

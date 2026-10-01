<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Snags</h1><p class="sf-muted mb-0">Outstanding installation and delivery items. A critical snag blocks job completion.</p></div>
<?php if (can('snags.manage')): ?>
    <section class="sf-panel p-3 mb-3">
        <form method="post" action="<?= e(url('/snags')) ?>" class="row g-2">
            <?= csrf_field() ?>
            <div class="col-6"><input class="form-control" name="job_id" placeholder="Job id" required></div>
            <div class="col-6">
                <select class="form-select" name="priority"><option>NORMAL</option><option>LOW</option><option>HIGH</option><option>CRITICAL</option></select>
            </div>
            <div class="col-12"><input class="form-control" name="description" placeholder="What is outstanding?" required></div>
            <div class="col-12"><button class="btn btn-sf" type="submit">Add snag</button></div>
        </form>
    </section>
<?php endif; ?>
<section class="sf-panel">
    <?php if ($rows === []): ?><p class="p-3 mb-0">No snags.</p><?php else: ?>
        <?php foreach ($rows as $row): ?>
            <form method="post" action="<?= e(url('/snags/' . $row['id'])) ?>" class="p-3 border-bottom">
                <?= csrf_field() ?>
                <p class="mb-1"><strong><?= e((string) $row['job_number']) ?></strong> · <?= e((string) $row['priority']) ?></p>
                <p class="mb-2"><?= e((string) $row['description']) ?></p>
                <div class="d-flex gap-2">
                    <select class="form-select" name="status">
                        <?php foreach (['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CANCELLED'] as $status): ?>
                            <option <?= (string) $row['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (can('snags.manage')): ?><button class="btn btn-outline-light" type="submit">Update</button><?php endif; ?>
                </div>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

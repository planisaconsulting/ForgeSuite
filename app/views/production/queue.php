<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Production queue</h1>
    <p class="mb-0">Released work only. <?= (int) $upcoming ?> job(s) are not released and are not in this list.</p>
</div>
<form method="get" class="sf-panel mb-3">
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Stage status</label><input class="form-control" name="status" value="<?= e((string) $filters['status']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Priority</label><input class="form-control" name="priority" value="<?= e((string) $filters['priority']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Project</label><input class="form-control" name="project_id" value="<?= (int) $filters['project_id'] ?: '' ?>"></div>
        <div class="col-md-3 d-flex align-items-end"><button class="btn btn-light" type="submit">Filter</button></div>
    </div>
</form>
<section class="sf-panel">
    <?php if ($rows === []): ?><p>No released stage is waiting.</p><?php else: ?>
        <table class="table">
            <thead><tr><th>Job</th><th>Customer</th><th>Item</th><th>Stage</th><th>Qty</th><th>Due</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['job_number']) ?></td>
                    <td><?= e((string) $row['company_name']) ?></td>
                    <td><?= e((string) ($row['item_description'] ?? '')) ?></td>
                    <td><?= e((string) $row['stage_name']) ?></td>
                    <td><?= e((string) $row['quantity']) ?></td>
                    <td><?= e((string) ($row['target_date'] ?? '')) ?></td>
                    <td><?= e((string) $row['priority']) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

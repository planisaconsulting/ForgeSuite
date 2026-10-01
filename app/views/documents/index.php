<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Documents</h1><p class="sf-muted mb-0">Files already attached to customers, quotes, jobs, and other records.</p></div>
<form class="sf-filters" method="get">
    <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Filename" aria-label="Search">
    <input class="form-control" name="type" value="<?= e($filters['entity_type']) ?>" placeholder="customer, quote, job" aria-label="Type">
    <input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="From">
    <input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="To">
    <button class="btn btn-sf" type="submit">Filter</button>
</form>
<section class="sf-panel">
    <?php if ($rows === []): ?>
        <div class="sf-empty"><p>No documents match.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm sf-table mb-0">
                <thead><tr><th>File</th><th>Type</th><th>Record</th><th>Purpose</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= e(url('/attachments/' . $row['id'])) ?>"><?= e((string) $row['original_filename']) ?></a></td>
                        <td><?= e((string) $row['entity_type']) ?></td>
                        <td><?= e((string) $row['entity_id']) ?></td>
                        <td><?= e((string) $row['purpose']) ?></td>
                        <td><?= e((string) $row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Workshop documents</h1><p class="sf-muted mb-0">Job cards, delivery notes, proof of delivery, and completion certificates.</p></div>
<form class="sf-filters" method="get">
    <input class="form-control" name="q" value="<?= e($filters['q']) ?>" placeholder="Number, job, customer">
    <input class="form-control" name="type" value="<?= e($filters['type']) ?>" placeholder="JOB_CARD">
    <input class="form-control" type="date" name="from" value="<?= e($filters['from']) ?>">
    <input class="form-control" type="date" name="to" value="<?= e($filters['to']) ?>">
    <button class="btn btn-sf" type="submit">Search</button>
</form>
<section class="sf-panel">
    <?php if ($rows === []): ?><p class="p-3 mb-0">No documents match.</p><?php else: ?>
        <table class="table table-sm sf-table mb-0"><thead><tr><th>Document</th><th>Type</th><th>Status</th><th>When</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/documents/generated/' . $row['id'])) ?>"><?= e((string) ($row['document_number'] ?: $row['document_type'])) ?></a></td>
                <td><?= e((string) $row['document_type']) ?></td>
                <td><?= e((string) $row['status']) ?><?= (int) $row['immutable'] === 1 ? ' · signed' : '' ?></td>
                <td><?= e((string) $row['generated_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table>
    <?php endif; ?>
</section>

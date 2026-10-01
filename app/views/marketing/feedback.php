<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Customer feedback</h1><p class="sf-muted mb-0">Low scores stay visible. A review request is not limited to positive feedback.</p></div>
<section class="sf-panel p-3 mb-3">
    <p class="mb-0">Received <?= e((string) $summary['received']) ?>.
        Average <?= e((string) ($summary['average'] ?? 'n/a')) ?> on the 1 to 5 scores that were given.
        Follow-up requested <?= e((string) $summary['followup']) ?>.
    </p>
</section>
<section class="sf-panel mb-3">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>When</th><th>Customer</th><th>Rating</th><th>Comment</th><th>Follow-up</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="5">No feedback yet.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['submitted_at']) ?></td>
                    <td><?= e((string) ($row['company_name'] ?: trim($row['first_name'] . ' ' . $row['last_name']))) ?></td>
                    <td><?= e((string) ($row['rating'] ?? '')) ?></td>
                    <td><?= e((string) ($row['feedback_text'] ?? '')) ?></td>
                    <td><?= (int) $row['followup_required'] === 1 ? 'Yes' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($canManage): ?>
<form class="sf-panel p-3" method="post" action="<?= e(url('/feedback')) ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-3"><input class="form-control" name="customer_id" placeholder="Customer id" required></div>
        <div class="col-md-2"><input class="form-control" name="job_id" placeholder="Job id"></div>
        <div class="col-md-2"><input class="form-control" name="rating" placeholder="1-5"></div>
        <div class="col-md-5"><input class="form-control" name="feedback_text" placeholder="Comment"></div>
        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="followup_required"> Follow-up required</label></div>
        <div class="col-12"><button class="btn btn-sf" type="submit">Save feedback</button></div>
    </div>
</form>
<?php endif; ?>

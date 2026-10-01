<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Approval</h1></div>
<section class="sf-panel p-3 mb-3">
    <p class="mb-1"><strong><?= e((string) $request['action_key']) ?></strong> on <?= e((string) $request['entity_type']) ?> <?= e((string) $request['entity_id']) ?></p>
    <p class="mb-1">Reason: <?= e((string) ($request['reason'] ?? '')) ?></p>
    <p class="mb-0">Status: <?= e((string) $request['status']) ?></p>
</section>
<section class="sf-panel mb-3">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Step</th><th>Approver</th><th>Status</th><th>Comment</th></tr></thead>
            <tbody>
            <?php foreach ($steps as $step): ?>
                <tr><?php foreach ($step as $cell): ?><td><?= e((string) $cell) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ((string) $request['status'] === 'PENDING' && can('approvals.decide')): ?>
    <form method="post" action="<?= e(url('/approvals/' . $request['id'])) ?>" class="sf-panel p-3">
        <?= csrf_field() ?>
        <label class="form-label" for="comment">Comment</label>
        <textarea class="form-control mb-3" id="comment" name="comment" rows="2"></textarea>
        <button class="btn btn-sf me-2" name="decision" value="APPROVED" type="submit">Approve</button>
        <button class="btn btn-outline-light me-2" name="decision" value="REJECTED" type="submit">Reject</button>
        <button class="btn btn-outline-light" name="decision" value="CHANGES_REQUESTED" type="submit">Request changes</button>
    </form>
<?php endif; ?>

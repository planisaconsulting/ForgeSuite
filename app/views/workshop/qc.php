<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Quality check</h1><p class="sf-muted mb-0">A failure stays on the item until it is reworked, reprinted, or overridden.</p></div>
<section class="sf-panel p-3">
    <form method="post" action="<?= e(url('/workshop/qc')) ?>" class="d-grid gap-2">
        <?= csrf_field() ?>
        <label class="form-label" for="job_id">Job number id</label>
        <input class="form-control form-control-lg" id="job_id" name="job_id" inputmode="numeric" required>
        <input class="form-control" name="production_item_id" inputmode="numeric" placeholder="Production item, if any">
        <label class="form-label" for="check_type">Check</label>
        <select class="form-select form-select-lg" id="check_type" name="check_type">
            <?php foreach ($checks as $check): ?><option><?= e((string) $check['label']) ?></option><?php endforeach; ?>
            <option>FINAL</option>
        </select>
        <select class="form-select form-select-lg" name="status">
            <option value="PASS">Pass</option>
            <option value="PASS_WITH_NOTE">Pass with note</option>
            <option value="FAIL">Fail</option>
        </select>
        <input class="form-control" name="fail_reason" placeholder="Reason if failed">
        <select class="form-select" name="fail_action">
            <option value="">Action if failed</option>
            <option>REWORK</option>
            <option>REPRINT</option>
            <option>HOLD</option>
        </select>
        <input class="form-control" name="notes" placeholder="Notes">
        <button class="btn btn-sf btn-lg" type="submit">Save check</button>
    </form>
</section>

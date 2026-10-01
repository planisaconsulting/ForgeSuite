<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1">Tablet</p>
    <h1>Workshop</h1>
</div>
<div class="sf-workshop-board">
    <section class="sf-panel">
        <div class="sf-panel-head"><h2>Jobs</h2></div>
        <?php if ($rows === []): ?><div class="sf-empty"><p>No jobs in the workshop states.</p></div><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <a class="sf-field-card" href="<?= e(url('/jobs/' . $row['id'])) ?>">
                <strong><?= e((string) $row['job_number']) ?></strong>
                <span><?= e((string) $row['title']) ?></span>
                <small><?= e((string) $row['status']) ?></small>
            </a>
        <?php endforeach; ?>
    </section>
    <section class="sf-panel">
        <div class="sf-panel-head"><h2>Actions</h2></div>
        <div class="p-3 d-grid gap-2">
            <a class="btn btn-sf sf-touch" href="<?= e(url('/workshop/scan')) ?>">Scan material</a>
            <a class="btn btn-outline-light sf-touch" href="<?= e(url('/workshop/qc')) ?>">QC</a>
            <a class="btn btn-outline-light sf-touch" href="<?= e(url('/workshop')) ?>">Workshop floor</a>
            <p class="sf-muted mb-0">Material issue requires a connection. If the network drops, you can still read a downloaded job card. Stock balances do not change on this tablet until the server accepts the issue.</p>
        </div>
    </section>
</div>

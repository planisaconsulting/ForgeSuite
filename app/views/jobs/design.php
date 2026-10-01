<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Design queue</h1>
    <p class="sf-muted mb-0">Jobs that still need artwork or a customer decision.</p>
</div>
<?php if ($rows === []): ?>
    <section class="sf-panel"><p class="p-3 mb-0">The design queue is clear.</p></section>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($rows as $row): ?>
        <div class="col-12 col-lg-6">
            <article class="sf-workshop-card">
                <p class="sf-kicker mb-1"><?= e((string) $row['job_number']) ?></p>
                <h2 class="h5"><?= e((string) $row['title']) ?></h2>
                <p class="mb-1"><?= e(customer_label($row)) ?></p>
                <p class="mb-1">Proof <?= e((string) ($row['proof_status'] ?? 'not uploaded')) ?></p>
                <p class="mb-2">Due <?= e((string) ($row['target_date'] ?? '—')) ?> · <?= e((string) ($row['assignee_name'] ?? 'Unassigned')) ?></p>
                <a class="btn btn-sf" href="<?= e(url('/jobs/' . $row['id'] . '?tab=artwork')) ?>">Open job</a>
            </article>
        </div>
    <?php endforeach; ?>
</div>

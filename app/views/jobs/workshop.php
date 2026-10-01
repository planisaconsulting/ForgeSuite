<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Workshop</h1>
    <p class="sf-muted mb-0">Open tasks. Costs and customer accounts stay off this screen.</p>
</div>
<?php if ($rows === []): ?>
    <section class="sf-panel"><p class="p-3 mb-0">Nothing is waiting on the floor.</p></section>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($rows as $row): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <article class="sf-workshop-card">
                <p class="sf-kicker mb-1"><?= e((string) $row['job_number']) ?></p>
                <h2 class="h4"><?= e(customer_label($row)) ?></h2>
                <p class="mb-1"><?= e((string) ($row['item_description'] ?: $row['job_title'])) ?></p>
                <p class="mb-1"><strong><?= e((string) $row['title']) ?></strong></p>
                <p class="mb-2"><?= priority_badge((string) $row['job_priority']) ?> · due <?= e((string) ($row['due_date'] ?? $row['target_date'] ?? '—')) ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (can('production.update')): ?>
                        <form method="post" action="<?= e(url('/jobs/' . $row['job_id'] . '/tasks/' . $row['id'])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="back" value="workshop">
                            <input type="hidden" name="version_number" value="<?= e((string) $row['version_number']) ?>">
                            <button class="btn btn-sf btn-lg" name="status" value="IN_PROGRESS" type="submit">Start</button>
                        </form>
                        <form method="post" action="<?= e(url('/jobs/' . $row['job_id'] . '/tasks/' . $row['id'])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="back" value="workshop">
                            <input type="hidden" name="version_number" value="<?= e((string) $row['version_number']) ?>">
                            <button class="btn btn-outline-light btn-lg" name="status" value="BLOCKED" type="submit">Pause / block</button>
                        </form>
                        <form method="post" action="<?= e(url('/jobs/' . $row['job_id'] . '/tasks/' . $row['id'])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="back" value="workshop">
                            <input type="hidden" name="version_number" value="<?= e((string) $row['version_number']) ?>">
                            <button class="btn btn-outline-light btn-lg" name="status" value="COMPLETE" type="submit">Complete</button>
                        </form>
                    <?php endif; ?>
                    <a class="btn btn-outline-light btn-lg" href="<?= e(url('/jobs/' . $row['job_id'])) ?>">View job</a>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>

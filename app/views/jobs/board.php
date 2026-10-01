<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Production board</h1>
    <p class="sf-muted mb-0">Move a job only when the workflow allows it. The server checks the change.</p>
</div>
<div class="sf-board">
    <?php foreach ($columns as $status => $label): ?>
        <section class="sf-board-col">
            <h2><?= e($label) ?></h2>
            <?php if ($grouped[$status] === []): ?><p class="sf-muted">Empty</p><?php endif; ?>
            <?php foreach ($grouped[$status] as $job): ?>
                <article class="sf-board-card">
                    <p class="mb-1"><a href="<?= e(url('/jobs/' . $job['id'])) ?>"><?= e((string) $job['job_number']) ?></a></p>
                    <p class="mb-1"><?= e(customer_label($job)) ?></p>
                    <p class="mb-1"><?= e((string) $job['title']) ?></p>
                    <p class="mb-1"><?= priority_badge((string) $job['priority']) ?> <?= e((string) ($job['current_stage'] ?? '')) ?></p>
                    <p class="mb-2 sf-muted">Due <?= e((string) ($job['target_date'] ?? '—')) ?> · <?= e((string) ($job['assignee_name'] ?? 'Unassigned')) ?></p>
                    <?php if (can('jobs.change_status') && $status !== 'COMPLETED'): ?>
                        <form method="post" action="<?= e(url('/jobs/' . $job['id'] . '/status')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="version_number" value="<?= e((string) $job['version_number']) ?>">
                            <label class="visually-hidden" for="move-<?= e((string) $job['id']) ?>">Move <?= e((string) $job['job_number']) ?></label>
                            <select class="form-select form-select-sm mb-2" id="move-<?= e((string) $job['id']) ?>" name="status">
                                <?php foreach (['IN_PRODUCTION' => 'In production', 'QUALITY_CONTROL' => 'QC', 'READY_FOR_INSTALLATION' => 'Ready for installation', 'COMPLETED' => 'Completed'] as $value => $text): ?>
                                    <?php if ($value === $status) { continue; } ?>
                                    <option value="<?= e($value) ?>"><?= e($text) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-outline-light btn-sm" type="submit">Move</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endforeach; ?>
</div>

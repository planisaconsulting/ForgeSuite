<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>My work</h1><p class="sf-muted mb-0">What is on you today, what is late, and what is coming.</p></div>
<?php foreach ($groups as $label => $tasks): ?>
    <section class="sf-panel mb-3">
        <div class="sf-panel-head"><h2><?= e($label) ?></h2></div>
        <?php if ($tasks === []): ?><div class="sf-empty"><p>Nothing in this list.</p></div><?php else: ?>
            <?php foreach ($tasks as $task): ?>
                <div class="p-3 border-bottom">
                    <h3 class="h5 mb-1"><?= e((string) $task['title']) ?></h3>
                    <p class="mb-2"><?= e((string) $task['job_number']) ?> · <?= e((string) $task['job_title']) ?> · due <?= e((string) ($task['due_date'] ?? '—')) ?></p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="<?= e(url('/work/start')) ?>"><?= csrf_field() ?><input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>"><button class="btn btn-sf btn-lg" type="submit">Start</button></form>
                        <form method="post" action="<?= e(url('/work/complete')) ?>"><?= csrf_field() ?><input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>"><button class="btn btn-outline-light btn-lg" type="submit">Complete</button></form>
                        <form class="d-flex flex-wrap gap-2" method="post" action="<?= e(url('/work/block')) ?>"><?= csrf_field() ?>
                            <input type="hidden" name="task_id" value="<?= e((string) $task['id']) ?>">
                            <select class="form-select form-select-lg" name="reason_id"><?php foreach ($reasons as $reason): ?><option value="<?= e((string) $reason['id']) ?>"><?= e((string) $reason['name']) ?></option><?php endforeach; ?></select>
                            <button class="btn btn-outline-light btn-lg" type="submit">Block</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Installations</h2></div>
    <?php if ($installations === []): ?><div class="sf-empty"><p>No installations assigned to you in the next two weeks.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($installations as $row): ?>
            <li><a href="<?= e(url('/field/' . $row['id'])) ?>"><?= e((string) $row['job_number']) ?></a><small><?= e((string) $row['scheduled_date']) ?> · <?= e((string) $row['status']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

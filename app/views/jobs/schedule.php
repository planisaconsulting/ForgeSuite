<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Schedule</h1>
    <p class="sf-muted mb-0">Open tasks with a due date.</p>
</div>
<?php foreach ($groups as $label => $rows): ?>
    <section class="sf-panel mb-3">
        <div class="sf-panel-head"><h2><?= e($label) ?></h2></div>
        <?php if ($rows === []): ?><p class="p-3 mb-0">None.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <article class="p-3 border-bottom border-secondary">
                <a href="<?= e(url('/jobs/' . $row['job_id'])) ?>"><?= e((string) $row['job_number']) ?></a>
                · <?= e(customer_label($row)) ?>
                · <?= e((string) $row['title']) ?>
                · <?= e((string) ($row['assignee_name'] ?? $row['team_name'] ?? 'Unassigned')) ?>
                · <?= e((string) $row['due_date']) ?>
                <?= priority_badge((string) $row['job_priority']) ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>

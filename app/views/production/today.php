<?php require base_path('app/views/partials/flashes.php'); ?>
<meta http-equiv="refresh" content="60">
<div class="sf-page-head">
    <h1 class="display-6">Today</h1>
    <p>Overdue <?= (int) $watch['overdue'] ?> · Blocked stages <?= (int) $watch['blocked'] ?> · Not released <?= (int) $watch['not_released'] ?></p>
</div>
<section class="sf-panel">
    <?php foreach ($rows as $row): ?>
        <p class="fs-4 mb-2"><?= e((string) $row['job_number']) ?> · <?= e((string) $row['stage_name']) ?> · <?= e((string) $row['status']) ?> · due <?= e((string) ($row['target_date'] ?? 'none')) ?></p>
    <?php endforeach; ?>
    <?php if ($rows === []): ?><p class="fs-4">Nothing released is on the board.</p><?php endif; ?>
</section>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Reminders</h1><p class="sf-muted mb-0">Follow-ups created for you. CRM follow-up dates stay on the activity.</p></div>
<section class="sf-panel">
    <?php if ($rows === []): ?>
        <div class="sf-empty"><p>No reminders.</p></div>
    <?php else: ?>
        <ul class="sf-feed">
            <?php foreach ($rows as $row): ?>
                <li>
                    <strong><?= e((string) $row['title']) ?></strong>
                    <small><?= e((string) $row['status']) ?> · <?= e((string) $row['remind_at']) ?> · <?= e((string) $row['entity_type']) ?> <?= e((string) $row['entity_id']) ?></small>
                    <p class="mb-1"><?= e((string) ($row['description'] ?? '')) ?></p>
                    <?php if ((string) $row['status'] === 'PENDING'): ?>
                        <form method="post" action="<?= e(url('/reminders/' . $row['id'] . '/status')) ?>" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-sf" name="status" value="COMPLETED" type="submit">Complete</button>
                            <button class="btn btn-sm btn-outline-light" name="status" value="DISMISSED" type="submit">Dismiss</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

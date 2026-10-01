<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Notifications</h1>
        <p class="sf-muted mb-0">Internal notices for your user and your role.</p>
    </div>
    <div class="sf-action-row">
        <a class="btn btn-outline-light" href="<?= e(url('/reminders')) ?>">Reminders</a>
        <a class="btn btn-outline-light" href="<?= e(url('/notifications/preferences')) ?>">Preferences</a>
        <form method="post" action="<?= e(url('/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn-sf" type="submit">Mark all read</button></form>
    </div>
</div>
<section class="sf-panel">
    <?php if ($rows === []): ?>
        <div class="sf-empty"><p>No notifications.</p></div>
    <?php else: ?>
        <ul class="sf-feed">
            <?php foreach ($rows as $row): ?>
                <li>
                    <form method="post" action="<?= e(url('/notifications/' . $row['id'] . '/read')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-link p-0" type="submit"><?= e((string) $row['title']) ?></button>
                    </form>
                    <small><?= e((string) $row['priority']) ?> · <?= e((string) $row['created_at']) ?><?= $row['read_at'] ? '' : ' · Unread' ?></small>
                    <p class="mb-0"><?= e((string) $row['message']) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

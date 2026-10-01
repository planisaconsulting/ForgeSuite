<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Checked list</h1><p class="sf-muted mb-0"><?= e((string) $count) ?> contacts have marketing email allowed and are not suppressed. This page does not send a message.</p></div>
<ul>
<?php foreach ($sample as $row): ?>
    <li><?= e((string) $row['name']) ?> · <?= e((string) $row['email']) ?></li>
<?php endforeach; ?>
</ul>

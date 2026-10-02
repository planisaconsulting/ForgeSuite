<div class="sf-page-head"><h1>Artwork library</h1></div>
<?php if ($rows === []): ?>
    <p>Approved artwork for your account will appear here.</p>
<?php else: ?>
    <ul>
        <?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['title']) ?> · <?= e((string) $row['category']) ?> · <?= e((string) $row['status']) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

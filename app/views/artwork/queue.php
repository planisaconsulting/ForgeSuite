<div class="sf-page-head">
    <h1>Design queue</h1>
    <p class="sf-muted"><?= (int) $metrics['artworks'] ?> numbered artworks · <?= (int) $metrics['revisions'] ?> revisions · <?= (int) $metrics['approvals'] ?> revision approvals</p>
</div>
<p><a href="<?= e(url('/artwork/storage')) ?>">Storage</a></p>
<?php foreach ($sections as $status => $rows): ?>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5"><?= e(str_replace('_', ' ', (string) $status)) ?></h2>
    <?php if ($rows === []): ?>
        <p class="sf-muted">Nothing in this section.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($rows as $row): ?>
                <li><a href="<?= e(url('/artwork/' . $row['id'])) ?>"><?= e((string) $row['artwork_number']) ?></a> <?= e((string) $row['title']) ?> · <?= e((string) $row['job_number']) ?> · <?= e((string) $row['priority']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endforeach; ?>

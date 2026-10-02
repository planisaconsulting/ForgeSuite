<div class="sf-page-head"><h1>Artwork storage</h1></div>
<section class="sf-panel p-3 mb-3">
    <p><?= (int) $storage['files'] ?> files · <?= (int) $storage['bytes'] ?> bytes · <?= (int) $storage['orphans'] ?> without a customer, job, or artwork · <?= (int) $storage['duplicates'] ?> duplicate hashes</p>
    <p>Nothing here is deleted automatically.</p>
    <ul>
        <?php foreach ($storage['largest'] as $file): ?>
            <li><?= e((string) $file['original_filename']) ?> · <?= e((string) $file['category']) ?> · <?= (int) $file['file_size'] ?> bytes</li>
        <?php endforeach; ?>
    </ul>
</section>
<form class="mb-3" method="get" action="<?= e(url('/artwork/storage')) ?>">
    <label class="form-label" for="q">Search files</label>
    <input class="form-control" id="q" name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>">
</form>
<ul>
    <?php foreach ($results as $row): ?>
        <li><?= e((string) $row['original_filename']) ?> · <?= e((string) ($row['artwork_number'] ?? '')) ?> · <?= e((string) ($row['job_number'] ?? '')) ?></li>
    <?php endforeach; ?>
</ul>

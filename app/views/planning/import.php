<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Import customers</h1></div>
<form method="post" action="<?= e(url('/admin/imports/preview')) ?>" enctype="multipart/form-data" class="sf-panel p-3 mb-3 d-grid gap-2">
    <?= csrf_field() ?>
    <label class="form-label" for="file">CSV file</label>
    <input class="form-control" id="file" type="file" name="file" accept=".csv,text/csv">
    <label class="form-label" for="csv">Or paste CSV</label>
    <textarea class="form-control" id="csv" name="csv" rows="6" placeholder="company_name,email,phone"></textarea>
    <button class="btn btn-sf btn-lg" type="submit">Preview</button>
</form>
<?php if (is_array($preview)): ?>
    <section class="sf-panel p-3 mb-3">
        <p class="mb-1">Valid <?= e((string) $preview['valid']) ?> · Invalid <?= e((string) $preview['invalid']) ?></p>
        <p class="sf-muted mb-0">Nothing is written until you confirm.</p>
    </section>
    <section class="sf-panel mb-3">
        <ul class="sf-feed">
            <?php foreach ($preview['rows'] as $row): ?>
                <?php if (!empty($row['ok']) && ($row['warnings'] ?? []) === []) { continue; } ?>
                <li>
                    Line <?= e((string) $row['line']) ?> <?= e((string) $row['company_name']) ?>
                    <?php foreach ($row['errors'] as $error): ?><small><?= e((string) $error) ?></small><?php endforeach; ?>
                    <?php foreach ($row['warnings'] as $warning): ?><small><?= e((string) $warning) ?></small><?php endforeach; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <form method="post" action="<?= e(url('/admin/imports/commit')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-sf btn-lg" type="submit">Import valid rows</button>
    </form>
<?php endif; ?>

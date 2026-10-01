<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Import assets</h1>
    <p class="sf-muted">Columns: customer, site, asset_name, type, reference, install_date, warranty, location. Invalid rows stop the file. Duplicates are skipped and listed. Nothing is merged.</p>
</div>
<form class="sf-panel mb-3" method="post" action="<?= e(url('/assets/import')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="p-3">
        <textarea class="form-control mb-2" name="csv" rows="8" placeholder="customer,site,asset_name,type,reference,install_date,warranty,location"></textarea>
        <input class="form-control mb-2" type="file" name="file" accept=".csv,text/csv">
        <button class="btn btn-sf" type="submit">Check and import</button>
    </div>
</form>
<?php if (is_array($result)): ?>
    <section class="sf-panel"><div class="p-3">
        <p>Imported <?= (int) $result['imported'] ?>. Skipped <?= (int) $result['skipped'] ?>. Failed <?= (int) $result['failed'] ?>.</p>
        <?php foreach (array_merge($result['errors'], $result['warnings']) as $line): ?><p><?= e((string) $line) ?></p><?php endforeach; ?>
    </div></section>
<?php endif; ?>

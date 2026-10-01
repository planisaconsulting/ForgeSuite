<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Document templates</h1><p class="sf-muted mb-0">Placeholders such as {{job.number}} and {{customer.name}} only. Code is not run.</p></div>
<section class="sf-panel p-3 mb-3">
    <form method="post" action="<?= e(url('/admin/document-templates')) ?>" class="d-grid gap-2">
        <?= csrf_field() ?>
        <input class="form-control" name="name" placeholder="Name" required>
        <select class="form-select" name="document_type">
            <?php foreach (['JOB_CARD','WORK_ORDER','DELIVERY_NOTE','COLLECTION_NOTE','COMPLETION_CERTIFICATE','MATERIAL_LABEL','JOB_LABEL','INVENTORY_LABEL','OTHER'] as $type): ?>
                <option><?= e($type) ?></option>
            <?php endforeach; ?>
        </select>
        <textarea class="form-control" name="layout_config" rows="5" placeholder="{{company.name}}&#10;{{job.number}}"></textarea>
        <button class="btn btn-sf" type="submit">Save template</button>
    </form>
</section>
<section class="sf-panel mb-3">
    <ul class="sf-feed"><?php foreach ($rows as $row): ?><li><?= e((string) $row['name']) ?> <small><?= e((string) $row['document_type']) ?> · v<?= e((string) $row['template_version']) ?></small></li><?php endforeach; ?></ul>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Label sizes</h2></div>
    <ul class="sf-feed"><?php foreach ($labels as $label): ?><li><?= e((string) $label['name']) ?> <small><?= e((string) $label['width_mm']) ?> × <?= e((string) $label['height_mm']) ?> mm</small></li><?php endforeach; ?></ul>
</section>

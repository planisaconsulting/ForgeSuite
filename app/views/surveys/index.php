<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div><h1>Site surveys</h1><p class="sf-muted mb-0">Measure a site before or after a quotation.</p></div>
    <?php if ($canCreate): ?><a class="btn btn-sf" href="<?= e(url('/surveys/new')) ?>">New site survey</a><?php endif; ?>
</div>
<form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-6"><input class="form-control form-control-lg" name="q" value="<?= e($term) ?>" placeholder="Number or site"></div>
    <div class="col-8 col-md-4"><select class="form-select form-select-lg" name="status"><option value="">Any status</option><?php foreach (['DRAFT','SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED'] as $option): ?><option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div>
    <div class="col-4 col-md-2"><button class="btn btn-outline-light btn-lg w-100" type="submit">Find</button></div>
</form>
<section class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No surveys yet.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($rows as $row): ?>
            <li><a href="<?= e(url('/surveys/' . $row['id'])) ?>"><?= e((string) $row['survey_number']) ?></a>
                <small><?= e((string) $row['site_name']) ?> · <?= e(customer_label($row)) ?> · <?= e((string) $row['status']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>

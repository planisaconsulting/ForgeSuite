<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker"><?= e((string) $spec['code']) ?> · version <?= (int) $spec['version'] ?></p>
    <h1><?= e((string) $spec['name']) ?></h1>
    <p><?= e((string) $spec['status']) ?> · <?= e((string) $spec['estimator_type']) ?> · from <?= e((string) $spec['effective_from']) ?></p>
</div>
<div class="d-flex gap-2 mb-3">
    <?php if (can('specifications.approve') && in_array((string) $spec['status'], ['DRAFT', 'REVIEW'], true)): ?>
        <form method="post" action="<?= e(url('/specifications/' . $spec['id'] . '/approve')) ?>"><?= csrf_field() ?><button class="btn btn-light" type="submit">Approve</button></form>
    <?php endif; ?>
    <?php if (can('specifications.edit')): ?>
        <form method="post" action="<?= e(url('/specifications/' . $spec['id'] . '/revise')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light" type="submit">New version</button></form>
    <?php endif; ?>
</div>
<section class="sf-panel">
    <h2>General</h2>
    <p><?= e((string) ($spec['description'] ?? '')) ?></p>
    <p>Frame: <?= e((string) ($spec['frame_profile'] ?? '')) ?>. Face: <?= e((string) ($spec['face_material'] ?? '')) ?>. Returns: <?= e((string) ($spec['return_material'] ?? '')) ?>.</p>
    <p>Allowance <?= e((string) $spec['manufacturing_allowance_percent']) ?>%. <?php if ((int) $spec['engineering_review_required'] === 1): ?>Engineering review is required by this specification.<?php endif; ?> <?php if ((int) $spec['electrical_review_required'] === 1): ?>Electrical review is required by this specification.<?php endif; ?></p>
    <?php if ($spec['height_review_mm'] !== null): ?><p>Height review above <?= e((string) $spec['height_review_mm']) ?> mm. That threshold belongs to this specification.</p><?php endif; ?>
</section>
<section class="sf-panel mt-3">
    <h2>Materials</h2>
    <ul><?php foreach ($materials as $row): ?><li><?= e((string) $row['role_code']) ?> · <?= e((string) $row['description']) ?></li><?php endforeach; ?></ul>
    <h2 class="h5">Components</h2>
    <ul><?php foreach ($components as $row): ?><li><?= e((string) $row['description']) ?></li><?php endforeach; ?></ul>
    <h2 class="h5">Labour</h2>
    <ul><?php foreach ($labour as $row): ?><li><?= e((string) $row['description']) ?> · <?= e((string) $row['minutes_formula']) ?></li><?php endforeach; ?></ul>
    <h2 class="h5">Production route</h2>
    <p><?php foreach ($operations as $row): ?><?= e((string) $row['operation_code']) ?> <?php endforeach; ?></p>
    <h2 class="h5">Compatibility</h2>
    <ul><?php foreach ($rules as $row): ?><li><?= e((string) $row['hardness']) ?> <?= e((string) $row['rule_type']) ?>: <?= e((string) $row['message']) ?></li><?php endforeach; ?></ul>
    <h2 class="h5">Notes</h2>
    <ul><?php foreach ($notes as $row): ?><li><?= e((string) $row['note_type']) ?>: <?= e((string) $row['body']) ?></li><?php endforeach; ?></ul>
</section>

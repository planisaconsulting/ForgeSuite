<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $survey['survey_number']) ?> · <?= e((string) $survey['status']) ?></p>
    <h1><?= e((string) $survey['site_name']) ?></h1>
    <p class="sf-muted mb-0"><?= e(customer_label($survey)) ?><?php if (!empty($survey['survey_date'])): ?> · <?= e((string) $survey['survey_date']) ?><?php endif; ?></p>
</div>
<div class="sf-action-row mb-3">
    <?php if ($canEdit): ?><a class="btn btn-outline-light" href="<?= e(url('/surveys/' . $survey['id'] . '/edit')) ?>">Edit</a><?php endif; ?>
    <a class="btn btn-outline-light" href="<?= e(url('/surveys/' . $survey['id'] . '/pdf')) ?>">Download site survey PDF</a>
    <?php if (!empty($survey['quote_id'])): ?><a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $survey['quote_id'])) ?>">Open quote</a><?php elseif (can('quotes.manage')): ?><a class="btn btn-sf" href="<?= e(url('/quotes/new?customer_id=' . $survey['customer_id'] . '&survey_id=' . $survey['id'] . (!empty($survey['opportunity_id']) ? '&opportunity_id=' . (int) $survey['opportunity_id'] : ''))) ?>">Create quotation</a><?php endif; ?>
</div>
<section class="sf-panel mb-3"><div class="p-3">
    <p class="mb-1"><?= e((string) ($survey['address_line_1'] ?? '')) ?> <?= e((string) ($survey['city'] ?? '')) ?></p>
    <p class="mb-1">Contact <?= e((string) ($survey['site_contact_name'] ?? '—')) ?> <?= e((string) ($survey['site_contact_phone'] ?? '')) ?></p>
    <p class="mb-0"><?= e((string) ($survey['environment'] ?? '')) ?> <?= e((string) ($survey['surface_type'] ?? '')) ?>
        <?php if (!empty($survey['ladder_required'])): ?> · Ladder<?php endif; ?>
        <?php if (!empty($survey['scaffolding_required'])): ?> · Scaffold<?php endif; ?>
        <?php if (!empty($survey['cherry_picker_required'])): ?> · Cherry picker<?php endif; ?>
        <?php if (!empty($survey['electrical_supply'])): ?> · Power<?php endif; ?>
    </p>
    <?php if (trim((string) ($survey['installation_notes'] ?? '')) !== ''): ?><p class="mt-2 mb-0"><?= nl2br(e((string) $survey['installation_notes'])) ?></p><?php endif; ?>
</div></section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Measurements</h2></div>
    <?php if ($measurements === []): ?><div class="sf-empty"><p>No measurements yet.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($measurements as $row): ?>
            <li><strong><?= e((string) $row['reference']) ?></strong>
                <small><?= e((string) $row['measurement_type']) ?> · <?= e((string) ($row['width_mm'] ?? '—')) ?> × <?= e((string) ($row['height_mm'] ?? '—')) ?> mm · qty <?= e((string) $row['quantity']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($canEdit): ?>
    <form class="p-3 row g-2" method="post" action="<?= e(url('/surveys/' . $survey['id'] . '/measurements')) ?>">
        <?= csrf_field() ?>
        <div class="col-12"><input class="form-control form-control-lg" name="reference" placeholder="Reference, for example Main shopfront" required></div>
        <div class="col-12"><select class="form-select" name="measurement_type"><?php foreach ($types as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><input class="form-control" name="width_mm" inputmode="decimal" placeholder="Width mm"></div>
        <div class="col-6"><input class="form-control" name="height_mm" inputmode="decimal" placeholder="Height mm"></div>
        <div class="col-6"><input class="form-control" name="depth_mm" inputmode="decimal" placeholder="Depth mm"></div>
        <div class="col-6"><input class="form-control" name="quantity" inputmode="decimal" value="1" placeholder="Qty"></div>
        <div class="col-12"><textarea class="form-control" name="notes" rows="2" placeholder="Notes"></textarea></div>
        <div class="col-12"><button class="btn btn-sf w-100" type="submit">Add measurement</button></div>
    </form>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Photos</h2></div>
    <ul class="sf-feed"><?php foreach ($photos as $photo): ?><li><a href="<?= e(url('/attachments/' . $photo['id'])) ?>"><?= e((string) $photo['original_filename']) ?></a><small><?= e((string) ($photo['photo_tag'] ?? '')) ?> <?= e((string) ($photo['notes'] ?? '')) ?></small></li><?php endforeach; ?></ul>
    <?php if ($canEdit): ?>
    <form class="p-3" method="post" action="<?= e(url('/surveys/' . $survey['id'] . '/photos')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="form-label" for="file">Take or upload a photo</label>
        <input class="form-control mb-2" id="file" type="file" name="file" accept="image/*" capture="environment" multiple>
        <input class="form-control mb-2" name="caption" placeholder="Caption">
        <select class="form-select mb-2" name="photo_tag"><?php foreach ($tags as $tag): ?><option value="<?= e($tag) ?>"><?= e($tag) ?></option><?php endforeach; ?></select>
        <select class="form-select mb-2" name="measurement_id"><option value="">Not linked to a measurement</option><?php foreach ($measurements as $row): ?><option value="<?= e((string) $row['id']) ?>"><?= e((string) $row['reference']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sf w-100" type="submit">Add photo</button>
    </form>
    <?php endif; ?>
</section>

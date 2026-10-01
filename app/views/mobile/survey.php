<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $survey['survey_number']) ?> · <?= e((string) $survey['status']) ?></p>
    <h1><?= e((string) $survey['site_name']) ?></h1>
    <p class="sf-muted mb-0"><?= e(customer_label($survey)) ?></p>
</div>
<div class="sf-action-row mb-3">
    <?php $phone = (string) ($survey['site_contact_phone'] ?: ($survey['mobile'] ?: $survey['phone'])); ?>
    <?php if ($phone !== ''): ?>
        <a class="btn btn-sf sf-touch" href="<?= e('tel:' . preg_replace('/\s+/', '', $phone)) ?>">Call</a>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://wa.me/' . preg_replace('/\D+/', '', $phone)) ?>">WhatsApp</a>
    <?php endif; ?>
    <?php $address = trim((string) $survey['address_line_1'] . ' ' . (string) $survey['city']); ?>
    <?php if ($address !== ''): ?>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address)) ?>">Navigate</a>
    <?php endif; ?>
    <button class="btn btn-outline-light sf-touch" type="button" id="sf-download-pack" data-pack-type="SURVEY" data-entity-id="<?= e((string) $survey['id']) ?>">Download field pack</button>
</div>
<p id="sf-pack-state" class="sf-muted">Online only until you download a field pack.</p>
<section class="sf-panel mb-3"><div class="p-3">
    <h2 class="h5">Measurements</h2>
    <ul class="sf-feed" id="sf-measure-list">
        <?php foreach ($measurements as $row): ?>
            <li><strong><?= e((string) $row['reference']) ?></strong>
                <small><?= e((string) ($row['width_mm'] ?? '—')) ?> × <?= e((string) ($row['height_mm'] ?? '—')) ?> mm · qty <?= e((string) $row['quantity']) ?></small></li>
        <?php endforeach; ?>
    </ul>
    <form id="sf-measure-form" class="row g-2 mt-2" data-sf-work>
        <div class="col-12"><label class="form-label" for="sf-ref">Type / reference</label><input class="form-control form-control-lg" id="sf-ref" required></div>
        <div class="col-4"><label class="form-label" for="sf-w">Width</label><input class="form-control form-control-lg" id="sf-w" inputmode="decimal"></div>
        <div class="col-4"><label class="form-label" for="sf-h">Height</label><input class="form-control form-control-lg" id="sf-h" inputmode="decimal"></div>
        <div class="col-4"><label class="form-label" for="sf-unit">Unit</label>
            <select class="form-select form-select-lg" id="sf-unit"><option value="mm">mm</option><option value="cm">cm</option><option value="m">m</option></select>
        </div>
        <div class="col-6"><label class="form-label" for="sf-qty">Qty</label><input class="form-control form-control-lg" id="sf-qty" value="1" inputmode="decimal"></div>
        <div class="col-12"><label class="form-label" for="sf-note">Note</label><input class="form-control" id="sf-note"></div>
        <div class="col-12"><button class="btn btn-sf sf-touch" type="submit">Add measurement</button></div>
    </form>
    <p id="sf-measure-warn" class="sf-muted mt-2"></p>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Photos</h2>
    <?php $photoCategories = ['SITE_OVERVIEW', 'MEASUREMENT', 'ACCESS', 'ELECTRICAL', 'SURFACE', 'INSTALLATION_AREA', 'REFERENCE', 'DAMAGE', 'COMPLETION', 'OTHER']; require base_path('app/views/mobile/partials/photo_tools.php'); ?>
    <ul class="sf-feed mt-3" id="sf-photo-list">
        <?php foreach ($photos as $photo): ?>
            <li data-photo-id="<?= e((string) $photo['id']) ?>"><a href="<?= e(url('/m/photos/' . $photo['id'])) ?>"><?= e((string) $photo['category']) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <button class="btn btn-outline-light sf-touch mt-2" type="button" id="sf-photo-order">Save photo order</button>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Access, electrical, and notes</h2>
    <ul class="sf-feed">
        <?php foreach ($notes as $note): ?>
            <li><?= e((string) $note['body']) ?></li>
        <?php endforeach; ?>
    </ul>
    <label class="form-label" for="sf-note-kind">Kind</label>
    <select class="form-select mb-2" id="sf-note-kind">
        <option value="NOTE">Note</option>
        <option value="ACCESS">Access</option>
        <option value="ELECTRICAL">Electrical</option>
        <option value="INSTALLATION">Installation conditions</option>
    </select>
    <label class="form-label" for="sf-note-body">Note</label>
    <textarea class="form-control mb-2" id="sf-note-body" rows="3"></textarea>
    <button class="btn btn-outline-light sf-touch" type="button" id="sf-note-add">Save note</button>
</div></section>
<button class="btn btn-sf sf-touch" type="button" data-sf-op="SURVEY_COMPLETE">Complete survey</button>
<p class="sf-muted mt-3">A completed survey stays pending until sync confirms it. The office record is updated only after that.</p>
<script type="application/json" id="sf-entity"><?= json_encode(['entity_type' => 'SITE_SURVEY', 'entity_id' => (int) $survey['id'], 'mode' => 'survey'], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

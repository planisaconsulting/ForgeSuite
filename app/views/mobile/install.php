<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $job['job_number']) ?> · <?= e((string) $installation['status']) ?></p>
    <h1><?= e(customer_label($job)) ?></h1>
</div>
<?php if ($fresh !== null && !empty($fresh['critical'])): ?>
    <div class="alert alert-warning">Update available. Refresh the field pack before you complete this installation. The approved artwork or the site details changed.</div>
<?php elseif ($fresh !== null): ?>
    <p class="sf-muted">Available offline until <?= e((string) ($fresh['pack']['expires_at'] ?? '')) ?>.</p>
<?php else: ?>
    <p class="sf-muted" id="sf-pack-state">Online only until you download a field pack.</p>
<?php endif; ?>
<div class="sf-action-row mb-3">
    <?php $phone = (string) ($job['site_contact_phone'] ?: ($job['mobile'] ?: $job['phone'])); ?>
    <?php if ($phone !== ''): ?>
        <a class="btn btn-sf sf-touch" href="<?= e('tel:' . preg_replace('/\s+/', '', $phone)) ?>">Call</a>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://wa.me/' . preg_replace('/\D+/', '', $phone)) ?>">WhatsApp</a>
    <?php endif; ?>
    <?php $address = (string) ($installation['site_address'] ?: $job['site_address']); ?>
    <?php if ($address !== ''): ?>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address)) ?>">Navigate</a>
    <?php endif; ?>
    <button class="btn btn-outline-light sf-touch" type="button" id="sf-download-pack" data-pack-type="INSTALLATION" data-entity-id="<?= e((string) $installation['id']) ?>">Download field pack</button>
</div>
<section class="sf-panel mb-3"><div class="p-3">
    <p class="mb-1"><?= e($address) ?></p>
    <p class="mb-1"><?= e((string) ($job['description'] ?? '')) ?></p>
    <p class="mb-0"><?= nl2br(e((string) ($installation['installation_notes'] ?? ''))) ?></p>
</div></section>
<?php if ($artwork !== null): ?>
<section class="sf-panel mb-3"><div class="p-3">
    <p class="sf-kicker mb-1">Approved revision <?= e((string) $artwork['revision_number']) ?></p>
    <p class="mb-0"><?= e((string) $artwork['title']) ?><?php if (!empty($artwork['customer_approved_at'])): ?> · <?= e((string) $artwork['customer_approved_at']) ?><?php endif; ?></p>
</div></section>
<?php endif; ?>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">On site</h2>
    <div class="d-grid gap-2">
        <button class="btn btn-sf sf-touch" type="button" data-sf-op="INSTALL_TRAVEL" data-gps="1">Start travel</button>
        <button class="btn btn-outline-light sf-touch" type="button" data-sf-op="INSTALL_ARRIVE" data-gps="1">Arrive</button>
    </div>
    <p class="mt-3 mb-1">Before you start, read the site risks, electrical notes, working-at-height requirements, and equipment on this job. This acknowledgement does not replace a formal safety process.</p>
    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" id="sf-safety" value="1"> I have read those notes</label>
    <button class="btn btn-sf sf-touch" type="button" data-sf-op="INSTALL_START">Start installation</button>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Checklist</h2>
    <?php if ($checklist === []): ?><p class="sf-muted">No checklist items on this installation.</p><?php endif; ?>
    <?php foreach ($checklist as $item): ?>
        <div class="border-top py-2">
            <p class="mb-2"><?= e((string) $item['label']) ?></p>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-light sf-touch" type="button" data-sf-op="INSTALL_CHECKLIST" data-item="<?= e((string) $item['id']) ?>" data-answer="YES">Yes</button>
                <button class="btn btn-outline-light sf-touch" type="button" data-sf-op="INSTALL_CHECKLIST" data-item="<?= e((string) $item['id']) ?>" data-answer="NO">No</button>
                <button class="btn btn-outline-light sf-touch" type="button" data-sf-op="INSTALL_CHECKLIST" data-item="<?= e((string) $item['id']) ?>" data-answer="NA">N/A</button>
            </div>
        </div>
    <?php endforeach; ?>
    <label class="form-label mt-2" for="sf-check-note">Checklist note</label>
    <input class="form-control" id="sf-check-note">
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Photos</h2>
    <?php $photoCategories = ['BEFORE', 'DURING', 'AFTER', 'DETAIL', 'ISSUE', 'COMPLETION', 'OTHER']; require base_path('app/views/mobile/partials/photo_tools.php'); ?>
    <ul class="sf-feed mt-3" id="sf-photo-list">
        <?php foreach ($photos as $photo): ?>
            <li data-photo-id="<?= e((string) $photo['id']) ?>"><a href="<?= e(url('/m/photos/' . $photo['id'])) ?>"><?= e((string) $photo['category']) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <button class="btn btn-outline-light sf-touch mt-2" type="button" id="sf-photo-order">Save photo order</button>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Snag</h2>
    <label class="form-label" for="sf-snag">Description</label>
    <input class="form-control mb-2" id="sf-snag">
    <label class="form-label" for="sf-snag-priority">Priority</label>
    <select class="form-select mb-2" id="sf-snag-priority"><option>LOW</option><option selected>NORMAL</option><option>HIGH</option><option>CRITICAL</option></select>
    <button class="btn btn-outline-light sf-touch" type="button" data-sf-op="INSTALL_SNAG">Save snag</button>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Time and mileage</h2>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button class="btn btn-outline-light sf-touch" type="button" id="sf-time-start">Start</button>
        <button class="btn btn-outline-light sf-touch" type="button" data-time="stop">Pause</button>
        <button class="btn btn-outline-light sf-touch" type="button" id="sf-time-stop">Stop</button>
    </div>
    <label class="form-label" for="sf-odo-start">Starting odometer</label>
    <input class="form-control mb-2" id="sf-odo-start" inputmode="decimal">
    <label class="form-label" for="sf-odo-end">Ending odometer</label>
    <input class="form-control mb-2" id="sf-odo-end" inputmode="decimal">
    <label class="form-label" for="sf-manual-km">Or kilometres</label>
    <input class="form-control mb-2" id="sf-manual-km" inputmode="decimal">
    <label class="form-label" for="sf-vehicle">Vehicle number</label>
    <input class="form-control mb-2" id="sf-vehicle" inputmode="numeric">
    <button class="btn btn-outline-light sf-touch" type="button" data-sf-op="MILEAGE">Save mileage</button>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Installation notes</h2>
    <label class="form-label" for="sf-install-note">Notes</label>
    <textarea class="form-control mb-2" id="sf-install-note" rows="3"><?= e((string) ($installation['installation_notes'] ?? '')) ?></textarea>
    <button class="btn btn-outline-light sf-touch" type="button" data-sf-op="INSTALL_NOTE" data-base="<?= e((string) ($installation['installation_notes'] ?? '')) ?>">Save notes</button>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Customer sign-off</h2>
    <p>Customer, job, and the work above. Internal notes and costs stay off this screen.</p>
    <p id="sf-sign-state" class="sf-muted">Signature captured on this phone waits here until sync confirms it. It is not a completed POD until the server accepts it.</p>
    <label class="form-label" for="sf-signer">Signer name</label>
    <input class="form-control form-control-lg mb-2" id="sf-signer">
    <canvas id="sf-sign" class="sf-sign" width="320" height="140"></canvas>
    <button class="btn btn-sf sf-touch mt-2" type="button" id="sf-sign-save" data-gps="1">Save signature</button>
    <button class="btn btn-sf sf-touch mt-2" type="button" data-sf-op="INSTALL_COMPLETE" data-gps="1">Complete installation</button>
</div></section>
<p class="sf-muted">Costs and internal notes are not on this screen. Completing does not issue stock or close the job accounts.</p>
<script type="application/json" id="sf-entity"><?= json_encode(['entity_type' => 'JOB_INSTALLATION', 'entity_id' => (int) $installation['id'], 'mode' => 'install', 'job_id' => (int) $job['id']], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

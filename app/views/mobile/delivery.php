<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $dispatch['job_number']) ?></p>
    <h1><?= e((string) $dispatch['dispatch_number']) ?></h1>
    <p class="sf-muted mb-0"><?= e(customer_label($dispatch)) ?> · <?= e((string) $dispatch['status']) ?></p>
</div>
<div class="sf-action-row mb-3">
    <?php $phone = (string) ($dispatch['mobile'] ?: $dispatch['phone']); ?>
    <?php if ($phone !== ''): ?><a class="btn btn-sf sf-touch" href="<?= e('tel:' . preg_replace('/\s+/', '', $phone)) ?>">Call</a><?php endif; ?>
    <?php if ((string) ($dispatch['site_address'] ?? '') !== ''): ?>
        <a class="btn btn-outline-light sf-touch" href="<?= e('https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string) $dispatch['site_address'])) ?>">Navigate</a>
    <?php endif; ?>
    <button class="btn btn-outline-light sf-touch" type="button" id="sf-download-pack" data-pack-type="DELIVERY" data-entity-id="<?= e((string) $dispatch['id']) ?>">Download field pack</button>
</div>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Items</h2></div>
    <?php if ($items === []): ?><div class="sf-empty"><p>No items on this delivery.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($items as $item): ?>
            <li><strong><?= e((string) $item['description']) ?></strong> <small>qty <?= e((string) $item['quantity']) ?> · <?= e((string) $item['status']) ?></small></li>
        <?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Verify</h2>
    <p class="sf-muted">Scan only items from the downloaded list. Stock does not change here.</p>
    <label class="form-label" for="sf-delivery-code">Item code</label>
    <input class="form-control form-control-lg mb-2" id="sf-delivery-code" autocomplete="off">
    <button class="btn btn-outline-light sf-touch" type="button" id="sf-scan-confirm">Confirm scan</button>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Exception</h2>
    <label class="form-label" for="sf-exception">Reason</label>
    <select class="form-select mb-2" id="sf-exception">
        <?php foreach (['CUSTOMER_NOT_AVAILABLE', 'WRONG_ADDRESS', 'ITEM_DAMAGED', 'PARTIAL_DELIVERY', 'REFUSED', 'OTHER'] as $reason): ?>
            <option value="<?= e($reason) ?>"><?= e(ucwords(strtolower(str_replace('_', ' ', $reason)))) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="form-label" for="sf-exception-note">Note</label>
    <textarea class="form-control mb-2" id="sf-exception-note" rows="3"></textarea>
    <button class="btn btn-outline-light sf-touch" type="button" data-sf-op="DELIVERY_EXCEPTION">Save exception</button>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Photo</h2>
    <?php $photoCategories = ['COMPLETION', 'DAMAGE', 'OTHER']; require base_path('app/views/mobile/partials/photo_tools.php'); ?>
</div></section>
<section class="sf-panel mb-3" data-sf-work><div class="p-3">
    <h2 class="h5">Recipient</h2>
    <p id="sf-sign-state" class="sf-muted">Signature captured on this phone waits here until sync confirms one proof of delivery.</p>
    <label class="form-label" for="sf-signer">Recipient name</label>
    <input class="form-control form-control-lg mb-2" id="sf-signer">
    <canvas id="sf-sign" class="sf-sign" width="320" height="140"></canvas>
    <button class="btn btn-sf sf-touch mt-2" type="button" id="sf-sign-save" data-gps="1">Save signature</button>
</div></section>
<p class="sf-muted">A scan does not change stock. The signature is not a completed proof of delivery until sync confirms it.</p>
<script type="application/json" id="sf-entity"><?= json_encode(['entity_type' => 'DISPATCH', 'entity_id' => (int) $dispatch['id'], 'mode' => 'delivery'], JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

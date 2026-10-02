<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) ($artwork['artwork_number'] ?? 'Revision ' . $artwork['revision_number'])) ?></p>
    <h1><?= e((string) $artwork['title']) ?></h1>
    <p class="sf-muted"><?= e((string) $artwork['status']) ?></p>
</div>
<?php if (!empty($proof)): ?>
<?php
    $customer = true;
    $annotations = $annotations ?? [];
    $fileUrl = url('/portal/artwork/' . $artwork['id'] . '/proof');
    $label = (string) ($artwork['artwork_number'] ?? '') . ' R' . (int) $artwork['revision_number'] . ' PROOF';
    require base_path('app/views/artwork/proof_viewer.php');
?>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/portal/artwork/' . $artwork['id'] . '/annotations')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="proof_id" value="<?= (int) $proof['id'] ?>">
    <input type="hidden" name="x" value="0.62">
    <input type="hidden" name="y" value="0.31">
    <input type="hidden" name="page_number" value="<?= max(1, (int) ($_GET['page'] ?? 1)) ?>">
    <label class="form-label" for="pin">Comment on the proof</label>
    <textarea class="form-control mb-2" id="pin" name="body" rows="3"></textarea>
    <button class="btn btn-outline-light w-100" type="submit">Place comment</button>
</form>
<?php endif; ?>
<p><a class="btn btn-outline-light" href="<?= e(url('/portal/artwork/' . $artwork['id'] . '/file')) ?>">Download proof</a></p>
<?php if (in_array((string) $artwork['status'], ['SENT_FOR_APPROVAL', 'CUSTOMER_REVIEW', 'CHANGES_REQUESTED'], true)): ?>
<section class="sf-panel mb-3">
    <div class="p-3">
        <p><?= e((string) $statement) ?></p>
        <form method="post" action="<?= e(url('/portal/artwork/' . $artwork['id'] . '/approve')) ?>">
            <?= csrf_field() ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="approve" value="1" id="approve">
                <label class="form-check-label" for="approve">I approve this artwork revision</label>
            </div>
            <button class="btn btn-sf btn-lg w-100" type="submit">Approve artwork</button>
        </form>
    </div>
</section>
<section class="sf-panel mb-3">
    <form class="p-3" method="post" action="<?= e(url('/portal/artwork/' . $artwork['id'] . '/changes')) ?>">
        <?= csrf_field() ?>
        <label class="form-label" for="message">Requested changes</label>
        <textarea class="form-control mb-2" id="message" name="message" rows="4"></textarea>
        <button class="btn btn-outline-light w-100" type="submit">Request changes</button>
    </form>
</section>
<?php endif; ?>
<p><a href="<?= e(url('/portal')) ?>">Back</a></p>

<div class="sf-page-head">
    <p class="sf-kicker mb-1">Revision <?= e((string) $artwork['revision_number']) ?></p>
    <h1><?= e((string) $artwork['title']) ?></h1>
    <p class="sf-muted"><?= e((string) $artwork['status']) ?></p>
</div>
<p><a class="btn btn-outline-light" href="<?= e(url('/portal/artwork/' . $artwork['id'] . '/file')) ?>">Download proof</a></p>
<?php if ((string) $artwork['status'] === 'SENT_FOR_APPROVAL' || (string) $artwork['status'] === 'CHANGES_REQUESTED'): ?>
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

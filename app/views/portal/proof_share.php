<div class="sf-page-head">
    <p class="sf-kicker">PROOF · NOT FOR PRODUCTION</p>
    <h1><?= e((string) $artwork['title']) ?></h1>
    <p><?= e((string) $artwork['artwork_number']) ?> · <?= e((string) $revision['revision_label']) ?></p>
</div>
<?php
    $customer = true;
    $fileUrl = '';
    $label = (string) $artwork['artwork_number'] . ' ' . (string) $revision['revision_label'];
    require base_path('app/views/artwork/proof_viewer.php');
?>
<?php if ((string) $token['permission'] === 'APPROVE'): ?>
<section class="sf-panel p-3">
    <p>Revision <?= e((string) $revision['revision_label']) ?></p>
    <p>Artwork <?= e((string) $artwork['title']) ?></p>
    <p><?= e($statement) ?></p>
    <form method="post" action="<?= e(url('/proof/' . $rawToken . '/approve')) ?>">
        <?= csrf_field() ?>
        <label class="form-label" for="name">Your name</label>
        <input class="form-control mb-2" id="name" name="name" required>
        <label class="form-label" for="email">Email</label>
        <input class="form-control mb-2" id="email" name="email" type="email">
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="approve" value="1" id="approve">
            <label class="form-check-label" for="approve">I approve this revision</label>
        </div>
        <button class="btn btn-sf w-100" type="submit">Approve artwork</button>
    </form>
</section>
<?php endif; ?>

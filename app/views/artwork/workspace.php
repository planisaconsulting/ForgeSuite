<?php
$tabs = [
    'overview' => 'Overview',
    'revisions' => 'Revisions',
    'proofing' => 'Proofing',
    'files' => 'Files',
    'production' => 'Production files',
    'approvals' => 'Approvals',
    'activity' => 'Activity',
];
?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $artwork['artwork_number']) ?> · <?= e((string) $artwork['job_number']) ?></p>
    <h1><?= e((string) $artwork['title']) ?></h1>
    <p class="sf-muted"><?= e((string) $artwork['company_name']) ?> · <?= e((string) $artwork['status']) ?> · revision <?= (int) $artwork['revision_number'] ?></p>
</div>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Artwork workspace">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="btn btn-sm <?= $tab === $key ? 'btn-sf' : 'btn-outline-light' ?>" href="<?= e(url('/artwork/' . $artwork['id'] . '?tab=' . $key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<?php if ($brandWarning !== null): ?>
    <p class="sf-panel p-3"><?= e($brandWarning) ?> The artwork file was not changed.</p>
<?php endif; ?>
<?php if ($tab === 'overview'): ?>
<section class="sf-panel p-3">
    <p>Finished size <?= e((string) ($artwork['finished_width_mm'] ?? '—')) ?> × <?= e((string) ($artwork['finished_height_mm'] ?? '—')) ?> mm<?= $artwork['scale_label'] ? ' · scale ' . e((string) $artwork['scale_label']) : '' ?></p>
    <p>Customer approval <?= (int) $artwork['customer_approved'] === 1 ? 'recorded on the current revision' : 'not on the current revision' ?>.</p>
    <p>Physical sample <?= e((string) $artwork['physical_sample_status']) ?>.</p>
    <?php if ($artwork['checkout_user_id']): ?><p>Checked out for editing.</p><?php endif; ?>
    <p><a href="<?= e(url('/artwork/' . $artwork['id'] . '/compare')) ?>">Compare revisions</a></p>
</section>
<?php elseif ($tab === 'revisions'): ?>
<section class="sf-panel p-3 mb-3">
    <ol>
        <?php foreach ($revisions as $revision): ?>
            <li>
                <strong><?= e((string) $revision['revision_label']) ?></strong>
                <?= e((string) $revision['change_summary']) ?>
                · <?= e((string) $revision['status']) ?>
                · <?= e((string) $revision['change_class']) ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <form method="post" action="<?= e(url('/artwork/' . $artwork['id'] . '/revisions')) ?>">
        <?= csrf_field() ?>
        <label class="form-label" for="change_summary">Change summary</label>
        <input class="form-control mb-2" id="change_summary" name="change_summary" required>
        <label class="form-label" for="change_class">What changed</label>
        <select class="form-select mb-2" id="change_class" name="change_class">
            <option value="CUSTOMER_VISIBLE">Customer-visible</option>
            <option value="PRODUCTION_ONLY">Production only</option>
            <option value="ADMINISTRATIVE">Administrative</option>
        </select>
        <label class="form-label" for="reason_code">Reason</label>
        <select class="form-select mb-2" id="reason_code" name="reason_code">
            <option value="CUSTOMER_CHANGE">Customer change</option>
            <option value="PRODUCTION_CORRECTION">Production correction</option>
            <option value="INTERNAL_CORRECTION">Internal correction</option>
        </select>
        <button class="btn btn-sf" type="submit">Create revision</button>
    </form>
</section>
<?php elseif ($tab === 'proofing'): ?>
<?php
    $fileUrl = $proof ? url('/artwork/proofs/' . $proof['id'] . '/file') : '';
    $label = (string) $artwork['artwork_number'] . ' R' . (int) $artwork['revision_number'];
    require base_path('app/views/artwork/proof_viewer.php');
?>
<form class="sf-panel p-3 mb-3" method="post" enctype="multipart/form-data" action="<?= e(url('/artwork/' . $artwork['id'] . '/proof')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="revision_id" value="<?= (int) ($artwork['current_revision_id'] ?? 0) ?>">
    <label class="form-label" for="file">Customer proof (PDF, JPG, PNG)</label>
    <input class="form-control mb-2" id="file" name="file" type="file" required>
    <button class="btn btn-sf" type="submit">Send proof</button>
</form>
<form class="sf-panel p-3" method="post" action="<?= e(url('/artwork/' . $artwork['id'] . '/annotations')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="proof_id" value="<?= (int) ($proof['id'] ?? 0) ?>">
    <input type="hidden" name="x" value="0.50">
    <input type="hidden" name="y" value="0.50">
    <input type="hidden" name="page_number" value="<?= max(1, (int) ($_GET['page'] ?? 1)) ?>">
    <label class="form-label" for="body">Comment</label>
    <textarea class="form-control mb-2" id="body" name="body" rows="3"></textarea>
    <label class="form-label" for="visibility">Visibility</label>
    <select class="form-select mb-2" id="visibility" name="visibility">
        <option value="CUSTOMER_SHARED">Customer shared</option>
        <option value="INTERNAL_ONLY">Internal only</option>
    </select>
    <button class="btn btn-outline-light" type="submit">Place comment</button>
</form>
<?php elseif ($tab === 'production'): ?>
<section class="sf-panel p-3">
    <?php foreach ($files as $file): ?>
        <p>
            <?= e((string) $file['version_label']) ?>
            · <?= e((string) $file['status']) ?>
            <?php if ((string) $file['status'] === 'SUPERSEDED'): ?>· SUPERSEDED — DO NOT USE<?php endif; ?>
            <?php if ((string) $file['status'] === 'APPROVED_FOR_PRODUCTION'): ?>· APPROVED FOR PRODUCTION<?php endif; ?>
        </p>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(url('/artwork/' . $artwork['id'] . '/production-files')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="revision_id" value="<?= (int) ($artwork['current_revision_id'] ?? 0) ?>">
        <label class="form-label" for="category">Category</label>
        <select class="form-select mb-2" id="category" name="category">
            <option value="PRINT">Print</option>
            <option value="CNC">CNC</option>
            <option value="CUT_FILE">Cut</option>
        </select>
        <input class="form-control mb-2" name="file" type="file" required>
        <button class="btn btn-sf" type="submit">Store production file</button>
    </form>
</section>
<?php elseif ($tab === 'approvals'): ?>
<section class="sf-panel p-3">
    <p><?= e($statement) ?></p>
    <?php foreach ($approvals as $approval): ?>
        <p><?= e((string) $approval['customer_name']) ?> · <?= e((string) ($approval['reference'] ?? '')) ?> · <?= e((string) ($approval['approved_at'] ?? '')) ?></p>
    <?php endforeach; ?>
</section>
<?php else: ?>
<section class="sf-panel p-3">
    <p>Activity is the revision list and the approval records. A new file does not replace an earlier revision.</p>
    <p><a href="<?= e(url('/artwork/jobs/' . $artwork['job_id'] . '/workshop')) ?>">Workshop file</a></p>
</section>
<?php endif; ?>

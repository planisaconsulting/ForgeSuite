<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Upload a proof</h2></div>
    <?php if (can('artwork.upload')): ?>
        <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/artwork')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="title">Proof title</label>
                    <input class="form-control" id="title" name="title" placeholder="Main sign proof" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="job_item_id">Job item</label>
                    <select class="form-select" id="job_item_id" name="job_item_id">
                        <option value="">Whole job</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= e((string) $item['id']) ?>"><?= e((string) $item['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="file">File</label>
                    <input class="form-control" id="file" name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,image/*" capture="environment" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="notes">Notes</label>
                    <input class="form-control" id="notes" name="notes">
                </div>
            </div>
            <div class="sf-form-actions"><button class="btn btn-sf btn-lg" type="submit">Upload proof</button></div>
        </form>
    <?php else: ?>
        <p class="p-3 mb-0">You can view proofs. Uploading needs the artwork permission.</p>
    <?php endif; ?>
</section>
<?php
$grouped = [];
foreach ($artworks as $artwork) {
    $grouped[(string) $artwork['title']][] = $artwork;
}
?>
<?php if ($grouped === []): ?>
    <section class="sf-panel"><p class="p-3 mb-0">No proofs yet. Uploading a new revision keeps the previous file.</p></section>
<?php endif; ?>
<?php foreach ($grouped as $title => $revisions): ?>
    <section class="sf-panel mb-3">
        <div class="sf-panel-head"><h2><?= e($title) ?></h2></div>
        <?php foreach ($revisions as $artwork): ?>
            <?php $current = (string) $artwork['status'] !== 'SUPERSEDED'; ?>
            <article class="p-3 border-bottom border-secondary">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <strong>Rev <?= e((string) $artwork['revision_number']) ?></strong>
                        <span class="sf-badge <?= $current ? '' : 'sf-badge-low' ?>"><?= $current ? 'Current' : 'Superseded' ?></span>
                        <span class="sf-badge"><?= e(enum_label(\App\Domain\ArtworkStatus::class, (string) $artwork['status'])) ?></span>
                    </div>
                    <a class="btn btn-outline-light" href="<?= e(url('/jobs/' . $job['id'] . '/artwork/' . $artwork['id'] . '/file')) ?>">Open file</a>
                </div>
                <p class="mb-1 mt-2"><?= e((string) $artwork['original_filename']) ?> · <?= e((string) ($artwork['uploader_name'] ?? 'Staff')) ?></p>
                <?php if (!empty($artwork['notes'])): ?><p class="mb-2"><?= e((string) $artwork['notes']) ?></p><?php endif; ?>
                <?php if ((int) $artwork['customer_approved'] === 1): ?>
                    <p class="mb-2">Recorded approval by <?= e((string) $artwork['customer_approved_by']) ?> at <?= e(format_datetime((string) $artwork['customer_approved_at'])) ?>. This is a staff record of the customer's response.</p>
                <?php endif; ?>
                <?php foreach ($approvals[(int) $artwork['id']] ?? [] as $approval): ?>
                    <p class="mb-1 sf-muted"><?= e((string) $approval['customer_name']) ?> via <?= e(enum_label(\App\Domain\ArtworkApprovalMethod::class, (string) $approval['approval_method'])) ?><?= $approval['reference'] ? ' · ' . e((string) $approval['reference']) : '' ?></p>
                <?php endforeach; ?>
                <?php if ($current && can('artwork.upload')): ?>
                    <form class="d-flex flex-wrap gap-2 mt-2" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/artwork/' . $artwork['id'] . '/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="version_number" value="<?= e((string) $artwork['version_number']) ?>">
                        <select class="form-select" name="status" style="max-width:16rem">
                            <?php foreach ($artworkStatuses as $status): ?>
                                <?php if ($status->value === 'APPROVED') { continue; } ?>
                                <option value="<?= e($status->value) ?>" <?= (string) $artwork['status'] === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-light" type="submit">Update proof</button>
                    </form>
                <?php endif; ?>
                <?php if ($current && (string) $artwork['status'] !== 'APPROVED' && can('artwork.approve_record')): ?>
                    <form class="row g-2 mt-2" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/artwork/' . $artwork['id'] . '/approval')) ?>">
                        <?= csrf_field() ?>
                        <div class="col-md-4"><input class="form-control" name="customer_name" placeholder="Customer name" required></div>
                        <div class="col-md-3">
                            <select class="form-select" name="approval_method" required>
                                <?php foreach ($methods as $method): ?>
                                    <option value="<?= e($method->value) ?>"><?= e($method->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><input class="form-control" name="reference" placeholder="Email or message reference"></div>
                        <div class="col-md-2"><button class="btn btn-sf w-100" type="submit">Record approval</button></div>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>

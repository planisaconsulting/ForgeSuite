<?php if (can('attachments.manage')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Add a file</h2></div>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/files')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="purpose">Type</label>
                <select class="form-select" id="purpose" name="purpose">
                    <?php foreach (['GENERAL' => 'General', 'SITE_PHOTO' => 'Site photo', 'REFERENCE' => 'Reference', 'PO' => 'Purchase order', 'SIGNED' => 'Signed document', 'COMPLETION_PHOTO' => 'Completion photo'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="file">File</label>
                <input class="form-control" id="file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.txt,image/*" capture="environment" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="notes">Note</label>
                <input class="form-control" id="notes" name="notes">
            </div>
        </div>
        <div class="sf-form-actions"><button class="btn btn-sf btn-lg" type="submit">Upload</button></div>
    </form>
</section>
<?php endif; ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Files</h2></div>
    <?php if ($files === []): ?><p class="p-3 mb-0">No files on this job yet.</p><?php endif; ?>
    <?php foreach ($files as $file): ?>
        <p class="px-3">
            <a href="<?= e(url('/attachments/' . $file['id'])) ?>"><?= e((string) $file['original_filename']) ?></a>
            · <?= e((string) ($file['purpose'] ?? 'GENERAL')) ?>
            <?php if (!empty($file['notes'])): ?> · <?= e((string) $file['notes']) ?><?php endif; ?>
        </p>
    <?php endforeach; ?>
</section>

<div class="sf-page-head">
    <h1>Current production file</h1>
</div>
<section class="sf-panel p-3">
    <?php if ($pack['current'] === null): ?>
        <p>No production file is approved for this job.</p>
    <?php else: ?>
        <p class="display-6"><?= e((string) $pack['current']['version_label']) ?></p>
        <p>APPROVED FOR PRODUCTION · artwork revision <?= (int) $pack['current']['artwork_revision'] ?></p>
        <p><a class="btn btn-sf" href="<?= e(url('/artwork/production-files/' . $pack['current']['id'] . '/download')) ?>">Download current file</a></p>
    <?php endif; ?>
    <ul class="mt-3">
        <?php foreach ($pack['files'] as $file): ?>
            <?php if ((string) $file['status'] === 'SUPERSEDED'): ?>
                <li><?= e((string) $file['version_label']) ?> · SUPERSEDED — DO NOT USE</li>
            <?php elseif ((int) $file['id'] !== (int) ($pack['current']['id'] ?? 0)): ?>
                <li><?= e((string) $file['version_label']) ?> · <?= e((string) $file['status']) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</section>

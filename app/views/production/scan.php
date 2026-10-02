<div class="sf-page-head"><h1>Release scan</h1></div>
<section class="sf-panel">
    <?php if (empty($scan['found'])): ?>
        <p>That release number was not found.</p>
    <?php else: ?>
        <?php if (!empty($scan['warning'])): ?><p class="display-6"><?= e((string) $scan['warning']) ?></p><?php endif; ?>
        <p>Scanned <?= e((string) $scan['release']['release_number']) ?> · <?= e((string) $scan['release']['status']) ?></p>
        <?php if (!empty($scan['current'])): ?>
            <p>Current authorised release <?= e((string) $scan['current']['release_number']) ?> R<?= (int) $scan['current']['release_version'] ?>.</p>
            <a href="<?= e(url('/production/releases/' . (int) $scan['current']['id'] . '/pack')) ?>">Open current pack</a>
            <?php if (!empty($scan['production_file'])): ?>
                <p>Current production file <?= e((string) $scan['production_file']['version_label']) ?> · APPROVED FOR PRODUCTION</p>
                <a href="<?= e(url('/artwork/jobs/' . (int) $scan['release']['job_id'] . '/workshop')) ?>">Open the current production file</a>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</section>

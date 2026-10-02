<section class="sf-panel">
    <p>Preparation <?= e((string) ($job['preparation_status'] ?? 'NOT_READY')) ?>. Customer acceptance does not authorise manufacture.</p>
    <?php if ($currentRelease): ?>
        <p>Current <?= e((string) $currentRelease['release_number']) ?> R<?= (int) $currentRelease['release_version'] ?></p>
    <?php else: ?>
        <p>No current production release.</p>
    <?php endif; ?>
    <a class="btn btn-light" href="<?= e(url('/production/jobs/' . (int) $job['id'] . '/release')) ?>">Open release</a>
</section>

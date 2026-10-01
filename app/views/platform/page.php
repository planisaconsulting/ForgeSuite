<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <?php if (($intro ?? '') !== ''): ?><p class="sf-muted mb-0"><?= e($intro) ?></p><?php endif; ?>
    </div>
    <?php if (!empty($links)): ?>
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach ($links as $link): ?>
                <a class="btn btn-sf" href="<?= e(url($link['href'])) ?>"><?= e($link['label']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php foreach ($sections as $section): ?>
    <?php if (($section['kind'] ?? '') === 'figure'): ?>
        <section class="sf-panel p-3 mb-3">
            <p class="sf-kicker mb-1"><?= e((string) $section['label']) ?></p>
            <p class="fs-3 mb-1"><?= e((string) $section['value']) ?></p>
            <?php if (($section['note'] ?? '') !== ''): ?><p class="sf-muted mb-0"><?= e((string) $section['note']) ?></p><?php endif; ?>
        </section>
    <?php else: ?>
        <section class="sf-panel mb-3">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><?php foreach ($section['head'] as $head): ?><th><?= e((string) $head) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php if ($section['rows'] === []): ?>
                        <tr><td colspan="<?= e((string) count($section['head'])) ?>">Nothing to show yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($section['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $index => $cell): ?>
                                <td>
                                    <?php if (is_string($cell) && str_starts_with($cell, '/')): ?>
                                        <a href="<?= e(url($cell)) ?>">Open</a>
                                    <?php else: ?>
                                        <?= e((string) $cell) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
<?php endforeach; ?>

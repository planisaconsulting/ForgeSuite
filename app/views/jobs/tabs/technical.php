<?php if ($technical === null): ?>
    <section class="sf-panel mt-3"><p class="mb-0">This job has no estimator snapshot. A later specification change does not fill one in.</p></section>
<?php else: ?>
    <section class="sf-panel mt-3">
        <h2>Specification</h2>
        <p><?= e((string) ($technical['specification_code'] ?? 'Manual')) ?> version <?= e((string) ($technical['specification_version'] ?? '')) ?></p>
        <p class="sf-muted"><?= e((string) ($technical['estimator_type'] ?? '')) ?>. This is the snapshot accepted with the job. It is not recalculated from the current specification.</p>
        <?php if (($technical['warnings'] ?? []) !== []): ?>
            <div class="alert alert-warning"><?php foreach ($technical['warnings'] as $warning): ?><div><?= e((string) $warning) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <h2 class="h5">Production route</h2>
        <p><?= e(implode(' · ', array_map('strval', $technical['route'] ?? []))) ?></p>
        <h2 class="h5">Bill of materials</h2>
        <ul><?php foreach (array_merge($technical['bom'] ?? [], $technical['components'] ?? []) as $line): ?>
            <li><?= e((string) ($line['description'] ?? '')) ?> · <?= e((string) ($line['quantity'] ?? '')) ?> <?= e((string) ($line['unit'] ?? '')) ?></li>
        <?php endforeach; ?></ul>
        <h2 class="h5">How this was calculated</h2>
        <ul><?php foreach ($technical['trace'] ?? [] as $step): ?>
            <li><strong><?= e((string) $step['label']) ?></strong> <?= e((string) $step['method']) ?> — <?= e((string) $step['detail']) ?> = <?= e((string) $step['result']) ?></li>
        <?php endforeach; ?></ul>
    </section>
<?php endif; ?>

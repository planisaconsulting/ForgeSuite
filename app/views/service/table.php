<div class="sf-page-head"><h1><?= e((string) $report['title']) ?></h1></div>
<section class="sf-panel"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><?php foreach ($report['columns'] as $column): ?><th><?= e(str_replace('_', ' ', (string) $column)) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($report['rows'] as $row): ?><tr><?php foreach ($report['columns'] as $column): ?><td><?= e((string) ($row[$column] ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
</table></div>
<?php if ($report['rows'] === []): ?><p class="p-3 mb-0">Nothing to show.</p><?php endif; ?>
</section>

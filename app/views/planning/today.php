<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Today</h1><p class="sf-muted mb-0"><?= e(date('l j F Y', strtotime($date))) ?></p></div>
<?php
$sections = [
    'Design' => array_values(array_filter($entries, static fn (array $row): bool => str_contains(strtolower((string) $row['resource_name']), 'design'))),
    'Production' => array_values(array_filter($entries, static fn (array $row): bool => in_array($row['entity_type'], ['PRODUCTION_STAGE', 'JOB_TASK', 'JOB'], true))),
    'Installations' => $installations,
    'Site surveys' => $surveys,
    'Deliveries' => $deliveries,
    'Maintenance' => $maintenance,
];
?>
<div class="row g-3">
    <?php foreach ($sections as $label => $rows): ?>
        <div class="col-md-6">
            <section class="sf-panel h-100">
                <div class="sf-panel-head"><h2><?= e($label) ?></h2></div>
                <?php if ($rows === []): ?><div class="sf-empty"><p>Nothing in this section today.</p></div><?php else: ?>
                    <ul class="sf-feed"><?php foreach ($rows as $row): ?>
                        <li><?= e((string) ($row['job_number'] ?? $row['survey_number'] ?? $row['resource_name'] ?? $row['description'] ?? 'Item')) ?>
                            <small><?= e((string) ($row['title'] ?? $row['site_name'] ?? $row['status'] ?? '')) ?></small></li>
                    <?php endforeach; ?></ul>
                <?php endif; ?>
            </section>
        </div>
    <?php endforeach; ?>
    <div class="col-12">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Unavailable</h2></div>
            <?php if ($unavailable === []): ?><div class="sf-empty"><p>No leave, breakdowns, or maintenance blocks today.</p></div><?php else: ?>
                <ul class="sf-feed"><?php foreach ($unavailable as $row): ?>
                    <li><?= e((string) $row['resource_name']) ?> · <?= e((string) $row['reason_type']) ?><small><?= e((string) $row['resource_type']) ?></small></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    </div>
</div>

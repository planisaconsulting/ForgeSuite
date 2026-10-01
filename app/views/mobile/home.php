<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1">My work</p>
    <h1>Today</h1>
</div>
<?php
$card = static function (array $row): void {
    ?>
    <a class="sf-field-card" href="<?= e(url('/m/installations/' . $row['id'])) ?>">
        <strong><?= e((string) ($row['scheduled_start_time'] ?? $row['scheduled_date'] ?? 'Open')) ?></strong>
        <span><?= e(customer_label($row)) ?></span>
        <small><?= e((string) $row['job_number']) ?> · <?= e((string) $row['status']) ?></small>
        <small><?= e((string) ($row['site_address'] ?? '')) ?></small>
    </a>
    <?php
};
?>
<?php if (($overdueInstallations ?? []) !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Overdue</h2></div>
    <?php foreach ($overdueInstallations as $row) { $card($row); } ?>
</section>
<?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Today</h2></div>
    <?php if ($installations === []): ?><div class="sf-empty"><p>Nothing assigned for today.</p></div><?php else: ?>
        <?php foreach ($installations as $row) { $card($row); } ?>
    <?php endif; ?>
</section>
<?php if (($nextInstallations ?? []) !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Next</h2></div>
    <?php foreach ($nextInstallations as $row) { $card($row); } ?>
</section>
<?php endif; ?>
<?php if (($returns ?? []) !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Snags needing a return</h2></div>
    <?php foreach ($returns as $row) { $card($row); } ?>
</section>
<?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Site surveys</h2></div>
    <?php if ($surveys === []): ?><div class="sf-empty"><p>No open surveys.</p></div><?php else: ?>
        <?php foreach ($surveys as $row): ?>
            <a class="sf-field-card" href="<?= e(url('/m/surveys/' . $row['id'])) ?>">
                <strong><?= e((string) $row['survey_number']) ?></strong>
                <span><?= e((string) $row['site_name']) ?></span>
                <small><?= e((string) $row['status']) ?><?php if (!empty($row['survey_date'])): ?> · <?= e((string) $row['survey_date']) ?><?php endif; ?></small>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Deliveries</h2></div>
    <?php if ($deliveries === []): ?><div class="sf-empty"><p>No open deliveries.</p></div><?php else: ?>
        <?php foreach ($deliveries as $row): ?>
            <a class="sf-field-card" href="<?= e(url('/m/deliveries/' . $row['id'])) ?>">
                <strong><?= e((string) $row['dispatch_number']) ?></strong>
                <span><?= e(customer_label($row)) ?></span>
                <small><?= e((string) $row['job_number']) ?> · <?= e((string) $row['status']) ?></small>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php if ($workshop !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Workshop</h2></div>
    <?php foreach ($workshop as $row): ?>
        <a class="sf-field-card" href="<?= e(url('/m/jobs/' . $row['id'])) ?>">
            <strong><?= e((string) $row['job_number']) ?></strong>
            <span><?= e((string) $row['title']) ?></span>
            <small><?= e((string) $row['status']) ?></small>
        </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($showApprovals): ?>
<p><a class="btn btn-sf sf-touch" href="<?= e(url('/approvals')) ?>">Approvals</a></p>
<?php endif; ?>
<p><a class="btn btn-outline-light sf-touch" href="<?= e(url('/m/scan')) ?>">Scan</a></p>

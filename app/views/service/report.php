<div class="sf-page-head"><p class="sf-kicker">Service report</p><h1><?= e((string) $report['request_number']) ?></h1></div>
<section class="sf-panel"><div class="p-3">
    <p>Job <?= e((string) $report['job_number']) ?></p>
    <p>Asset <?= e((string) $report['asset_number']) ?> <?= e((string) $report['asset_name']) ?></p>
    <p>Site <?= e((string) $report['site']) ?></p>
    <p>Problem <?= e((string) $report['problem']) ?></p>
    <p>Work <?= e((string) $report['work_performed']) ?></p>
    <p>Outstanding <?= e((string) $report['outstanding_issue']) ?></p>
    <p>Recommendations <?= e((string) $report['recommendations']) ?></p>
    <p>Signed <?= e((string) $report['signed_by']) ?> <?= e((string) $report['signed_at']) ?></p>
    <p>Next service <?= e((string) $report['next_service_date']) ?></p>
    <?php if ($report['parts'] !== []): ?><ul><?php foreach ($report['parts'] as $part): ?><li><?= e((string) $part['description']) ?> · <?= e((string) $part['quantity']) ?></li><?php endforeach; ?></ul><?php endif; ?>
</div></section>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $job['job_number']) ?></p>
    <h1><?= e((string) $job['title']) ?></h1>
</div>
<section class="sf-panel">
    <dl class="sf-dl">
        <div><dt>Customer</dt><dd><a href="<?= e(url('/customers/' . $job['customer_id'])) ?>"><?= e(customer_label($job)) ?></a></dd></div>
        <div><dt>Source quote</dt><dd><a href="<?= e(url('/quotes/' . $job['quote_id'])) ?>"><?= e((string) $job['quote_number']) ?></a> revision <?= e((string) $job['quote_revision_number']) ?></dd></div>
        <div><dt>Status</dt><dd><?= e((string) $job['status']) ?></dd></div>
        <div><dt>Priority</dt><dd><?= e((string) $job['priority']) ?></dd></div>
        <div><dt>Target date</dt><dd><?= e((string) ($job['target_date'] ?? '—')) ?></dd></div>
        <div><dt>Created by</dt><dd><?= e((string) ($job['created_by_name'] ?? '—')) ?></dd></div>
    </dl>
    <div class="p-3">
        <p class="mb-0">Production management will be available in Phase 3.</p>
    </div>
</section>

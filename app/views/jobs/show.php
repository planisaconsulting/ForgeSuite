<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head d-flex flex-wrap justify-content-between gap-3">
    <div>
        <?php $projectLink = project_context($job); ?>
        <p class="sf-kicker mb-1"><?= e((string) $job['job_number']) ?> · <?= e(customer_label($job)) ?><?php if ($projectLink): ?> · <a href="<?= e(url('/projects/' . $projectLink['id'])) ?>"><?= e((string) $projectLink['project_number']) ?></a><?php if (!empty($projectLink['site'])): ?> · <?= e((string) $projectLink['site']['site_code']) ?><?php endif; ?><?php if (!empty($projectLink['wave'])): ?> · <?= e((string) $projectLink['wave']['name']) ?><?php endif; ?><?php endif; ?></p>
        <h1 class="mb-1"><?= e((string) $job['title']) ?></h1>
        <p class="mb-0 d-flex flex-wrap gap-2 align-items-center">
            <span class="sf-badge"><?= e(enum_label(\App\Domain\JobStatus::class, (string) $job['status'])) ?></span>
            <?= priority_badge((string) $job['priority']) ?>
            <?php if (job_overdue($job)): ?><span class="sf-badge sf-badge-urgent">Overdue</span><?php endif; ?>
            <?php if (!empty($scheduleHealth['status'])): ?><span class="sf-badge"><?= e(str_replace('_', ' ', (string) $scheduleHealth['status'])) ?></span><?php endif; ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-light" href="<?= e(url('/jobs/' . $job['id'] . '/card')) ?>" target="_blank" rel="noopener">Print job card</a>
        <a class="btn btn-outline-light" href="<?= e(url('/jobs/' . $job['id'] . '/card.pdf')) ?>">Download job card PDF</a>
        <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $job['quote_id'])) ?>">View source quote</a>
        <?php if (can('site_surveys.create')): ?><a class="btn btn-outline-light" href="<?= e(url('/surveys/new?customer_id=' . $job['customer_id'] . '&job_id=' . $job['id'] . (!empty($job['quote_id']) ? '&quote_id=' . (int) $job['quote_id'] : ''))) ?>">Site survey</a><?php endif; ?>
    </div>
</div>
<?php if (!empty($scheduleHealth['reasons'])): ?>
    <div class="alert alert-warning"><?php foreach ($scheduleHealth['reasons'] as $reason): ?><div><?= e((string) $reason) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if (!empty($earliest['date'])): ?>
    <p class="sf-muted">Estimated earliest completion: <?= e((string) $earliest['date']) ?>. This is not a guaranteed date.</p>
<?php endif; ?>
<?php if (!empty($scheduleHealth['readiness']['found'])): ?>
    <p class="mb-3">Artwork <?= e((string) $scheduleHealth['readiness']['artwork']) ?> · Materials <?= e((string) $scheduleHealth['readiness']['materials']) ?> · Production <?= e((string) $scheduleHealth['readiness']['production']) ?> · Installation <?= e((string) $scheduleHealth['readiness']['installation']) ?></p>
<?php endif; ?>
<?php if ((int) ($job['account_on_hold'] ?? 0) === 1): ?>
    <div class="alert alert-warning">This customer account is on hold. Existing jobs stay open. New invoices need a finance reason.</div>
<?php endif; ?>
<nav class="sf-tabs" aria-label="Job sections">
    <?php foreach ($tabs as $key => $label): ?>
        <a class="<?= $tab === $key ? 'is-active' : '' ?>" href="<?= e(url('/jobs/' . $job['id'] . '?tab=' . $key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<?php require base_path('app/views/jobs/tabs/' . $tab . '.php'); ?>

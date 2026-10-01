<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Installations</h1>
</div>
<?php
$groups = ['Today' => $today, 'Upcoming' => $upcoming, 'Return required' => $returns];
?>
<?php foreach ($groups as $label => $rows): ?>
    <h2 class="h5 mt-3"><?= e($label) ?></h2>
    <?php if ($rows === []): ?><p class="sf-muted">None.</p><?php endif; ?>
    <div class="row g-3">
        <?php foreach ($rows as $row): ?>
            <div class="col-12 col-md-6">
                <article class="sf-workshop-card">
                    <?php $projectLink = project_context($row); ?>
                    <p class="sf-kicker mb-1"><?= e((string) $row['job_number']) ?><?php if ($projectLink): ?> · <a href="<?= e(url('/projects/' . $projectLink['id'])) ?>"><?= e((string) $projectLink['project_number']) ?></a><?php endif; ?></p>
                    <h3 class="h5"><?= e(customer_label($row)) ?></h3>
                    <p class="mb-1"><?= e((string) ($row['site_address'] ?? 'No address')) ?></p>
                    <p class="mb-1"><?= e((string) ($row['site_contact_name'] ?? '')) ?> <?= e((string) ($row['site_contact_phone'] ?? '')) ?></p>
                    <p class="mb-2"><?= e((string) ($row['scheduled_date'] ?? 'Unscheduled')) ?> <?= e((string) ($row['scheduled_start_time'] ?? '')) ?> · <?= e((string) ($row['team_name'] ?? 'No team')) ?> · <?= e(enum_label(\App\Domain\InstallationStatus::class, (string) $row['status'])) ?></p>
                    <a class="btn btn-sf btn-lg" href="<?= e(url('/jobs/' . $row['job_id'] . '?tab=installation')) ?>">Open job</a>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

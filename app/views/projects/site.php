<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1"><a href="<?= e(url('/projects/' . $site['project_id'])) ?>"><?= e((string) $site['project_number']) ?></a> · <?= e((string) ($site['wave_name'] ?? 'No wave')) ?></p>
    <h1><?= e((string) $site['site_code']) ?> · <?= e((string) $site['site_name']) ?></h1>
    <p><?= e(str_replace('_', ' ', (string) $site['status'])) ?> · Original <?= e((string) ($site['original_target_date'] ?: '—')) ?> · Current <?= e((string) ($site['current_target_date'] ?: '—')) ?></p>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <section class="sf-panel">
            <h2>Address</h2>
            <p><?= e((string) ($site['address_line_1'] ?? '')) ?><br><?= e((string) ($site['city'] ?? '')) ?> <?= e((string) ($site['province'] ?? '')) ?></p>
            <p>Survey <?= (int) $site['survey_required'] === 1 ? 'required' : 'not required' ?><?php if ($site['survey_id']): ?> · <a href="<?= e(url('/surveys/' . $site['survey_id'])) ?>">Open survey</a><?php endif; ?></p>
        </section>
        <section class="sf-panel mt-3">
            <h2>Jobs</h2>
            <?php if ($jobs === []): ?><p>No jobs on this site yet.</p><?php endif; ?>
            <ul><?php foreach ($jobs as $job): ?><li><a href="<?= e(url('/jobs/' . $job['id'])) ?>"><?= e((string) $job['job_number']) ?></a> <?= e((string) $job['title']) ?> · <?= e((string) $job['status']) ?></li><?php endforeach; ?></ul>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="sf-panel">
            <h2>Field pack</h2>
            <p class="sf-muted">This pack is this site only.</p>
            <ul><?php foreach ($pack['contacts'] ?? [] as $contact): ?><li><?= e((string) $contact['name']) ?> · <?= e((string) ($contact['phone'] ?: $contact['mobile'])) ?></li><?php endforeach; ?></ul>
        </section>
    </div>
</div>

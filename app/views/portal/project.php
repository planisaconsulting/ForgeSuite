<div class="sf-page-head">
    <h1><?= e((string) $project['name']) ?></h1>
    <p class="mb-0"><?= e((string) $project['project_number']) ?></p>
</div>
<section class="sf-panel p-3">
    <p><?= (int) $project['sites']['installed'] ?> of <?= (int) $project['sites']['total'] ?> sites installed.</p>
    <p><?= (int) $project['sites']['in_production'] ?> in production.</p>
    <p><?= (int) $project['sites']['awaiting_approval'] ?> awaiting your approval.</p>
    <p class="mb-0"><?= (int) $project['sites']['scheduled'] ?> scheduled.</p>
</section>

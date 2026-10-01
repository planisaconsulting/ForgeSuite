<div class="sf-page-head">
    <div>
        <h1><?= e($module['title']) ?></h1>
        <p class="sf-muted mb-0">This module is named so the workshop map is visible. It is not built in this phase.</p>
    </div>
</div>

<section class="sf-panel sf-upcoming">
    <span class="sf-stat-icon"><i class="fa-solid <?= e($module['icon']) ?>" aria-hidden="true"></i></span>
    <p class="sf-kicker">Not in this release</p>
    <p><?= e($module['summary']) ?></p>
    <a class="btn btn-sf" href="<?= e(url('/')) ?>">Back to dashboard</a>
</section>

<div class="sf-page-head">
    <div>
        <h1><?= e($module['title']) ?></h1>
        <p class="sf-muted mb-0">This section is on the menu so the desk can be navigated. The working screen comes in the next build.</p>
    </div>
</div>

<section class="sf-panel sf-upcoming">
    <span class="sf-stat-icon"><i class="fa-solid <?= e($module['icon']) ?>" aria-hidden="true"></i></span>
    <p class="sf-kicker">Not in this release</p>
    <p><?= e($module['summary']) ?></p>
    <a class="btn btn-sf" href="<?= e(url('/')) ?>">Back to dashboard</a>
</section>

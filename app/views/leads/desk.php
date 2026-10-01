<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>My sales</h1><p class="sf-muted mb-0">Counts for your work. This is not a ranking.</p></div>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">Response time this month</h2>
    <p class="mb-0">From lead created to the first logged call, email, or WhatsApp preparation. Automated notices are not counted.
        Samples <?= e((string) $response['samples']) ?>,
        median <?= e((string) ($response['median'] ?? 'n/a')) ?> minutes,
        average <?= e((string) ($response['average'] ?? 'n/a')) ?> minutes.
    </p>
</section>
<div class="row g-3">
    <div class="col-md-4"><section class="sf-panel p-3"><h2 class="h6">Uncontacted</h2><p class="display-6 mb-0"><?= e((string) count($uncontacted)) ?></p></section></div>
    <div class="col-md-4"><section class="sf-panel p-3"><h2 class="h6">Assigned to me</h2><p class="display-6 mb-0"><?= e((string) count($mine)) ?></p></section></div>
    <div class="col-md-4"><section class="sf-panel p-3"><h2 class="h6">Newest</h2><p class="display-6 mb-0"><?= e((string) count($newToday)) ?></p></section></div>
</div>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Production throughput</h1></div>
<section class="sf-panel p-3 mb-3">
    <p>Due today <?= e((string) $counts['due_today']) ?> · in progress <?= e((string) $counts['in_progress']) ?> · blocked <?= e((string) $counts['blocked']) ?> · ready <?= e((string) $counts['ready']) ?></p>
</section>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">First-pass yield</h2>
    <p>Items whose first quality check passed, divided by items inspected, times 100. This is not a ranking of people.</p>
    <p class="mb-0"><strong><?= $first_pass === null ? 'Not enough checks' : e((string) $first_pass . '%') ?></strong> · inspected <?= e((string) $inspected) ?></p>
</section>
<section class="sf-panel p-3">
    <h2 class="h5">Reprint rate</h2>
    <p>Reprinted quantity divided by quantity completed on production items, times 100.</p>
    <p class="mb-0"><strong><?= $reprint_rate === null ? 'No completed quantity yet' : e((string) $reprint_rate . '%') ?></strong> · reprinted <?= e((string) $reprinted) ?> / produced <?= e((string) $produced) ?></p>
</section>

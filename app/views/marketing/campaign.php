<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1><?= e((string) $campaign['name']) ?></h1>
    <p class="sf-muted mb-0">First-touch model. Budget is campaign spend, not invoiced value.</p>
</div>
<dl class="sf-dl sf-panel p-3">
    <div><dt>Budget</dt><dd><?= e((string) ($campaign['budget'] ?? 'Not recorded')) ?></dd></div>
    <div><dt>Valid leads</dt><dd><?= e((string) ($figures['valid_leads'] ?? 0)) ?> (spam excluded, unqualified included)</dd></div>
    <div><dt>Cost per valid lead</dt><dd><?= $costPerLead === null ? 'Not calculated' : e($costPerLead) ?></dd></div>
    <div><dt>Quotes</dt><dd><?= e((string) ($figures['quotes'] ?? 0)) ?> · value <?= e((string) ($figures['quote_value'] ?? '0')) ?></dd></div>
    <div><dt>Accepted quotes</dt><dd><?= e((string) ($figures['accepted'] ?? 0)) ?> · commercial value <?= e((string) ($figures['accepted_value'] ?? '0')) ?></dd></div>
    <div><dt>Jobs</dt><dd><?= e((string) ($figures['jobs'] ?? 0)) ?></dd></div>
    <div><dt>Invoiced</dt><dd><?= e((string) ($figures['invoiced'] ?? '0')) ?></dd></div>
    <div><dt>Paid</dt><dd><?= e((string) ($figures['paid'] ?? '0')) ?></dd></div>
    <div><dt>Gross profit on completed jobs</dt><dd><?= e((string) ($figures['gross_profit'] ?? '0')) ?></dd></div>
    <div><dt>Estimated customer acquisition cost</dt><dd><?= $cac === null ? 'Not enough accepted quotes to estimate.' : e($cac) . ' (budget divided by accepted quotes)' ?></dd></div>
</dl>

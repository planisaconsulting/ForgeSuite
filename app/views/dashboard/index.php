<?php require base_path('app/views/partials/flashes.php'); ?>

<div class="sf-page-head">
    <div>
        <h1>Workshop desk</h1>
        <p class="sf-muted mb-0">
            <?= e(company_name()) ?> ·
            <?= e((string) $desk['currency']) ?> <?= e((string) $desk['symbol']) ?> ·
            VAT <?= e((string) $desk['vat']) ?>% ·
            <?= e((string) $desk['timezone']) ?>
        </p>
    </div>
</div>

<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Your desk</h2></div>
    <div class="p-3">
        <?php if (in_array($roleCode, ['ADMIN', 'MANAGEMENT'], true) && can('reports.executive')): ?>
            <p><a href="<?= e(url('/reports/executive')) ?>">Executive report</a> · <a href="<?= e(url('/reports/executive?board=1')) ?>">Full-screen board</a></p>
        <?php endif; ?>
        <?php if ($roleCode === 'SALES' || can('reports.sales')): ?>
            <p><a href="<?= e(url('/opportunities')) ?>">Opportunities</a> · <a href="<?= e(url('/quotes')) ?>">Quotes</a> · <a href="<?= e(url('/reports/sales')) ?>">Sales report</a></p>
        <?php endif; ?>
        <?php if ($roleCode === 'DESIGN'): ?>
            <p><a href="<?= e(url('/jobs/design')) ?>">Artwork queue</a></p>
        <?php endif; ?>
        <?php if ($roleCode === 'PRODUCTION' || can('reports.operations')): ?>
            <p><a href="<?= e(url('/jobs')) ?>">Jobs</a> · <a href="<?= e(url('/jobs/workshop')) ?>">Production queue</a> · <a href="<?= e(url('/jobs?overdue=1')) ?>">Overdue jobs</a></p>
        <?php endif; ?>
        <?php if ($roleCode === 'INSTALLER'): ?>
            <p><a href="<?= e(url('/jobs/installations')) ?>">Today's installations</a></p>
            <ul class="sf-feed">
                <?php foreach ($todayInstalls as $install): ?>
                    <li><a href="<?= e(url('/jobs/' . $install['job_id'])) ?>"><?= e((string) $install['job_number']) ?></a><small><?= e((string) $install['status']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($roleCode === 'ACCOUNTS' || can('reports.finance')): ?>
            <p><a href="<?= e(url('/invoices')) ?>">Invoices</a> · <a href="<?= e(url('/payments')) ?>">Payments</a> · <a href="<?= e(url('/reports/debtors')) ?>">Debtors</a></p>
        <?php endif; ?>
        <?php if ($followUps !== []): ?>
            <h3 class="h6 mt-3">CRM follow-ups due</h3>
            <ul class="sf-feed">
                <?php foreach ($followUps as $row): ?>
                    <li><a href="<?= e(url('/customers/' . $row['customer_id'])) ?>"><?= e(customer_label($row)) ?></a><small><?= e((string) $row['follow_up_date']) ?> · <?= e((string) $row['subject']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($quoteFollows !== []): ?>
            <h3 class="h6 mt-3">Quotes to follow up</h3>
            <ul class="sf-feed">
                <?php foreach ($quoteFollows as $row): ?>
                    <li><a href="<?= e(url('/quotes/' . $row['id'])) ?>"><?= e((string) $row['quote_number']) ?></a><small><?= e(customer_label($row)) ?> · <?= e(money((string) $row['total'])) ?> · <?= e((string) ($row['salesperson'] ?? '')) ?></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<div class="row g-3 sf-stats">
    <div class="col-6 col-lg-3">
        <article class="sf-stat">
            <span class="sf-stat-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Customers</p>
            <p class="sf-stat-value"><?= e((string) $counts['customers']) ?></p>
            <p class="sf-stat-hint">Active accounts</p>
        </article>
    </div>
    <div class="col-6 col-lg-3">
        <article class="sf-stat">
            <span class="sf-stat-icon"><i class="fa-solid fa-box" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Products</p>
            <p class="sf-stat-value"><?= e((string) $counts['products']) ?></p>
            <p class="sf-stat-hint">Active catalogue rows</p>
        </article>
    </div>
    <div class="col-6 col-lg-3">
        <article class="sf-stat">
            <span class="sf-stat-icon"><i class="fa-solid fa-truck" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Suppliers</p>
            <p class="sf-stat-value"><?= e((string) $counts['suppliers']) ?></p>
            <p class="sf-stat-hint">Active suppliers</p>
        </article>
    </div>
    <div class="col-6 col-lg-3">
        <article class="sf-stat">
            <span class="sf-stat-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Pricing levels</p>
            <p class="sf-stat-value"><?= e((string) $counts['levels']) ?></p>
            <p class="sf-stat-hint">Active markup bands</p>
        </article>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <section class="sf-panel h-100">
            <div class="sf-panel-head"><h2>Recent customers</h2></div>
            <?php if ($recentCustomers === []): ?>
                <div class="sf-empty"><p>No customers yet, or your role cannot view them.</p></div>
            <?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($recentCustomers as $customer): ?>
                        <li>
                            <a href="<?= e(url('/customers/' . $customer['id'])) ?>"><?= e(customer_label($customer)) ?></a>
                            <small><?= e(format_date((string) $customer['created_at'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="sf-panel h-100">
            <div class="sf-panel-head"><h2>Recent activity</h2></div>
            <?php if ($recentActivities === []): ?>
                <div class="sf-empty"><p>No CRM activity recorded yet.</p></div>
            <?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($recentActivities as $activity): ?>
                        <li>
                            <a href="<?= e(url('/customers/' . $activity['customer_id'])) ?>"><?= e((string) $activity['subject']) ?></a>
                            <small><?= e(customer_label($activity)) ?> · <?= e(format_date((string) $activity['activity_date'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-12 col-xl-4">
        <section class="sf-panel h-100">
            <div class="sf-panel-head"><h2>Cost changes</h2></div>
            <?php if ($priceChanges === []): ?>
                <div class="sf-empty"><p>No product cost has changed since the catalogue was loaded.</p></div>
            <?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($priceChanges as $change): ?>
                        <li>
                            <a href="<?= e(url('/products/' . $change['product_id'])) ?>"><?= e((string) $change['product_name']) ?></a>
                            <small><?= e(money((string) $change['old_cost'])) ?> → <?= e(money((string) $change['new_cost'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php if ($sales !== null || $pipeline !== null): ?>
<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Sales</h2></div>
    <p class="px-3 mb-0 sf-muted">These are quotation values, not paid revenue. An accepted quote is not an invoice.</p>
    <div class="row g-3 p-3">
        <?php if ($pipeline !== null): ?>
            <div class="col-6 col-lg-3"><p class="sf-kicker">Open opportunities</p><p class="sf-stat-value"><?= e((string) $pipeline['open_count']) ?></p><p class="sf-muted mb-0"><?= e(money((string) $pipeline['open_value'])) ?> estimated</p></div>
        <?php endif; ?>
        <?php if ($sales !== null): ?>
            <?php
            $decided = (int) $sales['decided_accepted'] + (int) $sales['declined'];
            $rate = $decided > 0 ? number_format(((int) $sales['decided_accepted'] / $decided) * 100, 0) : null;
            ?>
            <div class="col-6 col-lg-3"><p class="sf-kicker">Draft quotes</p><p class="sf-stat-value"><?= e((string) $sales['drafts']) ?></p></div>
            <div class="col-6 col-lg-3"><p class="sf-kicker">Awaiting response</p><p class="sf-stat-value"><?= e((string) $sales['awaiting']) ?></p></div>
            <div class="col-6 col-lg-3"><p class="sf-kicker">Accepted, not converted</p><p class="sf-stat-value"><?= e((string) $sales['accepted']) ?></p></div>
            <div class="col-6 col-lg-3"><p class="sf-kicker">Expiring soon</p><p class="sf-stat-value"><?= e((string) $sales['expiring']) ?></p></div>
            <div class="col-6 col-lg-3"><p class="sf-kicker">Quoted this month</p><p class="mb-0"><?= e(money((string) $sales['quoted_value'])) ?></p></div>
            <div class="col-6 col-lg-3"><p class="sf-kicker">Accepted this month</p><p class="mb-0"><?= e(money((string) $sales['accepted_value'])) ?></p></div>
            <div class="col-6 col-lg-3"><p class="sf-kicker">Conversion</p><p class="mb-0"><?= $rate === null ? 'No decided quotes this month' : e($rate . '% of decided quotes') ?></p></div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($operations !== null): ?>
<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Workshop desk</h2></div>
    <div class="row g-3 p-3">
        <?php foreach ([
            'Jobs today' => $operations['today'],
            'Overdue' => $operations['overdue'],
            'Awaiting artwork' => $operations['artwork'],
            'Awaiting approval' => $operations['approval'],
            'Ready for production' => $operations['ready'],
            'In production' => $operations['producing'],
            'Quality control' => $operations['qc'],
            'Installations today' => $operations['install_today'],
            'Installations this week' => $operations['install_week'],
            'Completed this month' => $operations['completed_month'],
        ] as $label => $value): ?>
            <div class="col-6 col-lg-3">
                <p class="sf-kicker"><?= e($label) ?></p>
                <p class="sf-stat-value"><?= e((string) $value) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($productionActivity !== []): ?>
        <div class="px-3 pb-3">
            <h3 class="h6">Recent production activity</h3>
            <?php foreach ($productionActivity as $event): ?>
                <p class="mb-1"><a href="<?= e(url('/jobs/' . $event['entity_id'])) ?>"><?= e((string) $event['job_number']) ?></a> · <?= e(str_replace('_', ' ', (string) $event['action'])) ?> · <?= e(format_datetime((string) $event['created_at'])) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (!empty($financeDesk) && can('debtors.view')): ?>
<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Finance</h2></div>
    <div class="row g-3 p-3">
        <div class="col-6 col-md-3"><p class="sf-kicker">Invoices this month</p><p class="sf-stat-value"><?= e((string) $financeDesk['month_count']) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Invoice value</p><p class="sf-stat-value"><?= e(money((string) $financeDesk['month_value'])) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Payments this month</p><p class="sf-stat-value"><?= e(money((string) $financeDesk['payments_month'])) ?></p></div>
        <div class="col-6 col-md-3"><p class="sf-kicker">Unallocated</p><p class="sf-stat-value"><?= e(money((string) $financeDesk['unallocated'])) ?></p></div>
    </div>
</section>
<?php endif; ?>
<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Later modules</h2></div>
    <div class="row g-3 p-3">
        <?php foreach ([
            'Outstanding invoices' => isset($financeDesk) ? 'Outstanding debtors ' . money((string) $financeDesk['outstanding']) . '. Overdue ' . money((string) $financeDesk['overdue']) . '.' : 'Outstanding invoices are calculated from issued documents.',
            'Low stock' => isset($lowStock) ? (string) $lowStock . ' tracked products are at or below minimum.' : 'Stock is available to roles that can view inventory.',
        ] as $label => $note): ?>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="sf-future">
                    <p class="sf-kicker"><?= e($label) ?></p>
                    <p class="mb-0"><?= e($note) ?></p>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</section>

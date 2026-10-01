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

<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Later modules</h2></div>
    <div class="row g-3 p-3">
        <?php foreach ([
            'Open quotes' => 'Quotations are not stored yet.',
            'Jobs in production' => 'Jobs are not stored yet.',
            'Outstanding invoices' => 'Invoices are not stored yet.',
            'Low stock' => 'Stock movements are not stored yet. A product can be flagged for tracking only.',
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

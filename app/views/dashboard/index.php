<?php require base_path('app/views/partials/flashes.php'); ?>

<div class="sf-page-head">
    <div>
        <h1>Pricing desk</h1>
        <p class="sf-muted mb-0">
            <?= e(company_name()) ?>.
            VAT <?= e($desk['vat']) ?>% ·
            Quote numbers <?= e($desk['prefix']) ?>-<?= e($desk['year']) ?>-0001 ·
            Valid <?= e($desk['validity']) ?> days ·
            <?= e($desk['currency']) ?>
        </p>
    </div>
</div>

<div class="row g-3 sf-stats">
    <div class="col-12 col-sm-6 col-lg-4">
        <article class="sf-stat">
            <span class="sf-stat-icon"><i class="fa-solid fa-box" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Products</p>
            <p class="sf-stat-value"><?= e((string) $summary['products']) ?></p>
            <p class="sf-stat-hint">Active catalogue items</p>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-lg-4">
        <article class="sf-stat">
            <span class="sf-stat-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Active customers</p>
            <p class="sf-stat-value"><?= e((string) $summary['customers']) ?></p>
            <p class="sf-stat-hint">Accounts that can be quoted</p>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-lg-4">
        <article class="sf-stat">
            <span class="sf-stat-icon"><i class="fa-solid fa-pen-ruler" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Draft quotes</p>
            <p class="sf-stat-value"><?= e((string) $summary['drafts']) ?></p>
            <p class="sf-stat-hint">Not sent yet</p>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-lg-4">
        <article class="sf-stat">
            <span class="sf-stat-icon"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Quotes this month</p>
            <p class="sf-stat-value"><?= e((string) $summary['quotes_this_month']) ?></p>
            <p class="sf-stat-hint"><?= e(date('F Y')) ?></p>
        </article>
    </div>
    <div class="col-12 col-sm-6 col-lg-4">
        <article class="sf-stat sf-stat-accent">
            <span class="sf-stat-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></span>
            <p class="sf-stat-label">Quotation value</p>
            <p class="sf-stat-value"><?= e(money($summary['month_value'])) ?></p>
            <p class="sf-stat-hint">This month, excluding declined and expired</p>
        </article>
    </div>
</div>

<section class="sf-panel" aria-labelledby="recent-heading">
    <div class="sf-panel-head">
        <h2 id="recent-heading">Recently created quotes</h2>
    </div>
    <?php if ($summary['recent'] === []): ?>
        <div class="sf-empty">
            <i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>
            <p>No quotations yet. Quote capture is the next module. The catalogue seed is already in the database.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table sf-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Number</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Date</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary['recent'] as $quote): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($quote['quote_number']) ?></td>
                            <td><?= e($quote['company_name'] ?? '—') ?></td>
                            <td><?= e(format_date((string) $quote['quote_date'])) ?></td>
                            <td><span class="badge <?= e(status_badge_class((string) $quote['status'])) ?>"><?= e(human_status((string) $quote['status'])) ?></span></td>
                            <td class="text-end"><?= e(money((string) $quote['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
/** Primary navigation. Coming-soon items are not links. */
$activeNav = $activeNav ?? '';
$groups = [
    ['label' => null, 'links' => [
        ['dashboard', 'Dashboard', '/', 'fa-gauge-high', 'dashboard.view'],
    ]],
    ['label' => 'CRM', 'links' => [
        ['customers', 'Customers', '/customers', 'fa-users', 'customers.view'],
        ['activities', 'Activities', '/activities', 'fa-comments', 'activities.view'],
        ['opportunities', 'Opportunities', '/opportunities', 'fa-bullseye', 'opportunities.view'],
    ]],
    ['label' => 'Sales', 'links' => [
        ['calculator', 'Calculator', '/calculator', 'fa-calculator', 'calculator.use'],
        ['quotes', 'Quotes', '/quotes', 'fa-file-invoice', 'quotes.view'],
    ]],
    ['label' => 'Operations', 'links' => [
        ['jobs', 'Jobs', '/jobs', 'fa-clipboard-list', 'jobs.view'],
        ['workshop', 'Production', '/jobs/workshop', 'fa-industry', 'production.view'],
        ['board', 'Production board', '/jobs/board', 'fa-table-columns', 'production.view'],
        ['design', 'Design', '/jobs/design', 'fa-pen-ruler', 'jobs.view'],
        ['schedule', 'Schedule', '/jobs/schedule', 'fa-calendar-day', 'production.view'],
        ['installations', 'Installations', '/jobs/installations', 'fa-location-dot', 'installations.view'],
        ['setup', 'Production setup', '/jobs/setup', 'fa-sliders', 'settings.manage'],
    ]],
    ['label' => 'Inventory', 'links' => [
        ['inventory', 'Inventory', '/inventory', 'fa-warehouse', 'inventory.view'],
        ['products', 'Products', '/products', 'fa-box', 'products.view'],
        ['offcuts', 'Offcuts', '/inventory/offcuts', 'fa-scissors', 'inventory.view'],
        ['movements', 'Stock movements', '/inventory/movements', 'fa-right-left', 'inventory.view'],
        ['counts', 'Stock counts', '/inventory/counts', 'fa-clipboard-check', 'inventory.view'],
        ['requirements', 'Material requirements', '/inventory/requirements', 'fa-list-check', 'inventory.view'],
        ['categories', 'Categories', '/categories', 'fa-tags', 'categories.manage'],
    ]],
    ['label' => 'Purchasing', 'links' => [
        ['requests', 'Purchase requests', '/purchasing/requests', 'fa-cart-plus', 'purchasing.view'],
        ['orders', 'Purchase orders', '/purchasing/orders', 'fa-file-contract', 'purchasing.view'],
        ['purchasing', 'Goods receiving', '/purchasing', 'fa-dolly', 'purchasing.view'],
        ['suppliers', 'Suppliers', '/suppliers', 'fa-truck', 'suppliers.view'],
    ]],
    ['label' => 'Finance', 'links' => [
        ['invoices', 'Invoices', '/invoices', 'fa-receipt', 'invoices.view'],
        ['payments', 'Payments', '/payments', 'fa-money-bill', 'payments.view'],
        ['credits', 'Credit notes', '/credit-notes', 'fa-file-circle-minus', 'credit_notes.view'],
        ['statements', 'Statements', '/finance/statements', 'fa-file-lines', 'statements.view'],
        ['debtors', 'Debtors', '/finance/debtors', 'fa-scale-balanced', 'debtors.view'],
        ['vat', 'VAT summary', '/finance/vat', 'fa-percent', 'finance.vat_report.view'],
    ]],
    ['label' => 'Administration', 'links' => [
        ['pricing', 'Pricing levels', '/pricing-levels', 'fa-layer-group', 'pricing.view'],
        ['users', 'Users', '/users', 'fa-user-gear', 'users.manage'],
        ['settings', 'Settings', '/settings', 'fa-gear', 'settings.manage'],
    ]],
];
?>
<div class="offcanvas-header sf-offcanvas-header d-lg-none">
    <span class="sf-brand-name">Menu</span>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#appNav" aria-label="Close menu"></button>
</div>
<div class="offcanvas-body sf-sidebar-body">
    <a class="sf-brand" href="<?= e(url('/')) ?>">
        <img src="<?= e(asset('assets/images/mark.svg')) ?>" alt="" width="36" height="36">
        <span>
            <strong>Sign-Forge</strong>
            <small>Management System</small>
        </span>
    </a>

    <?php foreach ($groups as $group): ?>
        <?php
        $visible = [];
        foreach ($group['links'] as $link) {
            $permission = $link[4];
            if ($permission !== null && !can($permission)) {
                continue;
            }
            $visible[] = $link;
        }
        if ($visible === []) {
            continue;
        }
        ?>
        <?php if ($group['label'] !== null): ?>
            <p class="sf-nav-label"><?= e($group['label']) ?></p>
        <?php endif; ?>
        <nav class="sf-nav" aria-label="<?= e($group['label'] ?? 'Main') ?>">
            <?php foreach ($visible as [$key, $label, $href, $icon]): ?>
                <?php if ($href === null): ?>
                    <span class="sf-nav-link is-disabled" aria-disabled="true">
                        <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i>
                        <span><?= e($label) ?></span>
                        <small>Soon</small>
                    </span>
                <?php else: ?>
                    <a class="<?= $key === $activeNav ? 'sf-nav-link active' : 'sf-nav-link' ?>"
                       href="<?= e(url($href)) ?>"
                       <?= $key === $activeNav ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i>
                        <span><?= e($label) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>
</div>

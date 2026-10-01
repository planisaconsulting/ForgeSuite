<?php
/** Primary navigation. Coming-soon items are not links. */
$activeNav = $activeNav ?? '';
$groups = [
    ['label' => null, 'links' => [
        ['dashboard', 'Dashboard', '/', 'fa-gauge-high', 'dashboard.view'],
        ['my-work', 'My work', '/work', 'fa-list-check', 'schedule.view'],
    ]],
    ['label' => 'CRM', 'links' => [
        ['customers', 'Customers', '/customers', 'fa-users', 'customers.view'],
        ['activities', 'Activities', '/activities', 'fa-comments', 'activities.view'],
        ['opportunities', 'Opportunities', '/opportunities', 'fa-bullseye', 'opportunities.view'],
        ['surveys', 'Site surveys', '/surveys', 'fa-ruler-combined', 'site_surveys.view'],
    ]],
    ['label' => 'Sales', 'links' => [
        ['calculator', 'Calculator', '/calculator', 'fa-calculator', 'calculator.use'],
        ['quotes', 'Quotes', '/quotes', 'fa-file-invoice', 'quotes.view'],
        ['templates', 'Signage templates', '/templates', 'fa-swatchbook', 'templates.view'],
    ]],
    ['label' => 'Operations', 'links' => [
        ['jobs', 'Jobs', '/jobs', 'fa-clipboard-list', 'jobs.view'],
        ['today', 'Today', '/today', 'fa-sun', 'schedule.view'],
        ['workshop', 'Production', '/jobs/workshop', 'fa-industry', 'production.view'],
        ['schedule', 'Schedule', '/schedule', 'fa-calendar-day', 'schedule.view'],
        ['capacity', 'Capacity', '/capacity', 'fa-chart-bar', 'capacity.view'],
        ['installations', 'Installations', '/installations/planner', 'fa-location-dot', 'installations.view'],
        ['design', 'Artwork', '/jobs/design', 'fa-pen-ruler', 'jobs.view'],
    ]],
    ['label' => 'Resources', 'links' => [
        ['resources', 'Staff and resources', '/resources', 'fa-people-group', 'resources.view'],
        ['maintenance', 'Maintenance', '/maintenance', 'fa-screwdriver-wrench', 'maintenance.view'],
        ['vehicles', 'Vehicles', '/vehicles', 'fa-truck', 'vehicles.view'],
        ['calendar', 'Calendar', '/resources/calendar', 'fa-calendar', 'resources.view'],
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
    ['label' => 'Automation', 'links' => [
        ['recipes', 'Recipes', '/recipes', 'fa-flask', 'recipes.view'],
        ['recurring', 'Recurring jobs', '/recurring', 'fa-rotate', 'recurring_jobs.view'],
        ['subcontracts', 'Subcontractors', '/subcontracts', 'fa-handshake', 'subcontractors.view'],
        ['recipe-test', 'Recipe test', '/recipes/test', 'fa-vial', 'recipes.test'],
        ['setup', 'Production templates', '/jobs/setup', 'fa-sliders', 'settings.manage'],
    ]],
    ['label' => 'Reports', 'links' => [
        ['report-executive', 'Executive', '/reports/executive', 'fa-chart-line', 'reports.executive'],
        ['report-sales', 'Sales', '/reports/sales', 'fa-chart-simple', 'reports.sales'],
        ['report-customers', 'Customers', '/reports/customers', 'fa-user-group', 'reports.sales'],
        ['report-jobs', 'Jobs', '/reports/jobs', 'fa-clipboard-list', 'reports.operations'],
        ['report-profitability', 'Profitability', '/reports/profitability', 'fa-chart-pie', 'reports.profitability'],
        ['report-production', 'Production', '/reports/production', 'fa-industry', 'reports.operations'],
        ['report-utilisation', 'Resource utilisation', '/reports/utilisation', 'fa-chart-bar', 'capacity.view'],
        ['report-capacity', 'Capacity and demand', '/reports/capacity-demand', 'fa-scale-balanced', 'capacity.view'],
        ['report-downtime', 'Downtime', '/reports/downtime', 'fa-screwdriver-wrench', 'maintenance.view'],
        ['report-late', 'Late jobs', '/reports/late-jobs', 'fa-clock', 'reports.operations'],
        ['report-waste', 'Materials and waste', '/reports/waste', 'fa-recycle', 'reports.operations'],
        ['report-inventory', 'Inventory', '/reports/inventory', 'fa-warehouse', 'reports.inventory'],
        ['report-purchasing', 'Purchasing', '/reports/purchasing', 'fa-truck', 'reports.inventory'],
        ['report-finance', 'Finance', '/reports/finance', 'fa-coins', 'reports.finance'],
        ['report-debtors', 'Debtors', '/reports/debtors', 'fa-scale-balanced', 'reports.finance'],
        ['documents', 'Documents', '/documents', 'fa-folder-open', 'dashboard.view'],
    ]],
    ['label' => 'Administration', 'links' => [
        ['pricing', 'Pricing levels', '/pricing-levels', 'fa-layer-group', 'pricing.view'],
        ['users', 'Users', '/users', 'fa-user-gear', 'users.manage'],
        ['roles', 'Roles', '/admin/roles', 'fa-user-shield', 'users.manage'],
        ['portal', 'Portal access', '/admin/portal', 'fa-id-card', 'portal.access_manage'],
        ['notifications', 'Notifications', '/notifications', 'fa-bell', null],
        ['automations', 'Automations', '/admin/automations', 'fa-robot', 'automations.view'],
        ['audit', 'Audit log', '/admin/audit', 'fa-list-check', 'audit.view'],
        ['backups', 'Backups', '/admin/backups', 'fa-database', 'system.backup'],
        ['health', 'System health', '/admin/health', 'fa-heart-pulse', 'system.health'],
        ['logs', 'Error log', '/admin/logs', 'fa-bug', 'system.logs'],
        ['targets', 'KPI targets', '/admin/targets', 'fa-bullseye', 'settings.manage'],
        ['export', 'Data export', '/admin/export', 'fa-file-export', 'reports.export'],
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
    <p class="sf-nav-label">Version <?= e(\App\Version::NUMBER) ?></p>
</div>

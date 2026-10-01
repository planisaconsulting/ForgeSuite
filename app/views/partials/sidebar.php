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
        ['production', 'Production', null, 'fa-industry', null],
    ]],
    ['label' => 'Inventory', 'links' => [
        ['products', 'Products', '/products', 'fa-box', 'products.view'],
        ['categories', 'Categories', '/categories', 'fa-tags', 'categories.manage'],
        ['suppliers', 'Suppliers', '/suppliers', 'fa-truck', 'suppliers.view'],
        ['stock', 'Stock', null, 'fa-warehouse', null],
    ]],
    ['label' => 'Finance', 'links' => [
        ['invoices', 'Invoices', null, 'fa-receipt', null],
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

<?php
/** Primary navigation. $activeNav is the current section key. */
$activeNav = $activeNav ?? '';
$items = [
    'Work' => [
        ['dashboard', 'Dashboard', '/', 'fa-gauge-high'],
        ['calculator', 'Calculator', '/calculator', 'fa-calculator'],
        ['quotes', 'Quotes', '/quotes', 'fa-file-invoice'],
        ['customers', 'Customers', '/customers', 'fa-users'],
    ],
    'Catalogue' => [
        ['products', 'Products', '/products', 'fa-box'],
        ['categories', 'Categories', '/categories', 'fa-tags'],
        ['pricing', 'Pricing levels', '/pricing-levels', 'fa-layer-group'],
    ],
    'Admin' => [
        ['settings', 'Settings', '/settings', 'fa-gear'],
    ],
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
            <small><?= e(company_name()) ?></small>
        </span>
    </a>

    <?php foreach ($items as $group => $links): ?>
        <p class="sf-nav-label"><?= e($group) ?></p>
        <nav class="sf-nav" aria-label="<?= e($group) ?>">
            <?php foreach ($links as [$key, $label, $href, $icon]): ?>
                <a class="<?= $key === $activeNav ? 'sf-nav-link active' : 'sf-nav-link' ?>"
                   href="<?= e(url($href)) ?>"
                   <?= $key === $activeNav ? 'aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>
</div>

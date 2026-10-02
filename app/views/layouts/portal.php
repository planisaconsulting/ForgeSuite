<?php
/** Customer portal shell. No internal navigation. */
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <?php require base_path('app/views/partials/head.php'); ?>
</head>
<body class="sf-app-body">
    <a class="sf-skip" href="#content">Skip to content</a>
    <header class="sf-topbar">
        <a class="sf-brand" href="<?= e(url('/portal')) ?>">
            <img src="<?= e(asset('assets/images/mark.svg')) ?>" alt="" width="36" height="36">
            <span>
                <strong><?= e(company_name()) ?></strong>
                <small>Customer portal</small>
            </span>
        </a>
        <?php if (!empty($_SESSION['portal_user_id'])): ?>
            <form method="post" action="<?= e(url('/portal/logout')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light btn-sm" type="submit">Sign out</button></form>
        <?php endif; ?>
    </header>
    <?php if (!empty($_SESSION['portal_user_id'])):
        $hubUser = (new \App\Services\PortalAuthService())->user();
        $hubAllows = new \App\Services\CustomerHubService();
        $hubLinks = [
            ['Dashboard', '/portal', true],
            ['Request a quote', '/portal/quote-request', $hubUser !== null && $hubAllows->allows($hubUser, 'portal.quotes.request')],
            ['Order signage', '/portal/orders', $hubUser !== null && $hubAllows->allows($hubUser, 'portal.orders.create')],
            ['Assets', '/portal/assets', $hubUser !== null && $hubAllows->allows($hubUser, 'portal.assets.view')],
            ['Artwork', '/portal/artwork-library', $hubUser !== null && ($hubAllows->allows($hubUser, 'portal.artwork.view') || $hubAllows->allows($hubUser, 'portal.artwork.approve'))],
            ['Statement', '/portal/statement', $hubUser !== null && $hubAllows->allows($hubUser, 'portal.finance.view')],
        ];
    ?>
        <nav class="px-3 py-2 d-flex flex-wrap gap-2" aria-label="Customer hub">
            <?php foreach ($hubLinks as [$label, $href, $show]): if (!$show) { continue; } ?>
                <a class="btn btn-outline-light" href="<?= e(url($href)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    <main class="sf-content" id="content">
        <?php require base_path('app/views/partials/flashes.php'); ?>
        <?= $content ?>
    </main>
    <script src="<?= e(asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>

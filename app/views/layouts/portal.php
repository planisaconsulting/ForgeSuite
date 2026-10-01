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
    <main class="sf-content" id="content">
        <?php require base_path('app/views/partials/flashes.php'); ?>
        <?= $content ?>
    </main>
    <script src="<?= e(asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>

<?php
/** Contractor portal shell. No staff navigation and no customer commercial links. */
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <?php require base_path('app/views/partials/head.php'); ?>
</head>
<body class="sf-app-body">
    <a class="sf-skip" href="#content">Skip to content</a>
    <header class="sf-topbar">
        <a class="sf-brand" href="<?= e(url('/contractor')) ?>">
            <img src="<?= e(asset('assets/images/mark.svg')) ?>" alt="" width="36" height="36">
            <span>
                <strong><?= e(company_name()) ?></strong>
                <small>Contractor portal</small>
            </span>
        </a>
        <?php if (!empty($_SESSION['contractor_user_id'])): ?>
            <form method="post" action="<?= e(url('/contractor/logout')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light btn-sm" type="submit">Sign out</button></form>
        <?php endif; ?>
    </header>
    <main id="content" class="container py-4">
        <?php require base_path('app/views/partials/flashes.php'); ?>
        <?= $content ?>
    </main>
</body>
</html>

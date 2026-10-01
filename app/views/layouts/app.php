<?php
/** Signed-in shell: sidebar on desktop, offcanvas menu on a phone or tablet. */
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <?php require base_path('app/views/partials/head.php'); ?>
</head>
<body class="sf-app-body">
    <a class="sf-skip" href="#content">Skip to content</a>
    <div class="sf-shell">
        <aside class="offcanvas-lg offcanvas-start sf-sidebar" tabindex="-1" id="appNav" aria-label="Main">
            <?php require base_path('app/views/partials/sidebar.php'); ?>
        </aside>
        <div class="sf-main">
            <?php require base_path('app/views/partials/topbar.php'); ?>
            <main class="sf-content" id="content">
                <?= $content ?>
            </main>
        </div>
    </div>
    <script src="<?= e(asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <?php foreach (($scripts ?? []) as $script): ?>
        <script src="<?= e(asset((string) $script)) ?>"></script>
    <?php endforeach; ?>
</body>
</html>

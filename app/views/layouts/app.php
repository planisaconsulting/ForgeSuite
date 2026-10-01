<?php
/** Signed-in shell: sidebar on desktop, offcanvas menu on a phone or tablet. */
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <?php require base_path('app/views/partials/head.php'); ?>
</head>
<body class="sf-app-body" data-app-version="<?= e(\App\Version::NUMBER) ?>" data-field="<?= can('mobile.use') ? '1' : '0' ?>" data-image-quality="<?= e(\App\Services\FieldSettings::get('image_compression_quality')) ?>" data-storage-mb="<?= e(\App\Services\FieldSettings::get('offline_storage_warning_mb')) ?>" data-sanity-mm="<?= e(\App\Services\FieldSettings::get('measurement_sanity_mm')) ?>">
    <a class="sf-skip" href="#content">Skip to content</a>
    <div class="sf-shell">
        <aside class="offcanvas-lg offcanvas-start sf-sidebar" tabindex="-1" id="appNav" aria-label="Main">
            <?php require base_path('app/views/partials/sidebar.php'); ?>
        </aside>
        <div class="sf-main">
            <?php require base_path('app/views/partials/topbar.php'); ?>
            <div id="sf-offline" class="sf-offline" hidden>OFFLINE. Changes will sync when connection returns.</div>
            <div id="sf-sync-count" class="sf-sync-count" hidden></div>
            <div id="sf-update" class="sf-update" hidden>Update available. <button type="button" id="sf-update-go">Refresh</button></div>
            <main class="sf-content" id="content">
                <?= $content ?>
            </main>
        </div>
    </div>
    <?php if (can('mobile.use')): ?>
        <nav class="sf-mobile-nav" aria-label="Mobile">
            <a href="<?= e(url('/m')) ?>">Home</a>
            <a href="<?= e(url('/m')) ?>">My work</a>
            <?php if (can('workshop.scan') || can('mobile.use')): ?><a href="<?= e(url('/m/scan')) ?>">Scan</a><?php endif; ?>
            <a href="<?= e(url('/notifications')) ?>">Alerts</a>
            <a href="<?= e(url('/m/more')) ?>">More</a>
        </nav>
    <?php endif; ?>
    <script src="<?= e(asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/field.js')) ?>"></script>
    <?php foreach (($scripts ?? []) as $script): ?>
        <script src="<?= e(asset((string) $script)) ?>"></script>
    <?php endforeach; ?>
</body>
</html>

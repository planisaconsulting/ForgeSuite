<?php
/** Supplier portal. No internal navigation and no other supplier's prices. */
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <?php require base_path('app/views/partials/head.php'); ?>
</head>
<body class="sf-app-body">
    <header class="sf-topbar">
        <span class="sf-brand">
            <strong><?= e(company_name()) ?></strong>
            <small>Supplier portal</small>
        </span>
    </header>
    <main class="sf-content" id="content">
        <?php require base_path('app/views/partials/flashes.php'); ?>
        <?= $content ?>
    </main>
</body>
</html>

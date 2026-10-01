<?php
/** Layout for sign-in and the forced password change. No sidebar. */
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <?php require base_path('app/views/partials/head.php'); ?>
</head>
<body class="sf-auth-body">
    <main class="sf-auth-main" id="content">
        <?= $content ?>
    </main>
    <script src="<?= e(asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>

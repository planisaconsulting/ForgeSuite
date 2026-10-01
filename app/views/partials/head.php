<?php
/** Shared document head. $title is set by the layout. */
$pageTitle = trim(($title ?? 'Sign-Forge') . ' · Sign-Forge');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0e1114">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= e(asset('assets/images/mark.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/vendor/fontawesome/css/all.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">

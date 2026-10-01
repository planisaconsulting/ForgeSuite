<div class="sf-auth-card sf-narrow-card">
    <p class="sf-kicker">404</p>
    <h1><?= e($title ?? 'Page not found') ?></h1>
    <p class="sf-muted"><?= e($message ?? 'That page is not part of Sign-Forge.') ?></p>
    <a class="btn btn-sf" href="<?= e(url(auth_user() ? '/' : '/login')) ?>">
        <?= auth_user() ? 'Back to dashboard' : 'Back to sign in' ?>
    </a>
</div>

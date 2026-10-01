<div class="sf-auth-card sf-narrow-card">
    <p class="sf-kicker">Blocked</p>
    <h1>Request blocked</h1>
    <p class="sf-muted"><?= e($message ?? 'The form could not be submitted.') ?></p>
    <a class="btn btn-sf" href="<?= e(url(auth_user() ? '/' : '/login')) ?>">
        <?= auth_user() ? 'Back to dashboard' : 'Back to sign in' ?>
    </a>
</div>

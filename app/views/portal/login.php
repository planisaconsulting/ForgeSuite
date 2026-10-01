<div class="sf-auth-card mx-auto" style="max-width:28rem">
    <h1>Sign in</h1>
    <p class="sf-muted">Use the email and password your salesperson set up. This is separate from staff sign-in.</p>
    <?php if (!empty($errors['_form'])): ?><div class="alert alert-danger"><?= e($errors['_form']) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/portal/login')) ?>" class="sf-form">
        <?= csrf_field() ?>
        <label class="form-label" for="email">Email</label>
        <input class="form-control form-control-lg mb-3" id="email" name="email" type="email" autocomplete="username" required value="<?= e((string) ($old['email'] ?? '')) ?>">
        <label class="form-label" for="password">Password</label>
        <input class="form-control form-control-lg mb-3" id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="btn btn-sf btn-lg w-100" type="submit">Sign in</button>
    </form>
</div>

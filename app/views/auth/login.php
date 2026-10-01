<div class="sf-auth-card">
    <img class="sf-auth-mark" src="<?= e(asset('assets/images/mark.svg')) ?>" alt="" width="48" height="48">
    <h1>Sign in</h1>
    <p class="sf-muted">Sign-Forge pricing desk. Use the staff account you were given.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger sf-alert" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/login')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control form-control-lg" type="email" id="email" name="email" value="<?= e($email ?? '') ?>" autocomplete="username" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">Password</label>
            <input class="form-control form-control-lg" type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <div class="sf-auth-options">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="show_password">
                <label class="form-check-label" for="show_password">Show password</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="remember" name="remember" value="1" <?= !empty($remember) ? 'checked' : '' ?>>
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
        </div>
        <button class="btn btn-sf w-100 btn-lg" type="submit">Sign in</button>
    </form>
</div>

<div class="sf-auth-card">
    <img class="sf-auth-mark" src="<?= e(asset('assets/images/mark.svg')) ?>" alt="" width="48" height="48">
    <h1>Change password</h1>
    <?php if (!empty($forced)): ?>
        <p class="sf-muted">This account is still using the starting password. Choose a new one before opening the pricing desk.</p>
    <?php else: ?>
        <p class="sf-muted">Use at least 10 characters. You will stay signed in.</p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger sf-alert" role="alert">
            <?php foreach ($errors as $message): ?>
                <div><?= e($message) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/account/password')) ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="current_password">Current password</label>
            <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label" for="new_password">New password</label>
            <input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password" required minlength="10">
        </div>
        <div class="mb-4">
            <label class="form-label" for="confirm_password">Confirm new password</label>
            <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required minlength="10">
        </div>
        <button class="btn btn-sf w-100" type="submit">Update password</button>
    </form>

    <div class="sf-auth-links">
        <?php if (empty($forced)): ?>
            <a href="<?= e(url('/')) ?>">Back to dashboard</a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-link sf-link-btn" type="submit">Sign out</button>
        </form>
    </div>
</div>

<h1>Contractor sign in</h1>
<p>You will see the work assigned to your company.</p>
<?php if ($errors !== []): ?><div class="alert alert-danger"><?= e((string) reset($errors)) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/contractor/login')) ?>" class="sf-panel p-3">
    <?= csrf_field() ?>
    <label class="form-label" for="email">Email</label>
    <input class="form-control mb-3" id="email" name="email" type="email" required>
    <label class="form-label" for="password">Password</label>
    <input class="form-control mb-3" id="password" name="password" type="password" required>
    <button class="btn btn-sf" type="submit">Sign in</button>
</form>

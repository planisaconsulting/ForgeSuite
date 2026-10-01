<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-auth-card">
    <h1>Workshop kiosk</h1>
    <p class="sf-muted">Identify yourself. A badge does not grant administrator access.</p>
    <form method="post" action="<?= e(url('/workshop/kiosk')) ?>" class="d-grid gap-2 mb-4">
        <?= csrf_field() ?>
        <label class="form-label" for="email">Email</label>
        <input class="form-control form-control-lg" id="email" name="email" type="email" required>
        <label class="form-label" for="pin">PIN</label>
        <input class="form-control form-control-lg" id="pin" name="pin" inputmode="numeric" autocomplete="off" required>
        <button class="btn btn-sf btn-lg" type="submit">Continue</button>
    </form>
    <form method="post" action="<?= e(url('/workshop/kiosk/badge')) ?>" class="d-grid gap-2">
        <?= csrf_field() ?>
        <label class="form-label" for="token">Badge</label>
        <input class="form-control form-control-lg" id="token" name="token" autocomplete="off">
        <button class="btn btn-outline-light btn-lg" type="submit">Use badge</button>
    </form>
</div>

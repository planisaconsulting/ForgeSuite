<div class="sf-page-head">
    <p class="sf-kicker">Sign-Forge service</p>
    <h1><?= $card === null ? 'Code not recognised' : e((string) $card['asset_number']) ?></h1>
</div>
<?php require base_path('app/views/partials/flashes.php'); ?>
<?php if ($error !== ''): ?><p><?= e($error) ?></p><?php endif; ?>
<?php if ($card !== null): ?>
    <section class="sf-panel mb-3"><div class="p-3">
        <p class="mb-1"><?= e((string) $card['name']) ?></p>
        <?php if ($card['site'] !== ''): ?><p class="sf-muted mb-0"><?= e((string) $card['site']) ?></p><?php endif; ?>
        <p class="sf-muted mb-0"><?= e((string) ($contact ?? 'Sign-Forge service')) ?></p>
    </div></section>
    <form class="sf-panel" method="post" action="<?= e(url('/service/asset/' . $token)) ?>">
        <?= csrf_field() ?>
        <div class="p-3 row g-2">
            <div class="col-12"><label class="form-label">Your name</label><input class="form-control" name="reported_by" required></div>
            <div class="col-12"><label class="form-label">Phone or email</label><input class="form-control" name="contact_detail" required></div>
            <div class="col-12"><label class="form-label">What is wrong</label><textarea class="form-control" name="description" required></textarea></div>
            <div class="col-12" style="position:absolute;left:-9999px"><label>Website<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
            <div class="col-12"><button class="btn btn-sf" type="submit">Send report</button></div>
        </div>
    </form>
<?php endif; ?>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <div>
        <h1><?= e((string) $dispatch['dispatch_number']) ?></h1>
        <p class="sf-muted mb-0"><?= e((string) $dispatch['job_number']) ?> · <?= e((string) $dispatch['status']) ?></p>
    </div>
</div>
<section class="sf-panel p-3 mb-3">
    <p class="mb-1">Expected <?= e((string) $progress['expected']) ?> · scanned <?= e((string) $progress['scanned']) ?> · missing <?= e((string) $progress['missing']) ?></p>
    <?php if (!$progress['complete']): ?><p class="mb-0"><strong>INCOMPLETE DISPATCH</strong></p><?php endif; ?>
</section>
<section class="sf-panel p-3 mb-3">
    <?php if (can('dispatch.create') && !$progress['complete']): ?>
        <form method="post" action="<?= e(url('/dispatch/' . $dispatch['id'] . '/pack')) ?>" class="mb-3">
            <?= csrf_field() ?>
            <button class="btn btn-outline-light btn-lg" type="submit">Pack expected items</button>
        </form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/dispatch/' . $dispatch['id'] . '/scan')) ?>" class="d-grid gap-2">
        <?= csrf_field() ?>
        <label class="form-label" for="code">Scan item</label>
        <input class="form-control form-control-lg" id="code" name="code" autofocus>
        <button class="btn btn-sf btn-lg" type="submit">Load</button>
    </form>
</section>
<section class="sf-panel mb-3">
    <ul class="sf-feed"><?php foreach ($items as $item): ?>
        <li><?= e((string) $item['description']) ?> <small><?= e((string) $item['status']) ?> · qty <?= e((string) $item['quantity']) ?></small></li>
    <?php endforeach; ?></ul>
</section>
<?php if (can('dispatch.complete')): ?>
    <form method="post" action="<?= e(url('/dispatch/' . $dispatch['id'] . '/complete')) ?>" class="mb-3">
        <?= csrf_field() ?>
        <button class="btn btn-sf btn-lg" type="submit">Mark dispatched</button>
    </form>
<?php endif; ?>
<?php if ($pod): ?>
    <section class="sf-panel p-3"><p class="mb-0">Signed by <?= e((string) $pod['recipient_name']) ?> at <?= e((string) $pod['delivery_datetime']) ?>.</p></section>
<?php elseif (can('delivery.signoff')): ?>
    <section class="sf-panel p-3" id="pod-form">
        <h2 class="h5">Proof of delivery</h2>
        <p class="sf-muted" id="pod-sync">Saved on this device until you submit. It is not received until this page confirms it.</p>
        <form method="post" action="<?= e(url('/dispatch/' . $dispatch['id'] . '/pod')) ?>" class="d-grid gap-2">
            <?= csrf_field() ?>
            <input class="form-control form-control-lg" name="recipient_name" placeholder="Received by" required>
            <input class="form-control" name="recipient_contact" placeholder="Contact">
            <p><?= e($statement) ?></p>
            <canvas id="sign-pad" width="320" height="140" style="border:1px solid #ccc;background:#fff;touch-action:none"></canvas>
            <input type="hidden" name="signature_png" id="signature_png">
            <input type="hidden" name="client_signed_at" id="client_signed_at">
            <button class="btn btn-sf btn-lg" type="submit">Sign and store</button>
        </form>
    </section>
    <script src="<?= e(asset('assets/js/signature.js')) ?>"></script>
<?php endif; ?>

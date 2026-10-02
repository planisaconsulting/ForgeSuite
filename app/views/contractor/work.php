<?php require base_path('app/views/partials/flashes.php'); ?>
<h1><?= e((string) $row['work_order_number']) ?></h1>
<p><?= e((string) $row['work_type']) ?> · <?= e((string) $row['status']) ?></p>
<section class="sf-panel p-3 mb-3">
    <h2 class="h5">Scope</h2>
    <p><?= e((string) $row['scope']) ?></p>
    <p>Quantity <?= e((string) $row['quantity']) ?></p>
    <p><?= e((string) ($row['site_address'] ?? '')) ?></p>
    <p><?= e((string) ($row['contact_name'] ?? '')) ?> <?= e((string) ($row['contact_phone'] ?? '')) ?></p>
    <?php if (!empty($row['contact_phone'])): ?><p><a href="tel:<?= e((string) $row['contact_phone']) ?>">Call site contact</a></p><?php endif; ?>
    <?php if ($map): ?><p><a href="<?= e($map) ?>">Directions</a></p><?php endif; ?>
    <?php if ($row['instructions']): ?><p><?= e((string) $row['instructions']) ?></p><?php endif; ?>
    <?php if ($row['agreed_cost'] !== null): ?><p>Agreed amount <?= e((string) $row['agreed_cost']) ?></p><?php endif; ?>
</section>
<?php if (in_array((string) $row['status'], ['SENT', 'VIEWED', 'REVIEW_REQUIRED'], true)): ?>
    <form method="post" action="<?= e(url('/contractor/work/' . $row['id'] . '/respond')) ?>" class="sf-panel p-3 mb-3">
        <?= csrf_field() ?>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-sf" name="action_name" value="ACCEPT" type="submit">Accept</button>
            <button class="btn btn-outline-light" name="action_name" value="DECLINE" type="submit">Decline</button>
            <button class="btn btn-outline-light" name="action_name" value="REQUEST_CHANGE" type="submit">Request a change</button>
        </div>
        <input class="form-control mt-2" name="message" placeholder="Note for Sign-Forge">
    </form>
<?php endif; ?>
<form method="post" action="<?= e(url('/contractor/work/' . $row['id'] . '/start')) ?>" class="mb-3"><?= csrf_field() ?><button class="btn btn-outline-light" type="submit">Start</button></form>
<form method="post" action="<?= e(url('/contractor/work/' . $row['id'] . '/submit')) ?>" class="sf-panel p-3">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-6"><input class="form-control" name="actual_hours" placeholder="Hours"></div>
        <div class="col-6"><input class="form-control" name="mileage_km" placeholder="Kilometres"></div>
        <div class="col-12"><button class="btn btn-sf" type="submit">Submit completion</button></div>
    </div>
    <p class="text-secondary mt-2 mb-0">Submitting does not close the job or raise a payment.</p>
</form>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Portal access</h1><p class="sf-muted">Customer logins are separate from staff accounts.</p></div>
<?php if (!empty($issued)): ?><div class="alert alert-warning">Copy this link. It will not be shown again.<br><code><?= e((string) $issued) ?></code></div><?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Find a customer</h2></div>
    <form class="p-3" method="get" action="<?= e(url('/admin/portal')) ?>">
        <input class="form-control mb-2" name="q" placeholder="Customer name">
        <button class="btn btn-outline-light" type="submit">Search</button>
    </form>
    <ul class="sf-feed"><?php foreach ($customers as $customer): ?><li><a href="<?= e(url('/admin/portal?customer_id=' . $customer['id'])) ?>"><?= e(customer_label($customer)) ?></a></li><?php endforeach; ?></ul>
</section>
<?php if ($customerId > 0): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>New portal user</h2></div>
    <form class="p-3" method="post" action="<?= e(url('/admin/portal')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="customer_id" value="<?= e((string) $customerId) ?>">
        <label class="form-label" for="email">Email</label>
        <input class="form-control mb-2" id="email" name="email" type="email" required>
        <label class="form-label" for="password">Password</label>
        <input class="form-control mb-2" id="password" name="password" type="text" autocomplete="off">
        <button class="btn btn-sf" type="submit">Create access</button>
    </form>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Existing access</h2></div>
    <?php foreach ($users as $portalUser): ?>
        <div class="p-3 border-bottom">
            <strong><?= e((string) $portalUser['email']) ?></strong>
            <small class="sf-muted"><?= (int) $portalUser['active'] === 1 ? 'Active' : 'Inactive' ?></small>
            <form class="mt-2" method="post" action="<?= e(url('/admin/portal/link')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="customer_id" value="<?= e((string) $customerId) ?>">
                <input type="hidden" name="portal_user_id" value="<?= e((string) $portalUser['id']) ?>">
                <input type="hidden" name="purpose" value="LOGIN">
                <button class="btn btn-sm btn-outline-light" type="submit">Issue sign-in link</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if ($users === []): ?><div class="sf-empty"><p>No portal users for this customer.</p></div><?php endif; ?>
</section>
<?php endif; ?>

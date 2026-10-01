<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1><?= e((string) $supplier['name']) ?></h1>
        <p class="sf-muted mb-0"><?= (int) $supplier['active'] === 1 ? 'Active' : 'Inactive' ?></p>
    </div>
    <?php if ($canManage): ?>
        <div class="sf-action-row">
            <a class="btn btn-sf" href="<?= e(url('/suppliers/' . $supplier['id'] . '/edit')) ?>">Edit</a>
            <form method="post" action="<?= e(url('/suppliers/' . $supplier['id'] . '/active')) ?>" onsubmit="return confirm('<?= (int) $supplier['active'] === 1 ? 'Deactivate this supplier? The record is kept.' : 'Activate this supplier?' ?>');">
                <?= csrf_field() ?>
                <input type="hidden" name="active" value="<?= (int) $supplier['active'] === 1 ? '0' : '1' ?>">
                <button class="btn btn-outline-light" type="submit"><?= (int) $supplier['active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
            </form>
        </div>
    <?php endif; ?>
</div>
<section class="sf-panel">
    <dl class="sf-dl">
        <div><dt>Contact</dt><dd><?= e((string) ($supplier['contact_name'] ?? '—')) ?></dd></div>
        <div><dt>Email</dt><dd><?= e((string) ($supplier['email'] ?? '—')) ?></dd></div>
        <div><dt>Phone</dt><dd><?= e((string) ($supplier['phone'] ?? '—')) ?></dd></div>
        <div><dt>Website</dt><dd><?= e((string) ($supplier['website'] ?? '—')) ?></dd></div>
        <div><dt>Account</dt><dd><?= e((string) ($supplier['account_number'] ?? '—')) ?></dd></div>
        <div><dt>Address</dt><dd><?= nl_text((string) ($supplier['address'] ?? '')) ?: '—' ?></dd></div>
        <div><dt>Notes</dt><dd><?= nl_text((string) ($supplier['notes'] ?? '')) ?: '—' ?></dd></div>
    </dl>
</section>
<?php if ($audit !== []): ?>
    <section class="sf-panel mt-3">
        <div class="sf-panel-head"><h2>Record history</h2></div>
        <?php require base_path('app/views/partials/audit_list.php'); ?>
    </section>
<?php endif; ?>

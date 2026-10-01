<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Roles</h1><p class="sf-muted mb-0">Management can read reports and audit history. It does not receive every operational edit.</p></div>
<div class="row g-3">
    <div class="col-lg-4">
        <section class="sf-panel">
            <ul class="sf-feed">
                <?php foreach ($roles as $role): ?>
                    <li><a href="<?= e(url('/admin/roles?role=' . $role['id'])) ?>"><?= e((string) $role['name']) ?></a><small><?= e((string) $role['code']) ?> · <?= e((string) $role['permissions']) ?> permissions</small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <div class="col-lg-8">
        <section class="sf-panel">
            <?php if ($permissions === []): ?>
                <div class="sf-empty"><p>Choose a role to see its permissions.</p></div>
            <?php else: ?>
                <ul class="sf-feed">
                    <?php foreach ($permissions as $permission): ?>
                        <li><strong><?= e((string) $permission['code']) ?></strong><small><?= e((string) $permission['module']) ?> · <?= e((string) $permission['name']) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

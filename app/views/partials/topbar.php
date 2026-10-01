<?php
/** Top bar. $title is the page heading. The signed-in user comes from the session. */
$user = auth_user();
?>
<header class="sf-topbar">
    <button class="btn sf-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appNav" aria-controls="appNav" aria-label="Open menu">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <p class="sf-topbar-title"><?= e($title ?? '') ?></p>
    <form class="sf-search" method="get" action="<?= e(url('/search')) ?>" role="search">
        <label class="visually-hidden" for="global-search">Search</label>
        <input class="form-control" id="global-search" type="search" name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>" placeholder="Search records">
    </form>
    <?php if ($user !== null): ?>
        <?php $unread = (new App\Services\NotificationService())->unread((int) $user['id'], (int) $user['role_id']); ?>
        <a class="btn sf-icon-btn" href="<?= e(url('/notifications')) ?>" aria-label="Notifications">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <?php if ($unread > 0): ?><span class="sf-badge"><?= e((string) $unread) ?></span><?php endif; ?>
        </a>
        <div class="dropdown">
            <button class="btn sf-icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Quick actions"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
            <ul class="dropdown-menu dropdown-menu-end sf-menu">
                <?php if (can('customers.manage')): ?><li><a class="dropdown-item" href="<?= e(url('/customers/new')) ?>">New customer</a></li><?php endif; ?>
                <?php if (can('quotes.manage')): ?><li><a class="dropdown-item" href="<?= e(url('/quotes/new')) ?>">New quote</a></li><?php endif; ?>
                <?php if (can('jobs.view')): ?><li><a class="dropdown-item" href="<?= e(url('/jobs')) ?>">Jobs</a></li><?php endif; ?>
                <?php if (can('materials.record_usage')): ?><li><a class="dropdown-item" href="<?= e(url('/jobs')) ?>">Record material</a></li><?php endif; ?>
                <?php if (can('purchasing.create')): ?><li><a class="dropdown-item" href="<?= e(url('/purchasing/orders')) ?>">New purchase order</a></li><?php endif; ?>
                <?php if (can('invoices.create')): ?><li><a class="dropdown-item" href="<?= e(url('/invoices')) ?>">New invoice</a></li><?php endif; ?>
                <?php if (can('payments.record')): ?><li><a class="dropdown-item" href="<?= e(url('/payments')) ?>">Record payment</a></li><?php endif; ?>
                <li><a class="dropdown-item" href="<?= e(url('/search')) ?>">Search</a></li>
            </ul>
        </div>
        <div class="dropdown">
            <button class="btn sf-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="sf-avatar" aria-hidden="true"><?= e(initials((string) $user['name'])) ?></span>
                <span class="d-none d-sm-inline text-start">
                    <strong><?= e($user['name']) ?></strong>
                    <small><?= e((string) ($user['role_name'] ?? '')) ?></small>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end sf-menu">
                <li><h6 class="dropdown-header"><?= e($user['email']) ?></h6></li>
                <li><a class="dropdown-item" href="<?= e(url('/account/password')) ?>">Change password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="dropdown-item" type="submit">Sign out</button>
                    </form>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</header>

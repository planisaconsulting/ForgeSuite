<?php
/** Top bar. $title is the page heading. The signed-in user comes from the session. */
$user = auth_user();
?>
<header class="sf-topbar">
    <button class="btn sf-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appNav" aria-controls="appNav" aria-label="Open menu">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <p class="sf-topbar-title"><?= e($title ?? '') ?></p>
    <?php if ($user !== null): ?>
        <div class="dropdown ms-auto">
            <button class="btn sf-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="sf-avatar" aria-hidden="true"><?= e(initials((string) $user['name'])) ?></span>
                <span class="d-none d-sm-inline text-start">
                    <strong><?= e($user['name']) ?></strong>
                    <small><?= e(role_label((string) $user['role'])) ?></small>
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

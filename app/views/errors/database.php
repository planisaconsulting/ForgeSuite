<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database unavailable · Sign-Forge</title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="sf-auth-body">
    <main class="sf-auth-main">
        <div class="sf-auth-card">
            <h1>Database unavailable</h1>
            <?php if (($reason ?? '') === 'denied'): ?>
                <p class="sf-muted">The database settings are already on the server. MySQL refused the database user from this website, so the password or the user link is not accepted. In the Xneelo control panel, open Manage MySQL for this domain, reset the Full Password, and update <code>app/config/config.local.php</code>. Then import <code>database/schema.sql</code> and <code>database/seed.sql</code>.</p>
            <?php elseif (($reason ?? '') === 'missing-config'): ?>
                <p class="sf-muted">Copy <code>app/config/config.example.php</code> to <code>app/config/config.local.php</code> and set the database name, user, and password. Then import <code>database/schema.sql</code> and <code>database/seed.sql</code>.</p>
            <?php else: ?>
                <p class="sf-muted">Sign-Forge cannot reach the MySQL server named in <code>app/config/config.local.php</code>. Check the database host, then import <code>database/schema.sql</code> and <code>database/seed.sql</code>.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

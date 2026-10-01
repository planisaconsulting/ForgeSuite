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
            <p class="sf-muted">Sign-Forge cannot reach MySQL. Copy <code>app/config/config.example.php</code> to <code>app/config/config.local.php</code> and set the database name, user, and password. Then import <code>database/schema.sql</code> and <code>database/seed.sql</code>.</p>
        </div>
    </main>
</body>
</html>

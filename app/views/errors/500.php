<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong · Sign-Forge</title>
    <style>
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: #0e1114; color: #e7ebf0; }
        main { max-width: 40rem; margin: 10vh auto; padding: 1.5rem; }
        h1 { font-size: 1.6rem; }
        p, pre { color: #a7b0ba; }
        pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <main>
        <h1>Something went wrong</h1>
        <p>The request was not completed. Quote the reference if you ask for help. Passwords and database details are not shown here.</p>
        <p>Reference: <?= htmlspecialchars((string) ($GLOBALS['sf_error_id'] ?? 'ERR-UNKNOWN'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
        <?php if (!empty($debug) && isset($e) && $e instanceof Throwable): ?>
            <pre><?= htmlspecialchars($e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
        <?php endif; ?>
    </main>
</body>
</html>

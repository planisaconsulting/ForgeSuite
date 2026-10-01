<?php foreach (pull_flashes() as $flash): ?>
    <?php
    $type = (string) ($flash['type'] ?? 'info');
    $class = match ($type) {
        'success' => 'success',
        'danger', 'error' => 'danger',
        'warning' => 'warning',
        default => 'info',
    };
    ?>
    <div class="alert alert-<?= e($class) ?> sf-alert" role="status">
        <?= e($flash['message'] ?? '') ?>
    </div>
<?php endforeach; ?>

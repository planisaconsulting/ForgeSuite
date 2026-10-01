<?php /** Expects $audit, a list of audit_log rows. */ ?>
<ul class="sf-feed">
    <?php foreach ($audit as $entry): ?>
        <li>
            <strong><?= e(ucfirst(str_replace('_', ' ', (string) $entry['action']))) ?></strong>
            <small>
                <?= e((string) ($entry['user_name'] ?? 'System')) ?> · <?= e(format_datetime((string) $entry['created_at'])) ?>
            </small>
            <?php
            $after = audit_decode(isset($entry['new_values']) ? (string) $entry['new_values'] : null);
            if ($after !== []):
            ?>
                <small class="sf-audit-json"><?php foreach ($after as $key => $val): ?><?= e((string) $key) ?>: <?= e(is_scalar($val) || $val === null ? (string) $val : json_encode($val)) ?> · <?php endforeach; ?></small>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

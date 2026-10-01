<section class="sf-panel">
    <div class="sf-panel-head"><h2>Activity</h2></div>
    <?php if ($timeline === []): ?><p class="p-3 mb-0">No activity yet.</p><?php endif; ?>
    <ol class="list-unstyled mb-0">
        <?php foreach ($timeline as $event): ?>
            <li class="p-3 border-bottom border-secondary">
                <strong><?= e(str_replace('_', ' ', (string) $event['label'])) ?></strong>
                <span class="sf-muted"><?= e(format_datetime((string) $event['created_at'])) ?></span>
                <?php if (!empty($event['notes'])): ?><p class="mb-0"><?= e((string) $event['notes']) ?></p><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

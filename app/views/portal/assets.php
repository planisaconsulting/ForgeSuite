<div class="sf-page-head"><h1>Your assets</h1>
    <p class="sf-muted">Installed signs on your account. Costs and internal notes are not shown here.</p>
</div>
<section class="sf-panel">
    <?php if ($rows === []): ?><p class="p-3">No assets are on this account yet.</p><?php endif; ?>
    <ul class="sf-feed"><?php foreach ($rows as $row): ?>
        <li><a href="<?= e(url('/portal/assets/' . $row['id'])) ?>"><?= e((string) $row['name']) ?></a><small><?= e((string) ($row['site_name'] ?? 'Site not set')) ?> · <?= e((string) ($row['installation_date'] ?? '')) ?> · <?= e((string) $row['status']) ?> · next <?= e((string) ($row['next_service_date'] ?? '—')) ?></small></li>
    <?php endforeach; ?></ul>
</section>

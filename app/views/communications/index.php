<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Communication centre</h1><p class="sf-muted mb-0">Email, WhatsApp preparation, calls, and notes. Delivery is only claimed when the provider accepts it.</p></div>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-5"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Customer, quote, message"></div>
    <div class="col-md-3">
        <select class="form-select" name="channel">
            <option value="">All channels</option>
            <?php foreach (['EMAIL','WHATSAPP','PHONE','PORTAL','SMS','IN_PERSON','OTHER'] as $item): ?>
                <option value="<?= e($item) ?>" <?= $channel === $item ? 'selected' : '' ?>><?= e($item) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-light" type="submit">Search</button></div>
</form>
<section class="sf-panel">
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>When</th><th>Channel</th><th>Status</th><th>Subject</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="4">No messages in this page.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) ($row['sent_at'] ?: $row['created_at'])) ?></td>
                    <td><?= e((string) $row['channel']) ?> <?= e((string) $row['direction']) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                    <td><?= e((string) $row['subject']) ?><div class="small sf-muted"><?= e((string) ($row['message_summary'] ?? '')) ?></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if (count($rows) === 40): ?><a class="btn btn-outline-light mt-3" href="<?= e(url('/communications?page=' . ($page + 1) . '&q=' . urlencode($q) . '&channel=' . urlencode($channel))) ?>">Next</a><?php endif; ?>

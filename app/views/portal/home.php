<div class="sf-page-head">
    <h1><?= e((string) $data['customer']) ?></h1>
    <p class="sf-muted mb-0">Quotes, jobs, artwork, and invoices for your account.</p>
</div>
<section class="sf-panel mb-3"><div class="p-3"><p class="mb-1">Account balance</p><strong><?= e(money((string) $data['balance'])) ?></strong>
    <div class="mt-3"><a class="btn btn-outline-light" href="<?= e(url('/portal/statement')) ?>">Download statement</a></div>
</div></section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Assets</h2></div>
    <?php if (($data['assets'] ?? []) === []): ?><div class="sf-empty"><p>No installed assets on this account yet.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($data['assets'] as $row): ?><li><a href="<?= e(url('/portal/assets/' . $row['id'])) ?>"><?= e((string) $row['name']) ?></a><small><?= e((string) ($row['site_name'] ?? '')) ?> · <?= e((string) $row['status']) ?></small></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <p class="px-3"><a href="<?= e(url('/portal/assets')) ?>">All assets</a></p>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Quotes</h2></div>
    <?php if ($data['quotes'] === []): ?><div class="sf-empty"><p>No quotations yet.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($data['quotes'] as $row): ?><li><a href="<?= e(url('/portal/quotes/' . $row['id'])) ?>"><?= e((string) $row['quote_number']) ?></a><small>Rev <?= e((string) $row['revision_number']) ?> · <?= e((string) $row['status']) ?> · <?= e(money((string) $row['total'])) ?></small></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Jobs</h2></div>
    <?php if ($data['jobs'] === []): ?><div class="sf-empty"><p>No jobs yet.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($data['jobs'] as $row): ?><li><a href="<?= e(url('/portal/jobs/' . $row['id'])) ?>"><?= e((string) $row['job_number']) ?></a><small><?= e((string) $row['title']) ?> · <?= e((string) $row['customer_status']) ?></small></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Artwork</h2></div>
    <?php if ($data['artworks'] === []): ?><div class="sf-empty"><p>No proofs are waiting.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($data['artworks'] as $row): ?><li><a href="<?= e(url('/portal/artwork/' . $row['id'])) ?>"><?= e((string) $row['title']) ?></a><small><?= e((string) $row['job_number']) ?> · Rev <?= e((string) $row['revision_number']) ?> · <?= e((string) $row['status']) ?></small></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Invoices</h2></div>
    <?php if ($data['invoices'] === []): ?><div class="sf-empty"><p>No invoices yet.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($data['invoices'] as $row): ?><li><a href="<?= e(url('/portal/invoices/' . $row['id'])) ?>"><?= e((string) $row['invoice_number']) ?></a><small>Balance <?= e(money((string) $row['balance_due'])) ?> · due <?= e((string) ($row['due_date'] ?? '—')) ?></small></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Delivery and completion</h2></div>
    <?php if (($data['signed_documents'] ?? []) === []): ?><div class="sf-empty"><p>No delivery notes or completion certificates yet.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($data['signed_documents'] as $row): ?><li><a href="<?= e(url('/portal/documents/' . $row['id'])) ?>"><?= e((string) ($row['document_number'] ?: $row['document_type'])) ?></a><small><?= e((string) $row['document_type']) ?> · <?= e((string) $row['generated_at']) ?></small></li><?php endforeach; ?></ul>
    <?php endif; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Documents</h2></div>
    <?php if ($data['documents'] === []): ?><div class="sf-empty"><p>No shared documents.</p></div><?php else: ?>
        <ul class="sf-feed"><?php foreach ($data['documents'] as $row): ?><li><a href="<?= e(url('/portal/files/' . $row['id'])) ?>"><?= e((string) $row['original_filename']) ?></a><small><?= e((string) $row['visibility']) ?></small></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <form class="p-3" method="post" action="<?= e(url('/portal/files')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="form-label" for="file">Upload a logo, artwork, reference, or purchase order</label>
        <input class="form-control mb-2" id="file" name="file" type="file" accept="image/*,.pdf,.txt" capture="environment">
        <select class="form-select mb-2" name="purpose"><option>LOGO</option><option>ARTWORK</option><option>REFERENCE</option><option>PURCHASE_ORDER</option><option>SIGNED</option><option>OTHER</option></select>
        <button class="btn btn-sf" type="submit">Upload</button>
    </form>
</section>

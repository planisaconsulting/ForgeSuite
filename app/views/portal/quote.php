<div class="sf-page-head">
    <p class="sf-kicker mb-1"><?= e((string) $quote['quote_number']) ?> · Revision <?= e((string) $quote['revision_number']) ?></p>
    <h1><?= e(money((string) $quote['total'])) ?></h1>
    <p class="sf-muted">VAT <?= e(money((string) $quote['vat_amount'])) ?> · Expires <?= e((string) ($quote['expiry_date'] ?? '—')) ?> · <?= e((string) $quote['status']) ?></p>
</div>
<section class="sf-panel mb-3">
    <?php foreach ($items as $item): ?>
        <article class="sf-quote-line">
            <header><strong><?= e((string) ($item['customer_description'] ?: $item['product_name_snapshot'])) ?></strong><span><?= e(money((string) $item['line_total'])) ?></span></header>
            <p class="sf-muted mb-0">Qty <?= e((string) $item['quantity']) ?></p>
        </article>
    <?php endforeach; ?>
</section>
<?php if (trim((string) ($quote['terms'] ?? '')) !== ''): ?>
    <section class="sf-panel mb-3"><div class="p-3"><?= nl2br(e((string) $quote['terms'])) ?></div></section>
<?php endif; ?>
<div class="sf-action-row mb-3">
    <a class="btn btn-outline-light" href="<?= e(url('/portal/quotes/' . $quote['id'] . '/pdf')) ?>">Download PDF</a>
    <a class="btn btn-outline-light" href="<?= e(url('/portal/quotes/' . $quote['id'] . '/whatsapp')) ?>">WhatsApp text</a>
</div>
<?php if (in_array((string) $quote['status'], ['READY', 'SENT', 'VIEWED'], true)): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Accept</h2></div>
    <form class="p-3" method="post" action="<?= e(url('/portal/quotes/' . $quote['id'] . '/accept')) ?>">
        <?= csrf_field() ?>
        <p><?= e((string) $statement) ?></p>
        <label class="form-label" for="customer_po">Your purchase order (optional)</label>
        <input class="form-control mb-2" id="customer_po" name="customer_po">
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="accept" value="1" id="accept">
            <label class="form-check-label" for="accept">I accept this quotation</label>
        </div>
        <button class="btn btn-sf btn-lg w-100" type="submit">Accept quotation</button>
    </form>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Decline</h2></div>
    <form class="p-3" method="post" action="<?= e(url('/portal/quotes/' . $quote['id'] . '/decline')) ?>">
        <?= csrf_field() ?>
        <label class="form-label" for="reason">Reason (optional)</label>
        <select class="form-select mb-2" id="reason" name="reason">
            <option value="">No reason</option>
            <option value="PRICE">Price</option>
            <option value="TIMING">Timing</option>
            <option value="SCOPE">Scope</option>
            <option value="ALTERNATIVE_SUPPLIER">Alternative supplier</option>
            <option value="PROJECT_CANCELLED">Project cancelled</option>
            <option value="OTHER">Other</option>
        </select>
        <button class="btn btn-outline-light w-100" type="submit">Decline quotation</button>
    </form>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Request changes</h2></div>
    <form class="p-3" method="post" action="<?= e(url('/portal/quotes/' . $quote['id'] . '/changes')) ?>">
        <?= csrf_field() ?>
        <textarea class="form-control mb-2" name="message" rows="3" placeholder="What should change?"></textarea>
        <button class="btn btn-outline-light w-100" type="submit">Send message</button>
    </form>
</section>
<?php endif; ?>
<p><a href="<?= e(url('/portal')) ?>">Back</a></p>

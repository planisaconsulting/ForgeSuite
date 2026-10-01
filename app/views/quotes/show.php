<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1"><?= e((string) $quote['quote_number']) ?> · Revision <?= e((string) ($revisionNumber ?? $quote['revision_number'])) ?></p>
        <h1><?= e(customer_label($quote)) ?></h1>
        <p class="mb-0"><?= e(enum_label(App\Domain\QuoteStatus::class, (string) $quote['status'])) ?>
            <?php if ($expiry === 'EXPIRED'): ?> · Expired<?php elseif ($expiry === 'EXPIRING'): ?> · Expiring soon<?php endif; ?>
            <?php if (!empty($historical)): ?> · Read only<?php endif; ?>
        </p>
    </div>
    <div class="sf-action-row">
        <?php if (empty($historical) && $canManage && (string) $quote['status'] === 'DRAFT'): ?>
            <a class="btn btn-sf" href="<?= e(url('/quotes/' . $quote['id'] . '/edit')) ?>">Edit</a>
        <?php endif; ?>
        <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/pdf' . (!empty($historical) ? '?revision=' . (int) $revisionNumber : ''))) ?>">PDF</a>
        <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/print' . (!empty($historical) ? '?revision=' . (int) $revisionNumber : ''))) ?>">Print</a>
        <?php if (can('site_surveys.create')): ?><a class="btn btn-outline-light" href="<?= e(url('/surveys/new?customer_id=' . $quote['customer_id'] . '&quote_id=' . $quote['id'])) ?>">Site survey</a><?php endif; ?>
        <?php if (empty($historical) && $canManage): ?>
            <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/duplicate')) ?>">Duplicate</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Items</h2></div>
            <?php foreach ($items as $item): ?>
                <article class="sf-quote-line">
                    <?php if (!empty($item['section_id'])): ?>
                        <?php foreach ($sections as $section): ?>
                            <?php if ((int) $section['id'] === (int) $item['section_id']): ?><p class="sf-kicker mb-1"><?= e((string) $section['title']) ?></p><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <header>
                        <strong><?= e((string) ($item['customer_description'] ?: $item['product_name_snapshot'])) ?></strong>
                        <span><?= e(money((string) $item['line_total'])) ?></span>
                    </header>
                    <p class="sf-muted mb-0"><?= e(quote_line_size($item)) ?> · Qty <?= e((string) $item['quantity']) ?>
                        <?php if ((int) ($item['is_optional'] ?? 0) === 1 && (int) ($item['include_optional'] ?? 0) !== 1): ?> · Optional, not in the total<?php endif; ?>
                    </p>
                    <?php if ($canCost): ?>
                        <p class="sf-muted mb-0">Cost <?= e(money((string) $item['total_cost'])) ?> · markup <?= e((string) $item['markup_percent_snapshot']) ?>% · GP <?= e(money((string) \App\Helpers\Decimal::sub((string) $item['line_total'], (string) $item['total_cost']))) ?></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
            <?php if ($items === []): ?><div class="sf-empty"><p>No lines on this revision.</p></div><?php endif; ?>
        </section>
        <section class="sf-panel mt-3">
            <dl class="sf-dl">
                <div><dt>Subtotal</dt><dd><?= e(money((string) $quote['subtotal'])) ?></dd></div>
                <div><dt>Discount</dt><dd><?= e(money((string) $quote['discount_amount'])) ?></dd></div>
                <div><dt>VAT <?= e((string) $quote['vat_rate']) ?>%</dt><dd><?= e(money((string) $quote['vat_amount'])) ?></dd></div>
                <div><dt>Total</dt><dd><?= e(money((string) $quote['total'])) ?></dd></div>
                <div><dt>Deposit required</dt><dd><?= e(money((string) $quote['deposit_amount'])) ?></dd></div>
            </dl>
        </section>
        <?php if (trim((string) ($quote['customer_notes'] ?? '')) !== ''): ?>
            <section class="sf-panel mt-3"><div class="sf-panel-head"><h2>Customer notes</h2></div><div class="p-3"><?= nl2br(e((string) $quote['customer_notes'])) ?></div></section>
        <?php endif; ?>
        <?php if (trim((string) ($quote['terms'] ?? '')) !== ''): ?>
            <section class="sf-panel mt-3"><div class="sf-panel-head"><h2>Terms</h2></div><div class="p-3"><?= nl2br(e((string) $quote['terms'])) ?></div></section>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Details</h2></div>
            <dl class="sf-dl">
                <div><dt>Date</dt><dd><?= e((string) $quote['quote_date']) ?></dd></div>
                <div><dt>Expiry</dt><dd><?= e((string) ($quote['expiry_date'] ?? '—')) ?></dd></div>
                <div><dt>Next follow-up</dt><dd><?= e((string) ($quote['next_follow_up_date'] ?? '—')) ?></dd></div>
                <div><dt>Salesperson</dt><dd><?= e((string) ($quote['salesperson_name'] ?? '—')) ?></dd></div>
                <div><dt>Contact</dt><dd><?= e((string) ($quote['contact_name'] ?? '—')) ?></dd></div>
                <?php if (!empty($quote['accepted_at'])): ?>
                    <div><dt>Accepted</dt><dd><?= e((string) $quote['accepted_by_name']) ?> · <?= e((string) $quote['acceptance_method']) ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if (!empty($canManage) && (string) $quote['status'] === 'SENT'): ?>
                <form class="p-3" method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/follow-up')) ?>">
                    <?= csrf_field() ?>
                    <label class="form-label" for="next_follow_up_date">Next follow-up</label>
                    <input class="form-control" id="next_follow_up_date" type="date" name="next_follow_up_date" value="<?= e((string) ($quote['next_follow_up_date'] ?? '')) ?>">
                    <button class="btn btn-outline-light mt-2" type="submit">Save follow-up</button>
                </form>
            <?php endif; ?>
        </section>
        <?php if ($canCost): ?>
            <section class="sf-panel mt-3">
                <div class="sf-panel-head"><h2>Internal costing</h2></div>
                <dl class="sf-dl">
                    <div><dt>Revenue ex VAT</dt><dd><?= e(money((string) $summary['revenue'])) ?></dd></div>
                    <div><dt>Cost</dt><dd><?= e(money((string) $summary['cost'])) ?></dd></div>
                    <div><dt>Gross profit</dt><dd><?= e(money((string) $summary['gross_profit'])) ?></dd></div>
                    <div><dt>Gross margin</dt><dd><?= $summary['gross_margin_percent'] === null ? '—' : e((string) $summary['gross_margin_percent'] . '%') ?></dd></div>
                </dl>
                <p class="px-3 sf-muted">Not shown on the customer PDF. Quoted value is not paid revenue.</p>
                <?php if (!empty($quote['internal_notes'])): ?>
                    <div class="p-3"><?= nl2br(e((string) $quote['internal_notes'])) ?></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php if (empty($historical)): ?>
            <section class="sf-panel mt-3">
                <div class="sf-panel-head"><h2>Actions</h2></div>
                <div class="p-3 d-grid gap-2">
                    <?php if ($canManage && (string) $quote['status'] === 'DRAFT'): ?>
                        <a class="btn btn-outline-light" href="<?= e(url('/quotes/' . $quote['id'] . '/refresh')) ?>">Refresh current prices</a>
                    <?php endif; ?>
                    <?php if ($canManage && (string) $quote['status'] === 'READY'): ?>
                        <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><input type="hidden" name="status" value="DRAFT"><button class="btn btn-outline-light w-100" type="submit">Back to draft</button></form>
                        <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><input type="hidden" name="status" value="SENT"><button class="btn btn-sf w-100" type="submit">Mark sent</button></form>
                    <?php endif; ?>
                    <?php if ($canManage && (string) $quote['status'] === 'DRAFT'): ?>
                        <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><input type="hidden" name="status" value="READY"><button class="btn btn-outline-light w-100" type="submit">Mark ready</button></form>
                        <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><input type="hidden" name="status" value="SENT"><button class="btn btn-sf w-100" type="submit">Mark sent</button></form>
                    <?php endif; ?>
                    <?php if ($canManage && in_array((string) $quote['status'], ['SENT', 'VIEWED'], true)): ?>
                        <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><input type="hidden" name="status" value="VIEWED"><button class="btn btn-outline-light w-100" type="submit">Mark viewed</button></form>
                    <?php endif; ?>
                    <?php if ($canManage && !in_array((string) $quote['status'], ['DRAFT', 'CONVERTED'], true) && $job === null): ?>
                        <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/revision')) ?>"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><input class="form-control mb-2" name="change_summary" placeholder="What changed?"><button class="btn btn-outline-light w-100" type="submit">Create revision</button></form>
                    <?php endif; ?>
                    <?php if ($canAccept && in_array((string) $quote['status'], ['READY', 'SENT', 'VIEWED'], true)): ?>
                        <a class="btn btn-sf" href="<?= e(url('/quotes/' . $quote['id'] . '/accept')) ?>">Accept</a>
                        <form method="post" action="<?= e(url('/quotes/' . $quote['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="version_number" value="<?= e((string) $quote['version_number']) ?>"><input type="hidden" name="status" value="DECLINED"><button class="btn btn-outline-light w-100" type="submit">Decline</button></form>
                    <?php endif; ?>
                    <?php if ($job !== null): ?>
                        <a class="btn btn-sf" href="<?= e(url('/jobs/' . $job['id'])) ?>">Job <?= e((string) $job['job_number']) ?></a>
                    <?php elseif ($canConvert && (string) $quote['status'] === 'ACCEPTED'): ?>
                        <a class="btn btn-sf" href="<?= e(url('/quotes/' . $quote['id'] . '/convert')) ?>">Convert to job</a>
                        <?php if (can('invoices.create')): ?>
                            <a class="btn btn-outline-light" href="<?= e(url('/invoices/new?quote_id=' . $quote['id'])) ?>">Create invoice</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
            <section class="sf-panel mt-3">
                <div class="sf-panel-head"><h2>Revisions</h2></div>
                <ul class="sf-feed">
                    <li><a href="<?= e(url('/quotes/' . $quote['id'])) ?>">Current revision <?= e((string) $quote['revision_number']) ?></a></li>
                    <?php foreach ($revisions as $revision): ?>
                        <li>
                            <a href="<?= e(url('/quotes/' . $quote['id'] . '/revisions/' . $revision['revision_number'])) ?>">Revision <?= e((string) $revision['revision_number']) ?></a>
                            <small><?= e((string) ($revision['change_summary'] ?? '')) ?> · <?= e((string) ($revision['user_name'] ?? '')) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="sf-panel mt-3">
                <div class="sf-panel-head"><h2>Status history</h2></div>
                <ul class="sf-feed">
                    <?php foreach ($history as $event): ?>
                        <li><?= e((string) ($event['old_status'] ?? '—')) ?> → <?= e((string) $event['new_status']) ?><small><?= e((string) ($event['user_name'] ?? '')) ?> <?= e((string) ($event['notes'] ?? '')) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</div>

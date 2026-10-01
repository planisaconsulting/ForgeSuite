<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1"><?= e((string) $opportunity['opportunity_number']) ?></p>
        <h1><?= e((string) $opportunity['title']) ?></h1>
        <p class="mb-0"><?= e(customer_label($opportunity)) ?> · <?= e(enum_label(App\Domain\OpportunityStatus::class, (string) $opportunity['status'])) ?></p>
    </div>
    <?php if ($canManage): ?>
        <div class="sf-action-row">
            <a class="btn btn-outline-light" href="<?= e(url('/opportunities/' . $opportunity['id'] . '/edit')) ?>">Edit</a>
            <?php if ($canQuote): ?><a class="btn btn-sf" href="<?= e(url('/quotes/new?customer_id=' . $opportunity['customer_id'] . '&opportunity_id=' . $opportunity['id'])) ?>">Create quote</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <section class="sf-panel">
            <dl class="sf-dl">
                <div><dt>Source</dt><dd><?= e(enum_label(App\Domain\OpportunitySource::class, (string) $opportunity['source'])) ?></dd></div>
                <div><dt>Estimated value</dt><dd><?= $opportunity['estimated_value'] === null ? '—' : e(money((string) $opportunity['estimated_value'])) ?></dd></div>
                <div><dt>Probability</dt><dd><?= e((string) ($opportunity['probability_percent'] ?? '—')) ?></dd></div>
                <div><dt>Salesperson</dt><dd><?= e((string) ($opportunity['salesperson_name'] ?? '—')) ?></dd></div>
                <div><dt>Contact</dt><dd><?= e((string) ($opportunity['contact_name'] ?? '—')) ?></dd></div>
                <div><dt>Expected close</dt><dd><?= e((string) ($opportunity['expected_close_date'] ?? '—')) ?></dd></div>
                <div><dt>Next follow-up</dt><dd><?= e((string) ($opportunity['next_follow_up_date'] ?? '—')) ?></dd></div>
            </dl>
            <div class="p-3"><?= nl2br(e((string) ($opportunity['description'] ?? ''))) ?></div>
        </section>
        <section class="sf-panel mt-3">
            <div class="sf-panel-head"><h2>Quotations</h2></div>
            <?php if ($quotes === []): ?><div class="sf-empty"><p>No quotations linked yet.</p></div><?php endif; ?>
            <ul class="sf-feed">
                <?php foreach ($quotes as $quote): ?>
                    <li><a href="<?= e(url('/quotes/' . $quote['id'])) ?>"><?= e((string) $quote['quote_number']) ?></a><small><?= e((string) $quote['status']) ?> · <?= e(money((string) $quote['total'])) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <div class="col-lg-5">
        <?php if ($canManage && !in_array((string) $opportunity['status'], ['WON', 'LOST'], true)): ?>
            <section class="sf-panel">
                <div class="sf-panel-head"><h2>Close</h2></div>
                <form method="post" action="<?= e(url('/opportunities/' . $opportunity['id'] . '/win')) ?>" class="p-3"><?= csrf_field() ?><button class="btn btn-sf" type="submit">Mark won</button></form>
                <form method="post" action="<?= e(url('/opportunities/' . $opportunity['id'] . '/lose')) ?>" class="p-3 pt-0">
                    <?= csrf_field() ?>
                    <label class="form-label">Lost reason</label>
                    <select class="form-select mb-2" name="lost_reason">
                        <?php foreach ($reasons as $reason): ?><option value="<?= e($reason->value) ?>"><?= e($reason->label()) ?></option><?php endforeach; ?>
                    </select>
                    <textarea class="form-control mb-2" name="lost_notes" rows="2" placeholder="Notes"></textarea>
                    <button class="btn btn-outline-light" type="submit">Mark lost</button>
                </form>
            </section>
        <?php endif; ?>
        <?php if ((string) $opportunity['status'] === 'LOST'): ?>
            <section class="sf-panel mt-3"><div class="p-3">Lost: <?= e(enum_label(App\Domain\LostReason::class, (string) ($opportunity['lost_reason'] ?? 'OTHER'))) ?><br><?= e((string) ($opportunity['lost_notes'] ?? '')) ?></div></section>
        <?php endif; ?>
        <section class="sf-panel mt-3">
            <div class="sf-panel-head"><h2>Activity</h2></div>
            <ul class="sf-feed">
                <?php foreach (array_slice($activities, 0, 8) as $activity): ?>
                    <li><?= e((string) $activity['subject']) ?><small><?= e((string) $activity['activity_date']) ?></small></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canActivity): ?>
                <form class="p-3" method="post" action="<?= e(url('/opportunities/' . $opportunity['id'] . '/activity')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="activity_type" value="NOTE">
                    <input type="hidden" name="activity_date" value="<?= e(date('Y-m-d')) ?>">
                    <input class="form-control mb-2" name="subject" placeholder="Note" required>
                    <button class="btn btn-outline-light" type="submit">Add activity</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>

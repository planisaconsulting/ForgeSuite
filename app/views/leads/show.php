<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1><?= e((string) $lead['lead_number']) ?></h1>
    <p class="sf-muted mb-0"><?= e((string) $lead['status']) ?> · First-touch source <?= e((string) $lead['source']) ?><?php if (!empty($lead['campaign_code'])): ?> · <?= e((string) $lead['campaign_code']) ?><?php endif; ?></p>
</div>
<div class="sf-lead-actions mb-3">
    <?php if (!empty($lead['phone'])): ?><a class="btn btn-sf" href="tel:<?= e((string) $lead['phone']) ?>">Call</a><?php endif; ?>
    <?php if (!empty($lead['email'])): ?><a class="btn btn-outline-light" href="mailto:<?= e((string) $lead['email']) ?>">Email</a><?php endif; ?>
    <a class="btn btn-outline-light" href="#whatsapp">WhatsApp</a>
    <a class="btn btn-outline-light" href="#follow">Follow up</a>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <section class="sf-panel p-3">
            <h2 class="h5"><?= e((string) $lead['name']) ?></h2>
            <p class="mb-1"><?= e((string) ($lead['company_name'] ?? '')) ?></p>
            <p class="mb-1"><?= e((string) ($lead['phone'] ?? '')) ?> <?php if (!empty($lead['phone_normalised'])): ?><span class="sf-muted">normalised +<?= e((string) $lead['phone_normalised']) ?></span><?php endif; ?></p>
            <p class="mb-1"><?= e((string) ($lead['email'] ?? '')) ?></p>
            <p class="mb-1">Interest: <?= e((string) ($lead['service_interest'] ?? '')) ?></p>
            <p class="mb-0"><?= nl2br(e((string) $lead['message'])) ?></p>
        </section>
        <?php if ($matches !== []): ?>
            <section class="sf-panel p-3 mt-3">
                <h2 class="h5">Possible existing records</h2>
                <p class="sf-muted">These are suggestions. Nothing is merged until you choose a customer.</p>
                <ul class="mb-0">
                    <?php foreach ($matches as $match): ?>
                        <li><?= e((string) $match['reason']) ?>: <?= e((string) $match['label']) ?> <?php if (!empty($match['company_name'])): ?>(<?= e((string) $match['company_name']) ?>)<?php endif; ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <section class="sf-panel p-3 mt-3">
            <h2 class="h5">Timeline</h2>
            <?php if ($timeline === []): ?><p class="sf-muted mb-0">No calls, emails, or notes yet.</p><?php endif; ?>
            <?php foreach ($timeline as $item): ?>
                <article class="border-bottom border-secondary py-2">
                    <strong><?= e((string) $item['channel']) ?></strong> <?= e((string) $item['status']) ?>
                    <div><?= e((string) ($item['message_summary'] ?? $item['subject'])) ?></div>
                    <div class="small sf-muted"><?= e((string) $item['sent_at']) ?></div>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
    <div class="col-lg-5">
        <?php if (can('leads.assign')): ?>
        <form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/leads/' . $lead['id'] . '/assign')) ?>">
            <?= csrf_field() ?>
            <label class="form-label">Assign</label>
            <select class="form-select mb-2" name="assigned_to">
                <?php foreach ($users as $user): if ((int) $user['active'] !== 1) continue; ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= (int) $lead['assigned_to'] === (int) $user['id'] ? 'selected' : '' ?>><?= e((string) $user['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-light" type="submit">Save assignment</button>
        </form>
        <?php endif; ?>
        <form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/leads/' . $lead['id'] . '/call')) ?>">
            <?= csrf_field() ?>
            <h2 class="h6">Log call</h2>
            <select class="form-select mb-2" name="direction"><option value="OUTBOUND">Outbound</option><option value="INBOUND">Inbound</option></select>
            <select class="form-select mb-2" name="outcome"><?php foreach ($outcomes as $outcome): ?><option value="<?= e((string) $outcome['code']) ?>"><?= e((string) $outcome['label']) ?></option><?php endforeach; ?></select>
            <textarea class="form-control mb-2" name="notes" rows="2" placeholder="Notes"></textarea>
            <button class="btn btn-outline-light" type="submit">Save call</button>
        </form>
        <form class="sf-panel p-3 mb-3" id="whatsapp" method="post" action="<?= e(url('/leads/' . $lead['id'] . '/whatsapp')) ?>">
            <?= csrf_field() ?>
            <h2 class="h6">WhatsApp</h2>
            <p class="small sf-muted">This opens WhatsApp. It is logged as prepared, not delivered.</p>
            <?php if ($templates !== []): ?>
                <select class="form-select mb-2" id="wa-template">
                    <?php foreach ($templates as $template): ?><option value="<?= e((string) $template['body']) ?>"><?= e((string) $template['name']) ?></option><?php endforeach; ?>
                </select>
            <?php endif; ?>
            <textarea class="form-control mb-2" name="message" id="wa-message" rows="3"><?= e((string) ($templates[0]['body'] ?? '')) ?></textarea>
            <button class="btn btn-sf" type="submit">Open WhatsApp</button>
        </form>
        <form class="sf-panel p-3 mb-3" id="follow" method="post" action="<?= e(url('/leads/' . $lead['id'] . '/follow-up')) ?>">
            <?= csrf_field() ?>
            <label class="form-label">Next follow-up</label>
            <input class="form-control mb-2" type="datetime-local" name="follow_up">
            <button class="btn btn-outline-light" type="submit">Set follow-up</button>
        </form>
        <form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/leads/' . $lead['id'] . '/note')) ?>">
            <?= csrf_field() ?>
            <textarea class="form-control mb-2" name="note" rows="2" placeholder="Internal note"></textarea>
            <button class="btn btn-outline-light" type="submit">Add note</button>
        </form>
        <?php if (can('leads.convert') && (string) $lead['status'] !== 'CONVERTED'): ?>
        <form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/leads/' . $lead['id'] . '/convert')) ?>">
            <?= csrf_field() ?>
            <h2 class="h6">Convert</h2>
            <select class="form-select mb-2" name="customer_mode"><option value="new">New customer</option><option value="existing">Existing customer</option></select>
            <input class="form-control mb-2" name="customer_id" placeholder="Existing customer id">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="confirm_new" value="1"> Create a new customer even if suggestions exist</label>
            <button class="btn btn-sf mt-2" type="submit">Convert to opportunity</button>
        </form>
        <?php endif; ?>
        <?php if (!empty($lead['opportunity_id'])): ?>
            <p><a href="<?= e(url('/opportunities/' . $lead['opportunity_id'])) ?>">Open opportunity</a></p>
        <?php endif; ?>
        <?php if (can('leads.mark_lost') && !in_array((string) $lead['status'], ['CONVERTED', 'LOST', 'SPAM'], true)): ?>
        <form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/leads/' . $lead['id'] . '/lost')) ?>">
            <?= csrf_field() ?>
            <input class="form-control mb-2" name="lost_reason" placeholder="Lost reason" required>
            <button class="btn btn-outline-light" type="submit">Mark lost</button>
        </form>
        <form method="post" action="<?= e(url('/leads/' . $lead['id'] . '/spam')) ?>"><?= csrf_field() ?><button class="btn btn-outline-light" type="submit">Mark spam</button></form>
        <?php endif; ?>
    </div>
</div>
<script>
document.getElementById('wa-template')?.addEventListener('change', function (event) {
    var box = document.getElementById('wa-message');
    if (box) box.value = event.target.value;
});
</script>

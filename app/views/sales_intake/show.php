<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1><?= e((string) $intake['intake_number']) ?></h1>
    <p class="text-secondary mb-0"><?= e((string) $intake['status']) ?> · <?= e((string) ($intake['next_action'] ?? '')) ?></p>
</div>
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <section class="sf-panel p-3 mb-3">
            <h2 class="h5">Original enquiry</h2>
            <p class="mb-1"><?= e((string) ($intake['sender_name'] ?? '')) ?> <?= e((string) ($intake['sender_email'] ?? '')) ?></p>
            <p class="mb-1">Received <?= e((string) $intake['received_at']) ?> from <?= e((string) $intake['source_type']) ?></p>
            <pre class="mb-0"><?= e((string) $intake['original_message']) ?></pre>
        </section>
        <section class="sf-panel p-3 mb-3">
            <h2 class="h5">What we know</h2>
            <p>Intent <?= e((string) $intake['intent_type']) ?>. Language <?= e((string) ($intake['language_code'] ?? '')) ?>. Customer match <?= e((string) ($intake['match_state'] ?? '')) ?>.</p>
            <p>Requested date <?= e((string) ($intake['requested_date'] ?? 'not stated')) ?>. A date here is a request, not a promise.</p>
            <p>Budget reference <?= e((string) ($intake['customer_budget'] ?? 'none')) ?>. Discount requested <?= e((string) ($intake['discount_requested_percent'] ?? 'none')) ?>. Nothing is applied from those words.</p>
            <?php if (!empty($intake['estimator_kind'])): ?>
                <p>Suggested tool <?= e((string) $intake['estimator_kind']) ?>.</p>
                <pre class="mb-0"><?= e((string) ($intake['estimator_payload'] ?? '')) ?></pre>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-12 col-lg-6">
        <section class="sf-panel p-3 mb-3">
            <h2 class="h5">Requirements</h2>
            <?php foreach ($intake['items'] as $item): ?>
                <p class="mb-1">Line <?= (int) $item['line_no'] ?>: qty <?= e((string) ($item['quantity'] ?? '')) ?>, <?= e((string) ($item['width_mm'] ?? '')) ?> × <?= e((string) ($item['height_mm'] ?? '')) ?> mm, <?= e((string) ($item['material_category'] ?? $item['material_text'] ?? '')) ?><?= (int) $item['is_approximate'] === 1 ? ' (approximate)' : '' ?></p>
            <?php endforeach; ?>
            <?php if ($intake['items'] === []): ?><p>No sign lines yet.</p><?php endif; ?>
            <h3 class="h6 mt-3">Missing</h3>
            <?php if ($intake['missing'] === []): ?><p>No blocking question from the rules.</p><?php endif; ?>
            <?php foreach ($intake['missing'] as $missing): ?>
                <p class="mb-1"><?= e($missing['question']) ?></p>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('/sales/intake/' . (int) $intake['id'] . '/requirements')) ?>" class="mt-2">
                <?= csrf_field() ?>
                <button class="btn btn-sf" type="submit">Confirm requirements</button>
            </form>
        </section>
        <section class="sf-panel p-3 mb-3">
            <h2 class="h5">Matches</h2>
            <?php foreach ($intake['matches'] as $match): ?>
                <p class="mb-1"><?= e((string) $match['match_state']) ?> · <?= e((string) $match['why_text']) ?></p>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('/sales/intake/' . (int) $intake['id'] . '/matches')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-outline-light" type="submit">Find products</button>
            </form>
            <p class="mt-3 mb-1">Readiness <?= e((string) $intake['readiness']) ?>. Stock on hand <?= e((string) ($intake['stock_on_hand'] ?? 'not loaded')) ?>.</p>
            <form method="post" action="<?= e(url('/sales/intake/' . (int) $intake['id'] . '/estimate')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-sf" type="submit">Create estimate</button>
            </form>
            <form method="post" action="<?= e(url('/sales/intake/' . (int) $intake['id'] . '/quote')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-outline-light" type="submit">Draft quote</button>
            </form>
        </section>
    </div>
</div>
<form method="post" action="<?= e(url('/sales/intake/' . (int) $intake['id'] . '/analyse')) ?>" class="mt-2">
    <?= csrf_field() ?>
    <button class="btn btn-outline-light" type="submit">Analyse</button>
</form>

<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1><?= e((string) $expense['expense_number']) ?></h1>
    <p class="sf-muted mb-0"><?= e((string) $expense['description']) ?> · <?= e((string) $expense['status']) ?> · reimbursement <?= e((string) $expense['reimbursement_status']) ?></p>
</div>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Cost record</h2></div>
    <div class="p-3">
        <p>Date <?= e((string) $expense['expense_date']) ?>. Amount <?= e((string) $expense['amount_inc_vat']) ?> <?= e((string) $expense['currency_code']) ?>.</p>
        <p>Category <?= e((string) $expense['category_name']) ?>. Paid by <?= e((string) ($expense['payment_method'] ?? 'not recorded')) ?>.</p>
        <?php if ($expense['merchant_name']): ?><p>Merchant <?= e((string) $expense['merchant_name']) ?>.</p><?php endif; ?>
        <?php if ($expense['vat_amount'] !== null): ?><p>VAT <?= e((string) $expense['vat_amount']) ?>, confirmed by a person.</p><?php endif; ?>
        <?php if ($expense['represented_by_type']): ?><p>Already represented by <?= e((string) $expense['represented_by_type']) ?>. It will not be costed again.</p><?php endif; ?>
        <?php if ($expense['duplicate_of_id']): ?><p>Possible duplicate of expense <?= e((string) $expense['duplicate_of_id']) ?>.</p><?php endif; ?>
        <?php if ($expense['reversed_at']): ?><p>Reversed <?= e((string) $expense['reversed_at']) ?>. The original approval remains.</p><?php endif; ?>
        <?php if ($expense['rejection_reason']): ?><p>Rejected: <?= e((string) $expense['rejection_reason']) ?></p><?php endif; ?>
    </div>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Allocation</h2></div>
    <?php if ($expense['allocations'] === []): ?>
        <div class="sf-empty"><p>No allocation yet.</p></div>
    <?php else: ?>
        <ul class="sf-feed">
            <?php foreach ($expense['allocations'] as $line): ?>
                <li><strong><?= e((string) $line['target_type']) ?></strong><small><?= e((string) $line['amount']) ?> · <?= e((string) $line['method']) ?> · <?= e((string) ($line['calculation_note'] ?? '')) ?></small></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<div class="d-flex flex-wrap gap-2">
    <?php if (in_array((string) $expense['status'], ['DRAFT', 'REVIEW_REQUIRED'], true)): ?>
        <form method="post" action="<?= e(url('/expenses/' . $expense['id'] . '/submit')) ?>"><?= csrf_field() ?><button class="btn btn-sf btn-lg" type="submit">Submit for approval</button></form>
    <?php endif; ?>
    <?php if ((string) $expense['status'] === 'SUBMITTED' && can('expenses.approve')): ?>
        <form method="post" action="<?= e(url('/expenses/' . $expense['id'] . '/approve')) ?>"><?= csrf_field() ?><button class="btn btn-sf btn-lg" type="submit">Approve</button></form>
        <form class="d-flex gap-2" method="post" action="<?= e(url('/expenses/' . $expense['id'] . '/reject')) ?>"><?= csrf_field() ?><input class="form-control" name="reason" placeholder="Reason" required><button class="btn btn-outline-light btn-lg" type="submit">Reject</button></form>
    <?php endif; ?>
</div>

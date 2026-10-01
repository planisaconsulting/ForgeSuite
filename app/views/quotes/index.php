<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Quotations</h1>
        <p class="sf-muted mb-0">Saved prices stay on the quotation. A later catalogue change does not rewrite them.</p>
    </div>
    <?php if ($canManage): ?>
        <a class="btn btn-sf" href="<?= e(url('/quotes/new')) ?>">New quotation</a>
    <?php endif; ?>
</div>
<form class="sf-panel sf-form mb-3" method="get" action="<?= e(url('/quotes')) ?>">
    <div class="row g-2">
        <div class="col-md-3"><input class="form-control" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Number, customer, or contact"></div>
        <div class="col-6 col-md-2">
            <select class="form-select" name="status">
                <option value="">Any status</option>
                <?php foreach (App\Domain\QuoteStatus::cases() as $status): ?>
                    <option value="<?= e($status->value) ?>" <?= $filters['status'] === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" name="assigned_to">
                <option value="">Any salesperson</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= (int) $filters['assigned_to'] === (int) $user['id'] ? 'selected' : '' ?>><?= e((string) $user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="from" value="<?= e((string) $filters['from']) ?>"></div>
        <div class="col-6 col-md-2"><input class="form-control" type="date" name="to" value="<?= e((string) $filters['to']) ?>"></div>
        <div class="col-md-3">
            <select class="form-select" name="sort">
                <?php foreach (['newest' => 'Newest', 'oldest' => 'Oldest', 'highest' => 'Highest value', 'lowest' => 'Lowest value', 'customer' => 'Customer'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 form-check d-flex align-items-center">
            <input class="form-check-input me-2" type="checkbox" name="expired" value="1" id="expired" <?= !empty($filters['expired']) ? 'checked' : '' ?>>
            <label for="expired">Expired only</label>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-light w-100" type="submit">Filter</button></div>
    </div>
</form>
<div class="sf-table-wrap">
    <table class="table sf-table">
        <thead>
            <tr>
                <th>Quote number</th><th>Rev</th><th>Date</th><th>Customer</th><th>Status</th><th>Salesperson</th><th class="text-end">Subtotal</th><th class="text-end">Total</th><th>Expiry</th><th>Updated</th><th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="11">No quotations match those filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <?php $expiry = App\Services\QuoteService::expiryState($row['expiry_date'] ?? null, (string) $row['status']); ?>
            <tr>
                <td><a href="<?= e(url('/quotes/' . $row['id'])) ?>"><?= e((string) $row['quote_number']) ?></a></td>
                <td><?= e((string) $row['revision_number']) ?></td>
                <td><?= e((string) $row['quote_date']) ?></td>
                <td><?= e(customer_label($row)) ?></td>
                <td><?= e(enum_label(App\Domain\QuoteStatus::class, (string) $row['status'])) ?><?= $expiry === 'EXPIRED' ? ' · Expired' : ($expiry === 'EXPIRING' ? ' · Expiring soon' : '') ?></td>
                <td><?= e((string) ($row['salesperson_name'] ?? '')) ?></td>
                <td class="text-end"><?= e(money((string) $row['subtotal'])) ?></td>
                <td class="text-end"><?= e(money((string) $row['total'])) ?></td>
                <td><?= e((string) ($row['expiry_date'] ?? '')) ?></td>
                <td><?= e(format_date(substr((string) ($row['updated_at'] ?? ''), 0, 10))) ?></td>
                <td class="text-end text-nowrap">
                    <a href="<?= e(url('/quotes/' . $row['id'])) ?>">View</a>
                    <?php if ((string) $row['status'] === 'DRAFT' && $canManage): ?>
                        · <a href="<?= e(url('/quotes/' . $row['id'] . '/edit')) ?>">Edit</a>
                    <?php endif; ?>
                    · <a href="<?= e(url('/quotes/' . $row['id'] . '/pdf')) ?>">PDF</a>
                    <?php if ($canManage): ?>
                        · <a href="<?= e(url('/quotes/' . $row['id'] . '/duplicate')) ?>">Duplicate</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

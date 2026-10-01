<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Opportunities</h1>
        <p class="sf-muted mb-0">Enquiries before or beside a quotation. A quote does not require one.</p>
    </div>
    <?php if ($canManage): ?><a class="btn btn-sf" href="<?= e(url('/opportunities/new')) ?>">New opportunity</a><?php endif; ?>
</div>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-4"><input class="form-control" name="q" value="<?= e($term) ?>" placeholder="Number, title, or customer"></div>
    <div class="col-md-3">
        <select class="form-select" name="status">
            <option value="all">Any status</option>
            <?php foreach (App\Domain\OpportunityStatus::cases() as $case): ?>
                <option value="<?= e($case->value) ?>" <?= strtoupper($status) === $case->value ? 'selected' : '' ?>><?= e($case->label()) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-light" type="submit">Search</button></div>
</form>
<div class="sf-table-wrap">
    <table class="table sf-table">
        <thead><tr><th>Number</th><th>Customer</th><th>Title</th><th>Status</th><th>Value</th><th>Follow-up</th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?><tr><td colspan="6">No opportunities yet.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(url('/opportunities/' . $row['id'])) ?>"><?= e((string) $row['opportunity_number']) ?></a></td>
                <td><?= e(customer_label($row)) ?></td>
                <td><?= e((string) $row['title']) ?></td>
                <td><?= e(enum_label(App\Domain\OpportunityStatus::class, (string) $row['status'])) ?></td>
                <td><?= $row['estimated_value'] === null ? '—' : e(money((string) $row['estimated_value'])) ?></td>
                <td><?= e((string) ($row['next_follow_up_date'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

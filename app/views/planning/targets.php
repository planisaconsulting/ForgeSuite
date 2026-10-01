<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Targets</h1></div>
<p class="sf-muted">Targets can be for the company, a team, or a salesperson. They are not a staff ranking.</p>
<?php if (can('targets.manage')): ?>
<form method="post" action="<?= e(url('/budgets/targets')) ?>" class="sf-panel p-3 mb-3 d-grid gap-2">
    <?= csrf_field() ?>
    <input class="form-control form-control-lg" name="name" placeholder="Target name" required>
    <select class="form-select" name="metric_code">
        <option>INVOICED_VALUE</option>
        <option>ACCEPTED_VALUE</option>
        <option>QUOTE_VALUE</option>
        <option>GROSS_PROFIT</option>
        <option>NEW_CUSTOMERS</option>
    </select>
    <select class="form-select" name="scope_type">
        <option>COMPANY</option>
        <option>TEAM</option>
        <option>USER</option>
    </select>
    <input class="form-control" name="period_start" type="date">
    <input class="form-control" name="period_end" type="date">
    <input class="form-control" name="target_amount" inputmode="decimal" placeholder="Amount">
    <button class="btn btn-sf btn-lg" type="submit">Save target</button>
</form>
<?php endif; ?>
<section class="sf-panel">
    <ul class="sf-feed">
        <?php foreach ($rows as $row): ?>
            <li><?= e((string) $row['name']) ?> <small><?= e((string) $row['metric_code']) ?> · <?= e((string) $row['scope_type']) ?> · <?= e((string) $row['target_amount']) ?></small></li>
        <?php endforeach; ?>
    </ul>
</section>

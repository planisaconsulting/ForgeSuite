<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Operational budget</h1></div>
<?php if (can('budgets.manage')): ?>
<form method="post" action="<?= e(url('/budgets')) ?>" class="sf-panel p-3 mb-3 d-grid gap-2">
    <?= csrf_field() ?>
    <input class="form-control form-control-lg" name="name" placeholder="Budget name" required>
    <input class="form-control" name="financial_year" value="<?= e(date('Y')) ?>">
    <input class="form-control" name="period" type="month" value="<?= e(date('Y-m')) ?>">
    <select class="form-select" name="metric_code">
        <option>INVOICED_VALUE</option>
        <option>CASH_COLLECTED</option>
        <option>GROSS_PROFIT</option>
        <option>MATERIAL_COST</option>
        <option>LABOUR_COST</option>
        <option>PURCHASE_VALUE</option>
        <option>MARKETING_SPEND</option>
    </select>
    <input class="form-control" name="target_amount" inputmode="decimal" placeholder="Target amount">
    <button class="btn btn-sf btn-lg" type="submit">Save draft line</button>
</form>
<?php endif; ?>
<section class="sf-panel">
    <ul class="sf-feed">
        <?php foreach ($rows as $row): ?>
            <li><a href="<?= e(url('/budgets/' . $row['id'] . '/actual')) ?>"><?= e((string) $row['name']) ?></a> <small><?= e((string) $row['financial_year']) ?> · <?= e((string) $row['status']) ?></small></li>
        <?php endforeach; ?>
    </ul>
</section>

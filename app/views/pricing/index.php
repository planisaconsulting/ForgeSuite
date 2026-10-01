<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <h1>Pricing levels</h1>
    <p class="sf-muted mb-0">Markup is cost times (1 + percent / 100). Gross margin is shown on the calculator and is a different number. These names can later become Retail, Trade, or Wholesale.</p>
</div>
<div class="table-responsive sf-panel">
    <table class="table sf-table sf-stack align-middle mb-0">
        <thead><tr><th>Code</th><th>Name</th><th>Markup</th><th>Margin at this markup</th><th>Status</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $markup = (string) $row['markup_percent'];
                $sell = App\Helpers\Decimal::mul('100', App\Helpers\Decimal::add('1', App\Helpers\Decimal::div($markup, '100')));
                $profit = App\Helpers\Decimal::sub($sell, '100');
                $margin = App\Helpers\Decimal::cmp($sell, '0') === 0
                    ? '—'
                    : App\Helpers\Decimal::round(App\Helpers\Decimal::mul(App\Helpers\Decimal::div($profit, $sell), '100'), 2) . '%';
                ?>
                <tr>
                    <td data-label="Code"><?= e((string) $row['code']) ?></td>
                    <td data-label="Name"><?= e((string) $row['name']) ?></td>
                    <td data-label="Markup"><?= e($markup) ?>%</td>
                    <td data-label="Margin"><?= e($margin) ?> <span class="sf-muted">on a R100 cost</span></td>
                    <td data-label="Status"><?= (int) $row['active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    <td data-label=""><?php if ($canManage): ?><a href="<?= e(url('/pricing-levels/' . $row['id'] . '/edit')) ?>">Edit</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="sf-muted mt-2">The margin column is informational. The stored rule is markup. Example: 65% markup on R100 sells at R165, and the margin is 39.39%.</p>

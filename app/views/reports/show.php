<?php
require base_path('app/views/partials/flashes.php');
$range = $report['range'];
$presets = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'this_week' => 'This week',
    'last_week' => 'Last week',
    'this_month' => 'This month',
    'last_month' => 'Last month',
    'this_quarter' => 'This quarter',
    'last_quarter' => 'Last quarter',
    'this_year' => 'This year',
    'last_year' => 'Last year',
    'custom' => 'Custom',
];
$chartMax = '0';
foreach ($report['charts'] as $chart) {
    $amount = (string) ($chart['value'] ?? '0');
    if (\App\Helpers\Decimal::cmp($amount, $chartMax) > 0) {
        $chartMax = $amount;
    }
}
$query = http_build_query([
    'preset' => $range['preset'],
    'from' => $range['from'],
    'to' => $range['to'],
    'compare' => (string) ($query['compare'] ?? 'previous'),
    'group' => (string) ($query['group'] ?? 'job'),
]);
?>
<?php if (!empty($board)): ?>
    <p class="sf-muted">This board refreshes every 60 seconds. <a href="<?= e(url('/reports/' . $report['code'])) ?>">Leave full screen</a></p>
    <script>setTimeout(function () { window.location.reload(); }, 60000);</script>
<?php endif; ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1><?= e((string) $report['title']) ?></h1>
        <p class="sf-muted mb-0"><?= e((string) $report['intro']) ?></p>
    </div>
    <div class="sf-action-row">
        <?php if ($canExport): ?>
            <a class="btn btn-outline-light" href="<?= e(url('/reports/' . $report['code'] . '.csv?' . $query)) ?>">Export CSV</a>
            <a class="btn btn-outline-light" href="<?= e(url('/reports/' . $report['code'] . '.pdf?' . $query)) ?>">PDF</a>
        <?php endif; ?>
        <a class="btn btn-outline-light" href="<?= e(url('/reports/' . $report['code'] . '?' . $query . '&board=1')) ?>">Board</a>
    </div>
</div>
<form class="sf-filters" method="get" action="<?= e(url('/reports/' . $report['code'])) ?>">
    <label class="visually-hidden" for="preset">Period</label>
    <select class="form-select" id="preset" name="preset">
        <?php foreach ($presets as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= $range['preset'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="from" value="<?= e($range['from']) ?>" aria-label="From">
    <input class="form-control" type="date" name="to" value="<?= e($range['to']) ?>" aria-label="To">
    <select class="form-select" name="compare" aria-label="Comparison">
        <option value="previous" <?= (($_GET['compare'] ?? '') !== 'year') ? 'selected' : '' ?>>Previous period</option>
        <option value="year" <?= (($_GET['compare'] ?? '') === 'year') ? 'selected' : '' ?>>Same dates last year</option>
    </select>
    <?php if (in_array($report['code'], ['jobs', 'profitability'], true)): ?>
        <select class="form-select" name="group" aria-label="Group">
            <?php foreach (['job' => 'Job', 'customer' => 'Customer', 'salesperson' => 'Salesperson', 'month' => 'Month'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= (($_GET['group'] ?? 'job') === $value) ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <button class="btn btn-sf" type="submit">Apply</button>
</form>
<p class="sf-muted"><?= e($range['label']) ?>: <?= e($range['from']) ?> to <?= e($range['to']) ?>. Comparison basis: <?= e($range['compare_label']) ?> (<?= e($range['compare_from']) ?> to <?= e($range['compare_to']) ?>).</p>
<div class="row g-3 sf-stats mb-3">
    <?php foreach ($report['kpis'] as $kpi): ?>
        <div class="col-6 col-lg-3">
            <a class="sf-stat sf-stat-link" href="<?= e(url((string) $kpi['href'])) ?>">
                <p class="sf-stat-label"><?= e((string) $kpi['label']) ?></p>
                <p class="sf-stat-value"><?= e((string) $kpi['value']) ?></p>
                <?php if (($kpi['hint'] ?? '') !== ''): ?><p class="sf-stat-hint"><?= e((string) $kpi['hint']) ?></p><?php endif; ?>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($report['charts'] !== []): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Chart</h2></div>
    <div class="p-3">
        <?php foreach ($report['charts'] as $chart): ?>
            <?php
            $width = '0';
            if (\App\Helpers\Decimal::cmp($chartMax, '0') !== 0) {
                $width = \App\Helpers\Decimal::round(\App\Helpers\Decimal::mul(\App\Helpers\Decimal::div((string) $chart['value'], $chartMax), '100'), 1);
            }
            ?>
            <div class="sf-bar-row">
                <span><?= e((string) $chart['label']) ?></span>
                <span class="sf-bar" aria-hidden="true"><span style="width: <?= e($width) ?>%"></span></span>
                <strong><?= e((string) $chart['value']) ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php foreach ($report['tables'] as $table): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2><?= e((string) $table['title']) ?></h2></div>
    <?php if ($table['rows'] === []): ?>
        <div class="sf-empty"><p>Nothing in this period.</p></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm sf-table mb-0">
                <thead><tr>
                    <?php foreach ($table['columns'] as $column): ?><th><?= e((string) $column['label']) ?></th><?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($table['rows'] as $row): ?>
                    <tr>
                        <?php foreach ($table['columns'] as $column): ?>
                            <td>
                                <?php if (!empty($column['href']) && !empty($row[$column['href']])): ?>
                                    <a href="<?= e(url((string) $row[$column['href']])) ?>"><?= e((string) ($row[$column['key']] ?? '')) ?></a>
                                <?php else: ?>
                                    <?= e((string) ($row[$column['key']] ?? '')) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endforeach; ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>How these numbers are calculated</h2></div>
    <ul class="mb-0 p-3">
        <?php foreach ($report['formulas'] as $formula): ?><li><?= e((string) $formula) ?></li><?php endforeach; ?>
    </ul>
</section>

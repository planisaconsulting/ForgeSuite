<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Quoted vs actual</h2></div>
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <tbody>
                <tr><th>Quoted revenue</th><td><?= e(money((string) $costing['quoted_revenue'])) ?></td><td class="sf-muted">Accepted quotation, not money received.</td></tr>
                <tr><th>Quoted cost</th><td><?= e(money((string) $costing['quoted_cost'])) ?></td><td></td></tr>
                <tr><th>Quoted gross profit</th><td><?= e(money((string) $costing['quoted_profit'])) ?></td><td><?= $costing['quoted_margin_percent'] === null ? '' : e((string) $costing['quoted_margin_percent'] . '% margin') ?></td></tr>
                <tr><th>Actual material</th><td><?= e(money((string) $costing['actual_material_cost'])) ?></td><td></td></tr>
                <tr><th>Actual labour</th><td><?= e(money((string) $costing['actual_labour_cost'])) ?></td><td></td></tr>
                <tr><th>Actual other</th><td><?= e(money((string) $costing['actual_other_cost'])) ?></td><td></td></tr>
                <tr><th>Actual total cost</th><td><?= e(money((string) $costing['actual_total_cost'])) ?></td><td></td></tr>
                <tr><th>Gross profit</th><td><?= e(money((string) $costing['actual_profit'])) ?></td><td>Quoted revenue − actual cost</td></tr>
                <tr><th>Gross margin</th><td><?= $costing['actual_margin_percent'] === null ? '—' : e((string) $costing['actual_margin_percent'] . '%') ?></td><td>Gross profit / quoted revenue</td></tr>
                <tr class="<?= !empty($costing['overrun']) ? 'sf-row-overdue' : '' ?>">
                    <th>Cost variance</th>
                    <td><?= e(money((string) $costing['cost_variance'])) ?></td>
                    <td><?= $costing['cost_variance_percent'] === null ? '' : e((string) $costing['cost_variance_percent'] . '%') ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
<?php if (!empty($costing['materials'])): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Material variance</h2></div>
    <div class="table-responsive">
        <table class="table sf-table mb-0">
            <thead><tr><th>Material</th><th>Estimated</th><th>Production</th><th>Waste</th><th>Actual</th><th>Variance</th></tr></thead>
            <tbody>
                <?php foreach ($costing['materials'] as $row): ?>
                    <tr>
                        <td><?= e((string) $row['name']) ?></td>
                        <td><?= e((string) $row['estimated']) ?> <?= e((string) $row['unit']) ?></td>
                        <td><?= e((string) $row['production']) ?></td>
                        <td><?= e((string) $row['waste']) ?></td>
                        <td><?= e((string) $row['actual']) ?></td>
                        <td><?= e((string) $row['variance']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Labour time</h2></div>
    <p class="p-3 mb-0">Estimated <?= e(number_format(((int) $costing['labour']['estimated_minutes']) / 60, 1)) ?> hours. Actual <?= e(number_format(((int) $costing['labour']['actual_minutes']) / 60, 1)) ?> hours. Variance <?= e(number_format(((int) $costing['labour']['variance_minutes']) / 60, 1)) ?> hours.</p>
</section>
<?php if (can('costing.edit')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Other cost</h2></div>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/costs')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-3">
                <select class="form-select" name="cost_type">
                    <?php foreach ($costTypes as $type): ?>
                        <option value="<?= e($type->value) ?>"><?= e($type->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><input class="form-control" name="description" placeholder="Description" required></div>
            <div class="col-md-2"><input class="form-control" name="quantity" value="1" inputmode="decimal"></div>
            <div class="col-md-3"><input class="form-control" name="unit_cost" placeholder="Unit cost" inputmode="decimal" required></div>
            <div class="col-md-4">
                <select class="form-select" name="supplier_id">
                    <option value="">No supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= e((string) $supplier['id']) ?>"><?= e((string) $supplier['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><input class="form-control" name="reference" placeholder="Reference"></div>
            <div class="col-md-4"><button class="btn btn-sf w-100" type="submit">Add cost</button></div>
        </div>
    </form>
</section>
<?php endif; ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Other costs</h2></div>
    <?php if ($others === []): ?><p class="p-3 mb-0">No courier, hire, or subcontract costs yet.</p><?php endif; ?>
    <?php foreach ($others as $row): ?>
        <p class="px-3"><?= e(enum_label(\App\Domain\OtherCostType::class, (string) $row['cost_type'])) ?> · <?= e((string) $row['description']) ?> · <?= e(money((string) $row['total_cost'])) ?></p>
    <?php endforeach; ?>
</section>

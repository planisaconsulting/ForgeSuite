<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <p class="sf-kicker mb-1">Rollouts</p>
        <h1>Projects</h1>
        <p class="sf-muted mb-0">A project groups sites and jobs. A one-off job does not need a project.</p>
    </div>
    <?php if ($canCreate): ?>
        <a class="btn btn-sf" href="<?= e(url('/projects/new')) ?>">New project</a>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Active</p><h2><?= (int) ($portfolio['active_count'] ?? 0) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">At risk</p><h2><?= (int) ($portfolio['at_risk_count'] ?? 0) ?></h2></section></div>
    <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Overdue</p><h2><?= (int) ($portfolio['overdue_count'] ?? 0) ?></h2></section></div>
    <?php if ($showMoney): ?>
        <div class="col-6 col-md-3"><section class="sf-panel"><p class="sf-kicker">Open commercial value</p><h2><?= e(money((string) ($portfolio['commercial_value'] ?? '0'))) ?></h2></section></div>
    <?php endif; ?>
</div>

<form class="sf-panel mb-3" method="get" action="<?= e(url('/projects')) ?>">
    <div class="row g-2">
        <div class="col-12 col-md-4"><input class="form-control" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Project, customer, site, or job"></div>
        <div class="col-6 col-md-2">
            <select class="form-select" name="status">
                <option value="">Status</option>
                <?php foreach (['DRAFT','PLANNING','ACTIVE','ON_HOLD','AT_RISK','COMPLETED','CANCELLED'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select class="form-select" name="health">
                <option value="">Health</option>
                <?php foreach (['ON_TRACK','AT_RISK','OVERDUE','BLOCKED','COMPLETE'] as $health): ?>
                    <option value="<?= e($health) ?>" <?= $filters['health'] === $health ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $health)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-2"><button class="btn btn-outline-light w-100" type="submit">Filter</button></div>
    </div>
</form>

<section class="sf-panel">
    <?php if ($rows === []): ?><div class="sf-empty"><p>No projects match.</p></div><?php endif; ?>
    <div class="table-responsive">
        <table class="table table-dark table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Project</th><th>Customer</th><th>Manager</th><th>Type</th><th>Sites</th><th>Progress</th><th>Health</th><th>Target</th>
                    <?php if ($showMoney): ?><th>Commercial value</th><th>Actual cost</th><?php endif; ?>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><a href="<?= e(url('/projects/' . $row['id'])) ?>"><?= e((string) $row['project_number']) ?></a><br><small><?= e((string) $row['name']) ?></small></td>
                        <td><?= e(customer_label($row)) ?></td>
                        <td><?= e((string) ($row['manager_name'] ?? '')) ?></td>
                        <td><?= e((string) ($row['type_name'] ?? '')) ?></td>
                        <td><?= (int) $row['site_count'] ?></td>
                        <td><?= $row['progress_percent_cached'] === null ? '—' : e((string) $row['progress_percent_cached']) . '%' ?></td>
                        <td><?= e(str_replace('_', ' ', (string) $row['project_health'])) ?></td>
                        <td><?= e((string) ($row['current_target_date'] ?? '')) ?></td>
                        <?php if ($showMoney): ?>
                            <td><?= e(money((string) ($row['commercial_value_cached'] ?? '0'))) ?></td>
                            <td><?= e(money((string) ($row['actual_cost_cached'] ?? '0'))) ?></td>
                        <?php endif; ?>
                        <td><?= e(str_replace('_', ' ', (string) $row['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($upcoming !== []): ?>
<section class="sf-panel mt-3">
    <div class="sf-panel-head"><h2>Upcoming milestones</h2></div>
    <ul class="sf-feed">
        <?php foreach (array_slice($upcoming, 0, 8) as $milestone): ?>
            <li><a href="<?= e(url('/projects/' . $milestone['project_id'] . '?tab=milestones')) ?>"><?= e((string) $milestone['project_number']) ?></a> · <?= e((string) $milestone['name']) ?> <small><?= e((string) $milestone['current_due_date']) ?></small></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

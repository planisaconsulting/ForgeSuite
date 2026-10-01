<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head"><h1>Automations</h1><p class="sf-muted mb-0">Rules create reminders, notifications, or drafts. They do not run custom code. Customer email stays off unless automation outbound email is explicitly enabled, and even then a rule does not send a message on its own.</p></div>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Rules</h2></div>
    <div class="table-responsive">
        <table class="table table-sm sf-table mb-0">
            <thead><tr><th>Name</th><th>When</th><th>Action</th><th>Active</th></tr></thead>
            <tbody>
            <?php foreach ($rules as $rule): ?>
                <tr>
                    <td><?= e((string) $rule['name']) ?></td>
                    <td><?= e((string) $rule['trigger_type']) ?></td>
                    <td><?= e((string) $rule['action_type']) ?> <?= e((string) $rule['action_config_json']) ?></td>
                    <td>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= e(url('/admin/automations')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="mode" value="toggle">
                                <input type="hidden" name="id" value="<?= e((string) $rule['id']) ?>">
                                <input type="hidden" name="active" value="<?= (int) $rule['active'] === 1 ? '0' : '1' ?>">
                                <button class="btn btn-sm btn-outline-light" type="submit"><?= (int) $rule['active'] === 1 ? 'Turn off' : 'Turn on' ?></button>
                            </form>
                        <?php else: ?>
                            <?= (int) $rule['active'] === 1 ? 'On' : 'Off' ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($canManage): ?>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/admin/automations')) ?>">
    <?= csrf_field() ?>
    <h2 class="h5">Follow-up when a quote is sent</h2>
    <div class="row g-2">
        <div class="col-md-6"><input class="form-control" name="name" placeholder="Rule name" required></div>
        <div class="col-md-3"><input class="form-control" name="days" type="number" min="1" max="60" value="3" aria-label="Days"></div>
        <div class="col-md-3"><button class="btn btn-sf" type="submit">Save rule</button></div>
    </div>
</form>
<form class="sf-panel p-3 mb-3" method="post" action="<?= e(url('/admin/automations')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="mode" value="schedule">
    <h2 class="h5">Scheduled report notice</h2>
    <div class="row g-2">
        <div class="col-md-4">
            <select class="form-select" name="report_type">
                <?php foreach (['executive','sales','finance','debtors','production','waste','inventory'] as $type): ?>
                    <option value="<?= e($type) ?>"><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-select" name="frequency">
                <option value="DAILY">Daily</option>
                <option value="WEEKLY" selected>Weekly</option>
                <option value="MONTHLY">Monthly</option>
            </select>
        </div>
        <div class="col-md-4"><button class="btn btn-outline-light" type="submit">Schedule for me</button></div>
    </div>
</form>
<?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Scheduled reports</h2></div>
    <ul class="sf-feed">
        <?php foreach ($reports as $row): ?>
            <li><strong><?= e((string) $row['report_type']) ?></strong><small><?= e((string) $row['frequency']) ?> · <?= e((string) $row['recipient_name']) ?> · next <?= e((string) ($row['next_run_at'] ?? '')) ?></small></li>
        <?php endforeach; ?>
    </ul>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Recent log</h2></div>
    <ul class="sf-feed">
        <?php foreach ($log as $row): ?>
            <li><strong><?= e((string) ($row['rule_name'] ?? 'Rule')) ?></strong><small><?= e((string) $row['status']) ?> · <?= e((string) $row['executed_at']) ?></small><p class="mb-0"><?= e((string) ($row['message'] ?? '')) ?></p></li>
        <?php endforeach; ?>
    </ul>
</section>

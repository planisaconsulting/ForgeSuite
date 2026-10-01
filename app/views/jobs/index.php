<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head d-flex flex-wrap justify-content-between gap-2">
    <div>
        <h1>Jobs</h1>
        <p class="sf-muted mb-0">Accepted work in production, installation, or collection.</p>
    </div>
</div>
<form class="sf-panel sf-form mb-3" method="get" action="<?= e(url('/jobs')) ?>">
    <div class="row g-2">
        <div class="col-12 col-md-4">
            <label class="form-label" for="q">Search</label>
            <input class="form-control" id="q" name="q" value="<?= e((string) $filters['q']) ?>" placeholder="Job, customer, description, PO">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">Any</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= e($status->value) ?>" <?= $filters['status'] === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="priority">Priority</label>
            <select class="form-select" id="priority" name="priority">
                <option value="">Any</option>
                <?php foreach (['LOW' => 'Low', 'NORMAL' => 'Normal', 'HIGH' => 'High', 'URGENT' => 'Urgent'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="assigned_to">Assigned</label>
            <select class="form-select" id="assigned_to" name="assigned_to">
                <option value="">Anyone</option>
                <?php foreach ($staff as $person): ?>
                    <?php if ((int) $person['active'] !== 1) { continue; } ?>
                    <option value="<?= e((string) $person['id']) ?>" <?= (int) $filters['assigned_to'] === (int) $person['id'] ? 'selected' : '' ?>><?= e((string) $person['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="team_id">Team</label>
            <select class="form-select" id="team_id" name="team_id">
                <option value="">Any</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= e((string) $team['id']) ?>" <?= (int) $filters['team_id'] === (int) $team['id'] ? 'selected' : '' ?>><?= e((string) $team['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="stage">Current stage</label>
            <select class="form-select" id="stage" name="stage">
                <option value="">Any</option>
                <?php foreach ($stages as $stage): ?>
                    <option value="<?= e((string) $stage['name']) ?>" <?= $filters['stage'] === (string) $stage['name'] ? 'selected' : '' ?>><?= e((string) $stage['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="from">Due from</label>
            <input class="form-control" type="date" id="from" name="from" value="<?= e((string) $filters['from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="to">Due to</label>
            <input class="form-control" type="date" id="to" name="to" value="<?= e((string) $filters['to']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="install_from">Install from</label>
            <input class="form-control" type="date" id="install_from" name="install_from" value="<?= e((string) $filters['install_from']) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="install_to">Install to</label>
            <input class="form-control" type="date" id="install_to" name="install_to" value="<?= e((string) $filters['install_to']) ?>">
        </div>
        <div class="col-6 col-md-1 d-flex align-items-end">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="overdue" name="overdue" <?= !empty($filters['overdue']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="overdue">Overdue</label>
            </div>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-sf" type="submit">Filter</button>
            <a class="btn btn-outline-light" href="<?= e(url('/jobs')) ?>">Clear</a>
        </div>
    </div>
</form>
<div class="sf-panel table-responsive">
    <table class="table sf-table mb-0">
        <thead>
            <tr>
                <th>Job</th>
                <th>Customer</th>
                <th>Title</th>
                <th>Status</th>
                <th>Stage</th>
                <th>Priority</th>
                <th>Assigned</th>
                <th>Target</th>
                <th>Installation</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="10">No jobs match this filter.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr class="<?= job_overdue($row) ? 'sf-row-overdue' : '' ?>">
                    <td><a href="<?= e(url('/jobs/' . $row['id'])) ?>"><?= e((string) $row['job_number']) ?></a></td>
                    <td><?= e(customer_label($row)) ?></td>
                    <td><?= e((string) $row['title']) ?></td>
                    <td><?= e(enum_label(\App\Domain\JobStatus::class, (string) $row['status'])) ?></td>
                    <td><?= e((string) ($row['current_stage'] ?? '—')) ?></td>
                    <td><?= priority_badge((string) $row['priority']) ?></td>
                    <td><?= e((string) ($row['assignee_name'] ?? '—')) ?></td>
                    <td><?= e((string) ($row['target_date'] ?? '—')) ?><?= job_overdue($row) ? ' <span class="sf-badge sf-badge-urgent">Overdue</span>' : '' ?></td>
                    <td><?= e((string) ($row['installation_date'] ?? '—')) ?></td>
                    <td><?= e(format_datetime((string) $row['updated_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

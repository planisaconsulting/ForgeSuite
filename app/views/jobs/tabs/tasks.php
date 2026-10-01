<?php if (can('production.update') || can('jobs.edit')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Add task</h2></div>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/tasks')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="title">Title</label>
                <input class="form-control" id="title" name="title" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="task_type">Type</label>
                <select class="form-select" id="task_type" name="task_type">
                    <?php foreach ($taskTypes as $type): ?>
                        <option value="<?= e($type->value) ?>"><?= e($type->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="job_item_id">Item</label>
                <select class="form-select" id="job_item_id" name="job_item_id">
                    <option value="">Whole job</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= e((string) $item['id']) ?>"><?= e((string) $item['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="assigned_to">Person</label>
                <select class="form-select" id="assigned_to" name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php foreach ($staff as $person): ?>
                        <?php if ((int) $person['active'] !== 1) { continue; } ?>
                        <option value="<?= e((string) $person['id']) ?>"><?= e((string) $person['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="assigned_team_id">Team</label>
                <select class="form-select" id="assigned_team_id" name="assigned_team_id">
                    <option value="">No team</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?= e((string) $team['id']) ?>"><?= e((string) $team['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="due_date">Due</label>
                <input class="form-control" type="date" id="due_date" name="due_date">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="estimated_minutes">Est. minutes</label>
                <input class="form-control" id="estimated_minutes" name="estimated_minutes" inputmode="numeric">
            </div>
        </div>
        <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Add task</button></div>
    </form>
</section>
<?php endif; ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Tasks</h2></div>
    <?php if ($tasks === []): ?><p class="p-3 mb-0">No tasks yet. Apply a production route when you know the stages. Tasks are not invented at conversion.</p><?php endif; ?>
    <?php foreach ($tasks as $task): ?>
        <article class="p-3 border-bottom border-secondary d-flex flex-wrap justify-content-between gap-2">
            <div>
                <strong><?= e((string) $task['title']) ?></strong>
                <span class="sf-badge"><?= e(enum_label(\App\Domain\TaskStatus::class, (string) $task['status'])) ?></span>
                <?= priority_badge((string) $task['priority']) ?>
                <p class="mb-0 sf-muted"><?= e(enum_label(\App\Domain\TaskType::class, (string) $task['task_type'])) ?>
                    <?php if (!empty($task['assignee_name'])): ?> · <?= e((string) $task['assignee_name']) ?><?php endif; ?>
                    <?php if (!empty($task['team_name'])): ?> · <?= e((string) $task['team_name']) ?><?php endif; ?>
                    <?php if (!empty($task['due_date'])): ?> · due <?= e((string) $task['due_date']) ?><?php endif; ?>
                </p>
            </div>
            <?php if (can('production.update')): ?>
                <form class="d-flex gap-2" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/tasks/' . $task['id'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="version_number" value="<?= e((string) $task['version_number']) ?>">
                    <select class="form-select" name="status">
                        <?php foreach (\App\Domain\TaskStatus::cases() as $status): ?>
                            <option value="<?= e($status->value) ?>" <?= (string) $task['status'] === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sf" type="submit">Save</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

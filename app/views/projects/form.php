<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <p class="sf-kicker mb-1">Projects</p>
    <h1>New project</h1>
</div>
<form class="sf-panel sf-form" method="post" action="<?= e(url('/projects')) ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="customer_id">Customer number</label>
            <input class="form-control" id="customer_id" name="customer_id" value="<?= e((string) ($old['customer_id'] ?? '')) ?>" required>
            <?php if (!empty($errors['customer_id'])): ?><div class="text-warning small"><?= e($errors['customer_id']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="name">Project name</label>
            <input class="form-control" id="name" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
            <?php if (!empty($errors['name'])): ?><div class="text-warning small"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?= e((string) ($old['description'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="project_type_id">Type</label>
            <select class="form-select" id="project_type_id" name="project_type_id">
                <option value="">Choose</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= (string) ($old['project_type_id'] ?? '') === (string) $type['id'] ? 'selected' : '' ?>><?= e((string) $type['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="template_id">Template</label>
            <select class="form-select" id="template_id" name="template_id">
                <option value="">None</option>
                <?php foreach ($templates as $template): ?>
                    <option value="<?= (int) $template['id'] ?>"><?= e((string) $template['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Milestones are copied now. Later template edits do not change this project.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="commercial_mode">Commercial agreement</label>
            <select class="form-select" id="commercial_mode" name="commercial_mode">
                <option value="PROJECT">One project contract</option>
                <option value="SITE_JOB" <?= ($old['commercial_mode'] ?? '') === 'SITE_JOB' ? 'selected' : '' ?>>Quote each site or job</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="target_date">Target date</label>
            <input class="form-control" type="date" id="target_date" name="target_date" value="<?= e((string) ($old['target_date'] ?? '')) ?>">
            <div class="form-text">This becomes the original target and is kept if the date moves later.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="start_date">Start date</label>
            <input class="form-control" type="date" id="start_date" name="start_date" value="<?= e((string) ($old['start_date'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="project_manager_user_id">Project manager</label>
            <select class="form-select" id="project_manager_user_id" name="project_manager_user_id">
                <option value="">Unassigned</option>
                <?php foreach ($staff as $person): ?>
                    <option value="<?= (int) $person['id'] ?>"><?= e((string) $person['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="source_opportunity_id" value="<?= (int) ($old['source_opportunity_id'] ?? 0) ?>">
        <input type="hidden" name="source_quote_id" value="<?= (int) ($old['source_quote_id'] ?? 0) ?>">
    </div>
    <button class="btn btn-sf mt-3" type="submit">Create project</button>
</form>

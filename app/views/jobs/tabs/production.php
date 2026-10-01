<?php if (can('production.update')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Apply production route</h2></div>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/route')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="template_id">Template</label>
                <select class="form-select" id="template_id" name="template_id" required>
                    <option value="">Choose</option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?= e((string) $template['id']) ?>"><?= e((string) $template['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="job_item_id">Item</label>
                <select class="form-select" id="job_item_id" name="job_item_id">
                    <option value="">Whole job</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= e((string) $item['id']) ?>"><?= e((string) $item['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="sf-muted mt-2 mb-0">Applying a template copies the stages onto this job. Editing them here does not change the template.</p>
        <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Apply production route</button></div>
    </form>
</section>
<?php endif; ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Stages</h2></div>
    <?php if ($stages === []): ?><p class="p-3 mb-0">No route yet.</p><?php endif; ?>
    <?php foreach ($stages as $stage): ?>
        <article class="p-3 border-bottom border-secondary d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div>
                <strong><?= e((string) $stage['stage_name']) ?></strong>
                <span class="sf-badge"><?= e(str_replace('_', ' ', (string) $stage['status'])) ?></span>
            </div>
            <?php if (can('production.update')): ?>
                <form class="d-flex flex-wrap gap-2" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/stages/' . $stage['id'])) ?>">
                    <?= csrf_field() ?>
                    <?php foreach (['IN_PROGRESS' => 'Start', 'BLOCKED' => 'Pause / block', 'COMPLETE' => 'Complete'] as $value => $label): ?>
                        <button class="btn btn-outline-light btn-lg" name="status" value="<?= e($value) ?>" type="submit"><?= e($label) ?></button>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Items</h2></div>
    <?php foreach ($items as $item): ?>
        <article class="p-3 border-bottom border-secondary d-flex flex-wrap justify-content-between gap-2">
            <div>
                <strong><?= e((string) $item['description']) ?></strong>
                <p class="mb-0 sf-muted">Qty <?= e((string) $item['quantity']) ?> · <?= e(enum_label(\App\Domain\JobItemStatus::class, (string) $item['production_status'])) ?></p>
            </div>
            <?php if (can('production.update')): ?>
                <form method="post" action="<?= e(url('/jobs/' . $job['id'] . '/items/' . $item['id'])) ?>" class="d-flex gap-2">
                    <?= csrf_field() ?>
                    <select class="form-select" name="status">
                        <?php foreach (\App\Domain\JobItemStatus::cases() as $status): ?>
                            <option value="<?= e($status->value) ?>" <?= (string) $item['production_status'] === $status->value ? 'selected' : '' ?>><?= e($status->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sf" type="submit">Save</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Quality control</h2></div>
    <?php if (can('production.update')): ?>
        <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/quality')) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="check_type">Check</label>
                    <select class="form-select" id="check_type" name="check_type">
                        <?php foreach ($definitions as $definition): ?>
                            <option value="<?= e((string) $definition['name']) ?>"><?= e((string) $definition['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Result</label>
                    <select class="form-select" id="status" name="status">
                        <?php foreach ($qcStatuses as $status): ?>
                            <option value="<?= e($status->value) ?>"><?= e($status->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="notes">Notes</label>
                    <input class="form-control" id="notes" name="notes">
                </div>
            </div>
            <p class="sf-muted mt-2 mb-0">Fail or rework required creates a rework task on this job.</p>
            <div class="sf-form-actions"><button class="btn btn-sf" type="submit">Record check</button></div>
        </form>
    <?php endif; ?>
    <?php foreach ($checks as $check): ?>
        <p class="px-3"><?= e((string) $check['check_type']) ?> · <?= e(enum_label(\App\Domain\QcStatus::class, (string) $check['status'])) ?><?php if (!empty($check['notes'])): ?> · <?= e((string) $check['notes']) ?><?php endif; ?></p>
    <?php endforeach; ?>
</section>

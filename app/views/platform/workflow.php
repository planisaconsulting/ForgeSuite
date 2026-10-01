<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <div>
        <h1><?= e($title) ?></h1>
        <p class="sf-muted mb-0">When something happens, if the conditions match, then the listed actions run. Actions cannot contain code.</p>
    </div>
</div>
<form method="post" action="<?= e(url($workflow === null ? '/workflows' : '/workflows/' . $workflow['id'])) ?>" class="sf-panel p-3 mb-3">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Name</label>
            <input class="form-control" id="name" name="name" value="<?= e((string) ($workflow['name'] ?? '')) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="trigger_event">When</label>
            <select class="form-select" id="trigger_event" name="trigger_event">
                <?php foreach ($triggers as $trigger): ?>
                    <option value="<?= e($trigger) ?>" <?= ($workflow['trigger_event'] ?? '') === $trigger ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $trigger)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="entity_type">Record</label>
            <select class="form-select" id="entity_type" name="entity_type">
                <?php foreach ($entities as $entity): ?>
                    <option value="<?= e($entity) ?>" <?= ($workflow['entity_type'] ?? '') === $entity ? 'selected' : '' ?>><?= e($entity) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <input class="form-control" id="description" name="description" value="<?= e((string) ($workflow['description'] ?? '')) ?>">
        </div>
    </div>
    <h2 class="h5 mt-4">If</h2>
    <p class="sf-muted">Conditions in the same group must all match. Different groups are alternatives.</p>
    <?php for ($i = 0; $i < 3; $i++): ?>
        <?php $condition = $conditions[$i] ?? []; ?>
        <div class="row g-2 mb-2">
            <div class="col-md-3"><input class="form-control" name="field_key[]" placeholder="Field, for example total" value="<?= e((string) ($condition['field_key'] ?? '')) ?>"></div>
            <div class="col-md-3">
                <select class="form-select" name="operator[]">
                    <?php foreach ($operators as $operator): ?>
                        <option value="<?= e($operator) ?>" <?= ($condition['operator'] ?? '') === $operator ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $operator)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" name="comparison_value[]" placeholder="Value" value="<?= e((string) ($condition['comparison_value'] ?? '')) ?>"></div>
            <div class="col-md-3"><input class="form-control" name="condition_group[]" placeholder="Group" value="<?= e((string) ($condition['condition_group'] ?? '1')) ?>"></div>
        </div>
    <?php endfor; ?>
    <h2 class="h5 mt-4">Then</h2>
    <?php for ($i = 0; $i < 2; $i++): ?>
        <?php $action = $storedActions[$i] ?? []; ?>
        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <select class="form-select" name="action_type[]">
                    <option value="">No action</option>
                    <?php foreach ($actions as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($action['action_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8"><input class="form-control" name="action_config[]" placeholder='{"approver_type":"MANAGEMENT","action_key":"LARGE_QUOTE","reason":"Large quote"}' value="<?= e((string) ($action['configuration_json'] ?? '')) ?>"></div>
        </div>
    <?php endfor; ?>
    <div class="form-check mt-3">
        <input class="form-check-input" type="checkbox" name="active" value="1" id="active" <?= (int) ($workflow['active'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label class="form-check-label" for="active">Active</label>
    </div>
    <div class="mt-3">
        <label class="form-label" for="reason">Reason for this change</label>
        <input class="form-control" id="reason" name="reason">
    </div>
    <button class="btn btn-sf mt-3" type="submit">Save workflow</button>
</form>
<?php if ($workflow !== null): ?>
    <form method="post" action="<?= e(url('/workflows/' . $workflow['id'] . '/test')) ?>" class="sf-panel p-3">
        <?= csrf_field() ?>
        <h2 class="h5">Test workflow</h2>
        <p class="sf-muted">Pick a record. This shows matched and failed conditions and the actions that would run. Nothing is executed.</p>
        <div class="row g-2">
            <div class="col-md-4"><input class="form-control" name="entity_id" placeholder="Record id" required></div>
            <div class="col-md-4"><button class="btn btn-outline-light" type="submit">Test workflow</button></div>
        </div>
    </form>
<?php endif; ?>

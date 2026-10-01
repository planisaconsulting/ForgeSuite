<?php if (!empty($expectedLabour)): ?>
<section class="sf-panel mb-3"><div class="sf-panel-head"><h2>Expected from the recipe</h2></div>
    <ul class="sf-feed"><?php foreach ($expectedLabour as $row): ?><li><?= e((string) $row['description']) ?><small><?= e((string) $row['expected_minutes']) ?> minutes</small></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
<?php if (can('time.record')): ?>
<section class="sf-panel mb-3">
    <div class="sf-panel-head"><h2>Add time</h2></div>
    <form class="sf-form p-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/time')) ?>">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="work_type">Work type</label>
                <select class="form-select" id="work_type" name="work_type">
                    <?php foreach ($workTypes as $type): ?>
                        <option value="<?= e($type->value) ?>"><?= e($type->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="hours">Hours</label>
                <input class="form-control" id="hours" name="hours" inputmode="numeric" value="0">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="minutes">Minutes</label>
                <input class="form-control" id="minutes" name="minutes" inputmode="numeric" value="30">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="job_item_id">Job item</label>
                <select class="form-select" id="job_item_id" name="job_item_id">
                    <option value="">Whole job</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= e((string) $item['id']) ?>"><?= e((string) $item['description']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <input class="form-control" id="description" name="description" placeholder="Artwork revision 2">
            </div>
        </div>
        <p class="sf-muted mt-2 mb-0">Internal labour cost is snapshotted from the staff rate. A cost typed into this form is ignored.</p>
        <div class="sf-form-actions"><button class="btn btn-sf btn-lg" type="submit">Save time</button></div>
    </form>
    <form class="px-3 pb-3 d-flex flex-wrap gap-2" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/timer/start')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="work_type" value="OTHER">
        <button class="btn btn-outline-light btn-lg" type="submit">Start timer</button>
    </form>
    <?php if ($timer !== null): ?>
        <form class="px-3 pb-3" method="post" action="<?= e(url('/jobs/' . $job['id'] . '/timer/stop')) ?>">
            <?= csrf_field() ?>
            <p>Timer running since <?= e(format_datetime((string) $timer['started_at'])) ?>.</p>
            <button class="btn btn-sf btn-lg" type="submit">Stop timer</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php if (is_array($labour)): ?>
    <section class="sf-panel mb-3">
        <div class="sf-panel-head"><h2>Labour variance</h2></div>
        <dl class="sf-dl">
            <div><dt>Estimated</dt><dd><?= e(number_format(((int) $labour['estimated_minutes']) / 60, 1)) ?> hours</dd></div>
            <div><dt>Actual</dt><dd><?= e(number_format(((int) $labour['actual_minutes']) / 60, 1)) ?> hours</dd></div>
            <div><dt>Variance</dt><dd><?= e(number_format(((int) $labour['variance_minutes']) / 60, 1)) ?> hours</dd></div>
        </dl>
    </section>
<?php endif; ?>
<section class="sf-panel">
    <div class="sf-panel-head"><h2>Entries</h2></div>
    <?php if ($entries === []): ?><p class="p-3 mb-0">No time recorded.</p><?php endif; ?>
    <?php foreach ($entries as $entry): ?>
        <article class="p-3 border-bottom border-secondary">
            <strong><?= e(enum_label(\App\Domain\WorkType::class, (string) $entry['work_type'])) ?></strong>
            · <?= e((string) $entry['user_name']) ?>
            · <?= e((string) $entry['minutes']) ?> min
            <?php if ($entry['ended_at'] === null && $entry['started_at'] !== null): ?><span class="sf-badge">Running</span><?php endif; ?>
            <?php if ($showCost): ?> · <?= e(money((string) $entry['total_cost'])) ?><?php endif; ?>
            <?php if (!empty($entry['description'])): ?><p class="mb-0"><?= e((string) $entry['description']) ?></p><?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

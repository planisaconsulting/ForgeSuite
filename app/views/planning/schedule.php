<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head sf-page-head-row">
    <div>
        <h1>Schedule</h1>
        <p class="sf-muted mb-0">Resources against the work already on jobs. The server checks every move.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach (['today' => 'Today', 'week' => 'Week', 'fortnight' => '2 weeks', 'month' => 'Month'] as $key => $label): ?>
            <a class="btn <?= $range === $key ? 'btn-sf' : 'btn-outline-light' ?>" href="<?= e(url('/schedule?range=' . $key)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-9">
        <section class="sf-panel">
            <div class="table-responsive">
                <table class="table table-dark table-sm align-middle mb-0">
                    <thead><tr><th>Resource</th><?php foreach ($days as $day): ?><th><?= e(date('D j M', strtotime($day))) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($resources as $resource): ?>
                        <tr>
                            <th><?= e((string) $resource['name']) ?><br><small class="sf-muted"><?= e((string) $resource['resource_type']) ?></small></th>
                            <?php foreach ($days as $day): ?>
                                <td data-drop-resource="<?= e((string) $resource['id']) ?>" data-drop-date="<?= e($day) ?>">
                                    <?php foreach ($entries as $entry): ?>
                                        <?php if ((int) $entry['resource_id'] !== (int) $resource['id'] || substr((string) $entry['start_datetime'], 0, 10) !== $day) { continue; } ?>
                                        <div class="mb-1" draggable="<?= $canManage ? 'true' : 'false' ?>" data-entry-id="<?= e((string) $entry['id']) ?>" data-start="<?= e((string) $entry['start_datetime']) ?>" data-end="<?= e((string) $entry['end_datetime']) ?>">
                                            <strong><?= e((string) ($entry['job_number'] ?? $entry['entity_type'])) ?></strong>
                                            <small class="d-block"><?= e(substr((string) $entry['start_datetime'], 11, 5)) ?>–<?= e(substr((string) $entry['end_datetime'], 11, 5)) ?> · <?= e((string) $entry['status']) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php if ($canManage): ?>
        <section class="sf-panel mt-3">
            <div class="sf-panel-head"><h2>Assign work</h2></div>
            <form class="row g-2 p-3" method="post" action="<?= e(url('/schedule/entries')) ?>">
                <?= csrf_field() ?>
                <div class="col-md-3"><label class="form-label">Type</label><select class="form-select" name="entity_type"><?php foreach (\App\Services\ScheduleService::ENTITY_TYPES as $type): ?><option><?= e($type) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Record id</label><input class="form-control" name="entity_id" required></div>
                <div class="col-md-2"><label class="form-label">Job id</label><input class="form-control" name="job_id"></div>
                <div class="col-md-3"><label class="form-label">Resource</label><select class="form-select" name="resource_id"><?php foreach ($resources as $resource): ?><option value="<?= e((string) $resource['id']) ?>"><?= e((string) $resource['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Minutes</label><input class="form-control" name="estimated_minutes"></div>
                <div class="col-md-3"><label class="form-label">Start</label><input class="form-control" type="datetime-local" name="start_datetime" required></div>
                <div class="col-md-3"><label class="form-label">End</label><input class="form-control" type="datetime-local" name="end_datetime" required></div>
                <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option>PLANNED</option><option>CONFIRMED</option></select></div>
                <div class="col-md-4"><label class="form-label">Override reason</label><input class="form-control" name="override_reason"></div>
                <div class="col-12"><button class="btn btn-sf btn-lg" type="submit">Schedule</button></div>
            </form>
            <form class="row g-2 p-3 border-top" id="move-form" method="post" action="<?= e(url('/schedule/move')) ?>">
                <?= csrf_field() ?>
                <div class="col-md-2"><label class="form-label">Entry</label><input class="form-control" name="entry_id" id="move-entry" required></div>
                <div class="col-md-3"><label class="form-label">New start</label><input class="form-control" type="datetime-local" name="start_datetime" id="move-start" required></div>
                <div class="col-md-3"><label class="form-label">New end</label><input class="form-control" type="datetime-local" name="end_datetime" id="move-end" required></div>
                <div class="col-md-4"><label class="form-label">Reason</label><input class="form-control" name="reason" required></div>
                <div class="col-12"><button class="btn btn-outline-light btn-lg" type="submit">Move without dragging</button></div>
            </form>
        </section>
        <?php endif; ?>
    </div>
    <div class="col-lg-3">
        <section class="sf-panel">
            <div class="sf-panel-head"><h2>Unscheduled</h2></div>
            <?php if ($unscheduled === []): ?><div class="sf-empty"><p>No open stages are waiting for a time.</p></div><?php else: ?>
                <ul class="sf-feed"><?php foreach ($unscheduled as $row): ?>
                    <li><?= e((string) $row['job_number']) ?> · <?= e((string) $row['stage_name']) ?>
                        <small><?= e(customer_label($row)) ?> · due <?= e((string) ($row['target_date'] ?? '—')) ?> · <?= e((string) ($row['estimated_minutes'] ?? '—')) ?> min · <?= e((string) $row['priority']) ?></small></li>
                <?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php if ($canManage): ?>
<script>
document.querySelectorAll('[draggable="true"]').forEach(function (item) {
    item.addEventListener('dragstart', function (event) {
        event.dataTransfer.setData('text/plain', JSON.stringify({
            id: item.dataset.entryId,
            start: item.dataset.start,
            end: item.dataset.end
        }));
    });
});
document.querySelectorAll('[data-drop-date]').forEach(function (cell) {
    cell.addEventListener('dragover', function (event) { event.preventDefault(); });
    cell.addEventListener('drop', function (event) {
        event.preventDefault();
        var data = JSON.parse(event.dataTransfer.getData('text/plain') || '{}');
        if (!data.id) return;
        var start = new Date(data.start.replace(' ', 'T'));
        var end = new Date(data.end.replace(' ', 'T'));
        var day = cell.dataset.dropDate;
        var pad = function (value) { return String(value).padStart(2, '0'); };
        var stamp = function (date) {
            return day + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
        };
        document.getElementById('move-entry').value = data.id;
        document.getElementById('move-start').value = stamp(start);
        document.getElementById('move-end').value = stamp(end);
        var reason = window.prompt('Reason for moving this work');
        if (!reason) return;
        var form = document.getElementById('move-form');
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'reason';
        input.value = reason;
        form.appendChild(input);
        var resource = document.createElement('input');
        resource.type = 'hidden';
        resource.name = 'resource_ids[]';
        resource.value = cell.dataset.dropResource;
        form.appendChild(resource);
        form.submit();
    });
});
</script>
<?php endif; ?>

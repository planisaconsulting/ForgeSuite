<?php require base_path('app/views/partials/flashes.php'); ?>
<div class="sf-page-head">
    <div>
        <h1>Workshop floor</h1>
        <p class="sf-muted mb-0">Today’s work. No prices on this screen.</p>
    </div>
    <a class="btn btn-sf btn-lg" href="<?= e(url('/workshop/scan')) ?>">Scan</a>
</div>
<section class="row g-2 mb-3" id="workshop-counts" data-poll="<?= e((string) $poll) ?>">
    <?php foreach ([
        'due_today' => 'Due today',
        'due_tomorrow' => 'Due tomorrow',
        'overdue' => 'Overdue',
        'in_progress' => 'In progress',
        'blocked' => 'Blocked',
        'qc' => 'Awaiting QC',
        'ready' => 'Ready',
        'reprints' => 'Reprints today',
    ] as $key => $label): ?>
        <div class="col-6 col-md-3">
            <article class="sf-panel p-3">
                <p class="sf-muted mb-1"><?= e($label) ?></p>
                <strong class="h3 mb-0" data-count="<?= e($key) ?>"><?= e((string) $counts[$key]) ?></strong>
            </article>
        </div>
    <?php endforeach; ?>
</section>
<form class="sf-filters" method="get">
    <select class="form-select" name="priority" aria-label="Priority">
        <option value="">Any priority</option>
        <?php foreach (['LOW', 'NORMAL', 'HIGH', 'URGENT'] as $priority): ?>
            <option value="<?= e($priority) ?>" <?= $filters['priority'] === $priority ? 'selected' : '' ?>><?= e($priority) ?></option>
        <?php endforeach; ?>
    </select>
    <input class="form-control" type="date" name="due" value="<?= e($filters['due']) ?>" aria-label="Due date">
    <button class="btn btn-sf" type="submit">Filter</button>
</form>
<div class="row g-3">
    <?php if ($rows === []): ?>
        <div class="col-12"><section class="sf-panel"><p class="p-3 mb-0">Nothing is waiting on the floor.</p></section></div>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <article class="sf-workshop-card">
                <p class="sf-kicker mb-1"><?= e((string) $row['job_number']) ?></p>
                <h2 class="h4"><?= e(customer_label($row)) ?></h2>
                <p class="mb-1"><?= e((string) $row['title']) ?></p>
                <p class="mb-2"><?= e((string) $row['priority']) ?> · due <?= e((string) ($row['target_date'] ?? '—')) ?> · <?= e((string) $row['status']) ?></p>
                <a class="btn btn-sf btn-lg" href="<?= e(url('/jobs/' . $row['id'] . '/card')) ?>">Job card</a>
            </article>
        </div>
    <?php endforeach; ?>
</div>
<script>
(function () {
    var box = document.getElementById('workshop-counts');
    if (!box) return;
    var seconds = parseInt(box.getAttribute('data-poll') || '45', 10);
    if (!seconds || seconds < 15) seconds = 45;
    setInterval(function () {
        fetch('<?= e(url('/workshop/board.json')) ?>', {headers: {'Accept': 'application/json'}})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                box.querySelectorAll('[data-count]').forEach(function (node) {
                    var key = node.getAttribute('data-count');
                    if (data[key] !== undefined) node.textContent = data[key];
                });
            })
            .catch(function () {});
    }, seconds * 1000);
})();
</script>
